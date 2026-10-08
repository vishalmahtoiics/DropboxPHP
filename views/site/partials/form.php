<?php
/**
 * The one public form renderer. Every form on the site — hero, section,
 * product enquiry, popup, footer — is drawn here from its stored fields.
 *
 * @var array $form
 * @var string|null $ctaLocation
 * @var int|null $productId
 * @var string|null $ctaLabel
 * @var bool|null $compact
 */
use App\Services\Forms;

$uid = 'f' . bin2hex(random_bytes(4));
$compact ??= false;
$design = $form['design'] ?? [];
$columns = (int) ($design['columns'] ?? 2) === 1 ? 1 : 2;
?>
<form class="lead-form<?= $compact ? ' is-compact' : '' ?> cols-<?= $columns ?>" method="post" action="/forms/submit" data-lead-form novalidate>
    <input type="hidden" name="form" value="<?= e($form['slug']) ?>">
    <input type="hidden" name="token" value="<?= e(Forms::token((int) $form['id'])) ?>">
    <input type="hidden" name="cta_location" value="<?= e($ctaLocation ?? '') ?>">
    <input type="hidden" name="cta_label" value="<?= e($ctaLabel ?? '') ?>">
    <input type="hidden" name="product_id" value="<?= e((string) ($productId ?? '')) ?>">
    <input type="hidden" name="page_url" value="">
    <div class="hp" aria-hidden="true"><label>Leave this empty <input type="text" name="website_url" tabindex="-1" autocomplete="off"></label></div>
    <div class="form-grid">
        <?php foreach ($form['fields'] as $f): ?>
            <?php
            $id = $uid . '-' . $f['name'];
            $name = 'fields[' . $f['name'] . ']';
            $req = (int) $f['is_required'] === 1;
            $type = $f['type'];
            $width = $columns === 1 ? 'full' : $f['width'];
            $ph = (string) ($f['placeholder'] ?? '');
            $label = e($f['label']) . ($req ? ' <span class="req" aria-hidden="true">*</span>' : '');
            $showLabel = (int) $f['show_label'] === 1;
            $lengthAttrs = ($f['min_length'] ? ' minlength="' . (int) $f['min_length'] . '"' : '') . ($f['max_length'] ? ' maxlength="' . (int) $f['max_length'] . '"' : ' maxlength="' . ($type === 'TEXTAREA' ? 5000 : 255) . '"');
            ?>
            <?php if ($type === 'HIDDEN'): ?>
                <input type="hidden" name="<?= e($name) ?>" value="<?= e($f['default_value']) ?>">
                <?php continue; ?>
            <?php endif; ?>
            <div class="form-field w-<?= e($width) ?>" data-field="<?= e($f['name']) ?>">
                <?php if ($type === 'CHECKBOX' || $type === 'CONSENT'): ?>
                    <label class="checkbox">
                        <input type="checkbox" name="<?= e($name) ?>" value="1" <?= $req ? 'required' : '' ?> <?= $f['default_value'] === '1' ? 'checked' : '' ?>>
                        <span><?= $type === 'CONSENT' ? App\Core\Sanitizer::html($f['label']) : e($f['label']) ?><?= $req ? ' <span class="req">*</span>' : '' ?></span>
                    </label>
                <?php elseif ($type === 'RADIO' || $type === 'MULTISELECT'): ?>
                    <fieldset>
                        <legend class="<?= $showLabel ? '' : 'sr-only' ?>"><?= $label ?></legend>
                        <div class="choices">
                            <?php foreach ($f['options'] as $opt): ?>
                                <label class="checkbox">
                                    <input type="<?= $type === 'RADIO' ? 'radio' : 'checkbox' ?>" name="<?= e($name) . ($type === 'MULTISELECT' ? '[]' : '') ?>" value="<?= e($opt) ?>" <?= $req && $type === 'RADIO' ? 'required' : '' ?>>
                                    <span><?= e($opt) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                <?php else: ?>
                    <label for="<?= e($id) ?>" class="<?= $showLabel ? '' : 'sr-only' ?>"><?= $label ?></label>
                    <?php if ($type === 'TEXTAREA'): ?>
                        <textarea id="<?= e($id) ?>" name="<?= e($name) ?>" rows="4" placeholder="<?= e($ph) ?>" <?= $req ? 'required' : '' ?><?= $lengthAttrs ?>><?= e($f['default_value']) ?></textarea>
                    <?php elseif ($type === 'SELECT'): ?>
                        <select id="<?= e($id) ?>" name="<?= e($name) ?>" <?= $req ? 'required' : '' ?>>
                            <option value=""><?= e($ph ?: 'Select…') ?></option>
                            <?php foreach ($f['options'] as $opt): ?><option value="<?= e($opt) ?>" <?= $opt === $f['default_value'] ? 'selected' : '' ?>><?= e($opt) ?></option><?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <?php
                        [$htmlType, $auto, $mode] = match ($type) {
                            'EMAIL' => ['email', 'email', 'email'],
                            'PHONE' => ['tel', 'tel', 'tel'],
                            'NUMBER' => ['number', 'off', 'decimal'],
                            'URL' => ['url', 'url', 'url'],
                            'DATE' => ['date', 'off', ''],
                            'TIME' => ['time', 'off', ''],
                            'NAME' => ['text', 'name', ''],
                            'COMPANY' => ['text', 'organization', ''],
                            default => ['text', 'off', ''],
                        };
                        ?>
                        <input id="<?= e($id) ?>" type="<?= $htmlType ?>" name="<?= e($name) ?>" value="<?= e($f['default_value']) ?>" placeholder="<?= e($ph) ?>" autocomplete="<?= $auto ?>"<?= $mode ? ' inputmode="' . $mode . '"' : '' ?> <?= $req ? 'required' : '' ?><?= $type === 'NUMBER' ? '' : $lengthAttrs ?><?= $f['pattern'] ? ' pattern="' . e($f['pattern']) . '"' : '' ?>>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ($f['help_text']): ?><small class="field-help"><?= e($f['help_text']) ?></small><?php endif; ?>
                <small class="field-error" data-error-for="<?= e($f['name']) ?>" hidden></small>
            </div>
        <?php endforeach; ?>

        <?php if ($form['consent_text'] && (int) $form['creates_lead'] === 1): ?>
            <div class="form-field w-full">
                <label class="checkbox"><input type="checkbox" name="consent[enquiry]" value="1" required>
                    <span><?= App\Core\Sanitizer::html((string) $form['consent_text']) ?> <span class="req">*</span></span></label>
                <small class="field-error" data-error-for="consent" hidden></small>
            </div>
        <?php endif; ?>
        <?php if ((int) $form['offer_marketing_consent'] === 1): ?>
            <div class="form-field w-full">
                <label class="checkbox"><input type="checkbox" name="consent[marketing]" value="1"><span>Send me product news and offers. You can unsubscribe at any time.</span></label>
            </div>
        <?php endif; ?>
        <?php if ((int) $form['require_terms_acceptance'] === 1): ?>
            <div class="form-field w-full">
                <label class="checkbox"><input type="checkbox" name="consent[terms]" value="1" required><span>I accept the <a href="<?= e(App\Services\Countries::href(null, '/terms')) ?>" target="_blank">terms of service</a> <span class="req">*</span></span></label>
            </div>
        <?php endif; ?>
        <?php if ((int) $form['require_captcha'] === 1): ?>
            <?php $captcha = App\Services\Captcha::challenge((int) $form['id']); ?>
            <div class="form-field w-full">
                <label for="<?= e($uid) ?>-captcha"><?= e($captcha['question']) ?> <span class="req">*</span></label>
                <input id="<?= e($uid) ?>-captcha" type="text" name="captcha_answer" inputmode="numeric" required autocomplete="off" maxlength="4">
                <input type="hidden" name="captcha_token" value="<?= e($captcha['token']) ?>">
                <small class="field-error" data-error-for="captcha" hidden></small>
            </div>
        <?php endif; ?>
    </div>
    <div class="form-actions">
        <button class="btn btn-primary btn-block" type="submit"><?= e($form['submit_label']) ?></button>
    </div>
    <p class="form-status" role="status" aria-live="polite" hidden></p>
</form>
