<?php

declare(strict_types=1);

namespace App\Http\Requests\Meeting;

use App\Http\Controllers\MeetingController;
use App\Models\Meeting;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 担当コーチによる面談メモ作成・更新リクエスト。
 *
 * 担当コーチ本人かつ reserved/completed 状態の Meeting に限り受け付ける(MeetingPolicy::upsertMemo)。
 *
 * @see MeetingController::upsertMemo()
 */
class UpsertMemoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $meeting = $this->route('meeting');

        return $meeting instanceof Meeting
            && ($this->user()?->can('upsertMemo', $meeting) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'body' => '面談メモ',
        ];
    }
}
