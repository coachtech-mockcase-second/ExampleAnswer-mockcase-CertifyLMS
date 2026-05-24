<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\LearningHourTargetFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Enrollment 単位の学習時間目標(合計目標時間、1..9999h)。1 Enrollment あたり最大 1 行(UNIQUE)。
 * 未設定は行なしで表現し、再設定は UPDATE で扱う。
 *
 * 関連: Enrollment(親、UNIQUE で 1:1)
 */
class LearningHourTarget extends Model
{
    /** @use HasFactory<LearningHourTargetFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'enrollment_id',
        'target_total_hours',
    ];

    protected $casts = [
        'target_total_hours' => 'integer',
    ];

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }
}
