<?php

declare(strict_types=1);

namespace Ichinya\LaravelSocialite\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $driver
 * @property string $identity
 * @property string $username
 * @property string $email
 * @property string $avatar
 *
 * @mixin Model
 */
class SocialAccount extends Model
{
    protected $fillable = [
        'user_id',
        'driver',
        'identity',
        'username',
        'email',
        'avatar',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
