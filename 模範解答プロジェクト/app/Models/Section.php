<?php

namespace App\Models;

use App\Enums\ContentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Section extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'chapter_id',
        'title',
        'description',
        'body',
        'order',
        'status',
        'published_at',
    ];

    protected $casts = [
        'status' => ContentStatus::class,
        'order' => 'integer',
        'published_at' => 'datetime',
    ];

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(SectionImage::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Published->value)
            ->whereHas(
                'chapter',
                fn (Builder $q) => $q->where('status', ContentStatus::Published->value)
                    ->whereHas('part', fn (Builder $q2) => $q2->where('status', ContentStatus::Published->value)),
            );
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order');
    }

    public function scopeKeyword(Builder $query, ?string $keyword): Builder
    {
        if ($keyword === null || $keyword === '') {
            return $query;
        }

        $like = '%'.$keyword.'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('title', 'LIKE', $like)
                ->orWhere('body', 'LIKE', $like);
        });
    }
}
