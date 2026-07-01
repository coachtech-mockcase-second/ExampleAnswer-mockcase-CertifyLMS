<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\OnboardingRequest;
use App\Models\Invitation;
use App\Services\InvitationTokenService;
use App\UseCases\Auth\OnboardAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * 招待された受講生 / コーチが初回パスワード等を設定して利用を開始する Onboarding Controller。
 */
class OnboardingController extends Controller
{
    /**
     * 招待トークンを検証し、有効なら本人情報入力フォームを、無効なら招待無効画面を表示する。
     */
    public function show(Request $request, Invitation $invitation, InvitationTokenService $tokenService): View
    {
        if (! $tokenService->verify($request, $invitation)) {
            return view('auth.invitation-invalid');
        }

        $postUrl = URL::temporarySignedRoute(
            'onboarding.store',
            $invitation->expires_at,
            ['invitation' => $invitation->id],
        );

        return view('auth.onboarding', [
            'invitation' => $invitation,
            'postUrl' => $postUrl,
        ]);
    }

    /**
     * 入力内容で招待を受諾してアカウントを有効化し、ダッシュボードへリダイレクトする。
     */
    public function store(
        Invitation $invitation,
        OnboardingRequest $request,
        OnboardAction $action,
    ): RedirectResponse {
        $action($invitation, $request->validated());

        return redirect()->route('dashboard.index');
    }
}
