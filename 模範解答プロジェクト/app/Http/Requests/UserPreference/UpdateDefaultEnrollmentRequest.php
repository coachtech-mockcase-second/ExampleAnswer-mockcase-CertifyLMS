<?php

declare(strict_types=1);

namespace App\Http\Requests\UserPreference;

use App\Models\Enrollment;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 受講生のデフォルト資格変更リクエスト。
 *
 * Route パラメータ {enrollment} で対象を Route Model Binding。authorize() は EnrollmentPolicy::view 経由で
 * 本人検証(他者の Enrollment 指定で 403)。status が learning|passed|failed のどれかは Action 側で再検証する
 * (failed は DefaultEnrollmentInvalidTargetException で 422 拒否)。
 */
class UpdateDefaultEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $enrollment = $this->route('enrollment');

        return $enrollment instanceof Enrollment
            && $this->user()?->can('view', $enrollment) === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'redirect_to' => ['nullable', 'string', 'max:500'],
        ];
    }
}
