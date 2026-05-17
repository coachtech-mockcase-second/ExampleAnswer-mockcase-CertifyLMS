<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Certification;
use App\Models\User;
use App\UseCases\CertificationCoachAssignment\AttachAction;
use App\UseCases\CertificationCoachAssignment\DetachAction;
use Illuminate\Http\RedirectResponse;

/**
 * admin 用の担当コーチ割当 Controller。資格 × コーチ の attach / detach を URL パラメータベースで提供する。
 */
class CertificationCoachAssignmentController extends Controller
{
    public function attach(Certification $certification, User $coach, AttachAction $action): RedirectResponse
    {
        $this->authorize('attachCoach', $certification);

        $action($certification, $coach, request()->user());

        return redirect()
            ->route('admin.certifications.show', $certification)
            ->with('success', '担当コーチを追加しました。');
    }

    public function detach(Certification $certification, User $coach, DetachAction $action): RedirectResponse
    {
        $this->authorize('detachCoach', $certification);

        $action($certification, $coach, request()->user());

        return redirect()
            ->route('admin.certifications.show', $certification)
            ->with('success', '担当コーチを解除しました。');
    }
}
