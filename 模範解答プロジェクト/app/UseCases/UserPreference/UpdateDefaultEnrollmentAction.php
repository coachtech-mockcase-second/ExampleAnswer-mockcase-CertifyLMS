<?php

declare(strict_types=1);

namespace App\UseCases\UserPreference;

use App\Enums\EnrollmentStatus;
use App\Exceptions\UserPreference\DefaultEnrollmentInvalidTargetException;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 受講生によるデフォルト資格(users.default_enrollment_id)変更ユースケース。
 *
 * 本人検証は SettingsDefaultEnrollmentController に対する UpdateDefaultEnrollmentRequest::authorize() で
 * EnrollmentPolicy::view 経由で完結済の前提。本 Action は対象 Enrollment の status が learning|passed であることを
 * 整合性チェックし、failed の場合は DefaultEnrollmentInvalidTargetException で 422 を返す。
 *
 * @see \App\Http\Controllers\Settings\SettingsDefaultEnrollmentController::update()
 */
final class UpdateDefaultEnrollmentAction
{
    /**
     * @throws DefaultEnrollmentInvalidTargetException 対象 Enrollment が学習中止状態の場合
     */
    public function __invoke(User $user, Enrollment $enrollment): User
    {
        if ($enrollment->status === EnrollmentStatus::Failed) {
            throw new DefaultEnrollmentInvalidTargetException;
        }

        return DB::transaction(function () use ($user, $enrollment): User {
            $user->update(['default_enrollment_id' => $enrollment->id]);

            return $user->refresh();
        });
    }
}
