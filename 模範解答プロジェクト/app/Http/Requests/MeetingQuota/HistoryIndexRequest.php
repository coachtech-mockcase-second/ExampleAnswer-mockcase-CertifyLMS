<?php

declare(strict_types=1);

namespace App\Http\Requests\MeetingQuota;

use App\Enums\MeetingQuotaTransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HistoryIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('view-meeting-quota-history', $user);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['nullable', Rule::enum(MeetingQuotaTransactionType::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
