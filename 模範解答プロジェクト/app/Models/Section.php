<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContentStatus;
use Database\Factories\SectionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 教材階層の最小単位 Section を表す Model。Markdown 本文と紐づき演習問題・教材内画像を保持する。
 *
 * 関連: Chapter(親) / SectionQuestion(演習問題) / SectionImage(教材内画像)
 * scope: published(Chapter / Part も連鎖して Published 状態の場合のみ) / ordered / keyword(?string)(title / body 部分一致)
 */
class Section extends Model
{
    /** @use HasFactory<SectionFactory> */
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

    /**
     * @return BelongsTo<Chapter, $this>
     */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    /**
     * @return HasMany<SectionQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(SectionQuestion::class);
    }

    /**
     * @return HasMany<SectionImage, $this>
     */
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
