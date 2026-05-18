<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MockExamQuestionOptionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 模試問題の選択肢を表す Model。
 *
 * 関連: MockExamQuestion(親、必須)
 * `is_correct` は採点時に MockExamAnswer.is_correct の確定根拠となる。
 * 受験中の Blade / Resource には正答情報を露出しない(`NFR-mock-exam-008`)。
 * scope: ordered(order ASC)
 */
class MockExamQuestionOption extends Model
{
    /** @use HasFactory<MockExamQuestionOptionFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'mock_exam_question_id',
        'body',
        'is_correct',
        'order',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'order' => 'integer',
    ];

    /**
     * @return BelongsTo<MockExamQuestion, $this>
     */
    public function mockExamQuestion(): BelongsTo
    {
        return $this->belongsTo(MockExamQuestion::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order');
    }
}
