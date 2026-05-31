<?php

declare(strict_types=1);

namespace App\Http\Requests\AiChatMessage;

use App\Models\AiChatConversation;
use Illuminate\Foundation\Http\FormRequest;

/**
 * AI 相談メッセージ送信 (同期版) のリクエスト。
 * 認可は親会話への view 権限で判定 (会話オーナーのみメッセージ送信可)。
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $conversation = $this->route('conversation');

        return $conversation instanceof AiChatConversation
            && ($this->user()?->can('view', $conversation) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'min:1', 'max:2000'],
        ];
    }
}
