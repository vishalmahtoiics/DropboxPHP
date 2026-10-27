<?php

declare(strict_types=1);

use App\Core\Request;
use App\Services\Settings;

test('Tag Manager is printed live at the top of <head>, with its noscript after <body>, under Consent Mode', function (): void {
    Settings::save('tracking', ['gtm_enabled' => true, 'gtm_id' => 'GTM-ABC1234', 'ga4_enabled' => true, 'ga4_id' => 'G-TEST12345', 'consent_required' => true], true);
    Settings::flush();
    $html = dispatch(fake_request('GET', '/'))->body;
    $head = substr($html, 0, (int) strpos($html, '</head>'));

    assert_contains("'script','dataLayer','GTM-ABC1234'", $head, 'the standard container snippet');
    assert_true(strpos($head, 'gtm.js?id=') < strpos($head, '<title>'), 'as high in <head> as possible');
    $gtmScript = substr($head, (int) strrpos(substr($head, 0, (int) strpos($head, 'gtm.js?id=')), '<script'), 40);
    assert_not_contains('text/plain', $gtmScript, 'not held back until consent: Consent Mode handles that');
    assert_contains("gtag('consent','default',{ad_storage:'denied'", $head, 'storage denied until the visitor accepts');
    assert_true(strpos($head, "gtag('consent','default'") < strpos($head, 'gtm.js?id='), 'defaults are set before the container loads');
    assert_contains('type="text/plain" data-consent="analytics"', $head, 'other tags still wait for consent');

    $body = substr($html, (int) strpos($html, '<body'));
    assert_true(strpos($body, 'ns.html?id=GTM-ABC1234') < 300, 'the noscript iframe right after <body>');

    // Once the visitor has accepted, the defaults are granted.
    $req = fake_request('GET', '/');
    $granted = dispatch(new Request('GET', '/', [], [], [], $req->server, ['consent' => 'granted']))->body;
    assert_contains("gtag('consent','default',{ad_storage:'granted'", $granted);

    Settings::save('tracking', ['gtm_enabled' => false, 'ga4_enabled' => false], true);
    Settings::flush();
    $off = dispatch(fake_request('GET', '/'))->body;
    assert_not_contains('googletagmanager.com/gtm.js', $off);
    assert_not_contains('ns.html?id=', $off);
});
