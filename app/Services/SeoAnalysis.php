<?php

declare(strict_types=1);

namespace App\Services;

use App\Cms\Blocks;
use App\Core\DB;

/**
 * SEO, AEO (answer-engine) and GEO (generative-engine) checks for a page,
 * product or article. Each check is pass / warn / fail with a fix, and the
 * three scores are weighted sums of their checks.
 */
final class SeoAnalysis
{
    /** @return array{seo:int, aeo:int, geo:int, overall:int, checks:list<array{group:string,status:string,label:string,fix:string}>} */
    public static function forPage(array $page): array
    {
        $sections = Content::sections((int) $page['id']);
        $text = '';
        $headings = [];
        $images = 0;
        $imagesWithAlt = 0;
        $links = 0;
        $hasFaq = false;
        $faqCount = 0;
        $hasStats = false;
        foreach ($sections as $s) {
            $c = Blocks::normalize($s['block_type'], $s['content']);
            array_walk_recursive($c, static function ($v, $k) use (&$text, &$headings, &$images, &$imagesWithAlt, &$links): void {
                if (!is_string($v) || $v === '') {
                    return;
                }
                if (in_array($k, ['heading', 'title', 'question'], true)) {
                    $headings[] = $v;
                }
                if (in_array($k, ['image', 'avatar'], true)) {
                    $images++;
                }
                if (in_array($k, ['image_alt', 'alt'], true)) {
                    $imagesWithAlt++;
                }
                if (str_ends_with((string) $k, 'url') || $k === 'link') {
                    $links++;
                }
                if (!in_array($k, ['image', 'url', 'link', 'icon', 'layout', 'style', 'align', 'source'], true) && !str_ends_with((string) $k, '_url')) {
                    $text .= ' ' . strip_tags($v);
                }
            });
            if ($s['block_type'] === 'faq') {
                $hasFaq = true;
                $faqCount += count($c['items'] ?? []);
            }
            if ($s['block_type'] === 'stats') {
                $hasStats = true;
            }
        }
        $title = (string) ($page['seo_title'] ?: $page['title']);
        $desc = (string) $page['seo_description'];
        $keywords = array_values(array_filter(array_map('trim', explode(',', (string) ($page['primary_keywords'] ?? '')))));
        return self::score([
            'title' => $title,
            'description' => $desc,
            'text' => $text,
            'headings' => $headings,
            'h1' => $sections !== [] ? 1 : 0,
            'images' => $images,
            'imagesWithAlt' => $imagesWithAlt,
            'links' => $links,
            'keywords' => $keywords,
            'noindex' => (int) $page['no_index'] === 1,
            'slug' => (string) $page['slug'],
            'faq' => $hasFaq,
            'faqCount' => $faqCount,
            'stats' => $hasStats,
            'og' => (bool) $page['og_image_id'],
            'updated' => (string) $page['updated_at'],
        ]);
    }

    public static function forPost(array $post): array
    {
        $html = (string) $post['content'];
        preg_match_all('/<h[23][^>]*>(.*?)<\/h[23]>/is', $html, $h);
        preg_match_all('/<img\b[^>]*>/i', $html, $imgs);
        $withAlt = count(array_filter($imgs[0], static fn ($t) => preg_match('/alt="[^"]+"/i', $t)));
        preg_match_all('/<a\b/i', $html, $a);
        $keywords = array_values(array_filter(array_map('trim', explode(',', (string) ($post['focus_keyword'] ?? '')))));
        return self::score([
            'title' => (string) ($post['seo_title'] ?: $post['title']),
            'description' => (string) ($post['seo_description'] ?: $post['excerpt']),
            'text' => strip_tags($html),
            'headings' => array_map('strip_tags', $h[1]),
            'h1' => 1,
            'images' => count($imgs[0]) + ($post['featured_image_id'] ? 1 : 0),
            'imagesWithAlt' => $withAlt + ($post['featured_image_id'] ? 1 : 0),
            'links' => count($a[0]),
            'keywords' => $keywords,
            'noindex' => (int) $post['no_index'] === 1,
            'slug' => (string) $post['slug'],
            'faq' => (bool) preg_match('/<h[23][^>]*>[^<]*\?<\/h[23]>/i', $html),
            'faqCount' => (int) preg_match_all('/<h[23][^>]*>[^<]*\?<\/h[23]>/i', $html),
            'stats' => (bool) preg_match('/\d+(\.\d+)?\s?(%|percent|million|billion|x)\b/i', strip_tags($html)),
            'og' => (bool) ($post['og_image_id'] ?: $post['featured_image_id']),
            'updated' => (string) $post['updated_at'],
        ]);
    }

    /** @param array<string,mixed> $d */
    private static function score(array $d): array
    {
        $checks = [];
        $add = static function (string $group, string $status, string $label, string $fix = '') use (&$checks): void {
            $checks[] = compact('group', 'status', 'label', 'fix');
        };
        $words = str_word_count((string) $d['text']);
        $tl = mb_strlen($d['title']);
        $dl = mb_strlen($d['description']);
        $lowerText = mb_strtolower($d['text'] . ' ' . implode(' ', $d['headings']));

        // SEO
        $add('SEO', $d['noindex'] ? 'fail' : 'pass', $d['noindex'] ? 'Hidden from search engines (noindex)' : 'Indexable', $d['noindex'] ? 'Turn off noindex if this page should rank.' : '');
        $add('SEO', $tl >= 30 && $tl <= 65 ? 'pass' : ($tl > 0 ? 'warn' : 'fail'), "Title length: $tl characters", 'Aim for 30–65 characters.');
        $add('SEO', $dl >= 70 && $dl <= 165 ? 'pass' : ($dl > 0 ? 'warn' : 'fail'), $dl ? "Meta description: $dl characters" : 'No meta description', 'Write 70–165 characters that sell the click.');
        $add('SEO', $words >= 300 ? 'pass' : ($words >= 120 ? 'warn' : 'fail'), "$words words of content", 'Pages with 300+ useful words rank more reliably.');
        $add('SEO', $d['h1'] ? 'pass' : 'fail', $d['h1'] ? 'Has a main heading' : 'No main heading', 'Add a hero or heading section.');
        $add('SEO', count($d['headings']) >= 3 ? 'pass' : 'warn', count($d['headings']) . ' headings structure the content', 'Break content into sections with headings.');
        if ($d['images'] > 0) {
            $add('SEO', $d['imagesWithAlt'] >= $d['images'] ? 'pass' : 'warn', "{$d['imagesWithAlt']} of {$d['images']} images have alt text", 'Describe every meaningful image.');
        }
        $add('SEO', $d['links'] >= 2 ? 'pass' : 'warn', "{$d['links']} links", 'Link to related pages and products.');
        $add('SEO', mb_strlen($d['slug']) <= 60 && substr_count($d['slug'], '/') <= 2 ? 'pass' : 'warn', 'Short, readable address', 'Keep addresses short and descriptive.');
        $add('SEO', $d['og'] ? 'pass' : 'warn', $d['og'] ? 'Has a share image' : 'No share image', 'Add a 1200×630 image for social sharing.');
        if ($d['keywords']) {
            foreach (array_slice($d['keywords'], 0, 3) as $kw) {
                $k = mb_strtolower($kw);
                $inTitle = str_contains(mb_strtolower($d['title']), $k);
                $inText = substr_count($lowerText, $k);
                $add('SEO', $inTitle && $inText >= 2 ? 'pass' : ($inText ? 'warn' : 'fail'), "Keyword \"$kw\": " . ($inTitle ? 'in title, ' : 'not in title, ') . "$inText mention(s)", 'Use the keyword in the title and naturally in the content.');
            }
        } else {
            $add('SEO', 'warn', 'No primary keyword set', 'Set up to three primary keywords to check targeting.');
        }

        // AEO: can an answer engine lift a direct answer?
        $add('AEO', $d['faq'] ? 'pass' : 'warn', $d['faq'] ? "FAQ with {$d['faqCount']} question(s) (FAQPage schema)" : 'No FAQ', 'Add an FAQ section answering real customer questions.');
        $questions = count(array_filter($d['headings'], static fn ($h) => str_ends_with(trim($h), '?')));
        $add('AEO', $questions >= 2 ? 'pass' : 'warn', "$questions question-style headings", 'Phrase some headings as the questions people search.');
        $add('AEO', $dl >= 70 ? 'pass' : 'warn', 'Concise summary available', 'A clear description gives engines a ready answer.');
        $add('AEO', preg_match('/\b(how|what|why|when|which|cost|price)\b/i', $d['text']) ? 'pass' : 'warn', 'Answers intent words (how/what/price…)', 'Cover the practical questions buyers ask.');

        // GEO: is it citable by generative engines?
        $add('GEO', $d['stats'] || preg_match('/\d/', $d['text']) ? 'pass' : 'warn', 'Contains facts and figures', 'Specific numbers make content citable.');
        $add('GEO', $words >= 500 ? 'pass' : 'warn', 'Depth of coverage', 'Comprehensive pages are cited more often.');
        $fresh = $d['updated'] !== '' && strtotime($d['updated']) > strtotime('-180 days');
        $add('GEO', $fresh ? 'pass' : 'warn', $fresh ? 'Updated in the last 6 months' : 'Not updated for 6+ months', 'Refresh facts and prices regularly.');
        $add('GEO', preg_match('/\b(authori[sz]ed|certified|partner|official|guarantee)\b/i', $d['text']) ? 'pass' : 'warn', 'States expertise and trust signals', 'Mention credentials, certifications and guarantees.');

        $score = static function (string $group) use ($checks): int {
            $items = array_filter($checks, static fn ($c) => $c['group'] === $group);
            if (!$items) {
                return 0;
            }
            $points = array_sum(array_map(static fn ($c) => $c['status'] === 'pass' ? 1 : ($c['status'] === 'warn' ? .5 : 0), $items));
            return (int) round($points / count($items) * 100);
        };
        $seo = $score('SEO');
        $aeo = $score('AEO');
        $geo = $score('GEO');
        return ['seo' => $seo, 'aeo' => $aeo, 'geo' => $geo, 'overall' => (int) round($seo * .6 + $aeo * .2 + $geo * .2), 'checks' => $checks];
    }
}
