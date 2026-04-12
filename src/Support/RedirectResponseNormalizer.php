<?php

namespace Ichinya\LaravelSocialite\Support;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class RedirectResponseNormalizer
{
    public function normalize(string $driver, mixed $response): RedirectResponse|Response
    {
        if ($response instanceof RedirectResponse) {
            return $response;
        }

        if ($response instanceof Response) {
            return $response;
        }

        if ($response instanceof SymfonyRedirectResponse) {
            Log::debug('Normalizing Symfony redirect response from Socialite provider', [
                'driver' => $driver,
                'target_url' => $response->getTargetUrl(),
            ]);

            return redirect()->away($response->getTargetUrl());
        }

        if (is_string($response)) {
            if (filter_var($response, FILTER_VALIDATE_URL)) {
                Log::debug('Normalizing URL string response from Socialite provider', [
                    'driver' => $driver,
                    'target_url' => $response,
                ]);

                return redirect()->away($response);
            }

            Log::debug('Normalizing raw HTML/string response from Socialite provider', [
                'driver' => $driver,
                'content_length' => strlen($response),
            ]);

            return response($response);
        }

        throw new RuntimeException(
            'Unsupported redirect response from Socialite driver [' . $driver . ']: ' . get_debug_type($response)
        );
    }
}
