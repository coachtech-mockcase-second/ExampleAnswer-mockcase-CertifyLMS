<?php

declare(strict_types=1);

namespace App\Http\Requests\QaThread;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 質問スレッド編集リクエスト。投稿者本人のみ許可 (Policy::update で判定)。
 *
 * title / body のみ更新可。certification_id / user_id / status / resolved_at の変更はシグネチャに含まない。
 */
class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $thread = $this->route('thread');

        return $thread !== null && ($this->user()?->can('update', $thread) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200', 'not_regex:/\A[\s\x{3000}]*\z/u'],
            'body' => ['required', 'string', 'max:5000', 'not_regex:/\A[\s\x{3000}]*\z/u'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'タイトルを入力してください。',
            'title.not_regex' => 'タイトルを入力してください。',
            'body.required' => '本文を入力してください。',
            'body.not_regex' => '本文を入力してください。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'タイトル',
            'body' => '本文',
        ];
    }
}
