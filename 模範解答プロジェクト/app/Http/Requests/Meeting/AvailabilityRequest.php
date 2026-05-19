<?php

declare(strict_types=1);

namespace App\Http\Requests\Meeting;

use App\Http\Controllers\MeetingController;
use App\Models\Enrollment;
use App\Models\Meeting;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 受講生の予約画面が呼ぶ空き枠取得 JSON エンドポイントのリクエスト。
 *
 * Enrollment は URL Route Model Binding で受け取り、認証ユーザー本人のものか確認する。
 * date は `YYYY-MM-DD` 形式の今日以降のみ。
 *
 * @see MeetingController::fetchAvailability()
 */
class AvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $enrollment = $this->route('enrollment');

        if ($user === null || ! $user->can('create', Meeting::class)) {
            return false;
        }

        if (! $enrollment instanceof Enrollment) {
            return false;
        }

        return $enrollment->user_id === $user->id;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'date' => '対象日',
        ];
    }
}
