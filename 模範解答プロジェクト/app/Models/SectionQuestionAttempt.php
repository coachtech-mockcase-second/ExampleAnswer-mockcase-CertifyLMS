<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SectionQuestionAttemptFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 受講生 × SectionQuestion ごとの累計サマリ Model。
 * (user_id, section_question_id) が UNIQUE。解答送信のたびに UPSERT される。
 *
 * 関連: User(受講生) / SectionQuestion
 * scope: forUser / forEnrollment / forSection / forCategory / lastIs
 */
class SectionQuestionAttempt extends Model
{
    /** @use HasFactory<SectionQuestionAttemptFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'section_question_id',
        'attempt_count',
        'correct_count',
        'last_is_correct',
        'last_answered_at',
    ];

    protected $casts = [
        'attempt_count' => 'integer',
        'correct_count' => 'integer',
        'last_is_correct' => 'boolean',
        'last_answered_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<SectionQuestion, $this>
     */
    public function sectionQuestion(): BelongsTo
    {
        return $this->belongsTo(SectionQuestion::class);
    }

    /**
     * 正答率(0〜1)。attempt_count が 0 の時は null を返す。
     */
    public function accuracy(): ?float
    {
        if ($this->attempt_count === 0) {
            return null;
        }

        return $this->correct_count / $this->attempt_count;
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeForEnrollment(Builder $query, Enrollment $enrollment): Builder
    {
        return $query->where('user_id', $enrollment->user_id)
            ->whereHas(
                'sectionQuestion.section.chapter.part',
                fn (Builder $q) => $q->where('certification_id', $enrollment->certification_id),
            );
    }

    public function scopeForSection(Builder $query, string $sectionId): Builder
    {
        return $query->whereHas('sectionQuestion', fn (Builder $q) => $q->where('section_id', $sectionId));
    }

    public function scopeForCategory(Builder $query, string $categoryId): Builder
    {
        return $query->whereHas('sectionQuestion', fn (Builder $q) => $q->where('category_id', $categoryId));
    }

    public function scopeLastIs(Builder $query, bool $isCorrect): Builder
    {
        return $query->where('last_is_correct', $isCorrect);
    }
}
