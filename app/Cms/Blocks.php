<?php

declare(strict_types=1);

namespace App\Cms;

use App\Core\Sanitizer;

/**
 * The block registry.
 *
 * A block is a type key, a label, a group and a list of field descriptors.
 * The admin section editor is generated from the descriptors, saved content
 * is normalised against them (types coerced, lengths capped, rich text
 * sanitised, URLs checked, unknown keys dropped), and a renderer at
 * views/blocks/<type>.php draws it. Adding a block = a definition here plus
 * one template; the page builder needs no changes.
 *
 * Field descriptor: [name, type, label, default, options]
 *   types: text, textarea, richtext, number, bool, select, url, image, icon,
 *          color, length, form, magnet, products, category, brand,
 *          blogcategory, list (one item per line), repeater (options.fields)
 *   options: max, min, choices (value => label), help, showWhen [field => [values]]
 */
final class Blocks
{
    public const GROUPS = ['Content', 'Media', 'Products', 'Conversion', 'Social proof', 'Blog', 'Layout'];

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $defs = null;

    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        return self::$defs ??= self::define();
    }

    public static function get(string $type): ?array
    {
        return self::all()[$type] ?? null;
    }

    public static function exists(string $type): bool
    {
        return isset(self::all()[$type]);
    }

    /** @return array<string, mixed> */
    public static function defaults(string $type): array
    {
        $def = self::get($type);
        if (!$def) {
            return [];
        }
        $out = [];
        foreach ($def['fields'] as $field) {
            $out[$field[0]] = self::fieldDefault($field);
        }
        return $out;
    }

    /**
     * Coerce raw input (a form post or stored JSON) into the block's shape.
     * Never throws: anything unusable falls back to the field's default, so
     * partial or stale content can never break a page.
     *
     * @return array<string, mixed>
     */
    public static function normalize(string $type, mixed $input): array
    {
        $def = self::get($type);
        if (!$def) {
            return [];
        }
        $input = is_array($input) ? $input : [];
        $out = [];
        foreach ($def['fields'] as $field) {
            $out[$field[0]] = self::normalizeField($field, $input[$field[0]] ?? null, array_key_exists($field[0], $input));
        }
        return $out;
    }

    /** @param array<int, mixed> $field */
    private static function fieldDefault(array $field): mixed
    {
        $default = $field[3] ?? null;
        return match ($field[1]) {
            'bool' => (bool) $default,
            'number' => is_numeric($default) ? $default + 0 : 0,
            'list', 'products' => is_array($default) ? $default : [],
            'repeater' => is_array($default) ? array_map(
                static fn ($item) => self::normalizeRepeaterItem($field[4]['fields'] ?? [], $item),
                $default
            ) : [],
            default => (string) ($default ?? ''),
        };
    }

    /** @param array<int, mixed> $field */
    private static function normalizeField(array $field, mixed $value, bool $present): mixed
    {
        [$name, $type] = $field;
        $opts = $field[4] ?? [];
        $max = (int) ($opts['max'] ?? 0);
        if (!$present) {
            return $type === 'bool' ? false : self::fieldDefault($field);
        }
        switch ($type) {
            case 'bool':
                return in_array($value, [true, 1, '1', 'on', 'true'], true);
            case 'number':
                if (!is_numeric($value)) {
                    return self::fieldDefault($field);
                }
                $n = $value + 0;
                if (isset($opts['min'])) $n = max($opts['min'], $n);
                if (isset($opts['max'])) $n = min($opts['max'], $n);
                return $n;
            case 'select':
                $choices = array_map('strval', array_keys($opts['choices'] ?? []));
                return in_array((string) $value, $choices, true) ? (string) $value : (string) ($field[3] ?? ($choices[0] ?? ''));
            case 'richtext':
                return Sanitizer::html(is_string($value) ? $value : '');
            case 'textarea':
                return Sanitizer::text(is_scalar($value) ? (string) $value : '', $max ?: 4000);
            case 'url':
            case 'image':
                return Sanitizer::url(is_string($value) ? $value : '', $type === 'image');
            case 'icon':
                return is_string($value) && isset(Icons::PATHS[$value]) ? $value : '';
            case 'color':
                return Sanitizer::color(is_string($value) ? $value : '');
            case 'length':
                return Sanitizer::length(is_string($value) ? $value : '');
            case 'list':
                if (is_string($value)) {
                    $value = preg_split('/\R/', $value) ?: [];
                }
                $items = [];
                foreach ((array) $value as $item) {
                    $line = Sanitizer::line(is_scalar($item) ? (string) $item : '', $max ?: 300);
                    if ($line !== '') {
                        $items[] = $line;
                    }
                }
                return array_slice($items, 0, 50);
            case 'products':
                $ids = is_string($value) ? explode(',', $value) : (array) $value;
                return array_slice(array_values(array_unique(array_filter(array_map('intval', $ids)))), 0, 24);
            case 'repeater':
                $items = [];
                foreach (array_values(is_array($value) ? $value : []) as $item) {
                    if (is_array($item)) {
                        $items[] = self::normalizeRepeaterItem($opts['fields'] ?? [], $item);
                    }
                }
                return array_slice($items, 0, (int) ($opts['limit'] ?? 50));
            default: // text, form, magnet, category, brand, blogcategory
                return Sanitizer::line(is_scalar($value) ? (string) $value : '', $max ?: 300);
        }
    }

    /** @param list<array<int,mixed>> $fields @return array<string, mixed> */
    private static function normalizeRepeaterItem(array $fields, mixed $item): array
    {
        $item = is_array($item) ? $item : [];
        $out = [];
        foreach ($fields as $f) {
            $out[$f[0]] = self::normalizeField($f, $item[$f[0]] ?? null, array_key_exists($f[0], $item));
        }
        return $out;
    }

    // --- definitions -------------------------------------------------------

    /** @return array<string, array<string, mixed>> */
    private static function define(): array
    {
        $align = ['align', 'select', 'Alignment', 'left', ['choices' => ['left' => 'Left', 'center' => 'Centre']]];
        $alignC = ['align', 'select', 'Alignment', 'center', ['choices' => ['left' => 'Left', 'center' => 'Centre']]];
        $eyebrow = ['eyebrow', 'text', 'Eyebrow', '', ['max' => 120, 'help' => 'Small label above the heading.']];
        $heading = static fn (string $default = '') => ['heading', 'text', 'Heading', $default, ['max' => 240]];
        $sub = ['subheading', 'textarea', 'Intro text', '', ['max' => 1000]];
        $cols = static fn (string $d = '3') => ['columns', 'select', 'Columns (desktop)', $d, ['choices' => ['1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6']]];
        $buttons = [
            ['primary_label', 'text', 'Primary button label', '', ['max' => 60]],
            ['primary_url', 'url', 'Primary button link', '', ['help' => 'A path (/contact), a full URL, #popup-ID to open a popup, or #form to scroll to the form.']],
            ['secondary_label', 'text', 'Secondary button label', '', ['max' => 60]],
            ['secondary_url', 'url', 'Secondary button link', ''],
        ];
        $productSource = [
            ['source', 'select', 'Products to show', 'featured', ['choices' => [
                'featured' => 'Featured products', 'all' => 'All products', 'category' => 'By category',
                'brand' => 'By brand', 'manual' => 'Pick products',
            ]]],
            ['category', 'category', 'Category', '', ['showWhen' => ['source' => ['category']]]],
            ['brand', 'brand', 'Brand', '', ['showWhen' => ['source' => ['brand']]]],
            ['products', 'products', 'Products (in order)', [], ['showWhen' => ['source' => ['manual']]]],
            ['limit', 'number', 'Maximum products', 6, ['min' => 1, 'max' => 24]],
        ];

        return [
            'hero' => [
                'label' => 'Hero', 'group' => 'Content', 'icon' => 'sparkles',
                'description' => 'The opening section: headline, text, buttons, and an optional image or lead form.',
                'fields' => [
                    ['layout', 'select', 'Layout', 'contentImage', ['choices' => [
                        'content' => 'Text only', 'contentImage' => 'Text + image', 'contentForm' => 'Text + form',
                        'backgroundImage' => 'Text over background image',
                    ]]],
                    $eyebrow,
                    $heading('Get {brand} for your team'),
                    ['description', 'textarea', 'Description', 'Local billing, expert deployment and support for teams of every size.', ['max' => 1200]],
                    ['bullets', 'list', 'Bullet points', [], ['help' => 'One per line.']],
                    ...$buttons,
                    ['image', 'image', 'Image', '', ['showWhen' => ['layout' => ['contentImage', 'backgroundImage']]]],
                    ['image_alt', 'text', 'Image alt text', '', ['max' => 200, 'showWhen' => ['layout' => ['contentImage']]]],
                    ['image_side', 'select', 'Image side', 'right', ['choices' => ['right' => 'Right', 'left' => 'Left'], 'showWhen' => ['layout' => ['contentImage']]]],
                    ['split', 'select', 'Text column width', '50', ['choices' => ['40' => '40%', '45' => '45%', '50' => '50%', '55' => '55%', '60' => '60%', '65' => '65%'], 'showWhen' => ['layout' => ['contentImage', 'contentForm']]]],
                    ['form_slug', 'form', 'Form', '', ['showWhen' => ['layout' => ['contentForm']]]],
                    ['form_heading', 'text', 'Form heading', 'Get a quote', ['max' => 160, 'showWhen' => ['layout' => ['contentForm']]]],
                    ['form_description', 'textarea', 'Form description', '', ['max' => 400, 'showWhen' => ['layout' => ['contentForm']]]],
                    ['cta_location', 'text', 'Lead attribution label', 'Hero', ['max' => 120, 'help' => 'Recorded on every lead from this form, so you can see which placement converted.']],
                    ['badges', 'repeater', 'Trust badges', [], ['fields' => [
                        ['icon', 'icon', 'Icon', 'check'],
                        ['label', 'text', 'Label', '', ['max' => 80]],
                    ], 'limit' => 6]],
                    $align,
                ],
            ],
            'headingText' => [
                'label' => 'Heading & text', 'group' => 'Content', 'icon' => 'type',
                'description' => 'A heading, a paragraph of rich text and optional buttons.',
                'fields' => [$eyebrow, $heading('Section heading'), ['body', 'richtext', 'Text', '<p>Write something here.</p>'], ...$buttons, $alignC],
            ],
            'richText' => [
                'label' => 'Rich text', 'group' => 'Content', 'icon' => 'file-text',
                'description' => 'Free-form formatted text: headings, lists, links, tables, images.',
                'fields' => [
                    ['body', 'richtext', 'Content', '<h2>Heading</h2><p>Your content.</p>'],
                    ['max_width', 'select', 'Text width', 'normal', ['choices' => ['narrow' => 'Narrow (reading)', 'normal' => 'Normal', 'wide' => 'Full container']]],
                ],
            ],
            'featureGrid' => [
                'label' => 'Feature grid', 'group' => 'Content', 'icon' => 'grid',
                'description' => 'Features or benefits in a grid, each with an icon, title and text.',
                'fields' => [
                    $eyebrow, $heading('Why teams choose {brand}'), $sub, $cols('3'),
                    ['style', 'select', 'Card style', 'cards', ['choices' => ['cards' => 'Cards', 'bordered' => 'Bordered', 'plain' => 'Plain']]],
                    ['items', 'repeater', 'Features', [
                        ['icon' => 'shield-check', 'title' => 'Enterprise-grade security', 'text' => 'Encryption, admin controls and compliance built in.', 'link_label' => '', 'link_url' => ''],
                        ['icon' => 'refresh', 'title' => 'Effortless sync', 'text' => 'Files stay up to date on every device.', 'link_label' => '', 'link_url' => ''],
                        ['icon' => 'users', 'title' => 'Built for teams', 'text' => 'Shared folders, permissions and team spaces.', 'link_label' => '', 'link_url' => ''],
                    ], ['fields' => [
                        ['icon', 'icon', 'Icon', 'check'],
                        ['title', 'text', 'Title', '', ['max' => 160]],
                        ['text', 'textarea', 'Text', '', ['max' => 600]],
                        ['link_label', 'text', 'Link label', '', ['max' => 60]],
                        ['link_url', 'url', 'Link', ''],
                    ]]],
                    $alignC,
                ],
            ],
            'iconCards' => [
                'label' => 'Icon cards', 'group' => 'Content', 'icon' => 'layout',
                'description' => 'Centred cards with a large icon — services, use cases, highlights.',
                'fields' => [
                    $eyebrow, $heading('What we do'), $sub, $cols('4'),
                    ['icon_style', 'select', 'Icon style', 'circle', ['choices' => ['circle' => 'Circle', 'square' => 'Rounded square', 'none' => 'Icon only']]],
                    ['items', 'repeater', 'Cards', [
                        ['icon' => 'rocket', 'title' => 'Deployment', 'text' => 'We set up your team and migrate your files.'],
                        ['icon' => 'headphones', 'title' => 'Local support', 'text' => 'Talk to people who know your account.'],
                        ['icon' => 'credit-card', 'title' => 'Local billing', 'text' => 'Pay in your currency with a GST invoice.'],
                        ['icon' => 'award', 'title' => 'Authorised partner', 'text' => 'Genuine licences, full warranty.'],
                    ], ['fields' => [
                        ['icon', 'icon', 'Icon', 'star'],
                        ['title', 'text', 'Title', '', ['max' => 160]],
                        ['text', 'textarea', 'Text', '', ['max' => 600]],
                        ['link_url', 'url', 'Link (optional)', ''],
                    ]]],
                ],
            ],
            'imageCards' => [
                'label' => 'Image cards', 'group' => 'Media', 'icon' => 'image',
                'description' => 'Cards with an image, title, text and a link.',
                'fields' => [
                    $eyebrow, $heading('Explore'), $sub, $cols('3'),
                    ['ratio', 'select', 'Image shape', '16/9', ['choices' => ['16/9' => 'Wide 16:9', '4/3' => '4:3', '1/1' => 'Square', '3/4' => 'Portrait']]],
                    ['items', 'repeater', 'Cards', [], ['fields' => [
                        ['image', 'image', 'Image', ''],
                        ['image_alt', 'text', 'Alt text', '', ['max' => 200]],
                        ['title', 'text', 'Title', '', ['max' => 160]],
                        ['text', 'textarea', 'Text', '', ['max' => 600]],
                        ['link_label', 'text', 'Link label', 'Learn more', ['max' => 60]],
                        ['link_url', 'url', 'Link', ''],
                    ]]],
                ],
            ],
            'imageContent' => [
                'label' => 'Image + content', 'group' => 'Media', 'icon' => 'columns',
                'description' => 'Text beside an image, with optional bullets and buttons.',
                'fields' => [
                    $eyebrow, $heading('Work together from anywhere'),
                    ['body', 'richtext', 'Text', '<p>Describe the benefit in a sentence or two.</p>'],
                    ['bullets', 'list', 'Bullet points', []],
                    ['bullet_icon', 'icon', 'Bullet icon', 'check-circle'],
                    ['image', 'image', 'Image', ''],
                    ['image_alt', 'text', 'Image alt text', '', ['max' => 200]],
                    ['image_side', 'select', 'Image side', 'right', ['choices' => ['right' => 'Right', 'left' => 'Left']]],
                    ['image_rounded', 'bool', 'Rounded image corners', true],
                    ...$buttons,
                ],
            ],
            'listSection' => [
                'label' => 'Checklist', 'group' => 'Content', 'icon' => 'list',
                'description' => 'A list of points with icons, in one or more columns.',
                'fields' => [
                    $eyebrow, $heading('Everything included'), $sub,
                    ['columns', 'select', 'Columns', '2', ['choices' => ['1' => '1', '2' => '2', '3' => '3']]],
                    ['icon', 'icon', 'Icon', 'check-circle'],
                    ['items', 'list', 'Items', ['Licence provisioning', 'Admin console setup', 'Data migration', 'User training']],
                    $alignC,
                ],
            ],
            'cta' => [
                'label' => 'Call to action', 'group' => 'Conversion', 'icon' => 'megaphone',
                'description' => 'A strong closing message with buttons.',
                'fields' => [
                    ['style', 'select', 'Style', 'banner', ['choices' => ['banner' => 'Full-width banner', 'card' => 'Card', 'split' => 'Text left, buttons right']]],
                    $heading('Ready to get started with {brand}?'),
                    ['text', 'textarea', 'Text', 'Get pricing, a free trial and deployment help from our team.', ['max' => 800]],
                    ...$buttons,
                ],
            ],
            'faq' => [
                'label' => 'FAQ', 'group' => 'Content', 'icon' => 'help-circle',
                'description' => 'Questions and answers. Adds FAQPage structured data automatically.',
                'fields' => [
                    $eyebrow, $heading('Frequently asked questions'), $sub,
                    ['layout', 'select', 'Layout', 'accordion', ['choices' => ['accordion' => 'Accordion', 'two-column' => 'Two columns, open']]],
                    ['items', 'repeater', 'Questions', [
                        ['question' => 'Are you an authorised partner?', 'answer' => '<p>Yes. Every licence we sell is genuine and comes with full manufacturer support.</p>'],
                        ['question' => 'Can I pay in my local currency?', 'answer' => '<p>Yes, with a tax invoice for your business.</p>'],
                    ], ['fields' => [
                        ['question', 'text', 'Question', '', ['max' => 300]],
                        ['answer', 'richtext', 'Answer', ''],
                    ]]],
                    ['schema', 'bool', 'Add FAQ structured data', true],
                ],
            ],
            'stats' => [
                'label' => 'Statistics', 'group' => 'Social proof', 'icon' => 'trending-up',
                'description' => 'Big numbers with labels.',
                'fields' => [
                    $eyebrow, $heading(''), $sub, $cols('4'),
                    ['items', 'repeater', 'Numbers', [
                        ['value' => '700M+', 'label' => 'Registered users', 'text' => ''],
                        ['value' => '18M+', 'label' => 'Paying users', 'text' => ''],
                        ['value' => '575K+', 'label' => 'Business teams', 'text' => ''],
                        ['value' => '180+', 'label' => 'Countries', 'text' => ''],
                    ], ['fields' => [
                        ['value', 'text', 'Value', '', ['max' => 40]],
                        ['label', 'text', 'Label', '', ['max' => 120]],
                        ['text', 'textarea', 'Detail', '', ['max' => 300]],
                    ]]],
                ],
            ],
            'steps' => [
                'label' => 'Steps', 'group' => 'Content', 'icon' => 'navigation',
                'description' => 'A numbered process: how it works, onboarding, migration.',
                'fields' => [
                    $eyebrow, $heading('How it works'), $sub,
                    ['items', 'repeater', 'Steps', [
                        ['icon' => 'message', 'title' => 'Tell us what you need', 'text' => 'Users, storage and the plan you have in mind.'],
                        ['icon' => 'file-text', 'title' => 'Get a quote', 'text' => 'Transparent pricing in your currency.'],
                        ['icon' => 'rocket', 'title' => 'Go live', 'text' => 'We provision licences and help your team start.'],
                    ], ['fields' => [
                        ['icon', 'icon', 'Icon', ''],
                        ['title', 'text', 'Title', '', ['max' => 160]],
                        ['text', 'textarea', 'Text', '', ['max' => 600]],
                    ]]],
                ],
            ],
            'testimonials' => [
                'label' => 'Testimonials', 'group' => 'Social proof', 'icon' => 'quote',
                'description' => 'Customer quotes with name, role and rating.',
                'fields' => [
                    $eyebrow, $heading('What our customers say'), $sub, $cols('3'),
                    ['items', 'repeater', 'Testimonials', [
                        ['quote' => 'The migration was painless and the team was always a call away.', 'name' => 'Priya S.', 'role' => 'IT Manager', 'company' => '', 'avatar' => '', 'rating' => 5],
                    ], ['fields' => [
                        ['quote', 'textarea', 'Quote', '', ['max' => 1000]],
                        ['name', 'text', 'Name', '', ['max' => 120]],
                        ['role', 'text', 'Role', '', ['max' => 120]],
                        ['company', 'text', 'Company', '', ['max' => 120]],
                        ['avatar', 'image', 'Photo', ''],
                        ['rating', 'number', 'Rating (0–5)', 5, ['min' => 0, 'max' => 5]],
                    ]]],
                ],
            ],
            'logoWall' => [
                'label' => 'Logo wall', 'group' => 'Social proof', 'icon' => 'award',
                'description' => 'Customer, partner or certification logos.',
                'fields' => [
                    $heading('Trusted by teams at'),
                    ['grayscale', 'bool', 'Show logos in greyscale', true],
                    ['items', 'repeater', 'Logos', [], ['fields' => [
                        ['image', 'image', 'Logo', ''],
                        ['alt', 'text', 'Name (alt text)', '', ['max' => 120]],
                        ['url', 'url', 'Link (optional)', ''],
                    ]]],
                ],
            ],
            'productGrid' => [
                'label' => 'Product cards', 'group' => 'Products', 'icon' => 'package',
                'description' => 'Pricing cards for products from the catalogue, with monthly/annual toggle.',
                'fields' => [
                    $eyebrow, $heading('Choose your plan'), $sub,
                    ...$productSource,
                    $cols('3'),
                    ['billing', 'select', 'Show price', 'annual', ['choices' => ['annual' => 'Annual (per month)', 'monthly' => 'Monthly', 'toggle' => 'Let visitors toggle']]],
                    ['show_features', 'bool', 'Show features', true],
                    ['feature_limit', 'number', 'Features per card', 6, ['min' => 0, 'max' => 20]],
                    ['highlight_featured', 'bool', 'Highlight featured products', true],
                ],
            ],
            'productTable' => [
                'label' => 'Product comparison', 'group' => 'Products', 'icon' => 'columns',
                'description' => 'Compare catalogue products side by side, from their specs and features.',
                'fields' => [
                    $eyebrow, $heading('Compare plans'), $sub,
                    ...$productSource,
                    ['rows', 'list', 'Rows to compare', ['Storage', 'Users'], ['help' => 'Spec labels, one per line. "Price" and "Features" are always available.']],
                    ['show_price', 'bool', 'Show price row', true],
                ],
            ],
            'comparisonTable' => [
                'label' => 'Comparison table', 'group' => 'Content', 'icon' => 'columns',
                'description' => 'A fully manual comparison table: your own columns, rows and cell values.',
                'fields' => [
                    $eyebrow, $heading('How we compare'), $sub,
                    ['columns', 'repeater', 'Columns', [
                        ['name' => '{brand}', 'subtitle' => 'Business', 'price' => '', 'button_label' => 'Get a quote', 'button_url' => '/contact', 'highlight' => true, 'badge' => 'Recommended'],
                        ['name' => 'Google Workspace', 'subtitle' => '', 'price' => '', 'button_label' => '', 'button_url' => '', 'highlight' => false, 'badge' => ''],
                        ['name' => 'Microsoft 365', 'subtitle' => '', 'price' => '', 'button_label' => '', 'button_url' => '', 'highlight' => false, 'badge' => ''],
                    ], ['fields' => [
                        ['name', 'text', 'Name', '', ['max' => 80]],
                        ['subtitle', 'text', 'Subtitle', '', ['max' => 120]],
                        ['price', 'text', 'Price', '', ['max' => 60]],
                        ['button_label', 'text', 'Button label', '', ['max' => 60]],
                        ['button_url', 'url', 'Button link', ''],
                        ['highlight', 'bool', 'Highlight this column', false],
                        ['badge', 'text', 'Badge', '', ['max' => 40]],
                    ], 'limit' => 6]],
                    ['rows', 'repeater', 'Rows', [
                        ['label' => 'Storage & sync', 'is_group' => true, 'cells' => '', 'tooltip' => ''],
                        ['label' => 'Smart sync / online-only files', 'is_group' => false, 'cells' => "yes\npartial\nyes", 'tooltip' => ''],
                        ['label' => 'File recovery & version history', 'is_group' => false, 'cells' => "Up to 1 year\n30 days\n90 days", 'tooltip' => ''],
                        ['label' => 'Large file transfer', 'is_group' => false, 'cells' => "Up to 250 GB\nno\nno", 'tooltip' => ''],
                    ], ['fields' => [
                        ['label', 'text', 'Feature', '', ['max' => 200]],
                        ['is_group', 'bool', 'This is a group heading', false],
                        ['cells', 'textarea', 'Values (one line per column)', '', ['max' => 2000, 'help' => 'Write yes / no / partial for icons, or any text.']],
                        ['tooltip', 'text', 'Tooltip', '', ['max' => 200]],
                    ], 'limit' => 80]],
                    ['sticky_header', 'bool', 'Sticky header row', true],
                    ['mobile', 'select', 'On phones', 'scroll', ['choices' => ['scroll' => 'Scroll sideways', 'cards' => 'Stack as cards']]],
                ],
            ],
            'formBlock' => [
                'label' => 'Form', 'group' => 'Conversion', 'icon' => 'inbox',
                'description' => 'Any form from Form management, with optional supporting text.',
                'fields' => [
                    $eyebrow, $heading('Talk to our team'),
                    ['text', 'textarea', 'Text', 'Tell us about your team and we will get back within one business day.', ['max' => 1000]],
                    ['form_slug', 'form', 'Form', 'contact'],
                    ['layout', 'select', 'Layout', 'split', ['choices' => ['split' => 'Text beside form', 'stacked' => 'Text above form', 'card' => 'Form in a centred card']]],
                    ['bullets', 'list', 'Bullet points', []],
                    ['cta_location', 'text', 'Lead attribution label', 'Form section', ['max' => 120]],
                ],
            ],
            'leadMagnet' => [
                'label' => 'Lead magnet', 'group' => 'Conversion', 'icon' => 'gift',
                'description' => 'Offer a download (guide, checklist, e-book) in exchange for details.',
                'fields' => [
                    ['magnet', 'magnet', 'Lead magnet', ''],
                    ['heading', 'text', 'Heading (blank = magnet title)', '', ['max' => 240]],
                    ['text', 'textarea', 'Text (blank = magnet description)', '', ['max' => 1000]],
                    ['image_side', 'select', 'Image side', 'left', ['choices' => ['left' => 'Left', 'right' => 'Right']]],
                ],
            ],
            'imageWidget' => [
                'label' => 'Image', 'group' => 'Media', 'icon' => 'image',
                'description' => 'A single image with caption, size, alignment and link.',
                'fields' => [
                    ['image', 'image', 'Image', ''],
                    ['alt', 'text', 'Alt text', '', ['max' => 200]],
                    ['caption', 'text', 'Caption', '', ['max' => 300]],
                    ['link', 'url', 'Link (optional)', ''],
                    ['width', 'length', 'Width', '', ['help' => 'e.g. 600px or 80%. Blank = full width.']],
                    ['align', 'select', 'Alignment', 'center', ['choices' => ['left' => 'Left', 'center' => 'Centre', 'right' => 'Right']]],
                    ['rounded', 'bool', 'Rounded corners', true],
                    ['shadow', 'bool', 'Shadow', false],
                ],
            ],
            'gallery' => [
                'label' => 'Image slider', 'group' => 'Media', 'icon' => 'layers',
                'description' => 'A slider or gallery of images with captions.',
                'fields' => [
                    $heading(''),
                    ['mode', 'select', 'Display', 'slider', ['choices' => ['slider' => 'Slider', 'grid' => 'Grid']]],
                    ['autoplay', 'bool', 'Autoplay', true],
                    ['interval', 'number', 'Seconds per slide', 5, ['min' => 2, 'max' => 20]],
                    ['items', 'repeater', 'Images', [], ['fields' => [
                        ['image', 'image', 'Image', ''],
                        ['alt', 'text', 'Alt text', '', ['max' => 200]],
                        ['caption', 'text', 'Caption', '', ['max' => 300]],
                        ['link', 'url', 'Link', ''],
                    ], 'limit' => 20]],
                ],
            ],
            'video' => [
                'label' => 'Video', 'group' => 'Media', 'icon' => 'video',
                'description' => 'A YouTube or Vimeo video, loaded only when played.',
                'fields' => [
                    $eyebrow, $heading(''), $sub,
                    ['url', 'url', 'YouTube or Vimeo URL', ''],
                    ['poster', 'image', 'Poster image (optional)', ''],
                    ['caption', 'text', 'Caption', '', ['max' => 300]],
                ],
            ],
            'latestPosts' => [
                'label' => 'Latest articles', 'group' => 'Blog', 'icon' => 'file-text',
                'description' => 'Recent blog posts, optionally from one category.',
                'fields' => [
                    $eyebrow, $heading('From the blog'), $sub,
                    ['category', 'blogcategory', 'Category (blank = all)', ''],
                    ['count', 'number', 'Number of posts', 3, ['min' => 1, 'max' => 12]],
                    $cols('3'),
                    ['show_excerpt', 'bool', 'Show excerpt', true],
                    ['link_label', 'text', '"View all" label', 'View all articles', ['max' => 60]],
                ],
            ],
            'contactDetails' => [
                'label' => 'Contact details', 'group' => 'Conversion', 'icon' => 'phone',
                'description' => "The market's phone, email, WhatsApp, address and hours.",
                'fields' => [
                    $heading('Get in touch'), $sub,
                    ['show_phone', 'bool', 'Phone', true],
                    ['show_email', 'bool', 'Email', true],
                    ['show_whatsapp', 'bool', 'WhatsApp', true],
                    ['show_address', 'bool', 'Address', true],
                    ['show_hours', 'bool', 'Business hours', true],
                    ['map_url', 'url', 'Map link (optional)', ''],
                ],
            ],
            'spacer' => [
                'label' => 'Spacer / divider', 'group' => 'Layout', 'icon' => 'minus',
                'description' => 'Vertical space, optionally with a line.',
                'fields' => [
                    ['height', 'length', 'Height', '48px'],
                    ['line', 'bool', 'Show a line', false],
                ],
            ],
        ];
    }

    /** @return array<string, list<array{type:string,label:string,description:string,icon:string}>> */
    public static function grouped(): array
    {
        $out = [];
        foreach (self::all() as $type => $def) {
            $out[$def['group']][] = ['type' => $type, 'label' => $def['label'], 'description' => $def['description'], 'icon' => $def['icon']];
        }
        return $out;
    }
}
