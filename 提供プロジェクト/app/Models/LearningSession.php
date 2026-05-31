<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\LearningSessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Section 詳細ページ滞在時間を記録する学習セッション。
 *
 * Section 詳細ページの GET 表示時にサーバ側で auto-start(BrowseController::showSection 内)、
 * 別 Section 遷移時に旧 session を auto_closed=true で自動 close。Schedule Command でも保険 close する。
 *
 * user_id は enrollment.user_id から複製した denormalize カラム(StreakService / dashboard 集計の高速化用)。
 *
 * 関連: User(受講生) / Enrollment(受講登録) / Section(教材)
 * scope: open(未終了) / closed(終了済) / forUser(User) / forEnrollment(Enrollment) / onDate(Carbon)
 */
class LearningSession extends Model
{
    /** @use HasFactory<LearningSessionFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'enrollment_id',
        'section_id',
        'started_at',
        'ended_at',
        'duration_seconds',
        'auto_closed',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_seconds' => 'integer',
        'auto_closed' => 'boolean',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->whereNotNull('ended_at');
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeForEnrollment(Builder $query, Enrollment $enrollment): Builder
    {
        return $query->where('enrollment_id', $enrollment->id);
    }

    public function scopeOnDate(Builder $query, \DateTimeInterface $date): Builder
    {
        return $query->whereDate('started_at', $date);
    }
}
