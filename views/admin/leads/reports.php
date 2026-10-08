<?php
use App\Cms\Icons;
use App\Services\Leads;
use App\Services\Money;

$max = max(1, ...array_column($series, 'n') ?: [1]);
$fmax = max(1, ...array_values($funnel) ?: [1]);
$money = static fn ($v) => Money::format((string) round((float) $v, 2), $adminCountry['currency_symbol'], $adminCountry['currency']);
?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => 'Reports', 'sub' => format_date($from) . ' – ' . format_date($to) . ' · ' . $adminCountry['name']]) ?>
<form class="row mb-2" method="get">
    <?php foreach (['7' => '7 days', '30' => '30 days', '90' => '90 days', '365' => '12 months'] as $k => $v): ?><a class="btn btn-sm <?= $range === (string) $k ? 'btn-primary' : 'btn-secondary' ?>" href="?range=<?= $k ?>"><?= $v ?></a><?php endforeach; ?>
    <input type="hidden" name="range" value="custom"><input class="input input-sm" type="date" name="from" value="<?= e($from) ?>" style="width:auto"><input class="input input-sm" type="date" name="to" value="<?= e($to) ?>" style="width:auto"><button class="btn btn-secondary btn-sm" type="submit">Apply</button>
</form>
<div class="grid grid-4">
    <div class="card glass stat"><div class="stat-icon"><?= Icons::svg('inbox') ?></div><div><div class="stat-label">Leads</div><div class="stat-value"><?= number_format($total) ?></div></div></div>
    <div class="card glass stat"><div class="stat-icon green"><?= Icons::svg('award') ?></div><div><div class="stat-label">Won</div><div class="stat-value"><?= number_format($won) ?></div><div class="stat-trend up"><?= $total ? round($won / $total * 100, 1) : 0 ?>% conversion</div></div></div>
    <div class="card glass stat"><div class="stat-icon violet"><?= Icons::svg('trending-up') ?></div><div><div class="stat-label">Won value</div><div class="stat-value"><?= e($money($revenue)) ?></div></div></div>
    <div class="card glass stat"><div class="stat-icon orange"><?= Icons::svg('shield-alert') ?></div><div><div class="stat-label">Marked spam</div><div class="stat-value"><?= number_format($spam) ?></div></div></div>
</div>
<div class="grid grid-3 mt-2">
    <div class="card span-2"><div class="card-head"><h2>Leads over time</h2></div><div class="card-body">
        <div class="chart" role="img" aria-label="Leads over time"><?php foreach ($series as $pt): ?><div class="bar" style="height:<?= max(1, round($pt['n'] / $max * 100)) ?>%" data-label="<?= e($pt['label'] . ': ' . $pt['n']) ?>"></div><?php endforeach; ?></div>
        <div class="row-between small faint mt-1"><span><?= e($series[0]['label'] ?? '') ?></span><span><?= e(end($series)['label'] ?? '') ?></span></div>
    </div></div>
    <div class="card"><div class="card-head"><h2>Funnel</h2></div><div class="card-body">
        <?php foreach ($funnel as $st => $n): ?><div class="hbar"><span class="lbl"><?= e(Leads::STATUSES[$st]) ?></span><span class="track"><span class="fill" style="width:<?= round($n / $fmax * 100) ?>%"></span></span><span class="num"><?= (int) $n ?></span></div><?php endforeach; ?>
        <p class="small muted mt-1">Leads that reached each stage or beyond. Lost: <?= (int) ($statusCounts['LOST'] ?? 0) ?>.</p>
    </div></div>
</div>
<div class="grid grid-3 mt-2">
    <?php foreach ($tables as $name => $rows): ?>
        <div class="card"><div class="card-head"><h2>By <?= e(strtolower($name)) ?></h2></div>
            <?php if (!$rows): ?><div class="card-body muted small">No data.</div><?php else: ?>
            <div class="table-wrap"><table class="table"><thead><tr><th><?= e($name) ?></th><th class="right">Leads</th><th class="right">Won</th></tr></thead><tbody>
                <?php foreach ($rows as $r): ?><tr><td class="small truncate" style="max-width:180px" title="<?= e($r['label']) ?>"><?= e($r['label']) ?></td><td class="right"><?= (int) $r['n'] ?></td><td class="right small"><?= (int) $r['won'] ?><?php if ((float) $r['revenue'] > 0): ?><div class="muted"><?= e($money($r['revenue'])) ?></div><?php endif; ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
