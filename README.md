# Socialite for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ichinya/laravel-socialite.svg?style=flat-square)](https://packagist.org/packages/ichinya/laravel-socialite)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/ichinya/laravel-socialite/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/ichinya/laravel-socialite/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/ichinya/laravel-socialite/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/ichinya/laravel-socialite/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/ichinya/laravel-socialite.svg?style=flat-square)](https://packagist.org/packages/ichinya/laravel-socialite)

Пакет добавляет готовый OAuth-вход в Laravel через `laravel/socialite`:

- маршруты `/socialite/{driver}/redirect` и `/socialite/{driver}/callback`;
- таблицу `social_accounts` для привязки провайдера к пользователю;
- автоматическую авторизацию существующего пользователя или создание нового;
- привязку дополнительного провайдера для уже авторизованного пользователя.

## Требования

- PHP: `^8.3|8.4|8.5`
- Laravel: `^12`
- `laravel/socialite`

## Установка

```bash
composer require ichinya/laravel-socialite
```

Опубликуйте конфиг (опционально, но обычно нужно):

```bash
php artisan vendor:publish --tag=ichinya-socialite
```

Выполните миграции:

```bash
php artisan migrate
```

## Что происходит при логине

1. Гость нажимает вход через провайдер (`socialite.redirect`).
2. На callback пакет:
   - ищет запись в `social_accounts` по `driver + identity`;
   - если находит, авторизует связанного пользователя;
   - если не находит, создает пользователя и привязывает аккаунт.
3. Если пользователь уже авторизован, callback только привязывает новый провайдер к текущему пользователю.

## Шаг 1. Настройте `config/socialite.php`

```php
<?php

return [
    'drivers' => [
        'github' => '/assets/socialite/github.svg',
        'google' => '/assets/socialite/google.svg',
        'telegram' => '/assets/socialite/telegram.svg',
    ],

    'stateless_drivers' => [
        'telegram' => true,
    ],

    'redirects' => [
        'after_login' => '/cabinet',
        'after_bind' => '/cabinet',
        'on_error' => 'route:login',
    ],
];
```

### Пояснение по ключам

- `drivers` - список активных провайдеров (`driver => путь_к_иконке`).
- `stateless_drivers` - драйверы, для которых нужно `->stateless()` (часто `telegram`).
- `redirects.after_login` - куда отправлять после входа.
- `redirects.after_bind` - куда отправлять после привязки провайдера.
- `redirects.on_error` - куда отправлять при ошибке OAuth.  
  Поддерживает:
  - URL/путь (`/login`);
  - `route:имя_маршрута` (например `route:login`).

## Шаг 2. Настройте `config/services.php` и `.env`

### Пример для Google/GitHub

`config/services.php`:

```php
'google' => [
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect' => env('GOOGLE_REDIRECT_URI'),
],

'github' => [
    'client_id' => env('GITHUB_CLIENT_ID'),
    'client_secret' => env('GITHUB_CLIENT_SECRET'),
    'redirect' => env('GITHUB_REDIRECT_URI'),
],
```

`.env`:

```dotenv
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI="${APP_URL}/socialite/google/callback"

GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
GITHUB_REDIRECT_URI="${APP_URL}/socialite/github/callback"
```

### Пример для Telegram

Для Telegram нужен провайдер `socialiteproviders/telegram`.

```bash
composer require socialiteproviders/telegram
```

`config/services.php`:

```php
'telegram' => [
    'bot' => env('TELEGRAM_LOGIN_BOT_NAME', env('TELEGRAM_BOT_NAME')),
    'client_id' => null,
    'client_secret' => env('TELEGRAM_BOT_TOKEN'),
    'redirect' => env('TELEGRAM_LOGIN_REDIRECT_URI', env('APP_URL').'/socialite/telegram/callback'),
],
```

`.env`:

```dotenv
TELEGRAM_LOGIN_BOT_NAME=your_bot_name
TELEGRAM_BOT_TOKEN=your_bot_token
TELEGRAM_LOGIN_REDIRECT_URI="${APP_URL}/socialite/telegram/callback"
```

Регистрация драйвера Telegram (например в `App\Providers\AppServiceProvider::boot()`):

```php
use Illuminate\Support\Facades\Event;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Telegram\Provider as TelegramProvider;

Event::listen(function (SocialiteWasCalled $event): void {
    $event->extendSocialite('telegram', TelegramProvider::class);
});
```

## Шаг 3. Добавьте связь в модель `User`

Пакет работает с `App\Models\User`, поэтому обычно достаточно добавить trait:

```php
use Ichinya\LaravelSocialite\Traits\HasSocialites;

class User extends Authenticatable
{
    use HasSocialites;
}
```

После этого у пользователя будет связь:

```php
$user->socials; // коллекция SocialAccount
```

## Шаг 4. Добавьте кнопки входа

```blade
<a href="{{ route('socialite.redirect', ['driver' => 'github']) }}">
    Войти через GitHub
</a>

<a href="{{ route('socialite.redirect', ['driver' => 'google']) }}">
    Войти через Google
</a>

<a href="{{ route('socialite.redirect', ['driver' => 'telegram']) }}">
    Войти через Telegram
</a>
```

## Маршруты, которые добавляет пакет

- `GET /socialite/{driver}/redirect` (`socialite.redirect`)
- `GET /socialite/{driver}/callback` (`socialite.callback`)

## Обработка ошибок на форме логина

При ошибке OAuth пакет делает редирект на `socialite.redirects.on_error` и кладет текст ошибки в ключ `socialite`.

```blade
@if($errors->has('socialite'))
    <div>{{ $errors->first('socialite') }}</div>
@endif
```

## Частые проблемы

### `Invalid state`

Добавьте драйвер в `stateless_drivers`:

```php
'stateless_drivers' => [
    'telegram' => true,
],
```

### `Bot username required`

Проверьте `services.telegram.bot` и переменную `TELEGRAM_LOGIN_BOT_NAME`.

### Mixed Content (`http` в `https` приложении)

Проверьте:

- `APP_URL=https://...`
- доверие к proxy-заголовкам (`X-Forwarded-Proto`) в Laravel/веб-сервере.

## Журнал изменений

Смотрите [CHANGELOG](CHANGELOG.md).

## Contributing

Смотрите [CONTRIBUTING](CONTRIBUTING.md).

## Credits

- [Ichi](https://github.com/ichinya)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
