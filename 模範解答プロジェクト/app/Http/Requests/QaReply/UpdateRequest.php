<?php

declare(strict_types=1);

namespace App\Http\Requests\QaReply;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 回答編集リクエスト。投稿者本人のみ許可 (Policy::update で判定)。
 *
 * body のみ更新可。qa_thread_id / user_id の変更はシグネチャに含まない。
 */
class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $reply = $this->route('reply');

        return $reply !== null && ($this->user()?->can('update', $reply) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000', 'not_regex:/\A[\s\x{3000}]*\z/u'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.not_regex' => '回答本文を入力してください。',
        ];
    }
}
