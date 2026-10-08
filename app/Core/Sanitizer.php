<?php

declare(strict_types=1);

namespace App\Core;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allow-list HTML sanitiser for admin-authored rich text.
 *
 * The markup is parsed into a DOM, and anything not explicitly allowed is
 * removed: unknown elements are unwrapped (their text kept) or dropped with
 * their contents (script, style, iframe …), unknown attributes are removed,
 * every event handler goes, and URLs are limited to http(s), mailto, tel,
 * relative paths and fragments. Used on save and again on render.
 */
final class Sanitizer
{
    private const ALLOWED = [
        'p' => [], 'br' => [], 'hr' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [],
        'mark' => [], 'small' => [], 'sub' => [], 'sup' => [], 'span' => [], 'div' => [], 'blockquote' => ['cite'],
        'code' => [], 'pre' => [], 'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
        'ul' => [], 'ol' => ['start', 'type'], 'li' => [], 'dl' => [], 'dt' => [], 'dd' => [],
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height', 'loading'],
        'figure' => [], 'figcaption' => [],
        'table' => [], 'thead' => [], 'tbody' => [], 'tfoot' => [], 'tr' => [], 'th' => ['colspan', 'rowspan', 'scope'], 'td' => ['colspan', 'rowspan'],
        'caption' => [],
    ];
    private const GLOBAL_ATTRS = ['class', 'id', 'style'];
    private const DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'noscript', 'template', 'svg', 'math', 'form', 'input', 'button', 'textarea', 'select', 'link', 'meta', 'base', 'frame', 'frameset'];
    private const URL_ATTRS = ['href', 'src', 'cite'];

    public static function html(?string $html): string
    {
        $html = (string) $html;
        if (trim($html) === '') {
            return '';
        }
        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<?xml encoding="UTF-8"><!DOCTYPE html><html><body><div id="__root">' . $html . '</div></body></html>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('__root');
        if (!$root) {
            return '';
        }
        self::clean($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }
        return trim($out);
    }

    private static function clean(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
                $node->removeChild($child);
                continue;
            }
            if (!$child instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);
            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $node->removeChild($child);
                continue;
            }
            if (!array_key_exists($tag, self::ALLOWED)) {
                self::clean($child);
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }
            self::cleanAttributes($child, $tag);
            self::clean($child);
        }
    }

    private static function cleanAttributes(DOMElement $el, string $tag): void
    {
        $allowed = array_merge(self::ALLOWED[$tag], self::GLOBAL_ATTRS);
        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->name);
            if (!in_array($name, $allowed, true)) {
                $el->removeAttribute($attr->name);
                continue;
            }
            $value = $attr->value;
            if (in_array($name, self::URL_ATTRS, true)) {
                $safe = self::url($value, $tag === 'img');
                if ($safe === '') {
                    $el->removeAttribute($attr->name);
                } else {
                    $el->setAttribute($attr->name, $safe);
                }
            } elseif ($name === 'style') {
                $style = self::style($value);
                $style === '' ? $el->removeAttribute('style') : $el->setAttribute('style', $style);
            } elseif ($name === 'target') {
                $el->setAttribute('target', $value === '_blank' ? '_blank' : '_self');
            } elseif ($name === 'id' && !preg_match('/^[A-Za-z][\w\-]{0,63}$/', $value)) {
                $el->removeAttribute('id');
            }
        }
        if ($tag === 'a' && $el->getAttribute('target') === '_blank') {
            $el->setAttribute('rel', 'noopener noreferrer');
        }
    }

    /**
     * A URL safe to put in href/src, or '' when it is not.
     * Accepts http(s), mailto:, tel:, site-relative paths and #fragments.
     */
    public static function url(?string $url, bool $image = false): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }
        // Remove control characters and whitespace browsers ignore inside schemes.
        $probe = strtolower(preg_replace('/[\x00-\x20\x7f]+/', '', html_entity_decode($url, ENT_QUOTES | ENT_HTML5)) ?? '');
        if ($probe === '' || str_starts_with($probe, '//')) {
            return '';
        }
        if (preg_match('/^([a-z][a-z0-9+.\-]*):/', $probe, $m)) {
            $allowed = $image ? ['http', 'https'] : ['http', 'https', 'mailto', 'tel'];
            if (!in_array($m[1], $allowed, true)) {
                return '';
            }
        }
        return mb_substr($url, 0, 2000);
    }

    /** Only a handful of presentational properties survive, with no url() or expressions. */
    private static function style(string $style): string
    {
        $allowed = ['color', 'background-color', 'text-align', 'font-weight', 'font-style', 'text-decoration', 'font-size', 'line-height', 'margin', 'margin-top', 'margin-bottom', 'padding', 'width', 'max-width', 'height', 'border', 'border-radius', 'list-style-type', 'vertical-align'];
        $out = [];
        foreach (explode(';', $style) as $decl) {
            if (!str_contains($decl, ':')) {
                continue;
            }
            [$prop, $value] = array_map('trim', explode(':', $decl, 2));
            $prop = strtolower($prop);
            if (!in_array($prop, $allowed, true)) {
                continue;
            }
            if (preg_match('/url\s*\(|expression|javascript:|@import|[<>\\\\]/i', $value)) {
                continue;
            }
            $out[] = $prop . ': ' . $value;
        }
        return implode('; ', $out);
    }

    /** Plain text: tags removed, whitespace collapsed, length capped. */
    public static function text(?string $value, int $max = 5000): string
    {
        $value = strip_tags((string) $value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
        return mb_substr(trim($value), 0, $max);
    }

    /** Text for an HTML-free, single-line field (names, titles, slugs). */
    public static function line(?string $value, int $max = 255): string
    {
        $value = self::text($value, $max * 2);
        return mb_substr(trim(preg_replace('/\s+/u', ' ', $value) ?? ''), 0, $max);
    }

    /** A CSS colour (#hex, rgb()/rgba()/hsl()) or ''. */
    public static function color(?string $value): string
    {
        $value = trim((string) $value);
        if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value)) {
            return $value;
        }
        if (preg_match('/^(rgb|rgba|hsl|hsla)\(\s*[\d.\s%,\/]+\)$/i', $value)) {
            return $value;
        }
        if (in_array(strtolower($value), ['transparent', 'currentcolor', 'inherit', 'white', 'black'], true)) {
            return strtolower($value);
        }
        return '';
    }

    /** A CSS length such as 40px, 2.5rem, 85%, 4vh, or '' / auto. */
    public static function length(?string $value): string
    {
        $value = strtolower(trim((string) $value));
        if ($value === '' || $value === 'auto' || $value === '0') {
            return $value;
        }
        if (preg_match('/^-?\d{1,5}(\.\d{1,3})?$/', $value)) {
            return $value . 'px';
        }
        return preg_match('/^-?\d{1,5}(\.\d{1,3})?(px|rem|em|%|vw|vh|ch)$/', $value) ? $value : '';
    }
}
