<?php /** @var array $s */ use App\Cms\Icons; $icons = ['info' => 'info', 'success' => 'check-circle', 'warning' => 'alert', 'danger' => 'shield-alert']; ?>
<div class="bx-alert bx-alert-<?= e($s['kind']) ?>" role="note">
    <?= Icons::svg($icons[$s['kind']] ?? 'info', 'icon', 20) ?>
    <div><?php if ($s['title'] !== ''): ?><strong><?= e($s['title']) ?></strong><?php endif; ?><?php if ($s['text'] !== ''): ?><p><?= nl2br(e($s['text'])) ?></p><?php endif; ?></div>
</div>
