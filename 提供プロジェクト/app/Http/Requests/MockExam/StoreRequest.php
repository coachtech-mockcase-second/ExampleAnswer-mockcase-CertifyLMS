<?php

declare(strict_types=1);

namespace App\Http\Requests\MockExam;

use App\Models\Certification;
use App\Models\MockExam;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 模試マスタの新規作成リクエスト。
 *
 * `passing_score` は 1..100 の整数(百分率)。`is_published` は受け付けず、必ず draft で INSERT する。
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $certificationId = $this->input('certification_id');
        if (! is_string($certificationId)) {
            return false;
        }

        $certification = Certification::find($certificationId);
        if ($certification === null) {
            return false;
        }

        return $this->user()?->can('create', [MockExam::class, $certification]) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'certification_id' => ['required', 'ulid', 'exists:certifications,id'],
            'title' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'order' => ['required', 'integer', 'min:0', 'max:65535'],
            'passing_score' => ['required', 'integer', 'between:1,100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'certification_id' => '資格',
            'title' => '模試名',
            'description' => '説明',
            'order' => '並び順',
            'passing_score' => '合格点(%)',
        ];
    }
}
