<?php
use App\Cms\Icons;
use App\Services\Leads;
use App\Services\Money;

$mine = ($_GET['mine'] ?? '') === '1';
?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => 'Pipeline', 'sub' => 'Drag a card to move a lead between stages. Won and lost leads show for 60 days.',
    'actions' => '<a class="btn btn-' . ($mine ? 'primary' : 'secondary') . '" href="?mine=' . ($mine ? '0' : '1') . '">' . ($mine ? 'Showing my leads' : 'Only my leads') . '</a> <a class="btn btn-ghost" href="/admin/leads">List view</a>']) ?>
<div class="kanban" data-kanban>
    <?php foreach ($columns as $status => $cards): $sum = array_sum(array_map(static fn ($c) => (float) $c['value'], $cards)); ?>
        <section class="kanban-col">
            <header class="kanban-col-head"><span><?= e(Leads::STATUSES[$status]) ?> <span class="badge" data-count><?= count($cards) ?></span></span><span class="small muted"><?= $sum ? e(Money::format((string) $sum, $adminCountry['currency_symbol'], $adminCountry['currency'])) : '' ?></span></header>
            <div class="kanban-list" data-status="<?= e($status) ?>">
                <?php foreach ($cards as $c): ?>
                    <article class="kanban-card" draggable="<?= can('leads.edit') ? 'true' : 'false' ?>" data-id="<?= (int) $c['id'] ?>">
                        <a href="/admin/leads/<?= (int) $c['id'] ?>"><?= e($c['name']) ?></a>
                        <?php if ($c['priority'] === 'HIGH'): ?> <span class="badge HIGH">High</span><?php endif; ?>
                        <div class="small muted"><?= e($c['company'] ?: ($c['product_name'] ?? '')) ?></div>
                        <div class="row-between small mt-1"><span><?= $c['value'] ? e(Money::format($c['value'], $adminCountry['currency_symbol'], $adminCountry['currency'])) : '' ?></span><span class="muted"><?= e($c['assignee'] ?? '') ?></span></div>
                        <?php if ($c['follow_up_at']): ?><div class="small <?= strtotime($c['follow_up_at']) < time() ? 'error-text' : 'muted' ?>"><?= Icons::svg('clock', 'icon', 12) ?> <?= e(format_date($c['follow_up_at'])) ?></div><?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>
