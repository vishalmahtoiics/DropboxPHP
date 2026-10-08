<?php $site = App\Services\Settings::group('website'); ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title><?= e($site['site_name']) ?> — back soon</title>
<style nonce="<?= e(nonce()) ?>">body{font-family:system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;margin:0;background:#f4f6fb;color:#0b1b34}main{max-width:32rem;padding:2rem;text-align:center}h1{font-size:1.75rem}</style></head>
<body><main><h1>We'll be right back</h1><p><?= e($site['maintenance_message']) ?></p></main></body></html>
