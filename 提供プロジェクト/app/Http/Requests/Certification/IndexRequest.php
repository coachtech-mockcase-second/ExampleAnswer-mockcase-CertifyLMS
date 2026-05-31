<?php

declare(strict_types=1);

namespace App\Http\Requests\Certification;

use App\Enums\CertificationDifficulty;
use App\Enums\CertificationStatus;
use App\Models\Certification;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * admin 用資格マスタ一覧の絞り込みリクエスト。keyword / status / category / difficulty の 4 種フィルタ。
 */
class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Certification::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(CertificationStatus::class)],
            'category_id' => ['nullable', 'ulid', 'exists:certification_categories,id'],
            'difficulty' => ['nullable', Rule::enum(CertificationDifficulty::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
