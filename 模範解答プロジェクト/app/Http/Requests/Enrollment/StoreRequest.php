<?php

declare(strict_types=1);

namespace App\Http\Requests\Enrollment;

use App\Enums\CertificationStatus;
use App\Models\Enrollment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 受講生による自己受講登録リクエスト。受講生は公開済資格の詳細画面から登録ボタンで POST する。
 * exam_date は任意(null 可)、指定時は当日より後の日付のみ許容する。
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Enrollment::class) ?? false;
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
                    ->where('status', CertificationStatus::Published->value)
                    ->whereNull('deleted_at'),
            ],
            'exam_date' => ['nullable', 'date', 'after:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'certification_id' => '資格',
            'exam_date' => '目標受験日',
        ];
    }
}
