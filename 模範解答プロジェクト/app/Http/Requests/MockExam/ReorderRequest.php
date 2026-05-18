<?php

declare(strict_types=1);

namespace App\Http\Requests\MockExam;

use App\Models\MockExam;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 模試マスタの並び順一括更新リクエスト。同一資格内の MockExam を対象とする。
 */
class ReorderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', MockExam::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'certification_id' => ['required', 'ulid', 'exists:certifications,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'ulid'],
            'items.*.order' => ['required', 'integer', 'min:0', 'max:65535'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'certification_id' => '資格',
            'items' => '並び順情報',
            'items.*.id' => '模試 ID',
            'items.*.order' => '並び順',
        ];
    }
}
