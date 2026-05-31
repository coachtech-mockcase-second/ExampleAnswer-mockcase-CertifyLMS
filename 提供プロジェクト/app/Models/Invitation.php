<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvitationStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 招待 URL 経由のオンボーディング前の状態を表す Model。
 *
 * 1 招待 = 1 メール送信。同一 User に対する再招待は旧 pending Invitation を revoke した上で
 * 新規 Invitation を発行する設計(同 user_id に対する複数 Invitation が時系列に並ぶ)。
 *
 * 関連: User(対象、user_id) / invitedBy(発行 admin、invited_by_user_id)
 * 主要 Action: `IssueInvitationAction` / `RevokeInvitationAction` / `ExpireInvitationsAction` / `OnboardAction`
 */
class Invitation extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'email',
        'role',
        'invited_by_user_id',
        'expires_at',
        'accepted_at',
        'revoked_at',
        'status',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'revoked_at' => 'datetime',
        'role' => UserRole::class,
        'status' => InvitationStatus::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id')->withTrashed();
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', InvitationStatus::Pending);
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', InvitationStatus::Pending)
            ->where('expires_at', '<=', now());
    }

    public function isUsable(): bool
    {
        return $this->status === InvitationStatus::Pending
            && $this->expires_at instanceof \DateTimeInterface
            && $this->expires_at->getTimestamp() > now()->getTimestamp();
    }
}
