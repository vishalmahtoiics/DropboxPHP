<?php use App\Http\Form; ?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => 'Blog settings', 'crumbs' => [['Articles', '/admin/blog'], ['Settings', null]]]) ?>
<form class="card" method="post" action="/admin/blog/settings">
    <div class="card-body stack">
        <?= csrf_field() ?>
        <?= Form::input('title', 'Blog title', $s['title']) ?>
        <?= Form::textarea('intro', 'Intro', $s['intro'], ['rows' => 2]) ?>
        <?= Form::input('base_path', 'Address prefix', $s['base_path'], ['prefix' => '/', 'help' => 'Changing it redirects the old addresses automatically.']) ?>
        <?= Form::input('posts_per_page', 'Articles per page', $s['posts_per_page'], ['type' => 'number', 'min' => 1, 'max' => 48]) ?>
        <div class="grid grid-2">
            <?= Form::toggle('show_author', 'Show author', $s['show_author']) ?>
            <?= Form::toggle('show_reading_time', 'Show reading time', $s['show_reading_time']) ?>
            <?= Form::toggle('show_toc', 'Table of contents', $s['show_toc']) ?>
            <?= Form::toggle('show_share', 'Share buttons', $s['show_share']) ?>
            <?= Form::toggle('show_related', 'Related articles', $s['show_related']) ?>
            <?= Form::toggle('sidebar', 'Sidebar on articles', $s['sidebar']) ?>
        </div>
        <hr><h3>Sidebar call to action</h3>
        <?= Form::input('cta_heading', 'Heading', $s['cta_heading']) ?>
        <?= Form::textarea('cta_text', 'Text', $s['cta_text'], ['rows' => 2]) ?>
        <div class="grid grid-2"><?= Form::input('cta_label', 'Button label', $s['cta_label']) ?><?= Form::input('cta_url', 'Button link', $s['cta_url']) ?></div>
    </div>
    <div class="card-foot form-actions"><button class="btn btn-primary" type="submit">Save</button></div>
</form>
