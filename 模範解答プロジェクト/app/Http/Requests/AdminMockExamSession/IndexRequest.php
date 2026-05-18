<?php

declare(strict_types=1);

namespace App\Http\Requests\AdminMockExamSession;

use App\Enums\MockExamSessionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * admin / coach 用の模試受験セッション一覧フィルタ。
 */
class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAdmin', \App\Models\MockExamSession::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'certification_id' => ['nullable', 'ulid', 'exists:certifications,id'],
            'user_id' => ['nullable', 'ulid', 'exists:users,id'],
            'status' => ['nullable', Rule::enum(MockExamSessionStatus::class)],
            'pass' => ['nullable', 'in:0,1,true,false'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'certification_id' => '資格',
            'user_id' => '受講生',
            'status' => '状態',
            'pass' => '合否',
        ];
    }
}
