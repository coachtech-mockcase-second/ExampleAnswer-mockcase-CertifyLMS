<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\LearningHourTarget\UpsertRequest;
use App\Models\Enrollment;
use App\Models\LearningHourTarget;
use App\UseCases\LearningHourTarget\DestroyAction;
use App\UseCases\LearningHourTarget\ShowAction;
use App\UseCases\LearningHourTarget\UpsertAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * 学習時間目標 CRUD Controller。Enrollment × LearningHourTarget は UNIQUE で 1:1、未設定は行なしで表現する。
 * 認可は親 Enrollment 単位で Policy 判定する(Enrollment 本人 + 受講中ステータス前提)。
 */
class LearningHourTargetController extends Controller
{
    public function show(Enrollment $enrollment, ShowAction $action): View
    {
        $this->authorize('view', [LearningHourTarget::class, $enrollment]);

        return view('learning.hour-targets.show', $action($enrollment));
    }

    public function upsert(Enrollment $enrollment, UpsertRequest $request, UpsertAction $action): RedirectResponse
    {
        $this->authorize('update', [LearningHourTarget::class, $enrollment]);

        $action($enrollment, $request->validated());

        return redirect()
            ->route('learning.hourTarget.show', $enrollment)
            ->with('success', '学習時間目標を保存しました。');
    }

    public function destroy(Enrollment $enrollment, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', [LearningHourTarget::class, $enrollment]);

        $action($enrollment);

        return redirect()
            ->route('learning.hourTarget.show', $enrollment)
            ->with('success', '学習時間目標を削除しました。');
    }
}
