<?php

namespace Ichinya\LaravelSocialite\Tests\Fixtures\Hooks;

use Closure;
use Ichinya\LaravelSocialite\Contracts\RedirectHook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class NullRedirectHook implements RedirectHook
{
    public function handle(
        string $driver,
        mixed $provider,
        Closure $fallback
    ): RedirectResponse|Response|SymfonyRedirectResponse|string|null {
        return null;
    }
}
