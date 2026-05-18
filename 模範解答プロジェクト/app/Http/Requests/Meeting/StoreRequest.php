<?php

declare(strict_types=1);

namespace App\Http\Requests\Meeting;

use App\Models\Enrollment;
use App\Models\Meeting;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 受講生の面談予約作成リクエスト。
 *
 * `scheduled_at` は分単位を 00 に固定(60 分単位スロットしか提供しないため)。
 * URL 上の Enrollment が認証ユーザー本人のものでない場合は authorize で 403 を返す。
 *
 * @see \App\Http\Controllers\MeetingController::store()
 */
class StoreRequest extends FormRequest
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
            'scheduled_at' => [
                'required',
                'date',
                'after:now',
                'regex:/^\d{4}-\d{2}-\d{2}[T ]\d{2}:00(:00)?$/',
            ],
            'topic' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'scheduled_at.regex' => '面談予約は毎時 00 分のスロットからお選びください。',
            'scheduled_at.after' => '未来の時刻をお選びください。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'scheduled_at' => '面談開始時刻',
            'topic' => '相談内容',
        ];
    }
}
