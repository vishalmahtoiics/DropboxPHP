<?php

declare(strict_types=1);

namespace App\Http;

use App\Cms\Icons;

/**
 * Admin form controls. Each one prints its label, the value (from the failed
 * request's old input first), help text and its validation error, escaped.
 */
final class Form
{
    /** @var array<string,string>|null */
    private static ?array $errors = null;

    private static function error(string $name): string
    {
        self::$errors ??= errors();
        $key = str_replace(['[', ']'], ['.', ''], $name);
        return self::$errors[$name] ?? self::$errors[$key] ?? '';
    }

    private static function value(string $name, mixed $value): mixed
    {
        $old = old($name, null);
        return $old !== null ? $old : $value;
    }

    /** @param array<string,mixed> $o */
    private static function wrap(string $name, string $label, string $control, array $o): string
    {
        $err = self::error($name);
        $req = !empty($o['required']) ? ' <span class="req">*</span>' : '';
        return '<label class="field' . ($err ? ' has-error' : '') . (isset($o['class']) ? ' ' . e($o['class']) : '') . '"'
            . (isset($o['showWhen']) ? ' data-show-when="' . e(json_encode($o['showWhen'])) . '"' : '') . '>'
            . ($label !== '' ? '<span class="label">' . e($label) . $req . '</span>' : '')
            . $control
            . (!empty($o['help']) ? '<span class="help">' . e($o['help']) . '</span>' : '')
            . ($err ? '<span class="error-text">' . e($err) . '</span>' : '')
            . '</label>';
    }

    /** @param array<string,mixed> $o */
    private static function attrs(array $o): string
    {
        $out = '';
        foreach (['placeholder', 'maxlength', 'min', 'max', 'step', 'pattern', 'id', 'autocomplete', 'inputmode'] as $a) {
            if (isset($o[$a])) {
                $out .= ' ' . $a . '="' . e($o[$a]) . '"';
            }
        }
        foreach (['required', 'readonly', 'disabled', 'autofocus'] as $a) {
            if (!empty($o[$a])) {
                $out .= ' ' . $a;
            }
        }
        if (isset($o['data'])) {
            foreach ($o['data'] as $k => $v) {
                $out .= ' data-' . e($k) . '="' . e($v) . '"';
            }
        }
        return $out;
    }

    /** @param array<string,mixed> $o */
    public static function input(string $name, string $label, mixed $value = '', array $o = []): string
    {
        $type = $o['type'] ?? 'text';
        $control = '<input class="input" type="' . e($type) . '" name="' . e($name) . '" value="' . e(self::value($name, $value)) . '"' . self::attrs($o) . '>';
        if (isset($o['prefix'])) {
            $control = '<span class="prefix-group"><span>' . e($o['prefix']) . '</span>' . $control . '</span>';
        }
        return self::wrap($name, $label, $control, $o);
    }

    /** @param array<string,mixed> $o */
    public static function textarea(string $name, string $label, mixed $value = '', array $o = []): string
    {
        $rows = (int) ($o['rows'] ?? 4);
        $control = '<textarea class="textarea' . (!empty($o['code']) ? ' code' : '') . '" name="' . e($name) . '" rows="' . $rows . '"' . self::attrs($o) . '>' . e(self::value($name, $value)) . '</textarea>';
        return self::wrap($name, $label, $control, $o);
    }

    /**
     * @param array<string|int,string> $options value => label
     * @param array<string,mixed> $o
     */
    public static function select(string $name, string $label, array $options, mixed $value = '', array $o = []): string
    {
        $value = (string) self::value($name, $value);
        $html = '<select class="select" name="' . e($name) . '"' . self::attrs($o) . '>';
        if (isset($o['empty'])) {
            $html .= '<option value="">' . e($o['empty']) . '</option>';
        }
        foreach ($options as $k => $v) {
            $html .= '<option value="' . e($k) . '"' . ((string) $k === $value ? ' selected' : '') . '>' . e($v) . '</option>';
        }
        return self::wrap($name, $label, $html . '</select>', $o);
    }

    /**
     * The "with selected" bar above a list. Row checkboxes (bulkCheck) join it
     * through the form attribute, so rows can keep their own little forms.
     *
     * @param array<string, string|array<string, string>> $actions value => label, or group label => [value => label]
     */
    public static function bulkBar(string $id, string $url, array $actions, string $noun = 'items'): string
    {
        if ($actions === []) {
            return '';
        }
        $options = '<option value="">Choose an action…</option>';
        foreach ($actions as $value => $label) {
            if (is_array($label)) {
                $options .= '<optgroup label="' . e($value) . '">';
                foreach ($label as $v => $l) {
                    $options .= '<option value="' . e($v) . '">' . e($l) . '</option>';
                }
                $options .= '</optgroup>';
            } else {
                $options .= '<option value="' . e($value) . '">' . e($label) . '</option>';
            }
        }
        return '<form id="' . e($id) . '" class="toolbar bulk-bar" method="post" action="' . e($url) . '" data-bulk-form data-noun="' . e($noun) . '">' . csrf_field()
            . '<span class="small muted" data-bulk-count aria-live="polite">Select ' . e($noun) . ' to act on several at once</span>'
            . '<select class="select input-sm" name="action" aria-label="Action for the selected ' . e($noun) . '">' . $options . '</select>'
            . '<button class="btn btn-secondary btn-sm" type="submit" data-bulk-apply disabled>Apply</button></form>';
    }

    /** The "select all" checkbox for a bulk bar's list. */
    public static function bulkAll(string $form): string
    {
        return '<input type="checkbox" data-bulk-all="' . e($form) . '" aria-label="Select all">';
    }

    /** A row checkbox that belongs to the bulk bar $form. */
    public static function bulkCheck(string $form, int|string $value, string $label): string
    {
        return '<input type="checkbox" name="ids[]" value="' . e((string) $value) . '" form="' . e($form) . '" data-bulk-item aria-label="Select ' . e($label) . '">';
    }

    /** A switch; posts "1" when on, nothing when off. @param array<string,mixed> $o */
    public static function toggle(string $name, string $label, mixed $checked = false, array $o = []): string
    {
        $old = old($name, null);
        $on = $old !== null ? in_array($old, ['1', 'on', 1, true], true) : (bool) $checked;
        return '<div class="field"' . (isset($o['showWhen']) ? ' data-show-when="' . e(json_encode($o['showWhen'])) . '"' : '') . '><label class="switch"><input type="checkbox" name="' . e($name) . '" value="1"' . ($on ? ' checked' : '') . self::attrs($o) . '><span>' . e($label) . '</span></label>'
            . (!empty($o['help']) ? '<span class="help">' . e($o['help']) . '</span>' : '') . '</div>';
    }

    /** @param array<string,mixed> $o */
    public static function color(string $name, string $label, mixed $value = '', array $o = []): string
    {
        $v = (string) self::value($name, $value);
        $hex = preg_match('/^#[0-9a-f]{6}$/i', $v) ? $v : '#000000';
        $control = '<span class="color-field"><input type="color" value="' . e($hex) . '" aria-label="' . e($label) . ' picker"><input class="input" type="text" name="' . e($name) . '" value="' . e($v) . '" placeholder="' . e($o['placeholder'] ?? '#RRGGBB') . '" maxlength="40"></span>';
        return self::wrap($name, $label, $control, $o);
    }

    /** A URL field with a media-library picker. @param array<string,mixed> $o */
    public static function media(string $name, string $label, mixed $value = '', array $o = []): string
    {
        $v = (string) self::value($name, $value);
        $isImage = $v !== '' && preg_match('/\.(png|jpe?g|gif|webp|avif|svg)(\?|$)/i', $v);
        $control = '<span class="media-input"' . (isset($o['altTarget']) ? ' data-alt-target="' . e($o['altTarget']) . '"' : '') . '>'
            . '<img class="thumb" src="' . e($isImage ? $v : '') . '" alt=""' . ($isImage ? '' : ' hidden') . '>'
            . '<input class="input" type="text" name="' . e($name) . '" value="' . e($v) . '" placeholder="/media/… or https://…"' . (isset($o['fieldName']) ? ' data-field-name="' . e($o['fieldName']) . '"' : '') . '>'
            . '<button class="btn btn-secondary btn-sm" type="button" data-media-pick="' . e($o['kind'] ?? '') . '">' . Icons::svg('image', 'icon', 16) . ' Choose</button>'
            . '<button class="btn btn-ghost btn-sm" type="button" data-media-clear aria-label="Clear">' . Icons::svg('x', 'icon', 14) . '</button></span>';
        return self::wrap($name, $label, $control, $o);
    }

    /** Rich text editor posting HTML (sanitised on the server). @param array<string,mixed> $o */
    public static function richtext(string $name, string $label, mixed $value = '', array $o = []): string
    {
        $v = (string) self::value($name, $value);
        $btn = static fn (string $cmd, string $title, string $text, string $val = '') => '<button type="button" data-cmd="' . $cmd . '"' . ($val ? ' data-value="' . $val . '"' : '') . ' title="' . $title . '">' . $text . '</button>';
        $toolbar = '<div class="rte-toolbar">'
            . '<select data-block aria-label="Text style"><option value="">Style</option><option value="P">Paragraph</option><option value="H2">Heading 2</option><option value="H3">Heading 3</option><option value="H4">Heading 4</option><option value="BLOCKQUOTE">Quote</option></select>'
            . $btn('bold', 'Bold', '<b>B</b>') . $btn('italic', 'Italic', '<i>I</i>') . $btn('underline', 'Underline', '<u>U</u>')
            . $btn('insertUnorderedList', 'Bullet list', '•') . $btn('insertOrderedList', 'Numbered list', '1.')
            . $btn('createLink', 'Link', Icons::svg('link', 'icon', 14)) . $btn('unlink', 'Remove link', '⌫')
            . $btn('insertImage', 'Image', Icons::svg('image', 'icon', 14))
            . $btn('justifyLeft', 'Align left', '⇤') . $btn('justifyCenter', 'Centre', '↔')
            . $btn('removeFormat', 'Clear formatting', 'Tx') . $btn('source', 'HTML source', '&lt;/&gt;')
            . '</div>';
        $err = self::error($name);
        return '<div class="field' . ($err ? ' has-error' : '') . '"' . (isset($o['showWhen']) ? ' data-show-when="' . e(json_encode($o['showWhen'])) . '"' : '') . '>'
            . ($label !== '' ? '<span class="label">' . e($label) . '</span>' : '')
            . '<div data-rte>' . $toolbar . '<div class="rte" contenteditable="true" role="textbox" aria-multiline="true" aria-label="' . e($label) . '"' . (isset($o['minHeight']) ? ' style="min-height:' . e($o['minHeight']) . '"' : '') . '></div>'
            . '<textarea name="' . e($name) . '" hidden>' . e($v) . '</textarea></div>'
            . (!empty($o['help']) ? '<span class="help">' . e($o['help']) . '</span>' : '')
            . ($err ? '<span class="error-text">' . e($err) . '</span>' : '') . '</div>';
    }

    public static function iconPicker(string $name, string $label, string $value = ''): string
    {
        $html = '<div class="field" data-icon-field><span class="label">' . e($label) . '</span><input type="hidden" name="' . e($name) . '" value="' . e($value) . '"><div class="icon-picker">';
        $html .= '<button type="button" data-icon-choice=""' . ($value === '' ? ' class="selected"' : '') . ' title="None">—</button>';
        foreach (Icons::names() as $icon) {
            $html .= '<button type="button" data-icon-choice="' . e($icon) . '" title="' . e($icon) . '"' . ($icon === $value ? ' class="selected"' : '') . '>' . Icons::svg($icon, 'icon', 18) . '</button>';
        }
        return $html . '</div></div>';
    }

    /** Status badge. */
    public static function badge(string $status, ?string $label = null): string
    {
        return '<span class="badge ' . e($status) . '">' . e($label ?? ucfirst(strtolower(str_replace('_', ' ', $status)))) . '</span>';
    }

    /** A POST button in its own small form (delete, duplicate …). */
    public static function action(string $url, string $label, string $class = 'btn btn-ghost btn-sm', string $confirm = '', string $icon = ''): string
    {
        return '<form class="inline-form" method="post" action="' . e($url) . '"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . csrf_field()
            . '<button class="' . e($class) . '" type="submit">' . ($icon ? Icons::svg($icon, 'icon', 16) . ' ' : '') . e($label) . '</button></form>';
    }

    /** Pagination footer. */
    public static function pagination(int $page, int $pages, int $total, string $label = 'items'): string
    {
        if ($total === 0) {
            return '';
        }
        $q = $_GET;
        $link = static function (int $p) use ($q): string {
            $q['page'] = $p;
            return '?' . http_build_query($q);
        };
        $html = '<div class="pagination"><span class="muted small">' . number_format($total) . ' ' . e($label) . '</span><div class="pages">';
        if ($page > 1) {
            $html .= '<a class="btn btn-secondary btn-sm" href="' . e($link($page - 1)) . '">Previous</a>';
        }
        $html .= '<span class="btn btn-ghost btn-sm">Page ' . $page . ' of ' . max(1, $pages) . '</span>';
        if ($page < $pages) {
            $html .= '<a class="btn btn-secondary btn-sm" href="' . e($link($page + 1)) . '">Next</a>';
        }
        return $html . '</div></div>';
    }

    /** Sortable column header link. */
    public static function sortTh(string $key, string $label, string $current, string $dir): string
    {
        $q = $_GET;
        $q['sort'] = $key;
        $q['dir'] = $current === $key && $dir === 'asc' ? 'desc' : 'asc';
        unset($q['page']);
        $arrow = $current === $key ? ($dir === 'asc' ? ' ↑' : ' ↓') : '';
        return '<th><a href="?' . e(http_build_query($q)) . '">' . e($label) . $arrow . '</a></th>';
    }
}
