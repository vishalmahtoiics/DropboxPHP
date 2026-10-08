<?php

declare(strict_types=1);

use App\Core\Validator;
use App\Core\ValidationException;
use App\Services\Money;
use App\Services\Popups;
use App\Services\Redirects;
use App\Services\Slugs;

test('money is parsed exactly and formatted per currency', function (): void {
    assert_same('1299.50', Money::parse('1,299.50'));
    assert_same(null, Money::parse('12.345'));
    assert_same(null, Money::parse('abc'));
    assert_same('₹12,34,567', Money::format('1234567.00', '₹', 'INR'));
    assert_same('AED 1,234,567.50', Money::format('1234567.5', 'AED ', 'AED'));
});

test('slugs are made safe', function (): void {
    assert_same('dropbox-business-plus', Slugs::make('Dropbox Business+ Plus!'));
    assert_same('dropbox/business', Slugs::make('Dropbox / Business', true));
    assert_same('cafe-and-co', Slugs::make('Café & Co'));
});

test('validator enforces rules and returns only declared keys', function (): void {
    $out = Validator::make(['name' => '  Ann ', 'email' => 'A@B.CO', 'x' => 'y'], ['name' => 'required|max:10', 'email' => 'required|email']);
    assert_same(['name' => 'Ann', 'email' => 'a@b.co'], $out);
    $e = assert_throws(ValidationException::class, fn () => Validator::make(['email' => 'nope'], ['email' => 'required|email', 'name' => 'required']));
    assert_true(isset($e->errors['email'], $e->errors['name']));
    assert_throws(ValidationException::class, fn () => Validator::make(['u' => 'javascript:alert(1)'], ['u' => 'url']));
    assert_throws(ValidationException::class, fn () => Validator::make(['u' => '//evil.com'], ['u' => 'url']));
});

test('redirect sources are normalised', function (): void {
    assert_same('/old-page', Redirects::normalise('https://example.com/Old-Page/?x=1#y'));
    assert_same('/', Redirects::normalise(''));
});

test('popup page patterns', function (): void {
    assert_true(Popups::matches(['/blog/*'], '/blog'));
    assert_true(Popups::matches(['/blog/*'], '/blog/post'));
    assert_false(Popups::matches(['/blog/*'], '/blogger'));
    assert_true(Popups::matches(['/'], '/'));
    assert_false(Popups::matches(['/'], '/pricing'));
    assert_true(Popups::matches(['*'], '/anything'));
});

test('form tokens are signed and time-boxed', function (): void {
    $t = App\Services\Forms::token(7, time() - 5);
    assert_same('ok', App\Services\Forms::checkToken(7, $t));
    assert_same('invalid', App\Services\Forms::checkToken(8, $t), 'Token is bound to the form');
    assert_same('fast', App\Services\Forms::checkToken(7, App\Services\Forms::token(7)));
    assert_same('expired', App\Services\Forms::checkToken(7, App\Services\Forms::token(7, time() - 90000)));
    assert_same('invalid', App\Services\Forms::checkToken(7, '123.abc'));
});
