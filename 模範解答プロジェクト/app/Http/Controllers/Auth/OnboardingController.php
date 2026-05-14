<?php

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

class OnboardingController extends Controller
{
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

    public function store(
        Invitation $invitation,
        OnboardingRequest $request,
        OnboardAction $action,
    ): RedirectResponse {
        $action($invitation, $request->validated());

        return redirect()->route('dashboard.index');
    }
}
