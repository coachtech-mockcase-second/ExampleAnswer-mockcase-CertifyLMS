<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Notifications\Auth\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Builder;
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
        'meeting_url',
        'default_enrollment_id',
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

    /**
     * @return HasMany<UserStatusLog, $this>
     */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(UserStatusLog::class, 'user_id');
    }

    /**
     * @return HasMany<UserStatusLog, $this>
     */
    public function statusChanges(): HasMany
    {
        return $this->hasMany(UserStatusLog::class, 'changed_by_user_id');
    }

    /**
     * @return HasMany<Invitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'user_id');
    }

    /**
     * @return HasMany<Invitation, $this>
     */
    public function issuedInvitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'invited_by_user_id');
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * 受講生が「いつもこの資格を見る」と明示設定したデフォルト資格(受講登録)。
     * サイドバー / 教材 / 模試 / 面談予約画面の自動解決元になる。
     *
     * @return BelongsTo<Enrollment, $this>
     */
    public function defaultEnrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class, 'default_enrollment_id', 'id');
    }

    /**
     * @return HasMany<Certificate, $this>
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    /**
     * 担当コーチとして割り当てられている資格一覧（active 行のみ）。
     *
     * BelongsToMany は Pivot Model の SoftDeletes グローバルスコープを pivot join 句に自動適用しないため、
     * `deleted_at IS NULL` と `unassigned_at IS NULL` を両方明示する必要がある。
     *
     * @return BelongsToMany<Certification, $this>
     */
    public function assignedCertifications(): BelongsToMany
    {
        return $this->belongsToMany(
            Certification::class,
            'certification_coach_assignments',
            'user_id',
            'certification_id',
        )
            ->using(CertificationCoachAssignment::class)
            ->withPivot(['id', 'assigned_by_user_id', 'assigned_at', 'unassigned_at'])
            ->withTimestamps()
            ->wherePivotNull('deleted_at')
            ->wherePivot('unassigned_at', null);
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

    /**
     * @return HasMany<MeetingQuotaTransaction, $this>
     */
    public function meetingQuotaTransactions(): HasMany
    {
        return $this->hasMany(MeetingQuotaTransaction::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<LearningSession, $this>
     */
    public function learningSessions(): HasMany
    {
        return $this->hasMany(LearningSession::class);
    }

    /**
     * @return HasMany<SectionQuestionAnswer, $this>
     */
    public function sectionQuestionAnswers(): HasMany
    {
        return $this->hasMany(SectionQuestionAnswer::class);
    }

    /**
     * @return HasMany<SectionQuestionAttempt, $this>
     */
    public function sectionQuestionAttempts(): HasMany
    {
        return $this->hasMany(SectionQuestionAttempt::class);
    }

    /**
     * 受講生本人の模試受験セッション。enrollment_id 経由で取得可能だが非正規化された user_id を直接参照する。
     *
     * @return HasMany<MockExamSession, $this>
     */
    public function mockExamSessions(): HasMany
    {
        return $this->hasMany(MockExamSession::class, 'user_id');
    }

    /**
     * Laravel フレームワーク側のシグナル(`Illuminate\Foundation\Auth\User::sendPasswordResetNotification($token)`)
     * との LSP 整合のため、引数に型宣言を付与しない(親クラスが parameter type なしで宣言しているため)。
     *
     * @param  string  $token  パスワードリセット用の署名付きトークン
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * 受講中(in_progress) と 卒業(graduated) を「ログイン可能 = 活動アカウント」として扱うスコープ。
     * Fortify の認証通過判定や、管理画面の active 集計に使う。
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [UserStatus::InProgress, UserStatus::Graduated]);
    }
}
