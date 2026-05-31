<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AnswerSource;
use Database\Factories\SectionQuestionAnswerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SectionQuestion を 1 問解答した時の個別履歴 Model。append-only。
 *
 * 関連: User(受講生) / SectionQuestion / SectionQuestionOption(選択した選択肢、nullable)
 * scope: forUser / forEnrollment / forSection / forCategory / bySource / correct / incorrect
 * selected_option_id は SectionQuestionOption の物理削除に追従して NULL になり、
 * 履歴の可読性は selected_option_body スナップショットで担保する。
 */
class SectionQuestionAnswer extends Model
{
    /** @use HasFactory<SectionQuestionAnswerFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'section_question_id',
        'selected_option_id',
        'selected_option_body',
        'is_correct',
        'source',
        'answered_at',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'answered_at' => 'datetime',
        'source' => AnswerSource::class,
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
     * @return BelongsTo<SectionQuestionOption, $this>
     */
    public function selectedOption(): BelongsTo
    {
        return $this->belongsTo(SectionQuestionOption::class, 'selected_option_id');
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

    public function scopeBySource(Builder $query, AnswerSource $source): Builder
    {
        return $query->where('source', $source->value);
    }

    public function scopeCorrect(Builder $query): Builder
    {
        return $query->where('is_correct', true);
    }

    public function scopeIncorrect(Builder $query): Builder
    {
        return $query->where('is_correct', false);
    }
}
