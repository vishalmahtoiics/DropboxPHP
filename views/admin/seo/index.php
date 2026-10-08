<?php
use App\Http\Form;

$tone = static fn (int $s) => $s >= 80 ? 'good' : ($s >= 55 ? 'ok' : 'bad');
$avg = $scores ? (int) round(array_sum(array_map(static fn ($x) => $x['r']['overall'], $scores)) / count($scores)) : 0;
?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => 'SEO', 'sub' => 'Defaults, robots and sitemap, and an SEO / AEO / GEO score for every page and article.', 'actions' => '<a class="btn btn-secondary" href="/admin/redirects">Redirects</a> <a class="btn btn-ghost" href="/sitemap.xml" target="_blank">sitemap.xml</a> <a class="btn btn-ghost" href="/robots.txt" target="_blank">robots.txt</a>']) ?>
<?php if ($addressWarning): ?><div class="alert alert-warning"><span>You are browsing <b><?= e($addressWarning[1]) ?></b>, but the site address (APP_URL) is <b><?= e($addressWarning[0]) ?></b>. Canonical tags and the sitemap use APP_URL — set it to the address visitors end up on.</span></div><?php endif; ?>
<div data-tabs="seo">
    <div class="tabs" role="tablist"><button class="tab" type="button" role="tab" data-tab="intelligence">SEO intelligence</button><button class="tab" type="button" role="tab" data-tab="settings">Settings</button><button class="tab" type="button" role="tab" data-tab="health">URL health</button></div>
    <div class="tab-panel" id="intelligence">
        <div class="grid grid-4 mb-2">
            <div class="card glass stat"><span class="score <?= $tone($avg) ?>"><?= $avg ?></span><div><div class="stat-label">Average score</div><div class="small muted"><?= count($scores) ?> URLs in <?= e($adminCountry['name']) ?></div></div></div>
            <?php foreach (['good' => 'Healthy (80+)', 'ok' => 'Needs work', 'bad' => 'Poor (<55)'] as $t => $label): ?>
                <div class="card glass stat"><div><div class="stat-label"><?= e($label) ?></div><div class="stat-value"><?= count(array_filter($scores, static fn ($x) => $tone($x['r']['overall']) === $t)) ?></div></div></div>
            <?php endforeach; ?>
        </div>
        <div class="card"><div class="table-wrap"><table class="table">
            <thead><tr><th>URL</th><th>Overall</th><th>SEO</th><th>AEO</th><th>GEO</th><th>Top issue</th><th></th></tr></thead>
            <tbody><?php foreach ($scores as $x): $issue = array_values(array_filter($x['r']['checks'], static fn ($c) => $c['status'] === 'fail'))[0] ?? array_values(array_filter($x['r']['checks'], static fn ($c) => $c['status'] === 'warn'))[0] ?? null; ?>
                <tr><td><strong><?= e($x['title']) ?></strong> <span class="badge"><?= e($x['type']) ?></span><?php if ($x['status'] !== 'PUBLISHED'): ?> <?= Form::badge($x['status']) ?><?php endif; ?><div class="small muted"><?= e($x['path']) ?></div></td>
                    <td><span class="score <?= $tone($x['r']['overall']) ?>"><?= (int) $x['r']['overall'] ?></span></td>
                    <td><?= (int) $x['r']['seo'] ?></td><td><?= (int) $x['r']['aeo'] ?></td><td><?= (int) $x['r']['geo'] ?></td>
                    <td class="small"><?= $issue ? e($issue['label']) . '<div class="muted">' . e($issue['fix']) . '</div>' : '—' ?></td>
                    <td class="actions"><a class="btn btn-ghost btn-sm" href="<?= e($x['edit']) ?>">Improve</a></td></tr>
            <?php endforeach; ?></tbody>
        </table></div></div>
    </div>
    <div class="tab-panel" id="settings" hidden>
        <form class="card" method="post" action="/admin/seo"><div class="card-body stack">
            <?= csrf_field() ?>
            <div class="grid grid-2">
                <?= Form::input('default_title', 'Default title (homepage)', $s['default_title']) ?>
                <?= Form::input('title_template', 'Title template', $s['title_template'], ['help' => '%s is replaced by the page title.']) ?>
            </div>
            <?= Form::textarea('default_description', 'Default meta description', $s['default_description'], ['rows' => 2, 'data' => ['count' => 160]]) ?>
            <div class="grid grid-2">
                <?= Form::media('default_og_image', 'Default share image', $s['default_og_image'], ['kind' => 'IMAGE']) ?>
                <?= Form::input('twitter_handle', 'X / Twitter handle', $s['twitter_handle'], ['placeholder' => '@company']) ?>
                <?= Form::input('organization_name', 'Organisation name (schema)', $s['organization_name']) ?>
                <?= Form::select('organization_type', 'Organisation type', ['Organization' => 'Organization', 'Corporation' => 'Corporation', 'LocalBusiness' => 'Local business', 'ProfessionalService' => 'Professional service'], $s['organization_type']) ?>
                <?= Form::media('organization_logo', 'Organisation logo (schema)', $s['organization_logo'], ['kind' => 'IMAGE']) ?>
                <div></div>
                <?= Form::input('google_verification', 'Google Search Console verification code', $s['google_verification']) ?>
                <?= Form::input('bing_verification', 'Bing Webmaster verification code', $s['bing_verification']) ?>
            </div>
            <?= Form::textarea('robots_extra', 'Extra robots.txt rules', $s['robots_extra'], ['rows' => 3, 'code' => true, 'help' => 'Only User-agent, Allow, Disallow, Crawl-delay and Sitemap lines are kept.']) ?>
            <?= Form::toggle('sitemap_enabled', 'Publish sitemap.xml', $s['sitemap_enabled']) ?>
            <?= Form::toggle('noindex_site', 'Hide the whole site from search engines (staging)', $s['noindex_site']) ?>
        </div><div class="card-foot form-actions"><button class="btn btn-primary" type="submit">Save</button></div></form>
    </div>
    <div class="tab-panel" id="health" hidden>
        <div class="card"><div class="card-head"><h2>Addresses that returned "not found"</h2><a class="small" href="/admin/redirects">Manage redirects</a></div>
            <?php if (!$notFound): ?><div class="card-body muted">No open 404s. 🎉</div><?php else: ?>
            <div class="table-wrap"><table class="table"><thead><tr><th>Path</th><th>Hits</th><th>Last seen</th><th>Last referrer</th><th></th></tr></thead><tbody>
                <?php foreach ($notFound as $n): ?><tr><td><code><?= e($n['path']) ?></code></td><td><?= (int) $n['hits'] ?></td><td class="small"><?= e(time_ago($n['last_seen_at'])) ?></td><td class="small truncate" style="max-width:240px"><?= e($n['last_referrer'] ?? '') ?></td><td class="actions"><a class="btn btn-secondary btn-sm" href="/admin/redirects?from=<?= e(rawurlencode($n['path'])) ?>">Redirect</a></td></tr><?php endforeach; ?>
            </tbody></table></div><?php endif; ?>
        </div>
    </div>
</div>
