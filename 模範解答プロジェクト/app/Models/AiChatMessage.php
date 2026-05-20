<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use Database\Factories\AiChatMessageFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AI 相談メッセージ (1 会話 N メッセージ) を表す Model。
 *
 * SoftDelete は持たない。親会話が SoftDelete されてもメッセージは残る (履歴整合性のため)。
 * status は assistant role のみ意味を持つ。user role は INSERT 直後に Completed 固定。
 *
 * 関連: AiChatConversation(親)
 */
class AiChatMessage extends Model
{
    /** @use HasFactory<AiChatMessageFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'ai_chat_conversation_id',
        'role',
        'content',
        'status',
        'model',
        'input_tokens',
        'output_tokens',
        'response_time_ms',
        'error_detail',
    ];

    protected $casts = [
        'role' => AiChatMessageRole::class,
        'status' => AiChatMessageStatus::class,
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'response_time_ms' => 'integer',
    ];

    /**
     * @return BelongsTo<AiChatConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiChatConversation::class, 'ai_chat_conversation_id');
    }
}
