<?php

declare(strict_types=1);

namespace App\Http\Requests\EnrollmentGoal;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 受講生が個人目標を編集するリクエスト。title / description / target_date のみ更新可。
 * achieved_at の更新は MarkAchievedAction / UnmarkAchievedAction の専用エンドポイント経由のみ。
 */
class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('goal')) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'target_date' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => '目標',
            'description' => '詳細',
            'target_date' => '目標期日',
        ];
    }
}
