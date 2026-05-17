<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SectionQuestionOptionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SectionQuestion に紐づく選択肢を表す Model。
 *
 * 関連: SectionQuestion(親、必須)
 * SoftDelete は採用しない。SectionQuestion 更新時は delete-and-insert で全選択肢を再構築する。
 * 履歴的な関連付けは親 SectionQuestion の SoftDelete によって保持する。
 */
class SectionQuestionOption extends Model
{
    /** @use HasFactory<SectionQuestionOptionFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'section_question_id',
        'body',
        'is_correct',
        'order',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'order' => 'integer',
    ];

    /**
     * @return BelongsTo<SectionQuestion, $this>
     */
    public function sectionQuestion(): BelongsTo
    {
        return $this->belongsTo(SectionQuestion::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order');
    }
}
