<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\Invitation\ResendRequest;
use App\Http\Requests\Invitation\StoreRequest;
use App\Models\Invitation;
use App\Models\Plan;
use App\Models\User;
use App\UseCases\Invitation\DestroyAction;
use App\UseCases\Invitation\ResendAction;
use App\UseCases\Invitation\StoreAction;
use Illuminate\Http\RedirectResponse;

/**
 * 管理者が招待 Invitation を発行 / 再送信 / 取消する操作を受け付ける Controller。
 * 各 method 名と一致する `App\UseCases\Invitation\{Store,Resend,Destroy}Action` に処理を委譲する。
 */
class InvitationController extends Controller
{
    public function store(StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $validated = $request->validated();
        $role = UserRole::from($validated['role']);
        // 受講生のみ Plan を解決して渡す。コーチは Plan を持たない。
        $plan = $role === UserRole::Student ? Plan::findOrFail($validated['plan_id']) : null;

        $action(
            email: $validated['email'],
            role: $role,
            plan: $plan,
            admin: $request->user(),
        );

        return redirect()
            ->route('admin.users.index')
            ->with('success', '招待を送信しました。');
    }

    public function resend(User $user, ResendRequest $request, ResendAction $action): RedirectResponse
    {
        $action($user, $request->user());

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', '招待を再送信しました。');
    }

    public function destroy(Invitation $invitation, DestroyAction $action): RedirectResponse
    {
        $admin = request()->user();
        $userId = $invitation->user_id;

        $action($invitation, $admin);

        return redirect()
            ->route('admin.users.show', $userId)
            ->with('success', '招待を取り消しました。');
    }
}
