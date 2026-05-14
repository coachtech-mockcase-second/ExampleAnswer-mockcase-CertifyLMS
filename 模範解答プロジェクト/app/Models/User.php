<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Notifications\Auth\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUlids, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'bio',
        'avatar_url',
        'profile_setup_completed',
        'email_verified_at',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
        'role' => UserRole::class,
        'status' => UserStatus::class,
        'profile_setup_completed' => 'boolean',
    ];

    public function statusLogs(): HasMany
    {
        return $this->hasMany(UserStatusLog::class, 'user_id');
    }

    public function statusChanges(): HasMany
    {
        return $this->hasMany(UserStatusLog::class, 'changed_by_user_id');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'user_id');
    }

    public function issuedInvitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'invited_by_user_id');
    }

    /**
     * email を `{ulid}@deleted.invalid` 形式へリネーム + status=withdrawn + soft delete を 1 操作で行う。
     * UserStatusLog 記録は呼び出し側 Action の責務（同一トランザクション内で呼ぶこと）。
     */
    public function withdraw(): void
    {
        $this->forceFill([
            'email' => $this->id.'@deleted.invalid',
            'status' => UserStatus::Withdrawn,
        ])->save();

        $this->delete();
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
