<?php

declare(strict_types=1);

namespace App\Http\Requests\Avatar;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 本人のアバター画像をアップロードするリクエスト。
 *
 * MIME / サイズはサーバ側で必ず検証する(クライアント側 JS と二重化、`Avatar/StoreAction` 前提)。
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'avatar' => ['required', 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'avatar' => 'アバター画像',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'avatar.required' => 'アバター画像を選択してください。',
            'avatar.image' => 'アバター画像は画像ファイル(PNG / JPEG / WebP)で指定してください。',
            'avatar.mimes' => 'アバター画像は PNG / JPEG / WebP のいずれかで指定してください。',
            'avatar.max' => 'アバター画像は 2MB 以下で指定してください。',
        ];
    }
}
