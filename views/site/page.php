<?php
use App\Cms\SectionRenderer;

if (!empty($preview)): ?>
    <div class="preview-bar">Preview — <?= e(ucfirst(strtolower($page['status']))) ?><?= $page['status'] !== 'PUBLISHED' ? ' (not visible to visitors)' : '' ?></div>
<?php endif; ?>
<?php if (isset($builder) && $builder !== null): ?>
<?= $builder ?>
<?php elseif (!$sections): ?>
    <section class="cms-section"><div class="container narrow"><h1><?= e($page['title']) ?></h1></div></section>
<?php endif; ?>
<?php if (!isset($builder) || $builder === null): ?><?= SectionRenderer::render($sections, $page) ?><?php endif; ?>
