<?php

declare(strict_types=1);

namespace App\Http\Requests\CertificationCatalog;

use App\Enums\CertificationDifficulty;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 受講生向け資格カタログの絞り込みリクエスト。カテゴリ / 難易度 / 表示タブの 3 種フィルタ。
 */
class IndexRequest extends FormRequest
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
            'category_id' => ['nullable', 'ulid', 'exists:certification_categories,id'],
            'difficulty' => ['nullable', Rule::enum(CertificationDifficulty::class)],
        ];
    }
}
