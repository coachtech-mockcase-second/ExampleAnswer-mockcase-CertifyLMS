<?php

declare(strict_types=1);

namespace App\Http\Requests\Availability;

use App\Models\CoachAvailability;
use Illuminate\Foundation\Http\FormRequest;

/**
 * コーチが面談可能時間枠を新規作成するリクエスト。
 *
 * `day_of_week` は Carbon の `dayOfWeek` と整合する 0(日)〜6(土) の int で受け取る
 * (`coach_availabilities.day_of_week` カラムと一致)。`start_time` / `end_time` は
 * `HH:MM` の 24h 表記。`is_active` はチェックボックス未送信を false 扱いにする。
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('create', CoachAvailability::class);
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
