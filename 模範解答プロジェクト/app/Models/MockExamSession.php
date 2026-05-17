<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 模試セッション。受講生が公開模試を受験するときに生成される。
 * 学習ターム判定(TermJudgementService)と修了判定(CompletionEligibilityService)の起点となる最小 Model。
 *
 * status: not_started / in_progress / submitted / graded / canceled
 * pass: 採点後に true(合格点超過) / false(下回り) / null(未採点)
 *
 * 関連: Enrollment(受講登録) / MockExam(模試マスタ)
 */
class MockExamSession extends Model
{
    /** @use HasFactory<\Database\Factories\MockExamSessionFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'enrollment_id',
        'mock_exam_id',
        'status',
        'pass',
        'started_at',
        'submitted_at',
        'graded_at',
    ];

    protected $casts = [
        'pass' => 'boolean',
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'graded_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * @return BelongsTo<MockExam, $this>
     */
    public function mockExam(): BelongsTo
    {
        return $this->belongsTo(MockExam::class);
    }
}
