<?php
use App\Core\Session;

$messages = [];
foreach (['success' => 'check-circle', 'error' => 'x-circle', 'warning' => 'alert', 'info' => 'info'] as $type => $icon) {
    $msg = Session::getFlash($type);
    if (is_string($msg) && $msg !== '') {
        $messages[] = [$type, $icon, $msg];
    }
}
?>
<?php foreach ($messages as [$type, $icon, $msg]): ?>
    <div class="alert alert-<?= e($type) ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>">
        <?= App\Cms\Icons::svg($icon, 'icon', 18) ?>
        <span><?= e($msg) ?></span>
    </div>
<?php endforeach; ?>
