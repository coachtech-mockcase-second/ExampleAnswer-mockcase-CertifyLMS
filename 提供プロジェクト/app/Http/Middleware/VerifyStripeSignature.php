<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\MeetingQuota\StripeWebhookSignatureInvalidException;
use Closure;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stripe Webhook の HMAC-SHA256 署名検証を行う Middleware。
 *
 * `Stripe-Signature` ヘッダと `config('services.stripe.webhook_secret')` を使い改ざんを検出する。
 * 検証成功時は `stripe_event` キーに Stripe Event の配列表現を merge し、Controller / Action へ受け渡す。
 * 検証失敗時は StripeWebhookSignatureInvalidException(HTTP 400)を throw する。
 */
class VerifyStripeSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');
        $secret = config('services.stripe.webhook_secret');

        if (! is_string($signature) || ! is_string($secret) || $secret === '') {
            throw new StripeWebhookSignatureInvalidException;
        }

        try {
            $event = Webhook::constructEvent($payload, $signature, $secret);
        } catch (SignatureVerificationException $e) {
            throw new StripeWebhookSignatureInvalidException(previous: $e);
        }

        $request->merge(['stripe_event' => $event->toArray()]);

        return $next($request);
    }
}
