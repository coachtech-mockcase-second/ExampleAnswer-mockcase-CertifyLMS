<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContentStatus;
use Database\Factories\PartFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 教材階層の最上位 Part を表す Model。資格マスタ(Certification)直下の章立てを担う。
 *
 * 関連: Certification(親) / Chapter(子)
 * scope: published(自身が Published 状態) / ordered(order ASC)
 */
class Part extends Model
{
    /** @use HasFactory<PartFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'certification_id',
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
     * @return BelongsTo<Certification, $this>
     */
    public function certification(): BelongsTo
    {
        return $this->belongsTo(Certification::class);
    }

    /**
     * @return HasMany<Chapter, $this>
     */
    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Published->value);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order');
    }
}
