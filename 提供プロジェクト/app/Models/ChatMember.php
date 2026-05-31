<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ChatMemberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ChatRoom 参加者の中間 Model。受講生 + 担当コーチ集合の参加状態と既読時刻を保持する。
 *
 * last_read_at は個人別に保持し、グループ chat として自然な「個人別既読」を実現する
 * (あるコーチが既読を付けても、他コーチの last_read_at には影響しない)。
 *
 * 関連: ChatRoom / User
 * scope: forRoom(ChatRoom) / forUser(User) / unread()
 */
class ChatMember extends Model
{
    /** @use HasFactory<ChatMemberFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'chat_room_id',
        'user_id',
        'last_read_at',
        'joined_at',
    ];

    protected $casts = [
        'last_read_at' => 'datetime',
        'joined_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<ChatRoom, $this>
     */
    public function chatRoom(): BelongsTo
    {
        return $this->belongsTo(ChatRoom::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForRoom(Builder $query, ChatRoom $room): Builder
    {
        return $query->where('chat_room_id', $room->id);
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * last_read_at が未設定 or 直近メッセージより前のメンバーに絞り込む(未読ありの候補)。
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereNull('last_read_at')
                ->orWhereColumn('last_read_at', '<', 'chat_rooms.last_message_at');
        });
    }
}
