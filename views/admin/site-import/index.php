<?php
/** @var list<array> $countries @var array $current @var string $limit */
use App\Http\Form;

$options = [];
foreach ($countries as $c) {
    $options[(string) $c['id']] = $c['name'];
}
?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => 'Import a site', 'sub' => 'Bring a whole site in from a site package (.zip): pages built in the visual builder, blog articles, their images, forms, the header and footer, menus and redirects.']) ?>
<div class="grid grid-3">
    <form method="post" action="/admin/site-import" enctype="multipart/form-data" class="card span-2" data-confirm="Import this site package? With &quot;Replace everything&quot; on, the live site changes at once.">
        <?= csrf_field() ?>
        <div class="card-head"><h2>Site package</h2></div>
        <div class="card-body stack">
            <label class="field"><span class="label">Package file (.zip)</span><input class="input" type="file" name="package" accept=".zip,application/zip" required>
                <span class="help">This server accepts uploads up to <?= e($limit) ?>. For a larger package, raise upload_max_filesize and post_max_size in the hosting panel.</span></label>
            <?= Form::select('country_id', 'Market for the pages and menus', $options, (string) $current['id']) ?>
            <?= Form::toggle('replace', 'Replace everything: the package becomes the live site', false, ['help' => 'Pages with the same address, forms with the same key, the header and footer and the menus are replaced, and every page is published at once. The options below are then not used. Older page versions stay in each page\'s builder history.']) ?>
            <?= Form::select('existing', 'When a page already uses the same address', [
                'draft' => 'Put the import in that page\'s builder draft (the live page stays as it is)',
                'skip' => 'Leave that page alone',
            ], 'draft') ?>
            <?= Form::toggle('templates', 'Add the header and footer as builder templates (not made active)', true) ?>
            <?= Form::toggle('menus', 'Replace this market\'s menus with the package\'s menus', false) ?>
            <?= Form::toggle('redirects', 'Add the package\'s redirects (old addresses → new ones)', true) ?>
        </div>
        <div class="card-foot form-actions"><button class="btn btn-primary" type="submit">Import</button></div>
    </form>
    <div class="card">
        <div class="card-head"><h2>What happens</h2></div>
        <div class="card-body stack small">
            <p><strong>New pages are drafts.</strong> Check each one (Pages → Edit with Builder → Preview) and publish it when it is ready. Nothing on the live site changes by itself.</p>
            <p><strong>Replace everything</strong> instead puts the whole package live in one go: same-address pages are overwritten and published, forms are rebuilt, and the package's header, footer and menus become the site's.</p>
            <p><strong>Blog articles</strong> go into the market's blog (Blog → Articles), at the blog address. New ones are drafts; with Replace everything they are published with their original date and same-address articles are overwritten.</p>
            <p><strong>Images</strong> go into the Media library, in a folder named after the package, and the pages use their new addresses.</p>
            <p><strong>Forms</strong> are created when no form has the same key; existing forms are kept.</p>
            <p><strong>Header and footer</strong> arrive as templates. Make them active in Builder templates when you are happy with them.</p>
            <p>Take a backup first if the site already has content.</p>
        </div>
    </div>
</div>
