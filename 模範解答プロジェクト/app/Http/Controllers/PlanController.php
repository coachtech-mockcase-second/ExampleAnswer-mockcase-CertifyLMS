<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PlanStatus;
use App\Http\Requests\Plan\IndexRequest;
use App\Http\Requests\Plan\StoreRequest;
use App\Http\Requests\Plan\UpdateRequest;
use App\Models\Plan;
use App\UseCases\Plan\DestroyAction;
use App\UseCases\Plan\IndexAction;
use App\UseCases\Plan\ShowAction;
use App\UseCases\Plan\StoreAction;
use App\UseCases\Plan\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * 受講プラン マスタの admin 向け CRUD Controller。
 * 一覧 / 詳細 / 新規 / 編集 / 削除を受け持ち、状態遷移(publish / archive / unarchive)は PlanStatusController が担当する。
 */
class PlanController extends Controller
{
    /**
     * 受講プランを名前キーワード・公開ステータスで絞り込んで一覧表示する。
     */
    public function index(IndexRequest $request, IndexAction $action): View
    {
        $validated = $request->validated();

        $plans = $action(
            keyword: $validated['keyword'] ?? null,
            status: isset($validated['status']) ? PlanStatus::from($validated['status']) : null,
        );

        return view('plan.management.index', [
            'plans' => $plans,
            'keyword' => $validated['keyword'] ?? '',
            'status' => $validated['status'] ?? '',
        ]);
    }

    /**
     * 受講プランの詳細を表示する。
     */
    public function show(Plan $plan, ShowAction $action): View
    {
        $this->authorize('view', $plan);

        return view('plan.management.show', [
            'plan' => $action($plan),
        ]);
    }

    /**
     * 受講プランの新規作成フォームを表示する。
     */
    public function create(): View
    {
        $this->authorize('create', Plan::class);

        return view('plan.management.create');
    }

    /**
     * 受講プランを下書き状態で新規作成し、作成した詳細画面へリダイレクトする。
     */
    public function store(StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $plan = $action($request->user(), $request->validated());

        return redirect()
            ->route('admin.plans.show', $plan)
            ->with('success', 'プランを作成しました。');
    }

    /**
     * 受講プランの編集フォームを表示する。
     */
    public function edit(Plan $plan): View
    {
        $this->authorize('update', $plan);

        return view('plan.management.edit', [
            'plan' => $plan,
        ]);
    }

    /**
     * 受講プランの内容を更新し、詳細画面へリダイレクトする。
     */
    public function update(Plan $plan, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $action($plan, $request->user(), $request->validated());

        return redirect()
            ->route('admin.plans.show', $plan)
            ->with('success', 'プランを更新しました。');
    }

    /**
     * 受講プランを削除し、一覧画面へリダイレクトする。
     */
    public function destroy(Plan $plan, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $plan);

        $action($plan);

        return redirect()
            ->route('admin.plans.index')
            ->with('success', 'プランを削除しました。');
    }
}
