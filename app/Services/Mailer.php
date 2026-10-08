<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\ErrorHandler;
use RuntimeException;
use Throwable;

/**
 * A small SMTP client (AUTH LOGIN / PLAIN, STARTTLS or implicit TLS) with no
 * dependencies, so the app runs on hosts without Composer.
 *
 * Configuration comes from Admin → Settings → Email first, the SMTP_*
 * environment variables second. Messages are built from typed fields only —
 * every header value is stripped of CR/LF, so no input can add a header.
 * Sending never throws to a visitor-facing caller: use send() for best-effort
 * notifications and sendOrFail() where the admin wants the error (tests).
 */
final class Mailer
{
    /** @var list<array{to:string, subject:string}> captured in tests */
    public static array $sent = [];
    public static bool $fake = false;

    /** @return array<string, mixed> */
    public static function config(): array
    {
        $s = Settings::group('email');
        if (!empty($s['is_enabled']) && $s['host'] !== '') {
            return [
                'host' => $s['host'], 'port' => (int) $s['port'], 'username' => $s['username'],
                'password' => Settings::secret('email', 'password'), 'encryption' => $s['encryption'],
                'from' => $s['from_email'], 'from_name' => $s['from_name'], 'reply_to' => $s['reply_to'],
            ];
        }
        $c = config('mail');
        return [
            'host' => $c['host'], 'port' => (int) $c['port'], 'username' => $c['username'], 'password' => $c['password'],
            'encryption' => $c['encryption'], 'from' => $c['from'], 'from_name' => $c['from_name'], 'reply_to' => '',
        ];
    }

    public static function isConfigured(): bool
    {
        $c = self::config();
        return $c['host'] !== '' && $c['from'] !== '';
    }

    /** Best effort: logs and returns false on failure. */
    public static function send(string|array $to, string $subject, string $html, ?string $replyTo = null): bool
    {
        try {
            self::sendOrFail($to, $subject, $html, $replyTo);
            return true;
        } catch (Throwable $e) {
            ErrorHandler::log('mail failed: ' . $e->getMessage(), 'mail');
            return false;
        }
    }

    public static function sendOrFail(string|array $to, string $subject, string $html, ?string $replyTo = null): void
    {
        $recipients = array_values(array_filter(
            array_map('trim', is_array($to) ? $to : explode(',', $to)),
            static fn ($a) => filter_var($a, FILTER_VALIDATE_EMAIL) !== false
        ));
        if ($recipients === []) {
            throw new RuntimeException('No valid recipient.');
        }
        if (self::$fake) {
            foreach ($recipients as $r) {
                self::$sent[] = ['to' => $r, 'subject' => $subject];
            }
            return;
        }
        $c = self::config();
        if ($c['host'] === '' || $c['from'] === '') {
            throw new RuntimeException('Email is not configured. Set it up in Settings → Email.');
        }
        $message = self::build($c, $recipients, $subject, $html, $replyTo ?: ($c['reply_to'] ?: null));
        self::smtp($c, $recipients, $message);
    }

    /** Check the connection and login without sending anything. */
    public static function testConnection(): void
    {
        $c = self::config();
        if ($c['host'] === '') {
            throw new RuntimeException('No SMTP host configured.');
        }
        $socket = self::connect($c);
        self::command($socket, 'QUIT', [221]);
        fclose($socket);
    }

    private static function clean(string $value): string
    {
        return trim(str_replace(["\r", "\n", "\0"], '', $value));
    }

    private static function encodeHeader(string $value): string
    {
        $value = self::clean($value);
        return preg_match('/[^\x20-\x7e]/', $value) ? '=?UTF-8?B?' . base64_encode($value) . '?=' : $value;
    }

    /** @param list<string> $to */
    private static function build(array $c, array $to, string $subject, string $html, ?string $replyTo): string
    {
        $boundary = 'b' . bin2hex(random_bytes(12));
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
        $from = self::clean((string) $c['from']);
        $text = trim(html_entity_decode(strip_tags(preg_replace(['/<br\s*\/?>/i', '/<\/p>/i', '/<\/tr>/i'], ["\n", "\n\n", "\n"], $html) ?? ''), ENT_QUOTES | ENT_HTML5));
        $headers = [
            'Date: ' . date('r'),
            'From: ' . self::encodeHeader((string) $c['from_name']) . ' <' . $from . '>',
            'To: ' . implode(', ', array_map([self::class, 'clean'], $to)),
            'Subject: ' . self::encodeHeader($subject),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $host . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];
        if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: ' . self::clean($replyTo);
        }
        $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($text))
            . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html))
            . "--$boundary--\r\n";
        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    /** @return resource */
    private static function connect(array $c)
    {
        $enc = strtolower((string) $c['encryption']);
        $remote = ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $c['host'] . ':' . (int) $c['port'];
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
        $socket = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$socket) {
            throw new RuntimeException("Could not connect to {$c['host']}:{$c['port']} ($errstr).");
        }
        stream_set_timeout($socket, 20);
        self::expect($socket, [220]);
        $ehlo = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
        $caps = self::command($socket, 'EHLO ' . $ehlo, [250]);
        if ($enc === 'tls') {
            if (!str_contains(strtoupper($caps), 'STARTTLS')) {
                throw new RuntimeException('The server does not offer STARTTLS. Try SSL on port 465.');
            }
            self::command($socket, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                throw new RuntimeException('TLS negotiation failed.');
            }
            $caps = self::command($socket, 'EHLO ' . $ehlo, [250]);
        }
        if ($c['username'] !== '') {
            if (str_contains(strtoupper($caps), 'PLAIN')) {
                self::command($socket, 'AUTH PLAIN ' . base64_encode("\0" . $c['username'] . "\0" . $c['password']), [235]);
            } else {
                self::command($socket, 'AUTH LOGIN', [334]);
                self::command($socket, base64_encode((string) $c['username']), [334]);
                self::command($socket, base64_encode((string) $c['password']), [235]);
            }
        }
        return $socket;
    }

    /** @param list<string> $to */
    private static function smtp(array $c, array $to, string $message): void
    {
        $socket = self::connect($c);
        try {
            self::command($socket, 'MAIL FROM:<' . self::clean((string) $c['from']) . '>', [250]);
            foreach ($to as $rcpt) {
                self::command($socket, 'RCPT TO:<' . self::clean($rcpt) . '>', [250, 251]);
            }
            self::command($socket, 'DATA', [354]);
            $data = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r"], "\n", $message)) ?? '';
            fwrite($socket, str_replace("\n", "\r\n", $data) . "\r\n.\r\n");
            self::expect($socket, [250]);
            self::command($socket, 'QUIT', [221]);
        } finally {
            fclose($socket);
        }
    }

    /** @param resource $socket @param list<int> $ok */
    private static function command($socket, string $line, array $ok): string
    {
        fwrite($socket, $line . "\r\n");
        return self::expect($socket, $ok, str_starts_with($line, 'AUTH') || !str_contains($line, ' ') ? 'AUTH' : strtok($line, ' '));
    }

    /** @param resource $socket @param list<int> $ok */
    private static function expect($socket, array $ok, string $context = ''): string
    {
        $response = '';
        while (($line = fgets($socket, 1024)) !== false) {
            $response .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $ok, true)) {
            $msg = trim(preg_replace('/\s+/', ' ', $response) ?? '');
            throw new RuntimeException(($context ? "$context: " : '') . ($msg !== '' ? $msg : 'no response from server'));
        }
        return $response;
    }
}
