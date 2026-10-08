<?php

declare(strict_types=1);

namespace App\Cms;

use App\Core\ErrorHandler;
use App\Core\View;
use App\Services\Seo;
use Throwable;

/**
 * Renders a page's sections. Content is re-normalised against the block's
 * schema on the way out (stale or partial JSON falls back to defaults), the
 * Design panel becomes scoped CSS variables, and an unknown block type or a
 * failing template renders nothing rather than breaking the page.
 */
final class SectionRenderer
{
    /** @param list<array<string,mixed>> $sections */
    public static function render(array $sections, ?array $page = null): string
    {
        $out = '';
        $faqGraph = [];
        $firstHeading = true;
        foreach ($sections as $i => $section) {
            $type = (string) $section['block_type'];
            if (!Blocks::exists($type)) {
                continue;
            }
            $content = Blocks::normalize($type, $section['content']);
            $design = Design::normalize($section['settings']);
            $key = 's' . (int) $section['id'];
            $d = Design::render($design, $key);
            try {
                $inner = View::partial('blocks/' . $type, [
                    'c' => $content,
                    'section' => $section,
                    'sectionKey' => $key,
                    'isFirst' => $i === 0,
                    // The first heading on the page is the h1.
                    'h' => $firstHeading ? 'h1' : 'h2',
                    'page' => $page,
                ]);
            } catch (Throwable $e) {
                ErrorHandler::report($e);
                continue;
            }
            if (trim($inner) === '') {
                continue;
            }
            if ($firstHeading && preg_match('/<h1[\s>]/', $inner)) {
                $firstHeading = false;
            }
            if ($type === 'faq' && !empty($content['schema'])) {
                $faqGraph = array_merge($faqGraph, $content['items']);
            }
            $out .= ($d['css'] !== '' ? '<style nonce="' . e(nonce()) . '">' . $d['css'] . '</style>' : '')
                . '<section class="' . e($d['classes']) . ' block-' . e($type) . '" data-s="' . e($key) . '"'
                . ($d['id'] !== '' ? ' id="' . e($d['id']) . '"' : '')
                . ($d['style'] !== '' ? ' style="' . e($d['style']) . '"' : '')
                . '>' . $inner . '</section>';
        }
        if ($faqGraph && ($faq = Seo::faq($faqGraph))) {
            $out .= Seo::jsonLd($faq);
        }
        return $out;
    }
}
