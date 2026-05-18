<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MockExamFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 模試マスタを表す Model。
 *
 * 関連: Certification(親、資格マスタ) / MockExamQuestion(子、問題セット) / MockExamSession(受験セッション) /
 *      User(作成者・更新者)
 * scope: published(`is_published = true`) / forCertification(string)
 * 時間制限機能は持たない(受講生は時間制限なしで何度でも復習可能)。
 */
class MockExam extends Model
{
    /** @use HasFactory<MockExamFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'certification_id',
        'title',
        'description',
        'order',
        'passing_score',
        'is_published',
        'published_at',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'passing_score' => 'integer',
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
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /**
     * @return HasMany<MockExamQuestion, $this>
     */
    public function mockExamQuestions(): HasMany
    {
        return $this->hasMany(MockExamQuestion::class);
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

    public function scopeForCertification(Builder $query, string $certificationId): Builder
    {
        return $query->where('certification_id', $certificationId);
    }
}
