<?php

declare(strict_types=1);

namespace App\Http\Requests\MeetingQuotaPlan;

use App\Enums\MeetingQuotaPlanStatus;
use App\Models\MeetingQuotaPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', MeetingQuotaPlan::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(MeetingQuotaPlanStatus::class)],
            'keyword' => ['nullable', 'string', 'max:100'],
        ];
    }
}
