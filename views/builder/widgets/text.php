<?php /** @var array $s */ use App\Core\Sanitizer; ?>
<?php if (trim(strip_tags((string) $s['content'], '<img>')) !== ''): ?><div class="bx-text prose"><?= Sanitizer::html($s['content']) ?></div><?php endif; ?>
