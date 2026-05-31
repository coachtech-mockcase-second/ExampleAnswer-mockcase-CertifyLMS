<?php

declare(strict_types=1);

namespace App\Http\Requests\Plan;

use App\Enums\PlanStatus;
use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Plan::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(PlanStatus::class)],
            'keyword' => ['nullable', 'string', 'max:100'],
        ];
    }
}
