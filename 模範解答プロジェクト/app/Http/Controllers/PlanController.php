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

class PlanController extends Controller
{
    public function index(IndexRequest $request, IndexAction $action): View
    {
        $validated = $request->validated();

        $plans = $action(
            keyword: $validated['keyword'] ?? null,
            status: isset($validated['status']) ? PlanStatus::from($validated['status']) : null,
        );

        return view('admin.plans.index', [
            'plans' => $plans,
            'keyword' => $validated['keyword'] ?? '',
            'status' => $validated['status'] ?? '',
        ]);
    }

    public function show(Plan $plan, ShowAction $action): View
    {
        $this->authorize('view', $plan);

        return view('admin.plans.show', [
            'plan' => $action($plan),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Plan::class);

        return view('admin.plans.create');
    }

    public function store(StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $plan = $action($request->user(), $request->validated());

        return redirect()
            ->route('admin.plans.show', $plan)
            ->with('success', 'プランを作成しました。');
    }

    public function edit(Plan $plan): View
    {
        $this->authorize('update', $plan);

        return view('admin.plans.edit', [
            'plan' => $plan,
        ]);
    }

    public function update(Plan $plan, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $action($plan, $request->user(), $request->validated());

        return redirect()
            ->route('admin.plans.show', $plan)
            ->with('success', 'プランを更新しました。');
    }

    public function destroy(Plan $plan, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $plan);

        $action($plan);

        return redirect()
            ->route('admin.plans.index')
            ->with('success', 'プランを削除しました。');
    }
}
