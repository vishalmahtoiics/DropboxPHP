<?php
use App\Http\Form;
use App\Services\BrandStyle;

$fonts = array_combine(array_keys(BrandStyle::FONTS), array_keys(BrandStyle::FONTS));
$weights = ['300' => 'Light 300', '400' => 'Regular 400', '500' => 'Medium 500', '600' => 'Semibold 600', '700' => 'Bold 700', '800' => 'Extra bold 800'];
$open = static fn (string $group, string $tab, array $fields) => '<form method="post" action="/admin/settings/' . $group . '" class="card" data-guard>' . csrf_field()
    . '<input type="hidden" name="_tab" value="' . $tab . '"><input type="hidden" name="_fields" value="' . e(implode(',', $fields)) . '"><div class="card-body">';
$close = '</div><div class="card-foot form-actions"><button class="btn btn-primary" type="submit">Save</button></div></form>';
?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => 'Website settings', 'sub' => 'Brand, header, footer, email and maintenance. Changes apply on the next page load — no rebuild.']) ?>
<div data-tabs="settings">
    <div class="tabs" role="tablist">
        <?php foreach (['general' => 'General', 'brand' => 'Brand & design', 'header' => 'Header', 'footer' => 'Footer', 'email' => 'Email', 'maintenance' => 'Maintenance'] as $k => $v): ?><button class="tab" type="button" role="tab" data-tab="<?= $k ?>"><?= $v ?></button><?php endforeach; ?>
    </div>

    <div class="tab-panel" id="general">
        <?= $open('website', 'general', []) ?>
            <div class="grid grid-2">
                <?= Form::input('site_name', 'Site name', $s['site_name'], ['required' => true]) ?>
                <?= Form::input('tagline', 'Tagline', $s['tagline']) ?>
                <?= Form::input('contact_email', 'Contact email', $s['contact_email'], ['type' => 'email']) ?>
                <?= Form::input('contact_phone', 'Contact phone', $s['contact_phone']) ?>
                <?= Form::input('whatsapp_number', 'WhatsApp number', $s['whatsapp_number'], ['help' => 'International format, e.g. +919876543210']) ?>
            </div>
            <?= Form::textarea('address', 'Address', $s['address'], ['rows' => 2]) ?>
            <p class="help">Each market can override these under Countries.</p>
            <hr><h3>Social profiles</h3>
            <div class="grid grid-2">
                <?php foreach (['linkedin_url' => 'LinkedIn', 'twitter_url' => 'X / Twitter', 'facebook_url' => 'Facebook', 'instagram_url' => 'Instagram', 'youtube_url' => 'YouTube'] as $k => $l): ?><?= Form::input($k, $l, $s[$k], ['placeholder' => 'https://']) ?><?php endforeach; ?>
            </div>
        <?= $close ?>
    </div>

    <div class="tab-panel" id="brand" hidden>
        <?= $open('website', 'brand', []) ?>
            <h3>Logos</h3>
            <div class="grid grid-3">
                <?= Form::media('logo_url', 'Logo', $s['logo_url'], ['kind' => 'IMAGE']) ?>
                <?= Form::media('logo_dark_url', 'Logo on dark (footer)', $s['logo_dark_url'], ['kind' => 'IMAGE']) ?>
                <?= Form::media('favicon_url', 'Favicon', $s['favicon_url'], ['kind' => 'IMAGE']) ?>
            </div>
            <hr><h3>Colours</h3>
            <div class="grid grid-4">
                <?php foreach (['color_primary' => 'Primary', 'color_secondary' => 'Secondary (dark)', 'color_accent1' => 'Accent 1', 'color_accent2' => 'Accent 2', 'color_background' => 'Background', 'color_text' => 'Text', 'color_muted' => 'Muted text', 'color_border' => 'Borders'] as $k => $l): ?><?= Form::color($k, $l, $s[$k]) ?><?php endforeach; ?>
            </div>
            <hr><h3>Typography</h3>
            <p class="help">Only the families and weights selected here are downloaded by visitors.</p>
            <div class="grid grid-4">
                <?= Form::select('heading_font', 'Heading font', $fonts, $s['heading_font']) ?>
                <?= Form::select('heading_weight', 'Heading weight', $weights, $s['heading_weight']) ?>
                <?= Form::select('body_font', 'Body font', $fonts, $s['body_font']) ?>
                <?= Form::select('body_weight', 'Body weight', $weights, $s['body_weight']) ?>
                <?= Form::input('base_font_size', 'Base size', $s['base_font_size']) ?>
                <?= Form::input('body_line_height', 'Body line height', $s['body_line_height']) ?>
                <?= Form::input('heading_line_height', 'Heading line height', $s['heading_line_height']) ?>
            </div>
            <hr><h3>Layout</h3>
            <div class="grid grid-4">
                <?php foreach (['container_width' => 'Container width', 'section_spacing' => 'Section spacing', 'section_spacing_mobile' => 'Section spacing (mobile)', 'border_radius' => 'Corner radius', 'card_radius' => 'Card radius', 'button_radius' => 'Button radius'] as $k => $l): ?><?= Form::input($k, $l, $s[$k]) ?><?php endforeach; ?>
            </div>
        <?= $close ?>
    </div>

    <div class="tab-panel" id="header" hidden>
        <?= $open('website', 'header', ['header_sticky', 'header_glass', 'header_show_markets', 'announcement_enabled']) ?>
            <div class="grid grid-2">
                <?= Form::input('header_cta_label', 'Button label', $s['header_cta_label']) ?>
                <?= Form::input('header_cta_url', 'Button link', $s['header_cta_url']) ?>
                <?= Form::input('header_secondary_cta_label', 'Second button label', $s['header_secondary_cta_label']) ?>
                <?= Form::input('header_secondary_cta_url', 'Second button link', $s['header_secondary_cta_url']) ?>
            </div>
            <div class="grid grid-3">
                <?= Form::toggle('header_sticky', 'Sticky header', $s['header_sticky']) ?>
                <?= Form::toggle('header_glass', 'Frosted glass background', $s['header_glass']) ?>
                <?= Form::toggle('header_show_markets', 'Show the country switcher', $s['header_show_markets']) ?>
            </div>
            <hr><h3>Announcement bar</h3>
            <?= Form::toggle('announcement_enabled', 'Show the announcement bar', $s['announcement_enabled']) ?>
            <div class="grid grid-2"><?= Form::input('announcement_text', 'Text', $s['announcement_text']) ?><?= Form::input('announcement_url', 'Link', $s['announcement_url']) ?></div>
            <p class="help">Menus are edited under <a href="/admin/navigation">Navigation</a>.</p>
        <?= $close ?>
    </div>

    <div class="tab-panel" id="footer" hidden>
        <?= $open('footer', 'footer', ['show_social', 'show_contact', 'show_newsletter']) ?>
            <?= Form::textarea('about', 'About text', $footer['about'], ['rows' => 3]) ?>
            <?= Form::input('copyright', 'Copyright line', $footer['copyright'], ['help' => '{year} and {site} are replaced automatically.']) ?>
            <div class="grid grid-3">
                <?= Form::toggle('show_social', 'Social icons', $footer['show_social']) ?>
                <?= Form::toggle('show_contact', 'Contact details', $footer['show_contact']) ?>
                <?= Form::toggle('show_newsletter', 'Newsletter form', $footer['show_newsletter']) ?>
            </div>
            <div class="grid grid-3">
                <?= Form::select('newsletter_form_slug', 'Newsletter form', $forms, $footer['newsletter_form_slug'], ['empty' => 'None']) ?>
                <?= Form::color('background', 'Background', $footer['background']) ?>
                <?= Form::color('text_color', 'Text colour', $footer['text_color']) ?>
            </div>
            <p class="help">Footer columns and legal links are menus under <a href="/admin/navigation?location=FOOTER">Navigation</a>.</p>
        <?= $close ?>
    </div>

    <div class="tab-panel" id="email" hidden>
        <?php if (!$mailConfigured): ?><div class="alert alert-warning"><span>Email is not set up yet, so lead notifications are not being sent.</span></div><?php endif; ?>
        <?= $open('email', 'email', ['is_enabled', 'notify_new_lead', 'notify_assignment', 'notify_submission', 'send_confirmation']) ?>
            <?= Form::toggle('is_enabled', 'Send email using these settings', $email['is_enabled'], ['help' => 'Off = use the SMTP_* environment variables, if any.']) ?>
            <div class="grid grid-4">
                <div class="span-2"><?= Form::input('host', 'SMTP host', $email['host'], ['placeholder' => 'smtp.gmail.com']) ?></div>
                <?= Form::input('port', 'Port', $email['port'], ['type' => 'number']) ?>
                <?= Form::select('encryption', 'Security', ['tls' => 'STARTTLS (587)', 'ssl' => 'SSL/TLS (465)', 'none' => 'None'], $email['encryption']) ?>
                <div class="span-2"><?= Form::input('username', 'Username', $email['username'], ['autocomplete' => 'off']) ?></div>
                <div class="span-2"><?= Form::input('password', 'Password', '', ['type' => 'password', 'autocomplete' => 'new-password', 'placeholder' => $hasPassword ? '•••••••• (saved — leave blank to keep)' : '', 'help' => 'Encrypted before it is stored and never shown again.']) ?></div>
                <div class="span-2"><?= Form::input('from_name', 'From name', $email['from_name']) ?></div>
                <div class="span-2"><?= Form::input('from_email', 'From address', $email['from_email'], ['type' => 'email']) ?></div>
                <div class="span-2"><?= Form::input('reply_to', 'Reply-to', $email['reply_to'], ['type' => 'email']) ?></div>
            </div>
            <?php if ($hasPassword): ?><label class="check"><input type="checkbox" name="clear_password" value="1"> Remove the saved password</label><?php endif; ?>
            <hr><h3>Notifications</h3>
            <?= Form::input('sales_emails', 'Sales team addresses', $email['sales_emails'], ['help' => 'Comma-separated. They receive every new lead.']) ?>
            <div class="grid grid-2">
                <?= Form::toggle('notify_new_lead', 'Email the sales team about new leads', $email['notify_new_lead']) ?>
                <?= Form::toggle('notify_assignment', 'Email staff when a lead is assigned to them', $email['notify_assignment']) ?>
                <?= Form::toggle('notify_submission', 'Email submissions of forms that do not create leads', $email['notify_submission']) ?>
                <?= Form::toggle('send_confirmation', 'Send visitors a confirmation email', $email['send_confirmation']) ?>
            </div>
            <p class="help">Wording is edited in <a href="/admin/email-templates">Email templates</a>. Sending never delays a visitor: it happens after their response.</p>
        <?= $close ?>
        <div class="card"><div class="card-head"><h2>Test</h2></div><div class="card-body row">
            <form method="post" action="/admin/settings/email/test"><?= csrf_field() ?><input type="hidden" name="mode" value="connect"><button class="btn btn-secondary" type="submit">Test connection</button></form>
            <form method="post" action="/admin/settings/email/test" class="row"><?= csrf_field() ?><input class="input" type="email" name="to" value="<?= e($me['email']) ?>" style="width:auto"><button class="btn btn-secondary" type="submit">Send a test email</button></form>
        </div></div>
    </div>

    <div class="tab-panel" id="maintenance" hidden>
        <?= $open('website', 'maintenance', ['maintenance_mode']) ?>
            <?= Form::toggle('maintenance_mode', 'Maintenance mode', $s['maintenance_mode'], ['help' => 'Visitors see a "back soon" page (HTTP 503, so search engines wait). Signed-in staff still see the site.']) ?>
            <?= Form::textarea('maintenance_message', 'Message', $s['maintenance_message'], ['rows' => 2]) ?>
        <?= $close ?>
        <div class="card"><div class="card-head"><h2>Application</h2></div><div class="card-body">
            <dl class="dl small">
                <dt>Version</dt><dd><?= e(config('app.version')) ?></dd>
                <dt>PHP</dt><dd><?= e(PHP_VERSION) ?></dd>
                <dt>Database</dt><dd><?= e((string) App\Core\DB::value('SELECT VERSION()')) ?></dd>
                <dt>Environment</dt><dd><?= e(config('app.env')) ?><?= config('app.debug') ? ' — debug ON (turn off in production)' : '' ?></dd>
                <dt>Site address</dt><dd><?= e(config('app.url')) ?></dd>
                <dt>Storage</dt><dd><?= e(App\Services\Storage::driver()) ?></dd>
            </dl>
        </div></div>
    </div>
</div>
