<?php

declare(strict_types=1);

namespace App\Exceptions\MeetingQuota;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Stripe Webhook 受信時の HMAC-SHA256 署名検証に失敗した際に throw される(HTTP 400)。
 * 改ざんされた、または STRIPE_WEBHOOK_SECRET が一致しないリクエストを即座に拒否する。
 */
final class StripeWebhookSignatureInvalidException extends BadRequestHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('Stripe Webhook の署名検証に失敗しました。', $previous);
    }
}
