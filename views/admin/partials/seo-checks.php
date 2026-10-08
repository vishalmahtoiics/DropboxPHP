<?php
use App\Cms\Icons;

$tone = static fn (int $s) => $s >= 80 ? 'good' : ($s >= 55 ? 'ok' : 'bad');
?>
<hr>
<h3>SEO intelligence</h3>
<div class="row mb-2">
    <?php foreach (['overall' => 'Overall', 'seo' => 'SEO', 'aeo' => 'AEO', 'geo' => 'GEO'] as $k => $label): ?>
        <div class="row" style="gap:.5rem"><span class="score <?= $tone($report[$k]) ?>"><?= (int) $report[$k] ?></span><span class="small muted"><?= e($label) ?></span></div>
    <?php endforeach; ?>
</div>
<p class="small muted">Scores reflect the last saved version. AEO = answer engines (featured snippets, voice); GEO = generative engines (AI overviews, chat assistants).</p>
<ul class="timeline" style="margin-top:1rem">
    <?php foreach ($report['checks'] as $c): ?>
        <li style="border-left-color:transparent;padding-left:0;margin-left:0">
            <div class="row" style="gap:.5rem;align-items:flex-start">
                <span style="color:var(--<?= $c['status'] === 'pass' ? 'success' : ($c['status'] === 'warn' ? 'warning' : 'danger') ?>)"><?= Icons::svg($c['status'] === 'pass' ? 'check-circle' : ($c['status'] === 'warn' ? 'alert' : 'x-circle'), 'icon', 16) ?></span>
                <span><span class="badge"><?= e($c['group']) ?></span> <?= e($c['label']) ?><?php if ($c['status'] !== 'pass' && $c['fix']): ?><br><span class="small muted"><?= e($c['fix']) ?></span><?php endif; ?></span>
            </div>
        </li>
    <?php endforeach; ?>
</ul>
