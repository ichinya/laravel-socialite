<?php

namespace Ichinya\LaravelSocialite\Tests\Fixtures;

class FakeSocialiteProvider
{
    private int $redirectCalls = 0;

    public function __construct(private readonly mixed $redirectResponse)
    {
    }

    public function redirect(): mixed
    {
        $this->redirectCalls++;

        return $this->redirectResponse;
    }

    public function redirectCalls(): int
    {
        return $this->redirectCalls;
    }
}
