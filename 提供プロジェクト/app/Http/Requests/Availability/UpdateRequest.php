<?php

declare(strict_types=1);

namespace App\Http\Requests\Availability;

use App\Models\CoachAvailability;
use Illuminate\Foundation\Http\FormRequest;

/**
 * コーチ本人の面談可能時間枠を更新するリクエスト。
 *
 * 本人所有確認は `CoachAvailabilityPolicy::update($auth, $availability)` で実施。
 * バリデーション規則は `StoreRequest` と同等。
 */
class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $availability = $this->route('availability');

        return $user !== null
            && $availability instanceof CoachAvailability
            && $user->can('update', $availability);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'day_of_week' => '曜日',
            'start_time' => '開始時刻',
            'end_time' => '終了時刻',
            'is_active' => '有効',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'day_of_week.between' => '曜日は日曜(0)から土曜(6)で指定してください。',
            'start_time.date_format' => '開始時刻は HH:MM 形式で指定してください。',
            'end_time.date_format' => '終了時刻は HH:MM 形式で指定してください。',
            'end_time.after' => '終了時刻は開始時刻より後を指定してください。',
        ];
    }
}
