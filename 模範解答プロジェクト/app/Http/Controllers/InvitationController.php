<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\Invitation\ResendRequest;
use App\Http\Requests\Invitation\StoreRequest;
use App\Models\Invitation;
use App\Models\User;
use App\UseCases\Invitation\DestroyAction;
use App\UseCases\Invitation\ResendAction;
use App\UseCases\Invitation\StoreAction;
use Illuminate\Http\RedirectResponse;

class InvitationController extends Controller
{
    public function store(StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $validated = $request->validated();

        $action(
            email: $validated['email'],
            role: UserRole::from($validated['role']),
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
