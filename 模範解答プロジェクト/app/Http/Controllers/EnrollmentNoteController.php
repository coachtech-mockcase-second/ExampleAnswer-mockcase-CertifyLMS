<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\EnrollmentNote\StoreRequest;
use App\Http\Requests\EnrollmentNote\UpdateRequest;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\UseCases\EnrollmentNote\DestroyAction;
use App\UseCases\EnrollmentNote\StoreAction;
use App\UseCases\EnrollmentNote\UpdateAction;
use Illuminate\Http\RedirectResponse;

/**
 * コーチ / admin による受講生メモ Controller。受講生(student) は本リソースに対する全操作を Policy で拒否される。
 */
class EnrollmentNoteController extends Controller
{
    public function store(Enrollment $enrollment, StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $action($enrollment, auth()->user(), $request->validated());

        return back()->with('success', 'メモを追加しました。');
    }

    public function update(EnrollmentNote $note, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $action($note, $request->validated());

        return back()->with('success', 'メモを更新しました。');
    }

    public function destroy(EnrollmentNote $note, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $note);

        $action($note);

        return back()->with('success', 'メモを削除しました。');
    }
}
