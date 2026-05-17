<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CertificationCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 資格分類（カテゴリ）マスタを表す Model。受講生カタログのフィルタと admin の分類管理で利用される。
 *
 * 関連: Certification(子) / QuestionCategory(子)
 * scope: ordered(`sort_order` 昇順 + `created_at` 降順)
 */
class CertificationCategory extends Model
{
    /** @use HasFactory<CertificationCategoryFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'slug',
        'name',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /**
     * @return HasMany<Certification, $this>
     */
    public function certifications(): HasMany
    {
        return $this->hasMany(Certification::class, 'category_id');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderByDesc('created_at');
    }
}
