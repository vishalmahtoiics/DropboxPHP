<?php

declare(strict_types=1);

namespace App\Builder;

use App\Cms\Blocks;
use App\Cms\Design;
use App\Builder\Controls as C;

/**
 * Everything that can be placed in a builder layout: the container (a flex
 * box that holds other elements) and the widgets.
 *
 * Widgets come in three kinds:
 *  - builder widgets, drawn by views/builder/widgets/<type>.php
 *  - product widgets, which read the product being shown (product pages and
 *    product templates only)
 *  - every CMS block (type "block.<name>"), so whole ready-made sections from
 *    the classic editor can be dropped into a layout
 *
 * Each element has tabs (content, style, advanced) of sections of controls.
 */
final class Elements
{
    public const CATEGORIES = [
        'layout' => 'Layout',
        'basic' => 'Basic',
        'general' => 'General',
        'product' => 'Product',
        'site' => 'Site',
        'sections' => 'Ready-made sections',
    ];

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $defs = null;

    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        if (self::$defs !== null) {
            return self::$defs;
        }
        $defs = ['container' => self::container()] + self::widgets() + self::productWidgets();
        foreach (Blocks::all() as $type => $block) {
            $defs['block.' . $type] = self::block($type, $block);
        }
        foreach ($defs as $type => &$def) {
            if ($type !== 'container') {
                $def['tabs']['advanced'] = C::advanced(true);
            }
        }
        unset($def);
        return self::$defs = $defs;
    }

    public static function get(string $type): ?array
    {
        return self::all()[$type] ?? null;
    }

    public static function exists(string $type): bool
    {
        return isset(self::all()[$type]);
    }

    /** @return list<array<string, mixed>> every control of an element, tabs flattened */
    public static function controls(string $type): array
    {
        $def = self::get($type);
        if (!$def) {
            return [];
        }
        $out = [];
        foreach ($def['tabs'] as $sections) {
            foreach ($sections as $section) {
                foreach ($section['controls'] as $control) {
                    if ($control['type'] !== 'heading') {
                        $out[] = $control;
                    }
                }
            }
        }
        return $out;
    }

    /** Definitions sent to the editor (no PHP-only keys). */
    public static function forEditor(): array
    {
        $out = [];
        foreach (self::all() as $type => $def) {
            $out[$type] = [
                'label' => $def['label'],
                'icon' => $def['icon'],
                'category' => $def['category'],
                'keywords' => $def['keywords'] ?? '',
                'context' => $def['context'] ?? '',
                'code' => !empty($def['code']),
                'tabs' => $def['tabs'],
            ];
        }
        return $out;
    }

    // --- container ----------------------------------------------------------------------

    private static function container(): array
    {
        $I = '{{W}}>.bx-inner';
        $flexChoices = ['flex-start' => 'Start', 'center' => 'Centre', 'flex-end' => 'End', 'space-between' => 'Space between', 'space-around' => 'Space around', 'space-evenly' => 'Space evenly'];
        return [
            'label' => 'Container', 'icon' => 'layout', 'category' => 'layout', 'keywords' => 'section column row flex box',
            'tabs' => [
                'content' => [
                    ['label' => 'Container', 'controls' => [
                        C::c('choose', 'content_width', 'Content width', 'boxed', ['choices' => ['boxed' => 'Boxed', 'full' => 'Full width'], 'help' => 'Boxed keeps the content to the site width; the background still spans the screen.']),
                        C::c('slider', 'boxed_width', 'Boxed width', ['size' => '', 'unit' => 'px'], ['responsive' => true, 'units' => ['px', '%', 'vw', 'rem'], 'min' => 0, 'max' => 2400, 'showWhen' => ['content_width' => ['boxed']], 'css' => [$I => 'max-width:{{V}}']]),
                        C::c('slider', 'width', 'Width', ['size' => '', 'unit' => '%'], ['responsive' => true, 'units' => ['%', 'px', 'vw'], 'min' => 0, 'max' => 2400, 'help' => 'For a container inside another container.', 'css' => ['{{W}}' => 'width:{{V}};max-width:100%;flex-basis:auto;flex-grow:0']]),
                        C::c('slider', 'min_height', 'Minimum height', ['size' => '', 'unit' => 'px'], ['responsive' => true, 'units' => ['px', 'vh', 'em'], 'min' => 0, 'max' => 2000, 'css' => [$I => 'min-height:{{V}}']]),
                    ]],
                    ['label' => 'Items', 'controls' => [
                        C::c('choose', 'direction', 'Direction', 'column', ['responsive' => true, 'choices' => ['row' => 'Row →', 'column' => 'Column ↓', 'row-reverse' => 'Row ←', 'column-reverse' => 'Column ↑'], 'css' => [$I => 'flex-direction:{{V}}']]),
                        C::c('choose', 'justify', 'Justify content', '', ['responsive' => true, 'choices' => ['' => 'Default'] + $flexChoices, 'css' => [$I => 'justify-content:{{V}}']]),
                        C::c('choose', 'align', 'Align items', '', ['responsive' => true, 'choices' => ['' => 'Default', 'flex-start' => 'Start', 'center' => 'Centre', 'flex-end' => 'End', 'stretch' => 'Stretch'], 'css' => [$I => 'align-items:{{V}}']]),
                        C::c('slider', 'gap', 'Gap', ['size' => '', 'unit' => 'px'], ['responsive' => true, 'units' => ['px', 'em', 'rem', '%'], 'min' => 0, 'max' => 300, 'css' => [$I => 'gap:{{V}}']]),
                        C::c('choose', 'wrap', 'Wrap', '', ['responsive' => true, 'choices' => ['' => 'Default', 'nowrap' => 'No wrap', 'wrap' => 'Wrap'], 'css' => [$I => 'flex-wrap:{{V}}']]),
                    ]],
                    ['label' => 'Additional', 'controls' => [
                        C::c('select', 'tag', 'HTML tag', 'div', ['choices' => ['div' => 'div', 'section' => 'section', 'header' => 'header', 'footer' => 'footer', 'aside' => 'aside', 'article' => 'article', 'nav' => 'nav']]),
                        C::c('select', 'overflow', 'Overflow', '', ['choices' => ['' => 'Default', 'hidden' => 'Hidden'], 'css' => ['{{W}}' => 'overflow:{{V}}']]),
                        C::c('url', 'link', 'Link (whole container)', '', ['help' => 'Leave empty unless the whole box should be clickable.']),
                    ]],
                ],
                'style' => array_merge(C::boxStyle(), [
                    ['label' => 'Text', 'controls' => [
                        C::c('color', 'text_color', 'Text colour', '', ['css' => ['{{W}}' => 'color:{{V}}']]),
                        C::c('color', 'heading_color', 'Heading colour', '', ['css' => ['{{W}} :is(h1,h2,h3,h4,h5,h6)' => 'color:{{V}}']]),
                        C::c('color', 'link_color', 'Link colour', '', ['css' => ['{{W}} a:not(.btn)' => 'color:{{V}}']]),
                    ]],
                ]),
                'advanced' => C::advanced(false),
            ],
        ];
    }

    // --- builder widgets ----------------------------------------------------------------

    private static function widgets(): array
    {
        $align = static fn (string $sel, string $prop = 'text-align') => C::c('choose', 'align', 'Alignment', '', ['responsive' => true, 'choices' => ['' => 'Default'] + C::alignChoices(true), 'css' => [$sel => $prop . ':{{V}}']]);
        $flexAlign = static fn (string $sel) => C::c('choose', 'align', 'Alignment', '', ['responsive' => true, 'choices' => ['' => 'Default', 'flex-start' => 'Left', 'center' => 'Centre', 'flex-end' => 'Right', 'stretch' => 'Full width'], 'css' => [$sel => 'justify-content:{{V}}']]);
        $size = static fn (string $key, string $label, string $sel, string $prop, string $unit = 'px', int $max = 400) => C::c('slider', $key, $label, ['size' => '', 'unit' => $unit], ['responsive' => true, 'units' => ['px', 'em', 'rem', '%', 'vw'], 'min' => 0, 'max' => $max, 'css' => [$sel => $prop . ':{{V}}']]);
        $variants = ['primary' => 'Primary', 'secondary' => 'Outline', 'ghost' => 'Text only', 'white' => 'White', 'dark' => 'Dark'];

        return [
            'heading' => [
                'label' => 'Heading', 'icon' => 'type', 'category' => 'basic', 'keywords' => 'title h1 h2 text',
                'tabs' => [
                    'content' => [['label' => 'Heading', 'controls' => [
                        C::c('textarea', 'title', 'Title', 'Add your heading here', ['max' => 500]),
                        C::c('url', 'link', 'Link', ''),
                        C::c('select', 'tag', 'HTML tag', 'h2', ['choices' => ['h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6', 'div' => 'div', 'p' => 'p', 'span' => 'span']]),
                        C::c('select', 'size', 'Size preset', '', ['choices' => ['' => 'Default for the tag', 'small' => 'Small', 'medium' => 'Medium', 'large' => 'Large', 'xl' => 'Extra large', 'xxl' => 'Huge']]),
                    ]]],
                    'style' => [['label' => 'Heading', 'controls' => array_merge([
                        $align('{{W}}'),
                        C::c('color', 'color', 'Colour', '', ['css' => ['{{W}} .bx-heading, {{W}} .bx-heading a' => 'color:{{V}}']]),
                    ], C::typography('', '{{W}} .bx-heading'))]],
                ],
            ],
            'text' => [
                'label' => 'Text editor', 'icon' => 'align-left', 'category' => 'basic', 'keywords' => 'paragraph rich text content',
                'tabs' => [
                    'content' => [['label' => 'Text', 'controls' => [
                        C::c('richtext', 'content', 'Text', '<p>Add your text here. Click to edit it in the panel on the left.</p>'),
                    ]]],
                    'style' => [['label' => 'Text', 'controls' => array_merge([
                        $align('{{W}}'),
                        C::c('color', 'color', 'Text colour', '', ['css' => ['{{W}} .bx-text' => 'color:{{V}}']]),
                        C::c('color', 'link_color', 'Link colour', '', ['css' => ['{{W}} .bx-text a' => 'color:{{V}}']]),
                    ], C::typography('', '{{W}} .bx-text'))]],
                ],
            ],
            'image' => [
                'label' => 'Image', 'icon' => 'image', 'category' => 'basic', 'keywords' => 'photo picture',
                'tabs' => [
                    'content' => [['label' => 'Image', 'controls' => [
                        C::c('image', 'src', 'Image', ''),
                        C::c('text', 'alt', 'Alt text', '', ['max' => 200, 'help' => 'Describe the image for screen readers and search engines.']),
                        C::c('text', 'caption', 'Caption', '', ['max' => 300]),
                        C::c('url', 'link', 'Link', ''),
                        C::c('switch', 'lazy', 'Lazy load', true, ['help' => 'Turn off for the first image on the page.']),
                    ]]],
                    'style' => [['label' => 'Image', 'controls' => [
                        $align('{{W}}'),
                        $size('img_width', 'Width', '{{W}} img', 'width', '%', 2000),
                        $size('img_max_width', 'Max width', '{{W}} img', 'max-width', '%', 2000),
                        $size('img_height', 'Height', '{{W}} img', 'height', 'px', 2000),
                        C::c('select', 'fit', 'Object fit', '', ['choices' => ['' => 'Default', 'cover' => 'Cover', 'contain' => 'Contain'], 'css' => ['{{W}} img' => 'object-fit:{{V}}']]),
                        C::c('dimensions', 'img_radius', 'Corner radius', C::dims(), ['units' => ['px', '%'], 'dims' => ['{{W}} img', 'border-radius']]),
                        C::c('number', 'opacity', 'Opacity (%)', '', ['min' => 0, 'max' => 100, 'css' => ['{{W}} img' => 'opacity:calc({{V}} / 100)']]),
                        C::c('select', 'img_shadow', 'Shadow', '', ['choices' => ['' => 'None', 'sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large']]),
                    ]]],
                ],
            ],
            'button' => [
                'label' => 'Button', 'icon' => 'mouse-pointer', 'category' => 'basic', 'keywords' => 'cta link',
                'tabs' => [
                    'content' => [['label' => 'Button', 'controls' => [
                        C::c('text', 'text', 'Text', 'Click here', ['max' => 120]),
                        C::c('url', 'link', 'Link', '#', ['help' => 'A page (/contact), a full address, #section-id or #popup-ID.']),
                        C::c('switch', 'new_tab', 'Open in a new tab', false),
                        C::c('select', 'variant', 'Style', 'primary', ['choices' => $variants]),
                        C::c('select', 'size', 'Size', 'md', ['choices' => ['sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large', 'xl' => 'Extra large']]),
                        C::c('icon', 'icon', 'Icon', ''),
                        C::c('choose', 'icon_position', 'Icon position', 'after', ['choices' => ['before' => 'Before', 'after' => 'After']]),
                        C::c('text', 'cta_location', 'Lead attribution label', '', ['max' => 120, 'help' => 'Optional. Recorded with leads that click through.']),
                    ]]],
                    'style' => [
                        ['label' => 'Button', 'controls' => array_merge([
                            $flexAlign('{{W}}'),
                            C::c('color', 'text_color', 'Text colour', '', ['css' => ['{{W}} .bx-btn' => 'color:{{V}}']]),
                            C::c('color', 'bg', 'Background', '', ['css' => ['{{W}} .bx-btn' => 'background:{{V}}']]),
                            C::c('color', 'border_color', 'Border colour', '', ['css' => ['{{W}} .bx-btn' => 'border-color:{{V}}']]),
                            C::c('dimensions', 'btn_padding', 'Padding', C::dims(), ['responsive' => true, 'units' => ['px', 'em'], 'dims' => ['{{W}} .bx-btn', 'padding']]),
                            C::c('dimensions', 'btn_radius', 'Corner radius', C::dims(), ['units' => ['px', '%', 'em'], 'dims' => ['{{W}} .bx-btn', 'border-radius']]),
                        ], C::typography('', '{{W}} .bx-btn'))],
                        ['label' => 'On hover', 'controls' => [
                            C::c('color', 'hover_text', 'Text colour', '', ['css' => ['{{W}} .bx-btn:hover' => 'color:{{V}}']]),
                            C::c('color', 'hover_bg', 'Background', '', ['css' => ['{{W}} .bx-btn:hover' => 'background:{{V}}']]),
                            C::c('color', 'hover_border', 'Border colour', '', ['css' => ['{{W}} .bx-btn:hover' => 'border-color:{{V}}']]),
                        ]],
                    ],
                ],
            ],
            'icon' => [
                'label' => 'Icon', 'icon' => 'star', 'category' => 'basic', 'keywords' => 'symbol',
                'tabs' => [
                    'content' => [['label' => 'Icon', 'controls' => [
                        C::c('icon', 'icon', 'Icon', 'star'),
                        C::c('choose', 'view', 'View', 'default', ['choices' => ['default' => 'Default', 'stacked' => 'Filled', 'framed' => 'Framed']]),
                        C::c('choose', 'shape', 'Shape', 'circle', ['choices' => ['circle' => 'Circle', 'square' => 'Square'], 'showWhen' => ['view' => ['stacked', 'framed']]]),
                        C::c('url', 'link', 'Link', ''),
                        C::c('text', 'label', 'Accessible label', '', ['max' => 120]),
                    ]]],
                    'style' => [['label' => 'Icon', 'controls' => [
                        $align('{{W}}'),
                        C::c('color', 'color', 'Colour', '', ['css' => ['{{W}} .bx-icon' => 'color:{{V}}']]),
                        C::c('color', 'bg', 'Fill / frame colour', '', ['css' => ['{{W}} .bx-icon' => '--bx-icon-bg:{{V}}']]),
                        $size('icon_size', 'Size', '{{W}} .bx-icon svg', 'width', 'px', 300),
                        $size('icon_padding', 'Padding', '{{W}} .bx-icon', 'padding', 'px', 200),
                    ]]],
                ],
            ],
            'icon_box' => [
                'label' => 'Icon box', 'icon' => 'award', 'category' => 'basic', 'keywords' => 'feature service card',
                'tabs' => [
                    'content' => [['label' => 'Icon box', 'controls' => [
                        C::c('icon', 'icon', 'Icon', 'zap'),
                        C::c('text', 'title', 'Title', 'This is the heading', ['max' => 200]),
                        C::c('textarea', 'text', 'Description', 'Describe the benefit in a sentence or two.', ['max' => 1500]),
                        C::c('url', 'link', 'Link', ''),
                        C::c('choose', 'position', 'Icon position', 'top', ['choices' => ['top' => 'Top', 'left' => 'Left', 'right' => 'Right']]),
                        C::c('select', 'title_tag', 'Title tag', 'h3', ['choices' => ['h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'div' => 'div']]),
                    ]]],
                    'style' => [
                        ['label' => 'Box', 'controls' => [$align('{{W}} .bx-iconbox')]],
                        ['label' => 'Icon', 'controls' => [
                            C::c('color', 'icon_color', 'Colour', '', ['css' => ['{{W}} .bx-iconbox-icon' => 'color:{{V}}']]),
                            C::c('color', 'icon_bg', 'Background', '', ['css' => ['{{W}} .bx-iconbox-icon' => 'background:{{V}}']]),
                            $size('icon_size', 'Size', '{{W}} .bx-iconbox-icon svg', 'width', 'px', 200),
                        ]],
                        ['label' => 'Title', 'controls' => array_merge([C::c('color', 'title_color', 'Colour', '', ['css' => ['{{W}} .bx-iconbox-title' => 'color:{{V}}']])], C::typography('title_', '{{W}} .bx-iconbox-title'))],
                        ['label' => 'Description', 'controls' => array_merge([C::c('color', 'text_color', 'Colour', '', ['css' => ['{{W}} .bx-iconbox-text' => 'color:{{V}}']])], C::typography('text_', '{{W}} .bx-iconbox-text'))],
                    ],
                ],
            ],
            'image_box' => [
                'label' => 'Image box', 'icon' => 'image', 'category' => 'basic', 'keywords' => 'card picture title',
                'tabs' => [
                    'content' => [['label' => 'Image box', 'controls' => [
                        C::c('image', 'src', 'Image', ''),
                        C::c('text', 'alt', 'Alt text', '', ['max' => 200]),
                        C::c('text', 'title', 'Title', 'This is the heading', ['max' => 200]),
                        C::c('textarea', 'text', 'Description', 'Describe it in a sentence or two.', ['max' => 1500]),
                        C::c('url', 'link', 'Link', ''),
                        C::c('choose', 'position', 'Image position', 'top', ['choices' => ['top' => 'Top', 'left' => 'Left', 'right' => 'Right']]),
                    ]]],
                    'style' => [
                        ['label' => 'Box', 'controls' => [
                            $align('{{W}} .bx-imagebox'),
                            $size('img_width', 'Image width', '{{W}} .bx-imagebox-img', 'width', '%', 1000),
                            C::c('dimensions', 'img_radius', 'Image radius', C::dims(), ['units' => ['px', '%'], 'dims' => ['{{W}} .bx-imagebox-img img', 'border-radius']]),
                            C::c('color', 'title_color', 'Title colour', '', ['css' => ['{{W}} .bx-imagebox-title' => 'color:{{V}}']]),
                            C::c('color', 'text_color', 'Text colour', '', ['css' => ['{{W}} .bx-imagebox-text' => 'color:{{V}}']]),
                        ]],
                    ],
                ],
            ],
            'icon_list' => [
                'label' => 'Icon list', 'icon' => 'list', 'category' => 'basic', 'keywords' => 'bullets checklist features',
                'tabs' => [
                    'content' => [['label' => 'List', 'controls' => [
                        C::c('repeater', 'items', 'Items', [
                            ['text' => 'First benefit', 'icon' => 'check-circle', 'link' => ''],
                            ['text' => 'Second benefit', 'icon' => 'check-circle', 'link' => ''],
                            ['text' => 'Third benefit', 'icon' => 'check-circle', 'link' => ''],
                        ], ['limit' => 40, 'titleField' => 'text', 'fields' => [
                            C::c('text', 'text', 'Text', 'List item', ['max' => 300]),
                            C::c('icon', 'icon', 'Icon', 'check-circle'),
                            C::c('url', 'link', 'Link', ''),
                        ]]),
                        C::c('choose', 'layout', 'Layout', 'vertical', ['choices' => ['vertical' => 'Default', 'inline' => 'Inline']]),
                    ]]],
                    'style' => [['label' => 'List', 'controls' => array_merge([
                        $size('space', 'Space between', '{{W}} .bx-iconlist', 'gap', 'px', 100),
                        C::c('color', 'icon_color', 'Icon colour', '', ['css' => ['{{W}} .bx-iconlist .icon' => 'color:{{V}}']]),
                        $size('icon_size', 'Icon size', '{{W}} .bx-iconlist svg', 'width', 'px', 100),
                        C::c('color', 'text_color', 'Text colour', '', ['css' => ['{{W}} .bx-iconlist li, {{W}} .bx-iconlist a' => 'color:{{V}}']]),
                    ], C::typography('', '{{W}} .bx-iconlist li'))]],
                ],
            ],
            'spacer' => [
                'label' => 'Spacer', 'icon' => 'minus-square', 'category' => 'basic', 'keywords' => 'space gap empty',
                'tabs' => [
                    'content' => [['label' => 'Spacer', 'controls' => [
                        C::c('slider', 'height', 'Space', ['size' => 50, 'unit' => 'px'], ['responsive' => true, 'units' => ['px', 'vh', 'em'], 'min' => 0, 'max' => 1000, 'css' => ['{{W}} .bx-spacer' => 'height:{{V}}']]),
                    ]]],
                    'style' => [],
                ],
            ],
            'divider' => [
                'label' => 'Divider', 'icon' => 'minus', 'category' => 'basic', 'keywords' => 'line separator hr',
                'tabs' => [
                    'content' => [['label' => 'Divider', 'controls' => [
                        C::c('select', 'style', 'Style', 'solid', ['choices' => ['solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'double' => 'Double'], 'css' => ['{{W}} .bx-divider' => 'border-top-style:{{V}}']]),
                        C::c('slider', 'line_width', 'Width', ['size' => 100, 'unit' => '%'], ['responsive' => true, 'units' => ['%', 'px'], 'min' => 0, 'max' => 2000, 'css' => ['{{W}} .bx-divider' => 'width:{{V}}']]),
                        C::c('choose', 'align', 'Alignment', 'center', ['choices' => ['flex-start' => 'Left', 'center' => 'Centre', 'flex-end' => 'Right'], 'css' => ['{{W}} .bx-divider-wrap' => 'justify-content:{{V}}']]),
                    ]]],
                    'style' => [['label' => 'Divider', 'controls' => [
                        C::c('color', 'color', 'Colour', '', ['css' => ['{{W}} .bx-divider' => 'border-top-color:{{V}}']]),
                        C::c('slider', 'weight', 'Weight', ['size' => '', 'unit' => 'px'], ['units' => ['px'], 'min' => 1, 'max' => 20, 'css' => ['{{W}} .bx-divider' => 'border-top-width:{{V}}']]),
                        C::c('slider', 'gap', 'Space above and below', ['size' => '', 'unit' => 'px'], ['responsive' => true, 'units' => ['px'], 'min' => 0, 'max' => 200, 'css' => ['{{W}} .bx-divider-wrap' => 'padding-block:{{V}}']]),
                    ]]],
                ],
            ],
            'video' => [
                'label' => 'Video', 'icon' => 'video', 'category' => 'basic', 'keywords' => 'youtube vimeo embed',
                'tabs' => [
                    'content' => [['label' => 'Video', 'controls' => [
                        C::c('url', 'url', 'YouTube or Vimeo address', ''),
                        C::c('image', 'poster', 'Cover image (optional)', ''),
                        C::c('select', 'ratio', 'Aspect ratio', '16/9', ['choices' => ['16/9' => '16:9', '4/3' => '4:3', '1/1' => '1:1', '9/16' => '9:16 (vertical)', '21/9' => '21:9'], 'css' => ['{{W}} .video-frame' => 'aspect-ratio:{{V}}']]),
                    ]]],
                    'style' => [['label' => 'Video', 'controls' => [
                        C::c('dimensions', 'radius', 'Corner radius', C::dims(), ['units' => ['px', '%'], 'dims' => ['{{W}} .video-frame', 'border-radius']]),
                    ]]],
                ],
            ],
            'counter' => [
                'label' => 'Counter', 'icon' => 'trending-up', 'category' => 'general', 'keywords' => 'number stat statistic',
                'tabs' => [
                    'content' => [['label' => 'Counter', 'controls' => [
                        C::c('text', 'prefix', 'Before the number', '', ['max' => 20]),
                        C::c('number', 'number', 'Number', 100, ['min' => -1000000000, 'max' => 1000000000]),
                        C::c('text', 'suffix', 'After the number', '+', ['max' => 20]),
                        C::c('text', 'title', 'Title', 'Happy customers', ['max' => 200]),
                        C::c('switch', 'animate', 'Count up when visible', true),
                    ]]],
                    'style' => [
                        ['label' => 'Number', 'controls' => array_merge([$align('{{W}}'), C::c('color', 'number_color', 'Colour', '', ['css' => ['{{W}} .bx-counter-number' => 'color:{{V}}']])], C::typography('number_', '{{W}} .bx-counter-number'))],
                        ['label' => 'Title', 'controls' => array_merge([C::c('color', 'title_color', 'Colour', '', ['css' => ['{{W}} .bx-counter-title' => 'color:{{V}}']])], C::typography('title_', '{{W}} .bx-counter-title'))],
                    ],
                ],
            ],
            'testimonial' => [
                'label' => 'Testimonial', 'icon' => 'quote', 'category' => 'general', 'keywords' => 'review quote customer',
                'tabs' => [
                    'content' => [['label' => 'Testimonial', 'controls' => [
                        C::c('textarea', 'quote', 'Quote', 'They made moving our whole team to Dropbox painless.', ['max' => 2000]),
                        C::c('image', 'photo', 'Photo', ''),
                        C::c('text', 'name', 'Name', 'Priya Sharma', ['max' => 120]),
                        C::c('text', 'role', 'Title / company', 'IT Manager, Acme Ltd', ['max' => 200]),
                        C::c('number', 'rating', 'Stars (0 = hide)', 5, ['min' => 0, 'max' => 5]),
                    ]]],
                    'style' => [['label' => 'Testimonial', 'controls' => [
                        $align('{{W}} .bx-testimonial'),
                        C::c('color', 'quote_color', 'Quote colour', '', ['css' => ['{{W}} blockquote' => 'color:{{V}}']]),
                        C::c('color', 'name_color', 'Name colour', '', ['css' => ['{{W}} .bx-testimonial-name' => 'color:{{V}}']]),
                        C::c('color', 'star_color', 'Star colour', '', ['css' => ['{{W}} .bx-stars' => 'color:{{V}}']]),
                    ]]],
                ],
            ],
            'accordion' => [
                'label' => 'Accordion / FAQ', 'icon' => 'help-circle', 'category' => 'general', 'keywords' => 'faq toggle questions collapse',
                'tabs' => [
                    'content' => [['label' => 'Items', 'controls' => [
                        C::c('repeater', 'items', 'Items', [
                            ['title' => 'What is included?', 'content' => '<p>Write the answer here.</p>'],
                            ['title' => 'How long does setup take?', 'content' => '<p>Write the answer here.</p>'],
                        ], ['limit' => 40, 'titleField' => 'title', 'fields' => [
                            C::c('text', 'title', 'Title', 'Question', ['max' => 300]),
                            C::c('richtext', 'content', 'Content', '<p>Answer</p>'),
                        ]]),
                        C::c('switch', 'first_open', 'Open the first item', true),
                        C::c('switch', 'schema', 'Add FAQ structured data (SEO)', true),
                    ]]],
                    'style' => [['label' => 'Accordion', 'controls' => [
                        C::c('color', 'title_color', 'Title colour', '', ['css' => ['{{W}} summary' => 'color:{{V}}']]),
                        C::c('color', 'item_bg', 'Item background', '', ['css' => ['{{W}} details' => 'background:{{V}}']]),
                        C::c('color', 'border_color', 'Border colour', '', ['css' => ['{{W}} details' => 'border-color:{{V}}']]),
                    ]]],
                ],
            ],
            'tabs' => [
                'label' => 'Tabs', 'icon' => 'columns', 'category' => 'general', 'keywords' => 'tabbed panels',
                'tabs' => [
                    'content' => [['label' => 'Tabs', 'controls' => [
                        C::c('repeater', 'items', 'Tabs', [
                            ['title' => 'Tab one', 'content' => '<p>Content of the first tab.</p>'],
                            ['title' => 'Tab two', 'content' => '<p>Content of the second tab.</p>'],
                        ], ['limit' => 12, 'titleField' => 'title', 'fields' => [
                            C::c('text', 'title', 'Title', 'Tab', ['max' => 120]),
                            C::c('richtext', 'content', 'Content', '<p>Content</p>'),
                        ]]),
                    ]]],
                    'style' => [['label' => 'Tabs', 'controls' => [
                        C::c('color', 'active_color', 'Active tab colour', '', ['css' => ['{{W}} [role=tab][aria-selected=true]' => 'color:{{V}};border-color:{{V}}']]),
                    ]]],
                ],
            ],
            'social_icons' => [
                'label' => 'Social icons', 'icon' => 'share', 'category' => 'general', 'keywords' => 'facebook linkedin instagram youtube',
                'tabs' => [
                    'content' => [['label' => 'Social icons', 'controls' => [
                        C::c('repeater', 'items', 'Profiles', [
                            ['icon' => 'linkedin', 'link' => 'https://www.linkedin.com/'],
                            ['icon' => 'facebook', 'link' => 'https://www.facebook.com/'],
                            ['icon' => 'instagram', 'link' => 'https://www.instagram.com/'],
                        ], ['limit' => 12, 'titleField' => 'icon', 'fields' => [
                            C::c('select', 'icon', 'Network', 'linkedin', ['choices' => ['linkedin' => 'LinkedIn', 'twitter' => 'X / Twitter', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'whatsapp' => 'WhatsApp', 'mail' => 'Email', 'phone' => 'Phone', 'globe' => 'Website']]),
                            C::c('url', 'link', 'Link', ''),
                        ]]),
                    ]]],
                    'style' => [['label' => 'Icons', 'controls' => [
                        $flexAlign('{{W}} .bx-social'),
                        C::c('color', 'color', 'Icon colour', '', ['css' => ['{{W}} .bx-social a' => 'color:{{V}}']]),
                        C::c('color', 'bg', 'Background', '', ['css' => ['{{W}} .bx-social a' => 'background:{{V}}']]),
                        $size('icon_size', 'Size', '{{W}} .bx-social svg', 'width', 'px', 100),
                    ]]],
                ],
            ],
            'alert' => [
                'label' => 'Notice', 'icon' => 'info', 'category' => 'general', 'keywords' => 'alert callout message box',
                'tabs' => [
                    'content' => [['label' => 'Notice', 'controls' => [
                        C::c('select', 'kind', 'Type', 'info', ['choices' => ['info' => 'Info', 'success' => 'Success', 'warning' => 'Warning', 'danger' => 'Important']]),
                        C::c('text', 'title', 'Title', 'Good to know', ['max' => 200]),
                        C::c('textarea', 'text', 'Text', 'A short message for your visitors.', ['max' => 1500]),
                    ]]],
                    'style' => [],
                ],
            ],
            'html' => [
                'label' => 'Custom HTML', 'icon' => 'pen', 'category' => 'general', 'keywords' => 'code embed script iframe map css custom', 'code' => true,
                'tabs' => [
                    'content' => [['label' => 'Custom HTML', 'controls' => [
                        C::c('code', 'code', 'HTML', '<div class="my-box">Your HTML here</div>', ['code' => true, 'lang' => 'html', 'max' => Code::MAX_HTML, 'help' => 'Embeds (maps, calendars, videos) and scripts are allowed. Scripts run on the live page only, not in the editor.']),
                        C::c('code', 'css', 'CSS for this widget', '', ['code' => true, 'lang' => 'css', 'max' => Code::MAX_CSS, 'help' => 'Optional. "selector" means this widget, e.g. selector .my-box { padding: 20px }']),
                    ]]],
                    'style' => [],
                ],
            ],
            'form' => [
                'label' => 'Form', 'icon' => 'inbox', 'category' => 'site', 'keywords' => 'contact lead enquiry',
                'tabs' => [
                    'content' => [['label' => 'Form', 'controls' => [
                        C::c('form', 'form', 'Form', 'contact', ['help' => 'Forms are built in Admin → Forms.']),
                        C::c('text', 'heading', 'Heading above the form', '', ['max' => 200]),
                        C::c('text', 'cta_location', 'Lead attribution label', 'Builder form', ['max' => 120]),
                        C::c('switch', 'card', 'Show in a card', true),
                    ]]],
                    'style' => [],
                ],
            ],
            'site_logo' => [
                'label' => 'Site logo', 'icon' => 'dropbox', 'category' => 'site', 'keywords' => 'brand header logo name',
                'tabs' => [
                    'content' => [['label' => 'Logo', 'controls' => [
                        C::c('select', 'show', 'Show', 'logo', ['choices' => ['logo' => 'Logo (or name when there is none)', 'logo_name' => 'Logo and site name', 'name' => 'Site name only']]),
                        C::c('switch', 'dark', 'Use the logo for dark backgrounds', false, ['help' => 'Set it in Website settings → Brand.']),
                    ]]],
                    'style' => [['label' => 'Logo', 'controls' => array_merge([
                        C::c('choose', 'align', 'Alignment', '', ['responsive' => true, 'choices' => ['' => 'Default', 'flex-start' => 'Left', 'center' => 'Centre', 'flex-end' => 'Right'], 'css' => ['{{W}} .bx-logo' => 'justify-content:{{V}}']]),
                        C::c('slider', 'height', 'Logo height', ['size' => '', 'unit' => 'px'], ['responsive' => true, 'units' => ['px'], 'min' => 10, 'max' => 200, 'css' => ['{{W}} .bx-logo img' => 'height:{{V}};width:auto']]),
                        C::c('color', 'color', 'Name colour', '', ['css' => ['{{W}} .bx-logo' => 'color:{{V}}']]),
                    ], C::typography('', '{{W}} .bx-logo-name'))]],
                ],
            ],
            'nav_menu' => [
                'label' => 'Menu', 'icon' => 'menu', 'category' => 'site', 'keywords' => 'navigation nav links header footer',
                'tabs' => [
                    'content' => [['label' => 'Menu', 'controls' => [
                        C::c('select', 'menu', 'Menu', 'HEADER', ['choices' => ['HEADER' => 'Header menu', 'FOOTER' => 'Footer menu', 'FOOTER_SECONDARY' => 'Footer — second menu', 'LEGAL' => 'Legal links', 'MOBILE' => 'Mobile menu'], 'help' => 'Menus are edited in Admin → Navigation, for each market.']),
                        C::c('choose', 'layout', 'Layout', 'horizontal', ['choices' => ['horizontal' => 'Horizontal', 'vertical' => 'Vertical', 'columns' => 'Columns'], 'help' => 'Columns: each top-level link becomes a column heading with its sub-links below — for footers.']),
                        C::c('select', 'collapse', 'Turn into a menu button on', 'mobile', ['choices' => ['mobile' => 'Mobile', 'tablet' => 'Tablet and mobile', 'none' => 'Never']]),
                        C::c('text', 'toggle_label', 'Menu button label', 'Menu', ['max' => 40]),
                        C::c('switch', 'submenus', 'Show sub-menus', true),
                    ]]],
                    'style' => [
                        ['label' => 'Links', 'controls' => array_merge([
                            C::c('choose', 'align', 'Alignment', '', ['responsive' => true, 'choices' => ['' => 'Default', 'flex-start' => 'Start', 'center' => 'Centre', 'flex-end' => 'End', 'space-between' => 'Spread'], 'css' => ['{{W}} .bx-nav-list' => 'justify-content:{{V}}']]),
                            C::c('slider', 'gap', 'Space between', ['size' => '', 'unit' => 'px'], ['responsive' => true, 'units' => ['px', 'em'], 'min' => 0, 'max' => 120, 'css' => ['{{W}} .bx-nav-list' => 'gap:{{V}}']]),
                            C::c('color', 'color', 'Link colour', '', ['css' => ['{{W}} .bx-nav-list > li > a' => 'color:{{V}}']]),
                            C::c('color', 'hover', 'Hover / current colour', '', ['css' => ['{{W}} .bx-nav-list > li > a:hover, {{W}} .bx-nav-list > li > a.active' => 'color:{{V}}']]),
                            C::c('color', 'heading_color', 'Column heading colour', '', ['showWhen' => ['layout' => ['columns']], 'css' => ['{{W}} .bx-nav-columns .bx-nav-list > li > a' => 'color:{{V}}']]),
                        ], C::typography('', '{{W}} .bx-nav-list > li > a'))],
                        ['label' => 'Sub-menu and mobile panel', 'controls' => [
                            C::c('color', 'sub_bg', 'Background', '', ['css' => ['{{W}} .bx-sub, {{W}} .bx-nav.is-open .bx-nav-list' => 'background:{{V}}']]),
                            C::c('color', 'sub_color', 'Link colour', '', ['css' => ['{{W}} .bx-sub a' => 'color:{{V}}']]),
                            C::c('color', 'toggle_color', 'Menu button colour', '', ['css' => ['{{W}} .bx-nav-toggle' => 'color:{{V}}']]),
                        ]],
                    ],
                ],
            ],
            'market_switcher' => [
                'label' => 'Country switcher', 'icon' => 'globe', 'category' => 'site', 'keywords' => 'market language country region',
                'tabs' => [
                    'content' => [['label' => 'Country switcher', 'controls' => [
                        C::c('choose', 'label', 'Show', 'code', ['choices' => ['code' => 'Code (IN)', 'name' => 'Name (India)']]),
                    ]]],
                    'style' => [['label' => 'Switcher', 'controls' => [C::c('color', 'color', 'Colour', '', ['css' => ['{{W}} .market-switch summary' => 'color:{{V}}']])]]],
                ],
            ],
            'copyright' => [
                'label' => 'Copyright', 'icon' => 'file-text', 'category' => 'site', 'keywords' => 'footer year rights',
                'tabs' => [
                    'content' => [['label' => 'Copyright', 'controls' => [
                        C::c('text', 'text', 'Text', '© {year} {site}. All rights reserved.', ['max' => 300, 'help' => '{year} = this year, {site} = the company name of the market.']),
                    ]]],
                    'style' => [['label' => 'Text', 'controls' => array_merge([
                        C::c('choose', 'align', 'Alignment', '', ['responsive' => true, 'choices' => ['' => 'Default'] + C::alignChoices(), 'css' => ['{{W}}' => 'text-align:{{V}}']]),
                        C::c('color', 'color', 'Colour', '', ['css' => ['{{W}} .bx-copyright' => 'color:{{V}}']]),
                    ], C::typography('', '{{W}} .bx-copyright'))]],
                ],
            ],
            'contact_info' => [
                'label' => 'Contact info', 'icon' => 'phone', 'category' => 'site', 'keywords' => 'phone email address whatsapp hours footer',
                'tabs' => [
                    'content' => [['label' => 'Contact info', 'controls' => [
                        C::c('switch', 'phone', 'Phone', true),
                        C::c('switch', 'email', 'Email', true),
                        C::c('switch', 'whatsapp', 'WhatsApp', false),
                        C::c('switch', 'address', 'Address', true),
                        C::c('switch', 'hours', 'Business hours', false),
                        C::c('choose', 'layout', 'Layout', 'vertical', ['choices' => ['vertical' => 'Stacked', 'inline' => 'In a row']]),
                        C::c('heading', '', 'Values come from the market settings (Admin → Countries).'),
                    ]]],
                    'style' => [['label' => 'Contact info', 'controls' => [
                        C::c('color', 'icon_color', 'Icon colour', '', ['css' => ['{{W}} .bx-contact .icon' => 'color:{{V}}']]),
                        C::c('color', 'color', 'Text colour', '', ['css' => ['{{W}} .bx-contact, {{W}} .bx-contact a' => 'color:{{V}}']]),
                        C::c('slider', 'gap', 'Space between', ['size' => '', 'unit' => 'px'], ['units' => ['px'], 'min' => 0, 'max' => 60, 'css' => ['{{W}} .bx-contact' => 'gap:{{V}}']]),
                    ]]],
                ],
            ],
            'breadcrumbs' => [
                'label' => 'Breadcrumbs', 'icon' => 'chevron-right', 'category' => 'site', 'keywords' => 'navigation trail',
                'tabs' => [
                    'content' => [['label' => 'Breadcrumbs', 'controls' => [
                        C::c('text', 'home', 'Home label', 'Home', ['max' => 60]),
                    ]]],
                    'style' => [['label' => 'Breadcrumbs', 'controls' => [$align('{{W}}'), C::c('color', 'color', 'Colour', '', ['css' => ['{{W}} .breadcrumbs, {{W}} .breadcrumbs a' => 'color:{{V}}']])]]],
                ],
            ],
        ];
    }

    // --- product widgets ----------------------------------------------------------------

    private static function productWidgets(): array
    {
        $p = static fn (string $type, string $label, string $icon, array $content, array $style = [], string $kw = '') => [
            $type => [
                'label' => $label, 'icon' => $icon, 'category' => 'product', 'context' => 'product', 'keywords' => 'product ' . $kw,
                'tabs' => ['content' => $content ? [['label' => $label, 'controls' => $content]] : [], 'style' => $style],
            ],
        ];
        $align = C::c('choose', 'align', 'Alignment', '', ['responsive' => true, 'choices' => ['' => 'Default'] + C::alignChoices(), 'css' => ['{{W}}' => 'text-align:{{V}}']]);
        $textStyle = static fn (string $sel) => [['label' => 'Text', 'controls' => array_merge([
            C::c('choose', 'align', 'Alignment', '', ['responsive' => true, 'choices' => ['' => 'Default'] + C::alignChoices(), 'css' => ['{{W}}' => 'text-align:{{V}}']]),
            C::c('color', 'color', 'Colour', '', ['css' => [$sel => 'color:{{V}}']]),
        ], C::typography('', $sel))]];

        return $p('product_title', 'Product title', 'type', [
                C::c('select', 'tag', 'HTML tag', 'h1', ['choices' => ['h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'div' => 'div']]),
            ], $textStyle('{{W}} .bx-product-title'), 'name title')
            + $p('product_price', 'Product price', 'tag', [
                C::c('switch', 'toggle', 'Monthly / annual switch', true),
                C::c('switch', 'compare', 'Show the compare-at price', true),
                C::c('switch', 'note', 'Show the price note', true),
            ], $textStyle('{{W}} .amount'), 'cost pricing')
            + $p('product_image', 'Product image', 'image', [
                C::c('switch', 'lazy', 'Lazy load', false),
            ], [['label' => 'Image', 'controls' => [
                $align,
                C::c('slider', 'img_width', 'Width', ['size' => '', 'unit' => '%'], ['responsive' => true, 'units' => ['%', 'px'], 'max' => 2000, 'css' => ['{{W}} img' => 'width:{{V}}']]),
                C::c('dimensions', 'img_radius', 'Corner radius', C::dims(), ['units' => ['px', '%'], 'dims' => ['{{W}} img', 'border-radius']]),
            ]]], 'picture photo')
            + $p('product_gallery', 'Product gallery', 'layers', [
                C::c('select', 'columns', 'Columns', '3', ['choices' => ['2' => '2', '3' => '3', '4' => '4']]),
            ], [], 'images')
            + $p('product_short_description', 'Short description', 'file-text', [], $textStyle('{{W}} .bx-product-text'), 'summary excerpt')
            + $p('product_description', 'Description', 'file-text', [], $textStyle('{{W}} .prose'), 'content details')
            + $p('product_features', 'Features', 'check-circle', [
                C::c('text', 'heading', 'Heading', "What's included", ['max' => 200]),
                C::c('select', 'columns', 'Columns', '2', ['choices' => ['1' => '1', '2' => '2', '3' => '3']]),
                C::c('number', 'limit', 'Show at most (0 = all)', 0, ['min' => 0, 'max' => 100]),
            ], [['label' => 'List', 'controls' => [C::c('color', 'icon_color', 'Icon colour', '', ['css' => ['{{W}} .icon' => 'color:{{V}}']])]]], 'included list')
            + $p('product_benefits', 'Benefits', 'award', [
                C::c('text', 'heading', 'Heading ({product} = name)', 'Top benefits of {product}', ['max' => 200]),
            ], [], 'advantages')
            + $p('product_specs', 'Specifications', 'list', [
                C::c('text', 'heading', 'Heading', '', ['max' => 200]),
            ], [], 'specs details table')
            + $p('product_button', 'Product button', 'shopping-cart', [
                C::c('text', 'text', 'Text (blank = the product\'s)', '', ['max' => 120]),
                C::c('select', 'size', 'Size', 'lg', ['choices' => ['sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large']]),
                C::c('switch', 'full', 'Full width', true),
            ], [['label' => 'Button', 'controls' => [
                C::c('color', 'text_color', 'Text colour', '', ['css' => ['{{W}} .bx-btn' => 'color:{{V}}']]),
                C::c('color', 'bg', 'Background', '', ['css' => ['{{W}} .bx-btn' => 'background:{{V}}']]),
                C::c('color', 'hover_bg', 'Hover background', '', ['css' => ['{{W}} .bx-btn:hover' => 'background:{{V}}']]),
            ]]], 'cta buy quote')
            + $p('product_form', 'Product enquiry form', 'inbox', [
                C::c('text', 'heading', 'Heading ({product} = name)', 'Get {product}', ['max' => 200]),
                C::c('switch', 'card', 'Show in a card', true),
            ], [], 'lead quote')
            + $p('product_related', 'Related products', 'grid', [
                C::c('text', 'heading', 'Heading', 'You may also like', ['max' => 200]),
                C::c('number', 'limit', 'How many', 3, ['min' => 1, 'max' => 8]),
            ], [], 'similar more')
            + $p('product_breadcrumbs', 'Product breadcrumbs', 'chevron-right', [], [], 'navigation');
    }

    // --- CMS blocks as widgets ----------------------------------------------------------

    private static function block(string $type, array $block): array
    {
        $controls = array_map([self::class, 'blockField'], $block['fields']);
        return [
            'label' => $block['label'], 'icon' => $block['icon'], 'category' => 'sections',
            'keywords' => strtolower($block['group'] . ' ' . $block['description']),
            'block' => $type,
            'tabs' => [
                'content' => [['label' => $block['label'], 'controls' => $controls]],
                'style' => [['label' => 'Section', 'controls' => [
                    C::c('select', '_preset', 'Colour scheme', 'default', ['choices' => Design::PRESETS]),
                    C::c('select', '_section_width', 'Content width', 'boxed', ['choices' => Design::WIDTHS]),
                    C::c('switch', '_flush', 'Remove the section\'s own top and bottom space', false),
                ]]],
            ],
        ];
    }

    /** A CMS block field descriptor as an editor control (values are cleaned by Blocks). */
    private static function blockField(array $f): array
    {
        [$name, $type, $label] = $f;
        $o = $f[4] ?? [];
        $map = ['bool' => 'switch', 'length' => 'text'];
        $control = C::c($map[$type] ?? $type, $name, $label, $f[3] ?? '', array_intersect_key($o, array_flip(['choices', 'help', 'showWhen', 'min', 'max', 'limit'])));
        if ($type === 'repeater') {
            $control['fields'] = array_map([self::class, 'blockField'], $o['fields'] ?? []);
            $control['titleField'] = $control['fields'][0]['key'] ?? '';
        }
        if ($type === 'length') {
            $control['placeholder'] = 'e.g. 48px';
        }
        return $control;
    }
}
