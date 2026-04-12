<?php

namespace Ichinya\LaravelSocialite\Contracts;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

interface RedirectHook
{
    /**
     * @param Closure(): mixed $fallback
     */
    public function handle(
        string  $driver,
        mixed   $provider,
        Closure $fallback
    ): RedirectResponse|Response|SymfonyRedirectResponse|string|null;
}
