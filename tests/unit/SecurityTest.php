<?php

declare(strict_types=1);

use App\Core\Crypto;
use App\Core\Sanitizer;
use App\Core\Totp;
use App\Services\Csv;

test('sanitizer strips scripts, handlers and dangerous URLs', function (): void {
    $html = Sanitizer::html('<p onclick="x()">Hi<script>alert(1)</script><a href="javascript:alert(1)">x</a><img src="data:image/png;base64,AA" onerror="y"><iframe src="https://evil"></iframe></p>');
    assert_not_contains('script', $html);
    assert_not_contains('onclick', $html);
    assert_not_contains('onerror', $html);
    assert_not_contains('javascript', $html);
    assert_not_contains('iframe', $html);
    assert_not_contains('data:', $html);
    assert_contains('<p>Hi', $html);
});

test('sanitizer keeps safe formatting and adds rel to new-tab links', function (): void {
    $html = Sanitizer::html('<h2>T</h2><ul><li><strong>a</strong></li></ul><a href="https://x.com" target="_blank">x</a><a href="/p">p</a><a href="mailto:a@b.co">m</a>');
    assert_contains('<h2>T</h2>', $html);
    assert_contains('<strong>a</strong>', $html);
    assert_contains('rel="noopener noreferrer"', $html);
    assert_contains('href="/p"', $html);
    assert_contains('href="mailto:a@b.co"', $html);
});

test('sanitizer neutralises obfuscated schemes and css expressions', function (): void {
    assert_same('', Sanitizer::url("jav\tascript:alert(1)"));
    assert_same('', Sanitizer::url('JAVASCRIPT:alert(1)'));
    assert_same('', Sanitizer::url('&#106;avascript:alert(1)'));
    assert_same('', Sanitizer::url('//evil.com/x'));
    assert_same('/ok', Sanitizer::url('/ok'));
    $html = Sanitizer::html('<p style="color:red;background-image:url(x);width:expression(1)">t</p>');
    assert_contains('color: red', $html);
    assert_not_contains('url(', $html);
    assert_not_contains('expression', $html);
});

test('colours and lengths are validated', function (): void {
    assert_same('#0061FF', Sanitizer::color('#0061FF'));
    assert_same('', Sanitizer::color('red;}body{display:none'));
    assert_same('40px', Sanitizer::length('40'));
    assert_same('2.5rem', Sanitizer::length('2.5rem'));
    assert_same('', Sanitizer::length('10px;position:fixed'));
});

test('encryption round-trips and detects tampering', function (): void {
    $c = Crypto::encrypt('smtp-password', 'settings');
    assert_true(str_starts_with($c, 'enc:v1:'));
    assert_same('smtp-password', Crypto::decrypt($c, 'settings'));
    assert_same(null, Crypto::decrypt($c, 'other-purpose'), 'Purpose is bound into the ciphertext');
    $tampered = substr($c, 0, -4) . (substr($c, -4) === 'AAAA' ? 'BBBB' : 'AAAA');
    assert_same(null, Crypto::decrypt($tampered, 'settings'));
});

test('TOTP matches RFC 6238 vectors and refuses replay', function (): void {
    $secret = Totp::base32Encode('12345678901234567890');
    // RFC 6238 SHA-1 test vectors (last 6 digits).
    assert_same('287082', Totp::code($secret, intdiv(59, 30)));
    assert_same('081804', Totp::code($secret, intdiv(1111111109, 30)));
    assert_same('005924', Totp::code($secret, intdiv(1234567890, 30)));
    $step = Totp::verify($secret, '005924', null, 1234567890);
    assert_same(intdiv(1234567890, 30), $step);
    assert_same(null, Totp::verify($secret, '005924', $step, 1234567890), 'A used step must not verify again');
    assert_same(null, Totp::verify($secret, '000000', null, 1234567890));
});

test('CSV cells cannot carry formulas', function (): void {
    assert_same("'=HYPERLINK(\"x\")", Csv::safe('=HYPERLINK("x")'));
    assert_same("'+1", Csv::safe('+1'));
    assert_same("'@SUM(A1)", Csv::safe('@SUM(A1)'));
    assert_same('Plain', Csv::safe('Plain'));
});

test('QR encoder produces a valid-sized matrix for an otpauth URI', function (): void {
    $m = App\Core\QrCode::matrix('otpauth://totp/Site:a%40b.co?secret=JBSWY3DPEHPK3PXP&issuer=Site');
    assert_true(count($m) >= 21 && (count($m) - 17) % 4 === 0);
    assert_true($m[0][0] && $m[0][6] && $m[6][0], 'Finder pattern corners are dark');
});
