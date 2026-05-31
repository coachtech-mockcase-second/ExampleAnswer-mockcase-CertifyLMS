<?php

declare(strict_types=1);

namespace App\Http\Requests\QaThread;

use App\Enums\CertificationStatus;
use App\Models\QaThread;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 質問スレッド投稿リクエスト。student のみ発行可能 (Policy::create で判定)。
 *
 * 全角空白のみ / 通常空白のみのタイトル / 本文を `not_regex` で拒否する。
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', QaThread::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'certification_id' => [
                'required',
                'ulid',
                Rule::exists('certifications', 'id')
                    ->where('status', CertificationStatus::Published->value),
            ],
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
            'certification_id.exists' => '選択した資格は現在投稿を受け付けていません。公開中の資格を選んでください。',
            'title.not_regex' => 'タイトルを入力してください。',
            'body.not_regex' => '本文を入力してください。',
        ];
    }
}
