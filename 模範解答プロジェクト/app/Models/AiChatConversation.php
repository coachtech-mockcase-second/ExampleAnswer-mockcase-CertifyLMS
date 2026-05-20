<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AiChatConversationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 受講生 × AI の相談会話を表す Model。
 *
 * - user_id 必須 / enrollment_id・section_id は nullable (全般相談 / 資格相談 / 教材相談の 3 モード)
 * - SoftDelete: 受講生の削除操作で論理削除。メッセージは物理的には残る (history の整合性のため)
 * - last_message_at: 一覧の降順並べ替え + ウィジェットの既存会話再開判定用
 *
 * 関連: User(オーナー) / Enrollment(資格紐付け、nullable) / Section(教材紐付け、nullable) / AiChatMessage(発言)
 * 主要 Action: \App\UseCases\AiChat\StoreAction (作成、Section 紐付け時の Enrollment 自動補完)
 */
class AiChatConversation extends Model
{
    /** @use HasFactory<AiChatConversationFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'enrollment_id',
        'section_id',
        'title',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * @return HasMany<AiChatMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(AiChatMessage::class);
    }

    /**
     * 最新メッセージ 1 件のみ。一覧画面で「直近の話題」を表示するため Eager Load 用途。
     *
     * @return HasOne<AiChatMessage, $this>
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(AiChatMessage::class)->latestOfMany();
    }
}
