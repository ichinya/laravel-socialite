<?php

namespace Ichinya\LaravelSocialite\Traits;

use Ichinya\LaravelSocialite\Models\SocialAccount;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property SocialAccount[] $socials
 *
 * @mixin Model
 * @mixin Authenticatable
 */
trait HasSocialites
{
    public function socials(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }
}
