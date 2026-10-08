<?php
use App\Cms\Icons;
use App\Cms\Ui;
use App\Services\Forms;

$layout = $c['layout'];
$form = $layout === 'contentForm' ? Forms::bySlug($c['form_slug']) : null;
$hasImage = $layout === 'contentImage' && $c['image'] !== '';
$twoCol = $form || $hasImage;
$split = (int) $c['split'];
$isBg = $layout === 'backgroundImage' && $c['image'] !== '';
?>
<div class="hero<?= $isBg ? ' hero-bg' : '' ?><?= $twoCol ? ' hero-split' : '' ?><?= $hasImage && $c['image_side'] === 'left' ? ' image-left' : '' ?> align-<?= e($twoCol ? 'left' : $c['align']) ?>"<?= $twoCol ? ' style="--split:' . $split . '%"' : '' ?>>
    <?php if ($isBg): ?><div class="hero-bg-image"><?= Ui::img($c['image'], '', '', false) ?></div><?php endif; ?>
    <div class="container hero-inner">
        <div class="hero-content">
            <?php if ($c['eyebrow']): ?><p class="eyebrow"><?= e($c['eyebrow']) ?></p><?php endif; ?>
            <<?= $h ?> class="hero-title"><?= e($c['heading']) ?></<?= $h ?>>
            <?php if ($c['description']): ?><p class="hero-text"><?= nl2br(e($c['description'])) ?></p><?php endif; ?>
            <?php if ($c['bullets']): ?>
                <ul class="check-list">
                    <?php foreach ($c['bullets'] as $b): ?><li><?= Icons::svg('check-circle', 'icon', 20) ?><span><?= e($b) ?></span></li><?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?= Ui::buttons($c) ?>
            <?php if ($c['badges']): ?>
                <ul class="badges">
                    <?php foreach ($c['badges'] as $badge): ?><?php if ($badge['label']): ?><li><?= Icons::svg($badge['icon'], 'icon', 16) ?><?= e($badge['label']) ?></li><?php endif; ?><?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php if ($hasImage): ?>
            <div class="hero-media"><?= Ui::img($c['image'], $c['image_alt'], 'rounded shadow', !$isFirst) ?></div>
        <?php elseif ($form): ?>
            <div class="hero-form card" id="form">
                <?php if ($c['form_heading']): ?><h2 class="form-heading"><?= e($c['form_heading']) ?></h2><?php endif; ?>
                <?php if ($c['form_description']): ?><p class="muted"><?= e($c['form_description']) ?></p><?php endif; ?>
                <?= App\Core\View::partial('site/partials/form', ['form' => $form, 'ctaLocation' => $c['cta_location'] ?: 'Hero']) ?>
            </div>
        <?php endif; ?>
    </div>
</div>
