<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\QuestionCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 出題分野マスタを表す Model。演習問題(SectionQuestion) と模試問題(MockExamQuestion) の共有マスタ。
 *
 * 関連: Certification(親) / SectionQuestion(category_id 参照)
 * 削除時は演習問題と模試問題の両方からの参照を確認し、参照ありなら削除を拒否する(共有マスタ規約)。
 * scope: ordered(sort_order ASC → created_at DESC)
 */
class QuestionCategory extends Model
{
    /** @use HasFactory<QuestionCategoryFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'certification_id',
        'name',
        'slug',
        'sort_order',
        'description',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /**
     * @return BelongsTo<Certification, $this>
     */
    public function certification(): BelongsTo
    {
        return $this->belongsTo(Certification::class);
    }

    /**
     * @return HasMany<SectionQuestion, $this>
     */
    public function sectionQuestions(): HasMany
    {
        return $this->hasMany(SectionQuestion::class, 'category_id');
    }

    /**
     * @return HasMany<MockExamQuestion, $this>
     */
    public function mockExamQuestions(): HasMany
    {
        return $this->hasMany(MockExamQuestion::class, 'category_id');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderByDesc('created_at');
    }
}
