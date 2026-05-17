<?php

declare(strict_types=1);

namespace App\UseCases\Auth;

use App\Enums\InvitationStatus;
use App\Enums\UserStatus;
use App\Exceptions\Auth\InvalidInvitationTokenException;
use App\Models\Invitation;
use App\Models\User;
use App\Services\UserStatusChangeService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class OnboardAction
{
    public function __construct(private readonly UserStatusChangeService $statusChanger) {}

    /**
     * 招待を受領し、既存 invited User を active に遷移させ、自動ログインする。
     *
     * @param array{name: string, bio?: ?string, password: string} $validated
     */
    public function __invoke(Invitation $invitation, array $validated): User
    {
        return DB::transaction(function () use ($invitation, $validated) {
            $invitation->refresh();
            $user = $invitation->user;

            if (
                $user === null
                || $invitation->status !== InvitationStatus::Pending
                || $invitation->expires_at === null
                || $invitation->expires_at->isPast()
                || $user->status !== UserStatus::Invited
            ) {
                throw new InvalidInvitationTokenException;
            }

            $user->forceFill([
                'name' => $validated['name'],
                'bio' => $validated['bio'] ?? null,
                'password' => Hash::make($validated['password']),
                'status' => UserStatus::InProgress,
                'profile_setup_completed' => true,
                'email_verified_at' => now(),
            ])->save();

            $invitation->forceFill([
                'status' => InvitationStatus::Accepted,
                'accepted_at' => now(),
            ])->save();

            $this->statusChanger->record(
                $user,
                UserStatus::InProgress,
                $user,
                'オンボーディング完了',
            );

            Auth::login($user);

            return $user->refresh();
        });
    }
}
