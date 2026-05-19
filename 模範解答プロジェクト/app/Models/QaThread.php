<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QaThreadStatus;
use Database\Factories\QaThreadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 受講生 / コーチが公開で技術質問を投稿する Q&A 掲示板のスレッドを表す Model。
 *
 * 解決状態は status (open / resolved) と resolved_at の同時更新で表現する。
 * Action 側で両カラムを同 UPDATE するため、コード上は status を真として扱い resolved_at は表示用と捉える。
 *
 * 関連: Certification / User(投稿者) / QaReply(回答)
 */
class QaThread extends Model
{
    /** @use HasFactory<QaThreadFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'certification_id',
        'user_id',
        'title',
        'body',
        'status',
        'resolved_at',
    ];

    protected $casts = [
        'status' => QaThreadStatus::class,
        'resolved_at' => 'datetime',
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<QaReply, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(QaReply::class);
    }

    /**
     * @param Builder<QaThread> $query
     *
     * @return Builder<QaThread>
     */
    public function scopeResolved(Builder $query): Builder
    {
        return $query->where('status', QaThreadStatus::Resolved);
    }

    /**
     * @param Builder<QaThread> $query
     *
     * @return Builder<QaThread>
     */
    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->where('status', QaThreadStatus::Open);
    }

    /**
     * @param Builder<QaThread> $query
     *
     * @return Builder<QaThread>
     */
    public function scopeForCertification(Builder $query, string $certificationId): Builder
    {
        return $query->where('certification_id', $certificationId);
    }

    public function isResolved(): bool
    {
        return $this->status === QaThreadStatus::Resolved;
    }
}
