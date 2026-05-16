<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserStatusLog extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'changed_by_user_id',
        'status',
        'changed_at',
        'changed_reason',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
        'status' => UserStatus::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id')->withTrashed();
    }
}
