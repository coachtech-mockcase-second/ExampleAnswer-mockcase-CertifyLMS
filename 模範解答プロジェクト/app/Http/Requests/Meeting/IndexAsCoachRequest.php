<?php

declare(strict_types=1);

namespace App\Http\Requests\Meeting;

use App\Http\Controllers\MeetingController;
use App\Models\Meeting;
use Illuminate\Foundation\Http\FormRequest;

/**
 * コーチ視点の面談一覧リクエスト。受講生別 / 受講登録別フィルタ + viewAny 認可を行う。
 *
 * @see MeetingController::indexAsCoach()
 */
class IndexAsCoachRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Meeting::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'in:upcoming,past,all'],
            'student' => ['nullable', 'ulid', 'exists:users,id'],
            'enrollment' => ['nullable', 'ulid', 'exists:enrollments,id'],
        ];
    }
}
