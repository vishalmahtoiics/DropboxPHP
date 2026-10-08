<?php if (!empty($tracking['consent_required'])): ?>
<div class="consent-banner" role="dialog" aria-live="polite" aria-label="Cookie consent" data-consent-banner<?= in_array($consent ?? null, ['granted', 'denied'], true) ? ' hidden' : '' ?>>
    <p><?= e($tracking['consent_message']) ?></p>
    <div class="consent-actions">
        <button class="btn btn-ghost" type="button" data-consent="denied">Decline</button>
        <button class="btn btn-primary" type="button" data-consent="granted">Accept</button>
    </div>
</div>
<?php endif; ?>
