<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContentStatus;
use Database\Factories\SectionQuestionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Section に紐づく演習問題を表す Model。
 *
 * 関連: Section(親、必須) / QuestionCategory(出題分野マスタ) / SectionQuestionOption(選択肢) /
 *      SectionQuestionAnswer(個別解答ログ) / SectionQuestionAttempt(受講生別累計サマリ)
 * scope: published / ofSection(string) / byCategory(?string) / ordered / visibleForStudent
 * 資格(Certification)への参照は section から chapter → part → certification と辿る(直接の certification_id カラムは持たない)。
 */
class SectionQuestion extends Model
{
    /** @use HasFactory<SectionQuestionFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'section_id',
        'category_id',
        'body',
        'explanation',
        'order',
        'status',
        'published_at',
    ];

    protected $casts = [
        'status' => ContentStatus::class,
        'order' => 'integer',
        'published_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * @return BelongsTo<QuestionCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(QuestionCategory::class, 'category_id');
    }

    /**
     * @return HasMany<SectionQuestionOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(SectionQuestionOption::class);
    }

    /**
     * @return HasMany<SectionQuestionAnswer, $this>
     */
    public function sectionQuestionAnswers(): HasMany
    {
        return $this->hasMany(SectionQuestionAnswer::class);
    }

    /**
     * @return HasMany<SectionQuestionAttempt, $this>
     */
    public function sectionQuestionAttempts(): HasMany
    {
        return $this->hasMany(SectionQuestionAttempt::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Published->value);
    }

    /**
     * 受講生に出題可能な SectionQuestion を絞り込む。
     * 自身が Published かつ親 Section / Chapter / Part も Published で、いずれも SoftDelete されていない場合のみ。
     */
    public function scopeVisibleForStudent(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Published->value)
            ->whereHas('section', fn (Builder $q) => $q->where('status', ContentStatus::Published->value)
                ->whereHas('chapter', fn (Builder $q2) => $q2->where('status', ContentStatus::Published->value)
                    ->whereHas('part', fn (Builder $q3) => $q3->where('status', ContentStatus::Published->value))));
    }

    public function scopeOfSection(Builder $query, string $sectionId): Builder
    {
        return $query->where('section_id', $sectionId);
    }

    public function scopeByCategory(Builder $query, ?string $categoryId): Builder
    {
        if ($categoryId === null || $categoryId === '') {
            return $query;
        }

        return $query->where('category_id', $categoryId);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order');
    }
}
