<?php

declare(strict_types=1);

namespace App\Http\Requests\Enrollment;

use Illuminate\Foundation\Http\FormRequest;

/**
 * admin が Enrollment を手動で学習中止(failed)にするリクエスト。理由(reason)を任意で添付。
 */
class FailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('fail', $this->route('enrollment')) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reason' => '中止理由',
        ];
    }
}
