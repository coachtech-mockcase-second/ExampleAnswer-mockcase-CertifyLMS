<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContentStatus;
use Database\Factories\ChapterFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 教材階層の中段 Chapter を表す Model。Part 配下を更に章立てし、Section を束ねる。
 *
 * 関連: Part(親) / Section(子)
 * scope: published(自身 + 親 Part が Published) / ordered(order ASC)
 */
class Chapter extends Model
{
    /** @use HasFactory<ChapterFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'part_id',
        'title',
        'description',
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
     * @return BelongsTo<Part, $this>
     */
    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    /**
     * @return HasMany<Section, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Published->value)
            ->whereHas('part', fn (Builder $q) => $q->where('status', ContentStatus::Published->value));
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order');
    }
}
