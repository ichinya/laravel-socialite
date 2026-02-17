<?php

namespace Ichinya\LaravelSocialite;

use Illuminate\Support\ServiceProvider;

class LaravelSocialiteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/socialite.php', 'socialite');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        $this->publishes([
            __DIR__.'/../config/socialite.php' => config_path('socialite.php'),
        ], ['config', 'laravel-socialite', 'ichinya-socialite']);
    }
}
