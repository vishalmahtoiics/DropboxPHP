<?php

declare(strict_types=1);

namespace App\Builder;

use App\Cms\Design;
use App\Core\ErrorHandler;
use App\Core\View;
use App\Services\Seo;
use Throwable;

/**
 * Draws a builder layout as HTML plus its scoped CSS.
 *
 * The editor canvas and the public site use this same renderer, so what the
 * editor shows is what visitors get. Context:
 *   product  the product being shown (product pages and product templates)
 *   page     the page being shown
 *   editor   true inside the editor canvas: element ids are exposed for
 *            selection, empty containers show a drop hint, and widgets that
 *            need missing context explain themselves instead of vanishing
 */
final class Renderer
{
    private bool $h1Done = false;

    /** @var list<array<string, string>> FAQ entries for structured data */
    private array $faq = [];

    /** @param array<string, mixed> $ctx */
    private function __construct(private array $ctx)
    {
    }

    /**
     * @param array<string, mixed> $ctx
     * @return array{html:string, css:string, graph:list<array<string,mixed>>}
     */
    public static function render(array $layout, array $ctx = []): array
    {
        $r = new self($ctx + ['product' => null, 'page' => null, 'editor' => false]);
        $html = '';
        foreach ($layout['elements'] ?? [] as $el) {
            $html .= $r->element($el, 0);
        }
        $graph = [];
        if ($r->faq && !$r->ctx['editor'] && ($faq = Seo::faq($r->faq))) {
            $graph[] = $faq;
        }
        return ['html' => $html, 'css' => Style::layout($layout), 'graph' => $graph];
    }

    /** One page's full output: the CSS, the markup and any structured data. */
    public static function page(array $layout, array $ctx = []): string
    {
        $out = self::render($layout, $ctx);
        $html = '<div class="bx-root">' . $out['html'] . '</div>';
        if ($out['css'] !== '') {
            $html = '<style nonce="' . e(nonce()) . '">' . $out['css'] . '</style>' . $html;
        }
        foreach ($out['graph'] as $item) {
            $html .= Seo::jsonLd($item);
        }
        return $html;
    }

    private function element(array $el, int $depth): string
    {
        $type = (string) $el['type'];
        $def = Elements::get($type);
        if ($def === null) {
            return '';
        }
        $s = Layout::withDefaults($type, $el['settings'] ?? []);
        $classes = $this->commonClasses($el['id'], $s);
        $attrs = $this->commonAttrs($el['id'], $s, $type);

        if ($type === 'container') {
            $classes[] = 'bx-con';
            $classes[] = $s['content_width'] === 'full' ? 'bx-full' : 'bx-boxed';
            $classes[] = $depth > 0 ? 'bx-child' : 'bx-top';
            $tag = $s['tag'] ?: 'div';
            if ($s['link'] !== '') {
                $tag = 'a';
                $attrs .= ' href="' . e(\App\Cms\Ui::href($s['link'])) . '"';
            }
            $inner = '';
            foreach ($el['children'] ?? [] as $child) {
                $inner .= $this->element($child, $depth + 1);
            }
            if ($inner === '' && $this->ctx['editor']) {
                $inner = '<div class="bx-empty" data-bx-empty>Drag a widget here, or click + to add one</div>';
            }
            return '<' . $tag . ' class="' . e(implode(' ', $classes)) . '"' . $attrs . '><div class="bx-inner">' . $inner . '</div></' . $tag . '>';
        }

        $classes[] = 'bx-w';
        $classes[] = 'bx-w-' . str_replace('.', '-', $type);
        try {
            $inner = isset($def['block']) ? $this->block($def['block'], $el, $s) : $this->widget($type, $el, $s, $def);
        } catch (Throwable $e) {
            ErrorHandler::report($e);
            $inner = $this->ctx['editor'] ? '<div class="bx-note">This widget could not be shown.</div>' : '';
        }
        if (trim($inner) === '') {
            if (!$this->ctx['editor']) {
                return '';
            }
            $inner = '<div class="bx-note">' . e($def['label']) . ($def['context'] ?? '' ? ' — shows the product on product pages' : ' — nothing to show yet') . '</div>';
        }
        if (preg_match('/<h1[\s>]/', $inner)) {
            $this->h1Done = true;
        }
        return '<div class="' . e(implode(' ', $classes)) . '"' . $attrs . '>' . $inner . '</div>';
    }

    /** @return list<string> */
    private function commonClasses(string $id, array $s): array
    {
        $c = ['el-' . $id];
        foreach (['desktop', 'tablet', 'mobile'] as $d) {
            if (!empty($s['_hide_' . $d])) {
                $c[] = 'bx-hide-' . $d;
            }
        }
        if (($s['_animation'] ?? '') !== '') {
            $c[] = 'bx-anim';
            $c[] = 'bx-anim-' . $s['_animation'];
        }
        $extra = trim(preg_replace('/[^A-Za-z0-9_\- ]/', '', (string) ($s['_css_classes'] ?? '')) ?? '');
        if ($extra !== '') {
            $c[] = $extra;
        }
        return $c;
    }

    private function commonAttrs(string $id, array $s, string $type): string
    {
        $attrs = '';
        $cssId = (string) ($s['_css_id'] ?? '');
        if (preg_match('/^[A-Za-z][A-Za-z0-9\-_]{0,63}$/', $cssId)) {
            $attrs .= ' id="' . e($cssId) . '"';
        }
        if ($this->ctx['editor']) {
            $attrs .= ' data-el="' . e($id) . '" data-el-type="' . e($type) . '"';
        }
        return $attrs;
    }

    private function widget(string $type, array $el, array $s, array $def): string
    {
        if (($def['context'] ?? '') === 'product' && !$this->ctx['product']) {
            return '';
        }
        $out = View::partial('builder/widgets/' . $type, [
            's' => $s,
            'el' => $el,
            'product' => $this->ctx['product'],
            'page' => $this->ctx['page'],
            'editor' => $this->ctx['editor'],
        ]);
        if ($type === 'accordion' && !empty($s['schema'])) {
            foreach ($s['items'] as $item) {
                $this->faq[] = ['question' => $item['title'], 'answer' => $item['content']];
            }
        }
        return $out;
    }

    private function block(string $blockType, array $el, array $s): string
    {
        $design = Design::normalize(array_merge(
            is_array($s['_design'] ?? null) ? $s['_design'] : [],
            ['preset' => $s['_preset'] ?? 'default', 'width' => $s['_section_width'] ?? 'boxed']
        ));
        $key = 'b' . $el['id'];
        $d = Design::render($design, $key);
        $content = \App\Cms\Blocks::normalize($blockType, $s);
        $inner = View::partial('blocks/' . $blockType, [
            'c' => $content,
            'section' => ['id' => 0, 'block_type' => $blockType],
            'sectionKey' => $key,
            'isFirst' => !$this->h1Done,
            'h' => $this->h1Done ? 'h2' : 'h1',
            'page' => $this->ctx['page'],
        ]);
        if (trim($inner) === '') {
            return '';
        }
        if ($blockType === 'faq' && !empty($content['schema'])) {
            $this->faq = array_merge($this->faq, $content['items']);
        }
        $classes = $d['classes'] . ' block-' . $blockType . (!empty($s['_flush']) ? ' bx-flush' : '');
        return ($d['css'] !== '' ? '<style nonce="' . e(nonce()) . '">' . $d['css'] . '</style>' : '')
            . '<section class="' . e($classes) . '" data-s="' . e($key) . '"'
            . ($d['id'] !== '' ? ' id="' . e($d['id']) . '"' : '')
            . ($d['style'] !== '' ? ' style="' . e($d['style']) . '"' : '')
            . '>' . $inner . '</section>';
    }
}
