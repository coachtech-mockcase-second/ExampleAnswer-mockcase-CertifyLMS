<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MockExamAnswerFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 模試セッション中の個別問題への解答ログを表す Model。
 *
 * 関連: MockExamSession(親、必須) / MockExamQuestion(問題) / MockExamQuestionOption(選択肢、nullable)
 * SoftDelete は採用しない(物理削除前提、UNIQUE 制約に依存)。
 *
 * UNIQUE 制約: (mock_exam_session_id, mock_exam_question_id) — 同一問題への複数解答は UPSERT で 1 レコードに集約。
 * is_correct は GradeAction 内で確定される。
 */
class MockExamAnswer extends Model
{
    /** @use HasFactory<MockExamAnswerFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'mock_exam_session_id',
        'mock_exam_question_id',
        'selected_option_id',
        'selected_option_body',
        'is_correct',
        'answered_at',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'answered_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<MockExamSession, $this>
     */
    public function mockExamSession(): BelongsTo
    {
        return $this->belongsTo(MockExamSession::class);
    }

    /**
     * @return BelongsTo<MockExamQuestion, $this>
     */
    public function mockExamQuestion(): BelongsTo
    {
        return $this->belongsTo(MockExamQuestion::class);
    }

    /**
     * 受講生が選択した選択肢(nullable: 採点時点で選択肢が物理削除 / cascade null されていた場合)。
     *
     * @return BelongsTo<MockExamQuestionOption, $this>
     */
    public function selectedOption(): BelongsTo
    {
        return $this->belongsTo(MockExamQuestionOption::class, 'selected_option_id');
    }
}
