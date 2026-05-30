<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EnrollmentGoalFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 受講生が Enrollment 単位で立てる個人目標。受講生本人のみ CRUD 可、coach / admin は閲覧専用。
 * achieved_at が null = 未達成、datetime = 達成済。受講生のマーク / アンマーク操作で更新される。
 */
class EnrollmentGoal extends Model
{
    /** @use HasFactory<EnrollmentGoalFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'enrollment_id',
        'title',
        'description',
        'target_date',
        'achieved_at',
    ];

    protected $casts = [
        'target_date' => 'date',
        'achieved_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function isAchieved(): bool
    {
        return $this->achieved_at !== null;
    }

    /**
     * 個人目標の表示順。未達成を先頭にし、その中で目標期日が近い順(期日未設定は末尾)、
     * 同条件は新しく作成した順に並べる。受講登録詳細とダッシュボードの目標一覧で共用する。
     *
     * @param  Builder<EnrollmentGoal>  $query
     * @return Builder<EnrollmentGoal>
     */
    public function scopeDisplayOrder(Builder $query): Builder
    {
        return $query
            ->orderByRaw('CASE WHEN achieved_at IS NULL THEN 0 ELSE 1 END')
            ->orderByRaw('CASE WHEN target_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('target_date')
            ->latest();
    }
}
