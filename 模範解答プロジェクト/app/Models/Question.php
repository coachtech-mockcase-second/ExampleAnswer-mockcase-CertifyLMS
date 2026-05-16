<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContentStatus;
use App\Enums\QuestionDifficulty;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Question extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'certification_id',
        'section_id',
        'category_id',
        'body',
        'explanation',
        'difficulty',
        'order',
        'status',
        'published_at',
    ];

    protected $casts = [
        'status' => ContentStatus::class,
        'difficulty' => QuestionDifficulty::class,
        'order' => 'integer',
        'published_at' => 'datetime',
    ];

    public function certification(): BelongsTo
    {
        return $this->belongsTo(Certification::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(QuestionCategory::class, 'category_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Published->value);
    }

    public function scopeBySection(Builder $query, ?string $sectionId): Builder
    {
        if ($sectionId === null || $sectionId === '') {
            return $query;
        }

        return $query->where('section_id', $sectionId);
    }

    public function scopeStandalone(Builder $query): Builder
    {
        return $query->whereNull('section_id');
    }

    public function scopeByCategory(Builder $query, ?string $categoryId): Builder
    {
        if ($categoryId === null || $categoryId === '') {
            return $query;
        }

        return $query->where('category_id', $categoryId);
    }

    public function scopeDifficulty(Builder $query, ?QuestionDifficulty $difficulty): Builder
    {
        if ($difficulty === null) {
            return $query;
        }

        return $query->where('difficulty', $difficulty->value);
    }
}
