<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Enums\PlanStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 管理者操作のプラン延長 (`POST /admin/users/{user}/extend-course`) の入力検証。
 *
 * plan_id は published 状態の Plan のみ許可する(下書き / アーカイブされた Plan の延長を禁止)。
 */
class ExtendCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('extendCourse', $this->route('user'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'plan_id' => [
                'required',
                'ulid',
                Rule::exists('plans', 'id')->where('status', PlanStatus::Published->value),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'plan_id' => '受講プラン',
        ];
    }
}
