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

    /**
     * コーチが参加しているルームのうち、検索条件 (certification_id / keyword) と
     * 「未読あり」フィルタを適用する scope。`coach.chat.index` / `chat.show` の
     * rooms-pane 両方で利用する。
     *
     * @param array{filter?: string, certification_id?: ?string, keyword?: ?string} $filters
     */
    public function scopeFilterForCoach(Builder $query, User $coach, array $filters): Builder
    {
        if (! empty($filters['certification_id'])) {
            $certId = $filters['certification_id'];
            $query->whereHas('enrollment', fn (Builder $q) => $q->where('certification_id', $certId));
        }

        if (! empty($filters['keyword'])) {
            $keyword = $filters['keyword'];
            $query->whereHas('enrollment.user', function (Builder $q) use ($keyword): void {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%");
            });
        }

        if (($filters['filter'] ?? null) === 'unread') {
            $query->whereExists(function ($q) use ($coach): void {
                $q->select(\DB::raw(1))
                    ->from('chat_messages')
                    ->whereColumn('chat_messages.chat_room_id', 'chat_rooms.id')
                    ->where('chat_messages.sender_user_id', '!=', $coach->id)
                    ->whereNull('chat_messages.deleted_at')
                    ->whereRaw(
                        'chat_messages.created_at > COALESCE((SELECT last_read_at FROM chat_members WHERE chat_members.chat_room_id = chat_rooms.id AND chat_members.user_id = ? AND chat_members.deleted_at IS NULL LIMIT 1), "1970-01-01")',
                        [$coach->id]
                    );
            });
        }

        return $query;
    }

    /**
     * 管理者向け chat 監査の検索条件 (certification_id / keyword) を適用する scope。
     * `admin.chat-rooms.index` / `admin.chat-rooms.show` の rooms-pane 両方で利用する。
     *
     * @param array{certification_id?: ?string, keyword?: ?string} $filters
     */
    public function scopeFilterForAdmin(Builder $query, array $filters): Builder
    {
        if (! empty($filters['certification_id'])) {
            $certId = $filters['certification_id'];
            $query->whereHas('enrollment', fn (Builder $q) => $q->where('certification_id', $certId));
        }

        if (! empty($filters['keyword'])) {
            $keyword = $filters['keyword'];
            $query->whereHas('enrollment.user', function (Builder $q) use ($keyword): void {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%");
            });
        }

        return $query;
    }
}
