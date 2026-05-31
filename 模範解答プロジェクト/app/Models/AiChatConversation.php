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

/**
 * 受講生 × AI の相談会話を表す Model。
 *
 * - user_id 必須 / enrollment_id・section_id は nullable
 *   - 全般相談: section_id・enrollment_id とも null (資格コンテキストは user.default_enrollment_id から解決)
 *   - 教材相談: section_id あり + 所属資格の enrollment_id を自動補完
 * - 物理削除: 受講生の削除操作で会話を物理削除。配下メッセージも外部キーの cascade で連動削除される
 * - last_message_at: 一覧の降順並べ替え + ウィジェットの既存会話再開判定用
 *
 * 関連: User(オーナー) / Enrollment(資格紐付け、nullable) / Section(教材紐付け、nullable) / AiChatMessage(発言)
 * 主要 Action: \App\UseCases\AiChat\StoreAction (作成、Section 紐付け時の Enrollment 自動補完)
 */
class AiChatConversation extends Model
{
    /** @use HasFactory<AiChatConversationFactory> */
    use HasFactory, HasUlids;

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
