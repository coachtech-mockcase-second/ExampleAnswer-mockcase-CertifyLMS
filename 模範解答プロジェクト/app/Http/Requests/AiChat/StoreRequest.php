<?php

declare(strict_types=1);

namespace App\Http\Requests\AiChat;

use App\Models\AiChatConversation;
use Illuminate\Foundation\Http\FormRequest;

/**
 * AI 相談会話の新規作成リクエスト。
 *
 * - source=widget: フローティングウィジェット由来。section_id があれば既存会話再開も許容
 * - source=full-screen: フル画面の「新規相談」ボタン由来、常に新規作成
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', AiChatConversation::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'section_id' => ['nullable', 'ulid', 'exists:sections,id'],
            'message' => ['nullable', 'string', 'min:1', 'max:2000'],
            'source' => ['nullable', 'string', 'in:widget,full-screen'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'section_id.exists' => '指定したセクションが見つかりません。',
            'message.min' => 'メッセージを入力してください。',
            'message.max' => 'メッセージは2000文字以内で入力してください。',
            'source.in' => '相談の開始元が不正です。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'section_id' => 'セクション',
            'message' => 'メッセージ',
            'source' => '相談の開始元',
        ];
    }
}
