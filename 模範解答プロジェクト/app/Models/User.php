<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Notifications\Auth\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * プラン受講中のユーザーを表す Model。
 *
 * 関連: Plan / Enrollment / UserStatusLog / UserPlanLog / Invitation / Certificate
 * 主要 Service: UserStatusChangeService(status 遷移ログ) / PlanExpirationService(期限判定)
 */
class User extends Authenticatable
{
    use HasFactory, HasUlids, Notifiable, SoftDeletes;

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
        'plan_id',
        'plan_started_at',
        'plan_expires_at',
        'max_meetings',
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
        'plan_started_at' => 'datetime',
        'plan_expires_at' => 'datetime',
        'max_meetings' => 'integer',
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

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function assignedCertifications(): BelongsToMany
    {
        return $this->belongsToMany(
            Certification::class,
            'certification_coach_assignments',
            'coach_user_id',
            'certification_id',
        )
            ->using(CertificationCoachAssignment::class)
            ->withPivot(['id', 'assigned_by_user_id', 'assigned_at'])
            ->withTimestamps();
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return HasMany<UserPlanLog, $this>
     */
    public function planLogs(): HasMany
    {
        return $this->hasMany(UserPlanLog::class);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
