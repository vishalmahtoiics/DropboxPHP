<?php
use App\Cms\Icons;

$trend = $stats['leads_prev'] > 0 ? round((($stats['leads_month'] - $stats['leads_prev']) / $stats['leads_prev']) * 100) : null;
$max = max(1, max(array_column($series, 'count')));
$srcMax = max(1, ...array_map(static fn ($s) => (int) $s['n'], $sources ?: [['n' => 1]]));
$statuses = ['NEW', 'CONTACTED', 'QUALIFIED', 'PROPOSAL', 'NEGOTIATION', 'WON', 'LOST'];
$counts = array_column($statusCounts, 'n', 'status');
?>
<?= App\Core\View::partial('admin/partials/page-head', [
    'heading' => 'Good ' . (date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening')) . ', ' . explode(' ', $me['name'])[0],
    'sub' => 'Here is what is happening in ' . ($adminCountry['name'] ?? 'your market') . '.',
]) ?>

<div class="grid grid-4">
    <div class="card glass stat">
        <div class="stat-icon"><?= Icons::svg('inbox') ?></div>
        <div>
            <div class="stat-label">Leads this month</div>
            <div class="stat-value"><?= number_format($stats['leads_month']) ?></div>
            <?php if ($trend !== null): ?><div class="stat-trend <?= $trend >= 0 ? 'up' : 'down' ?>"><?= $trend >= 0 ? '▲' : '▼' ?> <?= abs($trend) ?>% vs last month</div><?php endif; ?>
        </div>
    </div>
    <div class="card glass stat">
        <div class="stat-icon orange"><?= Icons::svg('bell') ?></div>
        <div><div class="stat-label">New, not yet contacted</div><div class="stat-value"><?= number_format($stats['new_leads']) ?></div></div>
    </div>
    <div class="card glass stat">
        <div class="stat-icon green"><?= Icons::svg('award') ?></div>
        <div><div class="stat-label">Won this month</div><div class="stat-value"><?= number_format($stats['won']) ?></div></div>
    </div>
    <div class="card glass stat">
        <div class="stat-icon violet"><?= Icons::svg('trending-up') ?></div>
        <div><div class="stat-label">Open pipeline value</div><div class="stat-value"><?= e(($adminCountry['currency_symbol'] ?? '') . number_format($stats['pipeline_value'])) ?></div></div>
    </div>
</div>

<div class="grid grid-3 mt-2">
    <div class="card glass span-2">
        <div class="card-head"><h2>Leads, last 30 days</h2><a class="small" href="/admin/reports">Reports</a></div>
        <div class="card-body">
            <div class="chart" role="img" aria-label="Leads per day for the last 30 days">
                <?php foreach ($series as $point): ?>
                    <div class="bar" style="height:<?= max(1, round($point['count'] / $max * 100)) ?>%" data-label="<?= e(date('d M', strtotime($point['date'])) . ': ' . $point['count']) ?>"></div>
                <?php endforeach; ?>
            </div>
            <div class="row-between small faint mt-1"><span><?= e(date('d M', strtotime($series[0]['date']))) ?></span><span>Today</span></div>
        </div>
    </div>
    <div class="card glass">
        <div class="card-head"><h2>Top sources (90 days)</h2></div>
        <div class="card-body">
            <?php if (!$sources): ?><p class="muted">No leads yet.</p><?php endif; ?>
            <?php foreach ($sources as $s): ?>
                <div class="hbar"><span class="lbl" title="<?= e($s['s']) ?>"><?= e($s['s']) ?></span><span class="track"><span class="fill" style="width:<?= round($s['n'] / $srcMax * 100) ?>%"></span></span><span class="num"><?= (int) $s['n'] ?></span></div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="grid grid-3 mt-2">
    <div class="card glass span-2">
        <div class="card-head"><h2>Recent leads</h2><a class="small" href="/admin/leads">All leads</a></div>
        <?php if (!$recent): ?>
            <div class="empty"><?= Icons::svg('inbox', 'icon', 32) ?><p>No leads yet. They appear here the moment a form is submitted.</p></div>
        <?php else: ?>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Name</th><th class="hide-sm">Product</th><th>Status</th><th class="right">Received</th></tr></thead>
                <tbody>
                <?php foreach ($recent as $l): ?>
                    <tr>
                        <td><a class="row-title" href="/admin/leads/<?= (int) $l['id'] ?>"><?= e($l['name']) ?></a><div class="small muted"><?= e($l['company'] ?: $l['email']) ?></div></td>
                        <td class="hide-sm"><?= e($l['product'] ?? '—') ?></td>
                        <td><span class="badge <?= e($l['status']) ?>"><?= e(ucfirst(strtolower($l['status']))) ?></span></td>
                        <td class="right small muted"><?= e(time_ago($l['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </div>
    <div>
        <div class="card glass">
            <div class="card-head"><h2>Pipeline</h2><a class="small" href="/admin/pipeline">Open board</a></div>
            <div class="card-body">
                <?php $pmax = max(1, ...array_map('intval', array_values($counts) ?: [1])); ?>
                <?php foreach ($statuses as $st): ?>
                    <div class="hbar"><span class="lbl"><?= e(ucfirst(strtolower($st))) ?></span><span class="track"><span class="fill" style="width:<?= round(((int) ($counts[$st] ?? 0)) / $pmax * 100) ?>%"></span></span><span class="num"><?= (int) ($counts[$st] ?? 0) ?></span></div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="card glass">
            <div class="card-head"><h2>Follow-ups</h2></div>
            <div class="card-body">
                <?php if (!$followUps): ?><p class="muted small mb-0">No follow-ups scheduled.</p><?php endif; ?>
                <?php foreach ($followUps as $f): ?>
                    <div class="row-between small mb-2">
                        <a href="/admin/leads/<?= (int) $f['id'] ?>"><?= e($f['name']) ?></a>
                        <span class="<?= strtotime($f['follow_up_at']) < time() ? 'badge red' : 'muted' ?>"><?= e(format_datetime($f['follow_up_at'])) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="card glass mt-2">
    <div class="card-head"><h2>Quick actions</h2></div>
    <div class="card-body">
        <div class="quick-actions">
            <?php foreach ([
                ['pages.create', '/admin/pages/new', 'file-text', 'New page'],
                ['products.create', '/admin/products/new', 'package', 'New product'],
                ['blog.create', '/admin/blog/new', 'pen', 'New article'],
                ['leads.create', '/admin/leads/new', 'user-plus', 'Add a lead'],
                ['forms.create', '/admin/forms/new', 'list', 'New form'],
                ['media.upload', '/admin/media', 'upload', 'Upload media'],
                ['settings.manage', '/admin/settings', 'palette', 'Brand & design'],
                ['seo.manage', '/admin/seo', 'search', 'SEO settings'],
            ] as [$perm, $href, $icon, $label]): ?>
                <?php if (can($perm)): ?><a class="quick-action" href="<?= e($href) ?>"><?= Icons::svg($icon, 'icon', 18) ?> <?= e($label) ?></a><?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="grid grid-4 mt-2">
    <?php foreach ([['Published pages', $stats['pages'], 'file-text', '/admin/pages'], ['Live products', $stats['products'], 'package', '/admin/products'], ['Published articles', $stats['posts'], 'pen', '/admin/blog'], ['Customers', $stats['customers'], 'briefcase', '/admin/customers']] as [$label, $value, $icon, $href]): ?>
        <a class="card glass stat" href="<?= e($href) ?>" style="color:inherit;text-decoration:none">
            <div class="stat-icon"><?= Icons::svg($icon) ?></div>
            <div><div class="stat-label"><?= e($label) ?></div><div class="stat-value"><?= number_format((int) $value) ?></div></div>
        </a>
    <?php endforeach; ?>
</div>
