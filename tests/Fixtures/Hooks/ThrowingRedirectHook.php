<?php

namespace Ichinya\LaravelSocialite\Tests\Fixtures\Hooks;

use Closure;
use Ichinya\LaravelSocialite\Contracts\RedirectHook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class ThrowingRedirectHook implements RedirectHook
{
    public function handle(
        string $driver,
        mixed $provider,
        Closure $fallback
    ): RedirectResponse|Response|SymfonyRedirectResponse|string|null {
        throw new RuntimeException('Hook failure for ' . $driver);
    }
}
