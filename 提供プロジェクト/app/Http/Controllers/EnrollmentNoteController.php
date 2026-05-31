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
use Illuminate\View\View;

/**
 * コーチ / admin による受講生メモ Controller。受講生(student) は本リソースに対する全操作を Policy で拒否される。
 *
 * 受講生詳細画面(`enrollments.show`)は admin / coach / student の 3 ロール共有エンドポイントに統合済のため、
 * メモ編集後の戻り先もロールに依存せず単一 route で完結する。
 */
class EnrollmentNoteController extends Controller
{
    public function store(Enrollment $enrollment, StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $action($enrollment, auth()->user(), $request->validated());

        return back()->with('success', 'メモを追加しました。');
    }

    public function edit(EnrollmentNote $note): View
    {
        $this->authorize('update', $note);

        return view('enrollment-note.edit', [
            'note' => $note,
        ]);
    }

    public function update(EnrollmentNote $note, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $action($note, $request->validated());

        return redirect()
            ->route('enrollments.show', $note->enrollment_id)
            ->with('success', 'メモを更新しました。');
    }

    public function destroy(EnrollmentNote $note, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $note);

        $action($note);

        return back()->with('success', 'メモを削除しました。');
    }
}
