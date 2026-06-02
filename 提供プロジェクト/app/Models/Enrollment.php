<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Enums\TermType;
use App\Enums\UserRole;
use Database\Factories\EnrollmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 受講生 × 資格の受講登録を表す Model。1 受講生は複数資格を同時受講可。
 *
 * 担当コーチは Enrollment に直接紐づかず、Certification 経由(certification_coach_assignments、資格 × N コーチ N:N)
 * で参照する。修了は受講生「修了証を受け取る」自己発火で即時 passed 遷移し、admin 承認フローは持たない。
 *
 * 関連: User(受講生) / Certification / Certificate(発行済修了証) / EnrollmentStatusLog / MockExamSession
 * 逆リレーション: defaultedByUser(受講生がデフォルト資格として指している場合のみ存在)
 * scope: learning() / passed() / failed() / forUser(User)(admin = 全件 / coach = 担当資格の Enrollment / student = 自分の Enrollment)
 */
class Enrollment extends Model
{
    /** @use HasFactory<EnrollmentFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'certification_id',
        'exam_date',
        'status',
        'current_term',
        'passed_at',
    ];

    protected $casts = [
        'status' => EnrollmentStatus::class,
        'current_term' => TermType::class,
        'exam_date' => 'date',
        'passed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Certification, $this>
     */
    public function certification(): BelongsTo
    {
        return $this->belongsTo(Certification::class);
    }

    /**
     * @return HasOne<Certificate, $this>
     */
    public function certificate(): HasOne
    {
        return $this->hasOne(Certificate::class);
    }

    /**
     * @return HasMany<EnrollmentStatusLog, $this>
     */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(EnrollmentStatusLog::class);
    }

    /**
     * 最新の状態遷移ログ 1 件のみ。一覧で「直近の遷移理由」を表示するための eager load 用途。
     *
     * @return HasOne<EnrollmentStatusLog, $this>
     */
    public function latestStatusLog(): HasOne
    {
        return $this->hasOne(EnrollmentStatusLog::class)->latestOfMany('changed_at');
    }

    /**
     * @return HasMany<MockExamSession, $this>
     */
    public function mockExamSessions(): HasMany
    {
        return $this->hasMany(MockExamSession::class);
    }

    /**
     * 本受講登録をデフォルト資格として指している受講生(0 または 1 件)。
     * Enrollment は単一受講生に属するため、`defaultedByUser` も 1 件以下となる。
     *
     * @return HasOne<User, $this>
     */
    public function defaultedByUser(): HasOne
    {
        return $this->hasOne(User::class, 'default_enrollment_id', 'id');
    }

    /**
     * 受講登録に紐づく chat ルーム(1 Enrollment = 1 ChatRoom、受講登録時に eager 生成される)。
     *
     * @return HasOne<ChatRoom, $this>
     */
    public function chatRoom(): HasOne
    {
        return $this->hasOne(ChatRoom::class);
    }

    /**
     * @return HasMany<SectionProgress, $this>
     */
    public function sectionProgresses(): HasMany
    {
        return $this->hasMany(SectionProgress::class);
    }

    /**
     * @return HasMany<LearningSession, $this>
     */
    public function learningSessions(): HasMany
    {
        return $this->hasMany(LearningSession::class);
    }

    /**
     * @return HasOne<LearningHourTarget, $this>
     */
    public function learningHourTarget(): HasOne
    {
        return $this->hasOne(LearningHourTarget::class);
    }

    public function scopeLearning(Builder $query): Builder
    {
        return $query->where('status', EnrollmentStatus::Learning->value);
    }

    public function scopePassed(Builder $query): Builder
    {
        return $query->where('status', EnrollmentStatus::Passed->value);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', EnrollmentStatus::Failed->value);
    }

    /**
     * 操作者ロールに応じて表示行を絞り込む scope。
     *
     * - admin: 全件
     * - coach: 自分が担当として割り当てられた資格に属する Enrollment のみ
     * - student: 自分の Enrollment のみ (user_id = self.id)
     * - その他: 空集合
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            UserRole::Admin => $query,
            UserRole::Coach => $query->whereHas(
                'certification.coaches',
                fn (Builder $q) => $q->where('users.id', $user->id),
            ),
            UserRole::Student => $query->where('user_id', $user->id),
            default => $query->whereRaw('1 = 0'),
        };
    }
}
