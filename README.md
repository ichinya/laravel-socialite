# Laravel Socialite Helper

Пакет добавляет готовый OAuth-вход для Laravel через `laravel/socialite`:

- маршруты `/socialite/{driver}/redirect` и `/socialite/{driver}/callback`;
- таблицу `social_accounts` для привязки провайдера к пользователю;
- автоматическую авторизацию существующего пользователя или создание нового;
- привязку дополнительного провайдера для уже авторизованного пользователя;
- generic hook для кастомного redirect screen без правки vendor-кода.

## Требования

- PHP `^8.3|8.4|8.5`
- Laravel `^12.25|^13.1`
- `laravel/socialite`

## Установка

```bash
composer require ichinya/laravel-socialite
php artisan vendor:publish --tag=ichinya-socialite
php artisan migrate
```

## Как работает flow

1. Гость переходит на `socialite.redirect`.
2. Пакет создаёт provider через `Socialite::driver($driver)`.
3. Если для драйвера настроен `redirect_hooks`, пакет даёт hook-классу шанс вернуть кастомный response.
4. Если hook не настроен, вернул `null`, сконфигурирован неверно или завершился ошибкой, пакет делает fallback к штатному provider response.
5. На callback пакет связывает `driver + identity` с пользователем и выполняет login/bind flow.

## Локальная разработка пакета через path repository

Если пакет разрабатывается локально внутри Laravel-приложения:

```bash
composer config repositories.ichinya-laravel-socialite path packages/ichinya/laravel-socialite
composer update ichinya/laravel-socialite
composer show ichinya/laravel-socialite
```

Ожидаемый результат проверки: `dist: [path] packages/ichinya/laravel-socialite`.

Чтобы вернуться к обычной версии из registry:

```bash
composer config --unset repositories.ichinya-laravel-socialite
composer update ichinya/laravel-socialite
```

## Настройка `config/socialite.php`

```php
<?php

return [
    'drivers' => [
        'github' => '/assets/socialite/github.svg',
        'google' => '/assets/socialite/google.svg',
        'telegram' => '/assets/socialite/telegram.svg',
    ],

    'redirect_hooks' => [
        'telegram' => \App\Infrastructure\Auth\TelegramRedirectScreen::class,
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

### Ключи конфига

- `drivers`: активные провайдеры в формате `driver => icon_path`.
- `redirect_hooks`: карта `driver => class-string` для container-resolved hook-классов.
- `stateless_drivers`: драйверы, для которых нужно `->stateless()`.
- `redirects.after_login`: редирект после login flow.
- `redirects.after_bind`: редирект после bind flow.
- `redirects.on_error`: редирект при ошибке OAuth. Поддерживает путь и `route:<name>`.

## Generic redirect hook

Hook нужен для custom redirect screen, когда provider на этапе `redirect()` отдаёт HTML, legacy widget или другой нестандартный ответ, а приложению нужен собственный wrapper screen.

Hook-класс должен реализовывать `Ichinya\LaravelSocialite\Contracts\RedirectHook`:

```php
<?php

namespace App\Infrastructure\Auth;

use Closure;
use Ichinya\LaravelSocialite\Contracts\RedirectHook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class TelegramRedirectScreen implements RedirectHook
{
    public function handle(
        string $driver,
        mixed $provider,
        Closure $fallback,
    ): RedirectResponse|Response|SymfonyRedirectResponse|string|null {
        if ($driver !== 'telegram' || !method_exists($provider, 'getButton')) {
            return null;
        }

        return response('<html>custom wrapper screen</html>');
    }
}
```

Hook получает lazy callback `$fallback`, если приложению нужно вручную вернуть исходный provider response без преждевременного вызова `redirect()`.

### Что может вернуть hook

- `Illuminate\Http\Response`
- `Illuminate\Http\RedirectResponse`
- `Symfony\Component\HttpFoundation\RedirectResponse`
- `string`
- `null`

### Fallback semantics

- hook не настроен: пакет ведёт себя как раньше;
- hook сконфигурирован на несуществующий класс или класс с неверным контрактом: ошибка логируется, затем выполняется fallback;
- hook вернул `null`: используется штатный provider response;
- hook выбросил исключение: ошибка логируется, затем выполняется fallback;
- hook не должен заменять callback/login flow, он влияет только на этап `redirect`.

### Диагностика

В логах ищите:

- `DEBUG` на resolve hook по `driver`;
- `DEBUG` на fallback после `null`;
- `WARN` на отсутствующий hook-класс;
- `ERROR` на exception внутри hook или ошибке container resolution;
- `INFO` на successful custom response.

## Настройка `config/services.php` и `.env`

### Google / GitHub

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

```dotenv
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI="${APP_URL}/socialite/google/callback"

GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
GITHUB_REDIRECT_URI="${APP_URL}/socialite/github/callback"
```

### Telegram

Для Telegram нужен `socialiteproviders/telegram`.

```bash
composer require socialiteproviders/telegram
```

```php
'telegram' => [
    'bot' => env('TELEGRAM_LOGIN_BOT_NAME', env('TELEGRAM_BOT_NAME')),
    'client_id' => null,
    'client_secret' => env('TELEGRAM_BOT_TOKEN'),
    'redirect' => env('TELEGRAM_LOGIN_REDIRECT_URI', env('APP_URL').'/socialite/telegram/callback'),
],
```

```dotenv
TELEGRAM_LOGIN_BOT_NAME=your_bot_name
TELEGRAM_BOT_TOKEN=your_bot_token
TELEGRAM_LOGIN_REDIRECT_URI="${APP_URL}/socialite/telegram/callback"
```

Пример регистрации Telegram provider:

```php
use Illuminate\Support\Facades\Event;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Telegram\Provider as TelegramProvider;

Event::listen(function (SocialiteWasCalled $event): void {
    $event->extendSocialite('telegram', TelegramProvider::class);
});
```

## Связь в модели `User`

```php
use Ichinya\LaravelSocialite\Traits\HasSocialites;

class User extends Authenticatable
{
    use HasSocialites;
}
```

После этого доступна связь:

```php
$user->socials;
```

## Маршруты пакета

- `GET /socialite/{driver}/redirect` (`socialite.redirect`)
- `GET /socialite/{driver}/callback` (`socialite.callback`)

## Обработка ошибок на login screen

При ошибке OAuth пакет редиректит на `socialite.redirects.on_error` и кладёт текст ошибки в ключ `socialite`.

```blade
@if($errors->has('socialite'))
    <div>{{ $errors->first('socialite') }}</div>
@endif
```

## Частые проблемы

### Invalid state

Добавьте драйвер в `stateless_drivers`:

```php
'stateless_drivers' => [
    'telegram' => true,
],
```

### Bot username required

Проверьте `services.telegram.bot` и `TELEGRAM_LOGIN_BOT_NAME`.

### Mixed Content

Проверьте:

- `APP_URL=https://...`
- доверие к proxy-заголовкам в Laravel и веб-сервере.

## Changelog

Смотрите [CHANGELOG.md](CHANGELOG.md).

## License

MIT. See [LICENSE.md](LICENSE.md).
