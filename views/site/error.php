<section class="cms-section error-page">
    <div class="container narrow center">
        <p class="eyebrow">404</p>
        <h1><?= e($heading) ?></h1>
        <p class="lead"><?= e($message) ?></p>
        <p><a class="btn btn-primary btn-lg" href="<?= e(App\Services\Countries::href(null, '/')) ?>">Go to the homepage</a></p>
    </div>
</section>
