<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SectionProgressFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 受講生の Section 単位読了マーク。1 Enrollment × 1 Section の最大 1 行(UNIQUE 制約)。
 * 再マークは UPDATE で `completed_at` を更新する。
 *
 * 関連: Enrollment(親) / Section(対象)
 */
class SectionProgress extends Model
{
    /** @use HasFactory<SectionProgressFactory> */
    use HasFactory, HasUlids;

    protected $table = 'section_progresses';

    protected $fillable = [
        'enrollment_id',
        'section_id',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

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
}
