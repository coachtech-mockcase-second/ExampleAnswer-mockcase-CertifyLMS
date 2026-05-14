<?php

namespace App\Models;

use App\Enums\InvitationStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

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
