<?php
$html = App\Core\Sanitizer::html($c['body']);
if ($h === 'h1' && !preg_match('/<h1[\s>]/i', $html)) {
    // Keep the document outline sane when a page opens with rich text.
    $html = preg_replace('/<h2(\s|>)/i', '<h1$1', $html, 1) ?? $html;
    $html = preg_replace('#</h2>#i', '</h1>', $html, 1) ?? $html;
}
?>
<div class="container <?= $c['max_width'] === 'narrow' ? 'narrow' : ($c['max_width'] === 'normal' ? 'medium' : '') ?>">
    <div class="prose"><?= $html ?></div>
</div>
