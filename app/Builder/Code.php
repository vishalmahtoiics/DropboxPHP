<?php

declare(strict_types=1);

namespace App\Builder;

/**
 * Custom code in builder layouts: the HTML widget and custom CSS.
 *
 * Only staff with the "builder.code" permission can add or change it (see
 * Layout::keepCode). HTML is output as written on the live site, with the
 * page's CSP nonce added to its <script> tags so they run. Inside the editor
 * scripts never run: they are removed before the canvas sees the markup.
 * CSS is cleaned so it can never close its <style> element or pull in
 * outside stylesheets or script, and "selector" stands for the element.
 */
final class Code
{
    public const MAX_HTML = 100_000;
    public const MAX_CSS = 20_000;

    public static function css(string $css, string $selector = ''): string
    {
        $css = mb_substr($css, 0, self::MAX_CSS);
        $css = str_replace("\0", '', $css);
        // Nothing that can end the <style> element or the comment around it.
        $css = preg_replace('#<\s*/?\s*style|<!--|-->|<\s*/?\s*script|<#i', '', $css) ?? '';
        // No external stylesheets, legacy script hooks or script URLs.
        $css = preg_replace('/@import\b[^;]*;?|@charset\b[^;]*;?|expression\s*\(|behavior\s*:|-moz-binding|javascript\s*:|vbscript\s*:/i', '', $css) ?? '';
        if ($selector !== '') {
            $css = preg_replace('/\bselector\b/', $selector, $css) ?? '';
        }
        return trim($css);
    }

    /** HTML for the live page: scripts get the CSP nonce so they run. */
    public static function live(string $html): string
    {
        $nonce = e(nonce());
        return preg_replace('/<script\b(?![^>]*\bnonce\s*=)/i', '<script nonce="' . $nonce . '"', $html) ?? '';
    }

    /** HTML for the editor canvas: scripts and page-level tags removed. */
    public static function editor(string $html): string
    {
        $html = preg_replace('#<script\b[^>]*>.*?</script\s*>#is', '', $html) ?? '';
        $html = preg_replace('#<\s*/?\s*(script|meta|base|link|object|embed)\b[^>]*>#i', '', $html) ?? '';
        // Inline handlers are blocked by the canvas CSP anyway; drop them for clarity.
        return preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
    }

    public static function hasScript(string $html): bool
    {
        return (bool) preg_match('/<script\b/i', $html);
    }
}
