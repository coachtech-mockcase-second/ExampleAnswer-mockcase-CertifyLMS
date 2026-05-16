<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CertificationCoachAssignment\StoreRequest;
use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\User;
use App\UseCases\CertificationCoachAssignment\DestroyAction;
use App\UseCases\CertificationCoachAssignment\StoreAction;
use Illuminate\Http\RedirectResponse;

class CertificationCoachAssignmentController extends Controller
{
    public function store(Certification $certification, StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $coach = User::findOrFail($request->validated('coach_user_id'));

        $action($certification, $coach, $request->user());

        return redirect()
            ->route('admin.certifications.show', $certification)
            ->with('success', '担当コーチを追加しました。');
    }

    public function destroy(Certification $certification, User $user, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', CertificationCoachAssignment::class);

        $action($certification, $user);

        return redirect()
            ->route('admin.certifications.show', $certification)
            ->with('success', '担当コーチを解除しました。');
    }
}
