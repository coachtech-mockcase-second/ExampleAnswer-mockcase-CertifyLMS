<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\UseCases\StripeWebhook\HandleAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Stripe Webhook の受信窓口 Controller。
 * VerifyStripeSignature middleware で署名検証された後、Event 配列を HandleAction へ受け渡す。
 */
class StripeWebhookController extends Controller
{
    /**
     * Stripe Webhook の受信窓口。VerifyStripeSignature middleware で署名検証済の
     * Event 配列を request->input('stripe_event') から受け取り、Action に委譲する。
     */
    public function handle(Request $request, HandleAction $action): JsonResponse
    {
        $event = $request->input('stripe_event');

        if (! is_array($event) || ! isset($event['type'])) {
            return response()->json(['received' => false], 400);
        }

        $action($event);

        return response()->json(['received' => true]);
    }
}
