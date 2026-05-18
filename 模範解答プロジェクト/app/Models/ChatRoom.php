<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ChatRoomFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 1 Enrollment = 1 ChatRoom のグループ chat ルーム。
 *
 * 受講登録と同一トランザクション内で eager 生成され、参加者は ChatMember(受講生 + 担当コーチ集合)で管理する。
 * 状態遷移ロジックは持たない(status カラムなし)。
 *
 * 関連: Enrollment / ChatMember / ChatMessage
 * scope: forUser(User) / orderByLastMessage()
 */
class ChatRoom extends Model
{
    /** @use HasFactory<ChatRoomFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'enrollment_id',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * @return HasMany<ChatMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(ChatMember::class);
    }

    /**
     * @return HasMany<ChatMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    /**
     * 最終メッセージ 1 件のみ(一覧プレビュー用 Eager Load 用途)。
     *
     * @return HasOne<ChatMessage, $this>
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class)->latestOfMany();
    }

    /**
     * 指定 User が ChatMember として参加しているルームに絞り込む。
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->whereHas('members', function (Builder $q) use ($user): void {
            $q->where('user_id', $user->id);
        });
    }

    public function scopeOrderByLastMessage(Builder $query): Builder
    {
        return $query->orderByDesc('last_message_at')->orderByDesc('created_at');
    }
}
