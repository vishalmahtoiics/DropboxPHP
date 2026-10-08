<?= App\Core\View::partial('admin/partials/page-head', ['heading' => 'Search', 'sub' => $q !== '' ? 'Results for "' . $q . '"' : 'Type at least two characters in the search box.']) ?>
<?php if ($q !== '' && !$results): ?>
    <div class="card"><div class="empty"><?= App\Cms\Icons::svg('search', 'icon', 32) ?><p>Nothing matched.</p></div></div>
<?php endif; ?>
<?php foreach ($results as $group => $items): ?>
    <div class="card">
        <div class="card-head"><h2><?= e($group) ?></h2></div>
        <div class="table-wrap"><table class="table"><tbody>
            <?php foreach ($items as $item): ?>
                <tr><td><a class="row-title" href="<?= e($item['href']) ?>"><?= e($item['title']) ?></a><div class="small muted"><?= e($item['sub']) ?></div></td></tr>
            <?php endforeach; ?>
        </tbody></table></div>
    </div>
<?php endforeach; ?>
