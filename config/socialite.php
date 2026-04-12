<?php

// config for Ichinya/LaravelSocialite
return [
    'drivers' => [
        // 'google' =>  'icon/google.svg',
    ],

    'redirect_hooks' => [
        // 'telegram' => \App\Infrastructure\Auth\TelegramRedirectScreen::class,
    ],

    'stateless_drivers' => [
        // 'telegram' => true,
    ],

    'redirects' => [
        'after_login' => '/',
        'after_bind' => '/',
        'on_error' => 'route:login',
    ],
];
