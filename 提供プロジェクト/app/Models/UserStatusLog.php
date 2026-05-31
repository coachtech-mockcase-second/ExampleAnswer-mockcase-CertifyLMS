<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * User のステータス遷移を記録する append-only 監査ログ。
 *
 * `from_status` → `to_status` で遷移内容を表現する(イベント分類用の追加カラムは持たず、
 * 遷移そのものを from / to で読み取る設計)。書込責務は `UserStatusChangeService::record()` に集約し、
 * 本 Model は読取と関連解決を担う。
 *
 * 関連: User(対象 user_id) / changedBy(操作 admin、システム自動の場合 NULL)
 */
class UserStatusLog extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'from_status',
        'to_status',
        'changed_by_user_id',
        'changed_reason',
        'changed_at',
    ];

    protected $casts = [
        'from_status' => UserStatus::class,
        'to_status' => UserStatus::class,
        'changed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id')->withTrashed();
    }

    /**
     * @param Builder<UserStatusLog> $query
     *
     * @return Builder<UserStatusLog>
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * @param Builder<UserStatusLog> $query
     *
     * @return Builder<UserStatusLog>
     */
    public function scopeRecent(Builder $query): Builder
    {
        return $query->orderByDesc('changed_at');
    }
}
