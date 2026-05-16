<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Requests\User\IndexRequest;
use App\Http\Requests\User\UpdateRequest;
use App\Http\Requests\User\UpdateRoleRequest;
use App\Http\Requests\User\WithdrawRequest;
use App\Models\User;
use App\UseCases\User\IndexAction;
use App\UseCases\User\ShowAction;
use App\UseCases\User\UpdateAction;
use App\UseCases\User\UpdateRoleAction;
use App\UseCases\User\WithdrawAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(IndexRequest $request, IndexAction $action): View
    {
        $validated = $request->validated();

        $users = $action(
            keyword: $validated['keyword'] ?? null,
            role: isset($validated['role']) ? UserRole::from($validated['role']) : null,
            status: isset($validated['status']) ? UserStatus::from($validated['status']) : null,
        );

        return view('admin.users.index', [
            'users' => $users,
            'keyword' => $validated['keyword'] ?? '',
            'role' => $validated['role'] ?? '',
            'status' => $validated['status'] ?? '',
        ]);
    }

    public function show(User $user, ShowAction $action): View
    {
        $this->authorize('view', $user);

        return view('admin.users.show', [
            'user' => $action($user),
        ]);
    }

    public function update(User $user, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $action($user, $request->validated());

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', 'プロフィールを更新しました。');
    }

    public function updateRole(User $user, UpdateRoleRequest $request, UpdateRoleAction $action): RedirectResponse
    {
        $action($user, UserRole::from($request->validated('role')), $request->user());

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', 'ロールを変更しました。');
    }

    public function withdraw(User $user, WithdrawRequest $request, WithdrawAction $action): RedirectResponse
    {
        $action($user, $request->user(), $request->validated('reason'));

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'ユーザーを退会させました。');
    }
}
