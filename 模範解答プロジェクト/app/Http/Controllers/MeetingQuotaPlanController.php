<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\MeetingQuotaPlanStatus;
use App\Http\Requests\MeetingQuotaPlan\IndexRequest;
use App\Http\Requests\MeetingQuotaPlan\StoreRequest;
use App\Http\Requests\MeetingQuotaPlan\UpdateRequest;
use App\Models\MeetingQuotaPlan;
use App\UseCases\MeetingQuotaPlan\DestroyAction;
use App\UseCases\MeetingQuotaPlan\IndexAction;
use App\UseCases\MeetingQuotaPlan\ShowAction;
use App\UseCases\MeetingQuotaPlan\StoreAction;
use App\UseCases\MeetingQuotaPlan\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * 追加面談 SKU マスタの admin 向け CRUD Controller。
 * 一覧 / 詳細 / 新規 / 編集 / 削除を受け持ち、状態遷移(publish / archive / unarchive)は別 Controller が担当する。
 */
class MeetingQuotaPlanController extends Controller
{
    public function index(IndexRequest $request, IndexAction $action): View
    {
        $validated = $request->validated();

        $plans = $action(
            keyword: $validated['keyword'] ?? null,
            status: isset($validated['status']) ? MeetingQuotaPlanStatus::from($validated['status']) : null,
        );

        return view('admin.meeting-quota-plans.index', [
            'plans' => $plans,
            'keyword' => $validated['keyword'] ?? '',
            'status' => $validated['status'] ?? '',
        ]);
    }

    public function show(MeetingQuotaPlan $plan, ShowAction $action): View
    {
        $this->authorize('view', $plan);

        return view('admin.meeting-quota-plans.show', [
            'plan' => $action($plan),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', MeetingQuotaPlan::class);

        return view('admin.meeting-quota-plans.create');
    }

    public function store(StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $plan = $action($request->user(), $request->validated());

        return redirect()
            ->route('admin.meeting-quota-plans.show', $plan)
            ->with('success', '追加面談プランを作成しました。');
    }

    public function edit(MeetingQuotaPlan $plan): View
    {
        $this->authorize('update', $plan);

        return view('admin.meeting-quota-plans.edit', [
            'plan' => $plan,
        ]);
    }

    public function update(MeetingQuotaPlan $plan, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $action($plan, $request->user(), $request->validated());

        return redirect()
            ->route('admin.meeting-quota-plans.show', $plan)
            ->with('success', '追加面談プランを更新しました。');
    }

    public function destroy(MeetingQuotaPlan $plan, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $plan);

        $action($plan);

        return redirect()
            ->route('admin.meeting-quota-plans.index')
            ->with('success', '追加面談プランを削除しました。');
    }
}
