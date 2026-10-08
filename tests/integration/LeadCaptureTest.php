<?php

declare(strict_types=1);

use App\Core\DB;
use App\Services\Forms;
use App\Services\LeadCapture;

$form = null;
$getForm = static function () use (&$form): array {
    return $form ??= make_form('quote-test', [
        ['type' => 'NAME', 'label' => 'Name', 'name' => 'name', 'is_required' => 1],
        ['type' => 'EMAIL', 'label' => 'Email', 'name' => 'email', 'is_required' => 1],
        ['type' => 'SELECT', 'label' => 'Users', 'name' => 'users', 'is_required' => 1, 'options' => json_encode(['1-10', '11-50'])],
        ['type' => 'HIDDEN', 'label' => 'Source', 'name' => 'src', 'default_value' => 'server-value'],
    ], ['consent_text' => 'I agree', 'offer_marketing_consent' => 1]);
};

test('a valid submission creates a lead, submission and consent record with attribution', function () use ($getForm): void {
    $form = $getForm();
    $first = rtrim(strtr(base64_encode(json_encode(['utm_source' => 'google', 'utm_medium' => 'cpc', 'landing' => '/pricing'])), '+/', '-_'), '=');
    $req = fake_request('POST', '/forms/submit', [], ['attr_first' => $first]);
    $res = LeadCapture::submit($req, [
        'form' => 'quote-test', 'token' => Forms::token((int) $form['id'], time() - 5),
        'fields' => ['name' => 'Asha', 'email' => 'ASHA@Example.com', 'users' => '1-10', 'src' => 'tampered'],
        'consent' => ['enquiry' => '1', 'marketing' => '1'], 'page_url' => 'http://localhost/ae/pricing?utm_source=newsletter&utm_campaign=oct',
        'cta_location' => 'Hero',
    ]);
    assert_true($res['ok'], json_encode($res));
    $lead = DB::first('SELECT * FROM leads WHERE id = ?', [$res['leadId']]);
    assert_same('asha@example.com', $lead['email']);
    assert_same('newsletter', $lead['utm_source'], 'Last touch from the page URL');
    assert_same('oct', $lead['utm_campaign']);
    assert_same('google', $lead['first_utm_source'], 'First touch from the cookie');
    assert_same('Hero', $lead['cta_location']);
    assert_same((int) DB::value("SELECT id FROM countries WHERE code = 'AE'"), (int) $lead['country_id'], 'Market from the page path');
    assert_same(1, (int) $lead['marketing_consent']);
    $sub = DB::first('SELECT * FROM form_submissions WHERE lead_id = ?', [$lead['id']]);
    assert_same('server-value', json_decode($sub['data'], true)['src'], 'Hidden fields never come from the client');
    assert_same(1, (int) DB::value('SELECT COUNT(*) FROM consent_records WHERE lead_id = ?', [$lead['id']]));
    assert_true(strlen((string) $lead['ip_hash']) === 32 && !str_contains((string) $lead['ip_hash'], '.'), 'Only a hash of the IP is stored');
});

test('server-side validation cannot be bypassed', function () use ($getForm): void {
    $form = $getForm();
    $res = LeadCapture::submit(fake_request('POST', '/forms/submit'), [
        'form' => 'quote-test', 'token' => Forms::token((int) $form['id'], time() - 5),
        'fields' => ['name' => '', 'email' => 'not-an-email', 'users' => '9999'], 'consent' => ['enquiry' => '1'],
    ]);
    assert_false($res['ok']);
    assert_true(isset($res['fieldErrors']['name'], $res['fieldErrors']['email'], $res['fieldErrors']['users']));
});

test('missing consent, bad tokens and instant submits are refused', function () use ($getForm): void {
    $form = $getForm();
    $base = ['form' => 'quote-test', 'fields' => ['name' => 'A', 'email' => 'a@b.co', 'users' => '1-10']];
    assert_false(LeadCapture::submit(fake_request('POST', '/forms/submit'), $base + ['token' => Forms::token((int) $form['id'], time() - 5)])['ok'], 'consent');
    assert_false(LeadCapture::submit(fake_request('POST', '/forms/submit'), $base + ['token' => 'forged', 'consent' => ['enquiry' => 1]])['ok'], 'token');
    assert_false(LeadCapture::submit(fake_request('POST', '/forms/submit'), $base + ['token' => Forms::token((int) $form['id']), 'consent' => ['enquiry' => 1]])['ok'], 'too fast');
});

test('the honeypot reports success but stores nothing', function () use ($getForm): void {
    $form = $getForm();
    $before = (int) DB::value('SELECT COUNT(*) FROM leads');
    $res = LeadCapture::submit(fake_request('POST', '/forms/submit'), ['form' => 'quote-test', 'website_url' => 'http://spam', 'token' => Forms::token((int) $form['id'], time() - 5), 'fields' => []]);
    assert_true($res['ok']);
    assert_same($before, (int) DB::value('SELECT COUNT(*) FROM leads'));
});

test('one address is rate limited after five submissions', function () use ($getForm): void {
    $form = $getForm();
    $req = new App\Core\Request('POST', '/forms/submit', [], [], [], ['REMOTE_ADDR' => '198.51.100.9'], []);
    $results = [];
    for ($i = 0; $i < 7; $i++) {
        $results[] = LeadCapture::submit($req, ['form' => 'quote-test', 'token' => Forms::token((int) $form['id'], time() - 5),
            'fields' => ['name' => 'R', 'email' => "r$i@b.co", 'users' => '1-10'], 'consent' => ['enquiry' => 1]])['ok'];
    }
    assert_same([true, true, true, true, true, false, false], $results);
});
