<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CoachAvailabilityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 担当コーチの面談可能時間枠を表す Model(曜日 × 開始時刻 × 終了時刻の繰り返し枠)。
 *
 * 受講生の予約画面は資格に紐づく担当コーチ集合の全 active 枠を 60 分刻みでスロット化し、
 * 既存予約と突き合わせて空きスロットを算出する。編集 UI はプロフィール設定画面が所有する。
 *
 * 関連: User(coach)
 * scope: active / forDay(int dayOfWeek)
 */
class CoachAvailability extends Model
{
    /** @use HasFactory<CoachAvailabilityFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'coach_id',
        'day_of_week',
        'start_time',
        'end_time',
        'is_active',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForDay(Builder $query, int $dayOfWeek): Builder
    {
        return $query->where('day_of_week', $dayOfWeek);
    }
}
