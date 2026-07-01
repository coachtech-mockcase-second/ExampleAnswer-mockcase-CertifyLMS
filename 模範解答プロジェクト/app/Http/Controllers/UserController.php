<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Requests\User\ExtendCourseRequest;
use App\Http\Requests\User\GrantMeetingQuotaRequest;
use App\Http\Requests\User\IndexRequest;
use App\Http\Requests\User\WithdrawRequest;
use App\Models\Plan;
use App\Models\User;
use App\Services\MeetingQuotaService;
use App\UseCases\User\ExtendCourseAction;
use App\UseCases\User\GrantMeetingQuotaAction;
use App\UseCases\User\IndexAction;
use App\UseCases\User\ShowAction;
use App\UseCases\User\WithdrawAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * 管理者向けユーザー運用画面の HTTP エントリポイント。
 *
 * 一覧 / 詳細閲覧 / 強制退会 / プラン延長 / 面談回数手動付与を提供する。
 * 「他者のプロフィール / ロール変更」動線は本 LMS では提供しないため、`update` / `updateRole` method は持たない。
 */
class UserController extends Controller
{
    /**
     * ユーザーを名前キーワード・ロール・ステータスで絞り込んで一覧表示する。
     */
    public function index(IndexRequest $request, IndexAction $action): View
    {
        $validated = $request->validated();

        $users = $action(
            keyword: $validated['keyword'] ?? null,
            role: isset($validated['role']) ? UserRole::from($validated['role']) : null,
            status: isset($validated['status']) ? UserStatus::from($validated['status']) : null,
        );

        return view('user.management.index', [
            'users' => $users,
            'keyword' => $validated['keyword'] ?? '',
            'role' => $validated['role'] ?? '',
            'status' => $validated['status'] ?? '',
            'inviteFormPlans' => Plan::query()->published()->ordered()->get(),
        ]);
    }

    /**
     * ユーザーの詳細を、残り面談回数とプラン延長候補とあわせて表示する。
     */
    public function show(User $user, ShowAction $action, MeetingQuotaService $quotaService): View
    {
        $this->authorize('view', $user);

        $loaded = $action($user);

        return view('user.management.show', [
            'user' => $loaded,
            'plans' => Plan::query()->published()->ordered()->get(),
            'meetingsRemaining' => $quotaService->remaining($loaded),
        ]);
    }

    /**
     * 受講生を管理者操作で強制退会させ、一覧画面へリダイレクトする。
     */
    public function withdraw(User $user, WithdrawRequest $request, WithdrawAction $action): RedirectResponse
    {
        $action($user, $request->user());

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'ユーザーを退会させました。');
    }

    /**
     * 指定プランで受講生の受講期間を延長し、詳細画面へリダイレクトする。
     */
    public function extendCourse(User $user, ExtendCourseRequest $request, ExtendCourseAction $action): RedirectResponse
    {
        $plan = Plan::query()->whereKey($request->validated('plan_id'))->firstOrFail();

        $action($user, $plan, $request->user());

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', 'プランを延長しました。');
    }

    /**
     * 受講生に面談回数を手動付与し、詳細画面へリダイレクトする。
     */
    public function grantMeetingQuota(User $user, GrantMeetingQuotaRequest $request, GrantMeetingQuotaAction $action): RedirectResponse
    {
        $action(
            $user,
            (int) $request->validated('amount'),
            $request->user(),
            $request->validated('reason'),
        );

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', '面談回数を付与しました。');
    }
}
