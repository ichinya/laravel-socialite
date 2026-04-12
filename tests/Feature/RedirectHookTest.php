<?php

use Ichinya\LaravelSocialite\Tests\Fixtures\FakeSocialiteManager;
use Ichinya\LaravelSocialite\Tests\Fixtures\FakeSocialiteProvider;
use Ichinya\LaravelSocialite\Tests\Fixtures\Hooks\CustomResponseHook;
use Ichinya\LaravelSocialite\Tests\Fixtures\Hooks\NullRedirectHook;
use Ichinya\LaravelSocialite\Tests\Fixtures\Hooks\ThrowingRedirectHook;
use Laravel\Socialite\Facades\Socialite;

beforeEach(function (): void {
    config()->set('socialite.drivers', [
        'github' => '/assets/socialite/github.svg',
        'telegram' => '/assets/socialite/telegram.svg',
    ]);
    config()->set('socialite.redirect_hooks', []);
});

it('keeps default provider redirect when hook is not configured', function (): void {
    $provider = new FakeSocialiteProvider('https://provider.example/github');

    Socialite::swap(new FakeSocialiteManager([
        'github' => $provider,
    ]));

    $this->get('/socialite/github/redirect')
        ->assertRedirect('https://provider.example/github');

    expect($provider->redirectCalls())->toBe(1);
});

it('returns a custom response from the configured hook', function (): void {
    config()->set('socialite.redirect_hooks.telegram', CustomResponseHook::class);

    $provider = new FakeSocialiteProvider('<div>telegram-widget</div>');

    Socialite::swap(new FakeSocialiteManager([
        'telegram' => $provider,
    ]));

    $this->get('/socialite/telegram/redirect')
        ->assertOk()
        ->assertSeeText('custom-hook:telegram');

    expect($provider->redirectCalls())->toBe(0);
});

it('falls back to provider response when hook returns null', function (): void {
    config()->set('socialite.redirect_hooks.telegram', NullRedirectHook::class);

    $provider = new FakeSocialiteProvider('https://provider.example/telegram');

    Socialite::swap(new FakeSocialiteManager([
        'telegram' => $provider,
    ]));

    $this->get('/socialite/telegram/redirect')
        ->assertRedirect('https://provider.example/telegram');

    expect($provider->redirectCalls())->toBe(1);
});

it('falls back to provider response when hook throws', function (): void {
    config()->set('socialite.redirect_hooks.telegram', ThrowingRedirectHook::class);

    $provider = new FakeSocialiteProvider('https://provider.example/telegram');

    Socialite::swap(new FakeSocialiteManager([
        'telegram' => $provider,
    ]));

    $this->get('/socialite/telegram/redirect')
        ->assertRedirect('https://provider.example/telegram');

    expect($provider->redirectCalls())->toBe(1);
});

it('keeps raw html redirect responses for legacy providers without hook', function (): void {
    $provider = new FakeSocialiteProvider('<script>legacy-widget</script>');

    Socialite::swap(new FakeSocialiteManager([
        'telegram' => $provider,
    ]));

    $this->get('/socialite/telegram/redirect')
        ->assertOk()
        ->assertSee('legacy-widget', false);

    expect($provider->redirectCalls())->toBe(1);
});
