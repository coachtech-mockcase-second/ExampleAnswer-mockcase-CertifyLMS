<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MockExamQuestionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 模試マスタの子リソースとして独立した問題を表す Model。
 *
 * 関連: MockExam(親、必須) / QuestionCategory(出題分野マスタ) / MockExamQuestionOption(選択肢) /
 *      MockExamAnswer(個別解答ログ)
 * scope: ordered(order ASC)
 */
class MockExamQuestion extends Model
{
    /** @use HasFactory<MockExamQuestionFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'mock_exam_id',
        'category_id',
        'body',
        'explanation',
        'order',
    ];

    protected $casts = [
        'order' => 'integer',
    ];

    /**
     * @return BelongsTo<MockExam, $this>
     */
    public function mockExam(): BelongsTo
    {
        return $this->belongsTo(MockExam::class);
    }

    /**
     * @return BelongsTo<QuestionCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(QuestionCategory::class, 'category_id');
    }

    /**
     * @return HasMany<MockExamQuestionOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(MockExamQuestionOption::class);
    }

    /**
     * @return HasMany<MockExamAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(MockExamAnswer::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order');
    }
}
