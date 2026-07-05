<?php

namespace Ichinya\LaravelSocialite;

use App\Models\User;
use Closure;
use Ichinya\LaravelSocialite\Contracts\RedirectHook;
use Ichinya\LaravelSocialite\Models\SocialAccount;
use Ichinya\LaravelSocialite\Support\RedirectResponseNormalizer;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class LaravelSocialite extends Controller
{
    public function redirect(string $driver): RedirectResponse|Response
    {
        $this->ensureSocialiteIsInstalled();

        if (!$this->hasDriver($driver)) {
            throw new NotFoundHttpException("Socialite driver [$driver] is not configured.");
        }

        $provider = Socialite::driver($driver);
        $response = $this->applyRedirectHook(
            driver: $driver,
            provider: $provider,
            fallback: static fn (): mixed => $provider->redirect()
        );

        return $this->redirectResponseNormalizer()->normalize($driver, $response);
    }

    protected function ensureSocialiteIsInstalled(): void
    {
        if (class_exists(Socialite::class)) {
            return;
        }

        throw new RuntimeException('Please install the Socialite: laravel/socialite');
    }

    protected function hasDriver(string $driver): bool
    {
        return isset($this->drivers()[$driver]);
    }

    protected function drivers(): array
    {
        return config('socialite.drivers', []);
    }

    protected function redirectHooks(): array
    {
        return config('socialite.redirect_hooks', []);
    }

    public function callback(string $driver): RedirectResponse
    {
        $this->ensureSocialiteIsInstalled();

        if (!$this->hasDriver($driver)) {
            throw new NotFoundHttpException("Socialite driver [$driver] is not configured.");
        }

        try {
            $provider = Socialite::driver($driver);
            if ($this->isStateless($driver)) {
                $provider = $provider->stateless();
            }

            $socialiteUser = $provider->user();
        } catch (Throwable $e) {
            return $this->redirectToConfigured('on_error')
                ->withErrors([
                    'socialite' => "Authorization via [$driver] failed: {$e->getMessage()}",
                ]);
        }

        if ($this->auth()->check()) {
            $authUser = $this->auth()->user();
            $this->bindAccount($authUser, $socialiteUser, $driver);
            if ($authUser instanceof User) {
                $this->syncTelegramProfile($authUser, $socialiteUser, $driver);
            }

            return $this->redirectToConfigured('after_bind');
        }

        $account = SocialAccount::query()
            ->where('driver', $driver)
            ->where('identity', (string) $socialiteUser->getId())
            ->first();

        if ($account instanceof SocialAccount) {
            $existingUser = User::query()->find($account->user_id);
            if ($existingUser) {
                $this->bindAccount($existingUser, $socialiteUser, $driver);
                $this->syncTelegramProfile($existingUser, $socialiteUser, $driver);
            }

            $this->auth()->loginUsingId($account->user_id);

            return $this->redirectToConfigured('after_login');
        }

        $user = $this->resolveUser($socialiteUser, $driver);
        $this->auth()->loginUsingId($user->id);
        $this->bindAccount($user, $socialiteUser, $driver);
        $this->syncTelegramProfile($user, $socialiteUser, $driver);

        return $this->redirectToConfigured('after_login');
    }

    protected function bindAccount(Authenticatable $user, SocialiteUser $socialUser, string $driver): void
    {
        SocialAccount::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'driver' => $driver,
            ],
            [
                'identity' => (string) $socialUser->getId(),
                'username' => $socialUser->getNickname(),
                'email' => $socialUser->getEmail(),
                'avatar' => $socialUser->getAvatar(),
            ]
        );
    }

    protected function resolveUser(SocialiteUser $socialiteUser, string $driver): User
    {
        $identity = (string) $socialiteUser->getId();
        $email = $socialiteUser->getEmail();
        $name = $socialiteUser->getName()
            ?: $socialiteUser->getNickname()
                ?: ucfirst($driver) . ' user';

        if (!empty($email)) {
            return User::query()->firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => bcrypt(Str::random(40)),
                ]
            );
        }

        $fallbackEmail = sprintf(
            '%s_%s@social.local',
            $driver,
            preg_replace('/[^A-Za-z0-9_.-]/', '_', $identity)
        );

        return User::query()->firstOrCreate(
            ['email' => $fallbackEmail],
            [
                'name' => $name,
                'password' => bcrypt(Str::random(40)),
            ]
        );
    }

    protected function syncTelegramProfile(User $user, SocialiteUser $socialiteUser, string $driver): void
    {
        if ($driver !== 'telegram') {
            return;
        }

        if (empty($user->name)) {
            $user->name = $socialiteUser->getName()
                ?: (empty($socialiteUser->getNickname()) ? null : '@' . $socialiteUser->getNickname())
                    ?: 'Telegram user';

            $user->save();
        }
    }

    protected function isStateless(string $driver): bool
    {
        return (bool) data_get(config('socialite.stateless_drivers', []), $driver, false);
    }

    protected function auth(): Guard
    {
        return auth()->guard();
    }

    protected function redirectToConfigured(string $key): RedirectResponse
    {
        $target = (string) config("socialite.redirects.$key", '/');

        if (Str::startsWith($target, 'route:')) {
            return redirect()->route(Str::after($target, 'route:'));
        }

        return redirect($target);
    }

    /**
     * @param Closure(): mixed $fallback
     */
    protected function applyRedirectHook(string $driver, mixed $provider, Closure $fallback): mixed
    {
        $hook = $this->resolveRedirectHook($driver);
        if (!$hook) {
            Log::debug('Socialite redirect hook not configured, using provider response', [
                'driver' => $driver,
            ]);

            return $fallback();
        }

        try {
            $hookResponse = $hook->handle($driver, $provider, $fallback);
        } catch (Throwable $e) {
            Log::error('Socialite redirect hook failed, falling back to provider response', [
                'driver' => $driver,
                'hook' => $hook::class,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return $fallback();
        }

        if ($hookResponse === null) {
            Log::debug('Socialite redirect hook returned null, falling back to provider response', [
                'driver' => $driver,
                'hook' => $hook::class,
            ]);

            return $fallback();
        }

        Log::info('Socialite redirect hook returned custom response', [
            'driver' => $driver,
            'hook' => $hook::class,
            'response_type' => get_debug_type($hookResponse),
        ]);

        return $hookResponse;
    }

    protected function resolveRedirectHook(string $driver): ?RedirectHook
    {
        $hookClass = data_get($this->redirectHooks(), $driver);
        if (!is_string($hookClass) || $hookClass === '') {
            return null;
        }

        Log::debug('Resolving Socialite redirect hook', [
            'driver' => $driver,
            'hook' => $hookClass,
        ]);

        if (!class_exists($hookClass)) {
            Log::warning('Configured Socialite redirect hook class does not exist, using fallback response', [
                'driver' => $driver,
                'hook' => $hookClass,
            ]);

            return null;
        }

        try {
            $hook = app()->make($hookClass);
        } catch (Throwable $e) {
            Log::error('Failed to resolve Socialite redirect hook from container, using fallback response', [
                'driver' => $driver,
                'hook' => $hookClass,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        if (!$hook instanceof RedirectHook) {
            Log::error('Configured Socialite redirect hook does not implement RedirectHook, using fallback response', [
                'driver' => $driver,
                'hook' => $hookClass,
                'resolved_type' => get_debug_type($hook),
            ]);

            return null;
        }

        return $hook;
    }

    protected function redirectResponseNormalizer(): RedirectResponseNormalizer
    {
        return app(RedirectResponseNormalizer::class);
    }
}
