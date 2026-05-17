<?php

declare(strict_types=1);

namespace App\Http\Requests\MeetingQuota;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('purchase-meeting-quota') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'meeting_quota_plan_id' => [
                'required',
                'ulid',
                Rule::exists('meeting_quota_plans', 'id')
                    ->where('status', 'published')
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'meeting_quota_plan_id' => '追加面談プラン',
        ];
    }
}
