<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ChatMessageFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ChatRoom に投稿された個別メッセージ。テキスト本文のみ(添付ファイル非対応)。
 *
 * 編集 / 削除エンドポイントは提供しないため、ユーザー操作で消えることはない。
 * INSERT 時に親 ChatRoom の last_message_at を denormalize 更新する。
 *
 * 関連: ChatRoom / User(sender)
 */
class ChatMessage extends Model
{
    /** @use HasFactory<ChatMessageFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'chat_room_id',
        'sender_user_id',
        'body',
    ];

    protected static function booted(): void
    {
        static::created(function (ChatMessage $message): void {
            ChatRoom::query()
                ->where('id', $message->chat_room_id)
                ->update(['last_message_at' => $message->created_at]);
        });
    }

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
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }
}
