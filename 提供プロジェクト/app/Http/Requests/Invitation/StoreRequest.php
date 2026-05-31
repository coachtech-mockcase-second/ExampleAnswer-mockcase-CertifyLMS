<?php

declare(strict_types=1);

namespace App\Http\Requests\Invitation;

use App\Enums\PlanStatus;
use App\Models\Invitation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 管理者の招待発行リクエスト。
 *
 * - 受講生(role=student)招待では Plan を必須とする(受講期間 + 初期面談回数の起点)
 * - コーチ(role=coach)招待では Plan を受け付けない(面談を提供する側で受講期間という概念を持たない)
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Invitation::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'in:coach,student'],
            'plan_id' => [
                'required_if:role,student',
                'prohibited_if:role,coach',
                'nullable',
                'ulid',
                // 受講生に割り当てられる Plan は公開中のみ(draft / archived は弾く)
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
            'email' => 'メールアドレス',
            'role' => 'ロール',
            'plan_id' => '受講プラン',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plan_id.required_if' => '受講生招待では受講プランを選択してください。',
            'plan_id.prohibited_if' => 'コーチ招待では受講プランは指定できません。',
        ];
    }
}
