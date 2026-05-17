<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\MeetingQuota\CheckoutRequest;
use App\Models\MeetingQuotaPlan;
use App\Models\Payment;
use App\UseCases\MeetingQuota\CreateCheckoutSessionAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 受講生の追加面談購入動線 Controller。
 * SKU 選択画面の描画 / Stripe Checkout Session 発行 / 決済完了画面の描画を担当する。
 */
class MeetingQuotaCheckoutController extends Controller
{
    public function select(): View
    {
        $this->authorize('purchase-meeting-quota');

        $plans = MeetingQuotaPlan::query()
            ->published()
            ->ordered()
            ->get();

        return view('meeting-quota.checkout-select', [
            'plans' => $plans,
        ]);
    }

    public function create(CheckoutRequest $request, CreateCheckoutSessionAction $action): RedirectResponse
    {
        $plan = MeetingQuotaPlan::query()->findOrFail($request->validated('meeting_quota_plan_id'));

        $result = $action($request->user(), $plan);

        return redirect()->away($result['checkout_url']);
    }

    public function success(Request $request): View
    {
        $sessionId = $request->query('session_id');
        $payment = null;

        if (is_string($sessionId) && $sessionId !== '') {
            $payment = Payment::query()
                ->where('user_id', $request->user()?->id)
                ->where('stripe_checkout_session_id', $sessionId)
                ->first();
        }

        return view('meeting-quota.success', [
            'payment' => $payment,
        ]);
    }
}
