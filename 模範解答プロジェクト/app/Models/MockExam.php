<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 模試マスタ。受講登録の修了判定(CompletionEligibilityService)が「公開模試件数」基準に使う。
 * 設問・採点まわりは mock-exam Feature 側で拡張される最小 Model。
 *
 * 関連: Certification(資格マスタ) / MockExamSession(受験セッション)
 */
class MockExam extends Model
{
    /** @use HasFactory<\Database\Factories\MockExamFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'certification_id',
        'title',
        'passing_score',
        'is_published',
        'published_at',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'passing_score' => 'integer',
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
     * @return HasMany<MockExamSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(MockExamSession::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }
}
