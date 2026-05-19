<?php

declare(strict_types=1);

namespace App\Http\Requests\MockExamQuestion;

use App\Models\MockExam;
use App\Models\MockExamQuestion;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 模試問題の並び順一括更新リクエスト。
 */
class ReorderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $mockExam = $this->route('mockExam');

        return $mockExam instanceof MockExam
            && ($this->user()?->can('reorder', [MockExamQuestion::class, $mockExam]) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
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
            'items' => '並び順情報',
            'items.*.id' => '問題 ID',
            'items.*.order' => '並び順',
        ];
    }
}
