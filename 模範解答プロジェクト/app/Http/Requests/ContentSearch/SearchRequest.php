<?php

declare(strict_types=1);

namespace App\Http\Requests\ContentSearch;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 受講生向け教材検索リクエスト。certification_id 必須 + keyword 任意。
 * 認可は SearchAction 側で「自分の登録資格 + Published 教材のみ」を強制するため、本リクエストでは認証済みであれば許可する。
 */
class SearchRequest extends FormRequest
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
            'certification_id' => ['required', 'ulid'],
            'keyword' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'certification_id' => '資格 ID',
            'keyword' => 'キーワード',
        ];
    }
}
