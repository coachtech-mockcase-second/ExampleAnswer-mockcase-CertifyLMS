<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserPreference\UpdateDefaultEnrollmentRequest;
use App\Models\Enrollment;
use App\UseCases\UserPreference\UpdateDefaultEnrollmentAction;
use Illuminate\Http\RedirectResponse;

/**
 * 受講生のデフォルト資格(users.default_enrollment_id)を変更する Controller。
 *
 * Route Model Binding で対象 Enrollment を受け、Action に委譲する。本人検証(EnrollmentPolicy::view)は
 * UpdateDefaultEnrollmentRequest::authorize() で実施済。redirect_to が指定されていればその URL に、
 * 未指定なら受講登録詳細(/enrollments/{enrollment}) にリダイレクトする。
 */
class SettingsDefaultEnrollmentController extends Controller
{
    public function update(
        UpdateDefaultEnrollmentRequest $request,
        Enrollment $enrollment,
        UpdateDefaultEnrollmentAction $action,
    ): RedirectResponse {
        ($action)($request->user(), $enrollment);

        $redirectTo = $request->validated('redirect_to');

        $redirect = $redirectTo !== null
            ? redirect($redirectTo)
            : redirect()->route('enrollments.show', $enrollment);

        return $redirect->with('success', 'デフォルト資格を変更しました。');
    }
}
