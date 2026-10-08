<?php
use App\Cms\Ui;

$cols = $c['columns'];
if (!$cols) {
    return;
}
$n = count($cols);
?>
<div class="container">
    <?= Ui::head($c, $h) ?>
    <div class="table-scroll mobile-<?= e($c['mobile']) ?>" tabindex="0" role="region" aria-label="<?= e($c['heading'] ?: 'Comparison') ?>">
        <table class="compare-table manual<?= $c['sticky_header'] ? ' sticky-head' : '' ?>">
            <thead>
                <tr>
                    <th scope="col"><span class="sr-only">Feature</span></th>
                    <?php foreach ($cols as $col): ?>
                        <th scope="col" class="<?= $col['highlight'] ? 'is-highlight' : '' ?>">
                            <?php if ($col['badge']): ?><span class="col-badge"><?= e($col['badge']) ?></span><?php endif; ?>
                            <span class="col-name"><?= e($col['name']) ?></span>
                            <?php if ($col['subtitle']): ?><small><?= e($col['subtitle']) ?></small><?php endif; ?>
                            <?php if ($col['price']): ?><span class="col-price"><?= e($col['price']) ?></span><?php endif; ?>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($c['rows'] as $row): ?>
                    <?php if ($row['is_group']): ?>
                        <tr class="group-row"><th scope="rowgroup" colspan="<?= $n + 1 ?>"><?= e($row['label']) ?></th></tr>
                        <?php continue; ?>
                    <?php endif; ?>
                    <?php $cells = preg_split('/\R/', (string) $row['cells']) ?: []; ?>
                    <tr>
                        <th scope="row"><?= e($row['label']) ?><?php if ($row['tooltip']): ?> <span class="tip" title="<?= e($row['tooltip']) ?>" tabindex="0" aria-label="<?= e($row['tooltip']) ?>">?</span><?php endif; ?></th>
                        <?php for ($i = 0; $i < $n; $i++): ?>
                            <td class="<?= $cols[$i]['highlight'] ? 'is-highlight' : '' ?>" data-label="<?= e($cols[$i]['name']) ?>"><?= Ui::cell((string) ($cells[$i] ?? '')) ?></td>
                        <?php endfor; ?>
                    </tr>
                <?php endforeach; ?>
                <?php if (array_filter(array_column($cols, 'button_label'))): ?>
                    <tr class="button-row"><th scope="row"><span class="sr-only">Action</span></th>
                        <?php foreach ($cols as $col): ?>
                            <td class="<?= $col['highlight'] ? 'is-highlight' : '' ?>"><?= Ui::button($col['button_label'], $col['button_url'], $col['highlight'] ? 'primary' : 'secondary', 'btn-sm') ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
