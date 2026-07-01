<?php

declare(strict_types=1);

namespace App\Http\Requests\QaReply;

use App\Models\QaReply;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 回答投稿リクエスト。QaReplyPolicy::create に対象スレッドを渡して認可を判定する。
 *
 * admin は create で常に false (admin は閲覧 + 削除のみ)。coach は担当資格のみ、student は公開資格のみ。
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $thread = $this->route('thread');

        return $thread !== null && ($this->user()?->can('create', [QaReply::class, $thread]) ?? false);
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
            'body.required' => '回答本文を入力してください。',
            'body.not_regex' => '回答本文を入力してください。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'body' => '回答本文',
        ];
    }
}
