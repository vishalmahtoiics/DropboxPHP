<?php
/** @var array $s @var bool $editor */
use App\Builder\Code;

$code = (string) $s['code'];
if (trim($code) === '') {
    return;
}
?>
<?php if ($editor): ?>
<div class="bx-html"><?= Code::editor($code) ?></div>
<?php if (Code::hasScript($code)): ?><div class="bx-note bx-note-small">Contains a script — it runs on the live page, not in the editor.</div><?php endif; ?>
<?php else: ?>
<div class="bx-html"><?= Code::live($code) ?></div>
<?php endif; ?>
