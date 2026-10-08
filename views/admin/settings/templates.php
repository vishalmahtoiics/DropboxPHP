<?php use App\Http\Form; ?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => 'Email templates', 'sub' => 'Use {{tokens}} for lead and site details. Values are escaped automatically.']) ?>
<?php foreach ($rows as $t): $tokens = $defaults[$t['key']][3] ?? []; ?>
    <form class="card" id="t<?= (int) $t['id'] ?>" method="post" action="/admin/email-templates/<?= (int) $t['id'] ?>">
        <div class="card-head"><h2><?= e($t['name']) ?></h2><?= Form::badge($t['is_active'] ? 'ACTIVE' : 'INACTIVE', $t['is_active'] ? 'Active' : 'Off') ?></div>
        <div class="card-body">
            <?= csrf_field() ?>
            <?= Form::input('subject', 'Subject', $t['subject'], ['required' => true, 'maxlength' => 250]) ?>
            <?= Form::richtext('body', 'Body', $t['body']) ?>
            <p class="help">Tokens: <?php foreach ($tokens as $tok): ?><code>{{<?= e($tok) ?>}}</code> <?php endforeach; ?></p>
            <?= Form::toggle('is_active', 'Send this email', $t['is_active']) ?>
        </div>
        <div class="card-foot form-actions"><button class="btn btn-ghost" type="submit" name="reset" value="1" formnovalidate>Reset to default</button><button class="btn btn-primary" type="submit">Save</button></div>
    </form>
<?php endforeach; ?>
