<?php
use App\Cms\Ui;
use App\Core\View;
use App\Services\Forms;
use App\Services\Popups;

$popups = Popups::forPath($requestPath ?? '/', (int) ($country['id'] ?? 0));
?>
<?php foreach ($popups as $popup): ?>
    <?php $form = $popup['form_id'] ? Forms::byId((int) $popup['form_id']) : null; ?>
    <dialog class="popup popup-<?= e(strtolower($popup['type'])) ?>" data-popup="<?= (int) $popup['id'] ?>" data-trigger="<?= e($popup['trigger']) ?>" data-delay="<?= (int) $popup['delay_seconds'] ?>" data-scroll="<?= (int) $popup['scroll_percent'] ?>" data-device="<?= e($popup['device']) ?>" data-frequency="<?= (int) $popup['frequency_days'] ?>" aria-label="<?= e($popup['heading'] ?: $popup['name']) ?>">
        <button class="dialog-close" type="button" data-close-dialog aria-label="Close">&times;</button>
        <div class="popup-body<?= $popup['image_url'] ? ' has-image' : '' ?>">
            <?php if ($popup['image_url']): ?><div class="popup-image"><?= Ui::img($popup['image_url'], '') ?></div><?php endif; ?>
            <div class="popup-content">
                <?php if ($popup['heading']): ?><h2><?= e($popup['heading']) ?></h2><?php endif; ?>
                <?php if ($popup['body']): ?><p><?= nl2br(e($popup['body'])) ?></p><?php endif; ?>
                <?php if ($form): ?>
                    <?= View::partial('site/partials/form', ['form' => $form, 'ctaLocation' => 'Popup: ' . $popup['name'], 'compact' => true]) ?>
                <?php elseif ($popup['cta_label'] && $popup['cta_url']): ?>
                    <?= Ui::button($popup['cta_label'], $popup['cta_url'], 'primary', 'btn-block') ?>
                <?php endif; ?>
            </div>
        </div>
    </dialog>
<?php endforeach; ?>
