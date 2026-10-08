<?php /** @var array $s @var array $el */ use App\Core\Sanitizer; $base = 'bxt-' . $el['id']; ?>
<?php if ($s['items']): ?>
<div class="bx-tabs" data-bx-tabs>
    <div class="bx-tablist" role="tablist">
        <?php foreach ($s['items'] as $i => $item): ?>
            <button type="button" role="tab" id="<?= e($base . '-t' . $i) ?>" aria-controls="<?= e($base . '-p' . $i) ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"<?= $i === 0 ? '' : ' tabindex="-1"' ?>><?= e($item['title']) ?></button>
        <?php endforeach; ?>
    </div>
    <?php foreach ($s['items'] as $i => $item): ?>
        <div class="bx-tabpanel prose" role="tabpanel" id="<?= e($base . '-p' . $i) ?>" aria-labelledby="<?= e($base . '-t' . $i) ?>"<?= $i === 0 ? '' : ' hidden' ?>><?= Sanitizer::html($item['content']) ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
