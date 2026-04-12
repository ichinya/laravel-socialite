<?php

namespace Ichinya\LaravelSocialite\Tests\Fixtures\Hooks;

use Closure;
use Ichinya\LaravelSocialite\Contracts\RedirectHook;
use Illuminate\Http\Response;

class CustomResponseHook implements RedirectHook
{
    public function handle(string $driver, mixed $provider, Closure $fallback): Response
    {
        return response('custom-hook:' . $driver);
    }
}
