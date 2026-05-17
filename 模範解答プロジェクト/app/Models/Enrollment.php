<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Enums\TermType;
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
 * 関連: User(受講生) / Certification / Certificate(発行済修了証) / EnrollmentGoal / EnrollmentNote / EnrollmentStatusLog / MockExamSession
 * scope: learning() / passed() / failed() / forUser(User)
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
     * @return HasMany<EnrollmentGoal, $this>
     */
    public function goals(): HasMany
    {
        return $this->hasMany(EnrollmentGoal::class);
    }

    /**
     * @return HasMany<EnrollmentNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(EnrollmentNote::class);
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

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }
}
