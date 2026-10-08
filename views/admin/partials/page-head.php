<div class="page-head">
    <div>
        <?php if (!empty($crumbs)): ?>
            <div class="breadcrumbs">
                <?php foreach ($crumbs as $i => [$label, $href]): ?>
                    <?php if ($i > 0): ?> / <?php endif; ?>
                    <?php if ($href): ?><a href="<?= e($href) ?>"><?= e($label) ?></a><?php else: ?><?= e($label) ?><?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <h1><?= e($heading) ?></h1>
        <?php if (!empty($sub)): ?><p><?= e($sub) ?></p><?php endif; ?>
    </div>
    <?php if (!empty($actions)): ?><div class="row"><?= $actions ?></div><?php endif; ?>
</div>
