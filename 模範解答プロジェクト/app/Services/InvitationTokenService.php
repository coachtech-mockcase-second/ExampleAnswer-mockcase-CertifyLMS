<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InvitationStatus;
use App\Enums\UserStatus;
use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class InvitationTokenService
{
    /**
     * オンボーディング画面への署名付き URL を生成する。
     * 有効期限は Invitation.expires_at と一致させる（クエリ ?expires=... を Laravel が自動付与）。
     */
    public function generateUrl(Invitation $invitation): string
    {
        return URL::temporarySignedRoute(
            'onboarding.show',
            $invitation->expires_at,
            ['invitation' => $invitation->id],
        );
    }

    /**
     * 署名 + Invitation 状態 + User 状態の整合性をまとめて検証する。
     * 全条件パスで true、いずれかが NG なら false。
     */
    public function verify(Request $request, Invitation $invitation): bool
    {
        if (! $request->hasValidSignature()) {
            return false;
        }

        if ($invitation->status !== InvitationStatus::Pending) {
            return false;
        }

        if ($invitation->expires_at === null || $invitation->expires_at->isPast()) {
            return false;
        }

        $user = $invitation->user;
        if ($user === null || $user->status !== UserStatus::Invited) {
            return false;
        }

        return true;
    }
}
