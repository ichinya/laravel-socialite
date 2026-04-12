<?php

namespace Ichinya\LaravelSocialite\Tests\Fixtures;

use RuntimeException;

class FakeSocialiteManager
{
    /**
     * @param array<string, mixed> $providers
     */
    public function __construct(private readonly array $providers)
    {
    }

    public function driver(string $driver): mixed
    {
        if (!array_key_exists($driver, $this->providers)) {
            throw new RuntimeException("Fake Socialite provider [$driver] is not configured.");
        }

        return $this->providers[$driver];
    }
}
