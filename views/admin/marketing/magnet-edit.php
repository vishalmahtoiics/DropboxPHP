<?php
use App\Http\Form;
use App\Http\Controllers\Admin\LeadMagnetController;

$v = $m ?? [];
?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => $m ? $m['title'] : 'New lead magnet', 'crumbs' => [['Lead magnets', '/admin/lead-magnets'], [$m ? 'Edit' : 'New', null]]]) ?>
<form class="card" method="post" action="<?= $m ? '/admin/lead-magnets/' . (int) $m['id'] : '/admin/lead-magnets' ?>"><div class="card-body stack">
    <?= csrf_field() ?>
    <?= Form::input('title', 'Title', $v['title'] ?? '', ['required' => true, 'data' => ['slug-source' => 'lm-slug']]) ?>
    <?= Form::input('slug', 'Key', $v['slug'] ?? '', ['id' => 'lm-slug']) ?>
    <?= Form::select('kind', 'Kind', LeadMagnetController::KINDS, $v['kind'] ?? 'PDF') ?>
    <?= Form::textarea('description', 'Description', $v['description'] ?? '', ['rows' => 3]) ?>
    <?= Form::media('image', 'Cover image', $imageUrl, ['kind' => 'IMAGE']) ?>
    <?= Form::media('file', 'File to download', $fileUrl, ['kind' => 'DOCUMENT', 'help' => 'Upload a PDF to the media library and choose it here.']) ?>
    <?= Form::input('external_url', 'Or an external link', $v['external_url'] ?? '') ?>
    <?= Form::select('form_id', 'Ask for details with', $forms, (string) ($v['form_id'] ?? ''), ['empty' => 'No form — download directly', 'help' => 'With a form, the file is released only after a successful submission.']) ?>
    <?= Form::input('cta_label', 'Button label', $v['cta_label'] ?? 'Download', ['required' => true]) ?>
    <?= Form::toggle('is_active', 'Active', $v['is_active'] ?? 1) ?>
</div><div class="card-foot form-actions"><a class="btn btn-ghost" href="/admin/lead-magnets">Cancel</a><button class="btn btn-primary" type="submit">Save</button></div></form>
