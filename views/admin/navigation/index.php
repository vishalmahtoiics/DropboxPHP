<?php
use App\Cms\Icons;
use App\Services\Navigation;

$strip = static function (array $items) use (&$strip): array {
    return array_map(static fn ($i) => [
        'label' => $i['label'], 'link_type' => $i['link_type'], 'url' => $i['url'], 'page_id' => $i['page_id'], 'product_id' => $i['product_id'],
        'blog_post_id' => $i['blog_post_id'], 'blog_category_id' => $i['blog_category_id'], 'description' => $i['description'], 'icon' => $i['icon'],
        'open_in_new_tab' => (bool) $i['open_in_new_tab'], 'is_highlighted' => (bool) $i['is_highlighted'], 'mega_menu' => (bool) $i['mega_menu'],
        'mega_columns' => (int) $i['mega_columns'], 'is_visible' => (bool) $i['is_visible'], 'children' => $strip($i['children']),
    ], $items);
};
$sources = ['pages' => $pages, 'products' => $products, 'posts' => $posts, 'cats' => $blogCategories, 'icons' => App\Cms\Icons::names()];
?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => 'Navigation', 'sub' => 'Menus for ' . $adminCountry['name'] . '. Links to pages and products follow them when their address changes.']) ?>
<nav class="tabs"><?php foreach (Navigation::LOCATIONS as $k => $v): ?><a class="tab<?= $location === $k ? ' active' : '' ?>" href="?location=<?= $k ?>"><?= e($v) ?></a><?php endforeach; ?></nav>
<form method="post" action="/admin/navigation/<?= e($location) ?>" id="nav-form" data-guard>
    <?= csrf_field() ?>
    <input type="hidden" name="tree" id="nav-tree">
    <div class="card"><div class="card-body">
        <p class="help mb-2"><?= $location === 'FOOTER' ? 'Top-level items are column headings; their children are the links.' : 'Top-level items appear in the bar; children open as a dropdown (or a mega menu).' ?> Drag isn't needed: use the arrows.</p>
        <ol class="section-list" id="nav-root"></ol>
        <?php if (!$tree): ?><div class="empty" id="nav-empty"><?= Icons::svg('navigation', 'icon', 32) ?><p>This menu is empty.</p></div><?php endif; ?>
        <button class="btn btn-secondary" type="button" id="nav-add"><?= Icons::svg('plus', 'icon', 16) ?> Add menu item</button>
    </div>
    <div class="card-foot form-actions"><button class="btn btn-primary" type="submit">Save menu</button></div></div>
</form>
<template id="nav-item-tpl">
    <li class="repeater-item" data-nav-item>
        <div class="repeater-item-head">
            <strong data-title>New item</strong>
            <span class="row" style="gap:.2rem">
                <button class="btn btn-ghost btn-xs" type="button" data-act="up" aria-label="Move up"><?= Icons::svg('chevron-up', 'icon', 14) ?></button>
                <button class="btn btn-ghost btn-xs" type="button" data-act="down" aria-label="Move down"><?= Icons::svg('chevron-down', 'icon', 14) ?></button>
                <button class="btn btn-ghost btn-xs" type="button" data-act="child">+ Child</button>
                <button class="btn btn-danger-ghost btn-xs" type="button" data-act="remove" aria-label="Remove"><?= Icons::svg('trash', 'icon', 14) ?></button>
            </span>
        </div>
        <div class="grid grid-4">
            <label class="field"><span class="label">Label</span><input class="input" data-k="label" maxlength="120"></label>
            <label class="field"><span class="label">Links to</span><select class="select" data-k="link_type"><option value="PAGE">Page</option><option value="PRODUCT">Product</option><option value="BLOG_POST">Article</option><option value="BLOG_CATEGORY">Blog category</option><option value="INTERNAL">Path on this site</option><option value="EXTERNAL">External URL</option></select></label>
            <label class="field span-2" data-for="PAGE"><span class="label">Page</span><select class="select" data-k="page_id" data-src="pages"></select></label>
            <label class="field span-2" data-for="PRODUCT"><span class="label">Product</span><select class="select" data-k="product_id" data-src="products"></select></label>
            <label class="field span-2" data-for="BLOG_POST"><span class="label">Article</span><select class="select" data-k="blog_post_id" data-src="posts"></select></label>
            <label class="field span-2" data-for="BLOG_CATEGORY"><span class="label">Category</span><select class="select" data-k="blog_category_id" data-src="cats"></select></label>
            <label class="field span-2" data-for="INTERNAL EXTERNAL"><span class="label">URL</span><input class="input" data-k="url" placeholder="/blog, https://…, #popup-ID"></label>
            <label class="field span-2"><span class="label">Description (dropdowns)</span><input class="input" data-k="description" maxlength="255"></label>
            <label class="field"><span class="label">Icon</span><select class="select" data-k="icon" data-src="icons"></select></label>
            <label class="field"><span class="label">Mega menu columns</span><input class="input" type="number" min="1" max="4" data-k="mega_columns"></label>
        </div>
        <div class="row">
            <label class="switch"><input type="checkbox" data-k="is_visible"><span>Visible</span></label>
            <label class="switch"><input type="checkbox" data-k="open_in_new_tab"><span>New tab</span></label>
            <label class="switch"><input type="checkbox" data-k="is_highlighted"><span>Highlight</span></label>
            <label class="switch"><input type="checkbox" data-k="mega_menu"><span>Mega menu</span></label>
        </div>
        <ol class="section-list" data-children style="margin:1rem 0 0 1.5rem"></ol>
    </li>
</template>
<script nonce="<?= e(nonce()) ?>">
(function () {
  var sources = <?= js($sources) ?>;
  var tree = <?= js($strip($tree)) ?>;
  var root = document.getElementById('nav-root'), tpl = document.getElementById('nav-item-tpl');
  function fillSelect(sel) {
    var src = sources[sel.dataset.src]; var empty = document.createElement('option'); empty.value = ''; empty.textContent = sel.dataset.src === 'icons' ? 'None' : 'Choose…'; sel.appendChild(empty);
    if (Array.isArray(src)) src.forEach(function (v) { var o = document.createElement('option'); o.value = v; o.textContent = v; sel.appendChild(o); });
    else Object.keys(src).forEach(function (k) { var o = document.createElement('option'); o.value = k; o.textContent = src[k]; sel.appendChild(o); });
  }
  function sync(li) {
    var type = li.querySelector('[data-k="link_type"]').value;
    li.querySelectorAll(':scope > .grid [data-for]').forEach(function (el) { el.hidden = el.dataset.for.split(' ').indexOf(type) === -1; });
    li.querySelector('[data-title]').textContent = li.querySelector('[data-k="label"]').value || 'New item';
    var isChild = !!li.parentNode.closest('[data-nav-item]');
    li.querySelector('[data-act="child"]').hidden = isChild;
  }
  function make(item) {
    var li = tpl.content.firstElementChild.cloneNode(true);
    li.querySelectorAll('select[data-src]').forEach(fillSelect);
    item = Object.assign({ link_type: 'PAGE', is_visible: true, mega_columns: 2 }, item || {});
    li.querySelectorAll(':scope > .grid [data-k], :scope > .row [data-k]').forEach(function (el) {
      var v = item[el.dataset.k];
      if (el.type === 'checkbox') el.checked = !!v; else el.value = v == null ? '' : v;
    });
    (item.children || []).forEach(function (c) { li.querySelector('[data-children]').appendChild(make(c)); });
    setTimeout(function () { sync(li); });
    return li;
  }
  function read(list) {
    return Array.from(list.children).map(function (li) {
      var o = {};
      li.querySelectorAll(':scope > .grid [data-k], :scope > .row [data-k]').forEach(function (el) { o[el.dataset.k] = el.type === 'checkbox' ? el.checked : el.value; });
      o.children = read(li.querySelector('[data-children]'));
      return o;
    });
  }
  tree.forEach(function (i) { root.appendChild(make(i)); });
  document.getElementById('nav-add').addEventListener('click', function () { root.appendChild(make({})); var e = document.getElementById('nav-empty'); if (e) e.remove(); });
  root.addEventListener('input', function (e) { var li = e.target.closest('[data-nav-item]'); if (li) sync(li); });
  root.addEventListener('change', function (e) { var li = e.target.closest('[data-nav-item]'); if (li) sync(li); });
  root.addEventListener('click', function (e) {
    var b = e.target.closest('[data-act]'); if (!b) return;
    var li = b.closest('[data-nav-item]');
    if (b.dataset.act === 'remove' && confirm('Remove this item and its children?')) li.remove();
    if (b.dataset.act === 'up' && li.previousElementSibling) li.parentNode.insertBefore(li, li.previousElementSibling);
    if (b.dataset.act === 'down' && li.nextElementSibling) li.parentNode.insertBefore(li.nextElementSibling, li);
    if (b.dataset.act === 'child') li.querySelector('[data-children]').appendChild(make({}));
  });
  document.getElementById('nav-form').addEventListener('submit', function () { document.getElementById('nav-tree').value = JSON.stringify(read(root)); });
})();
</script>
