<?php

declare(strict_types=1);

namespace App\Exceptions\Chat;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * 担当コーチが資格に 1 人も割当てられていない状態で chat メッセージを送信しようとした場合に throw する例外。
 *
 * 422 Unprocessable Entity を返し、Flash メッセージで受講生 / コーチ向けに対応案内を表示する。
 * Policy::sendMessage が false を返す事象のうち、認可違反(403)ではなく業務ガードに当たるケースの分岐用。
 */
final class CertificationCoachNotAssignedForChatException extends HttpException
{
    public function __construct(?string $message = null, ?\Throwable $previous = null)
    {
        parent::__construct(
            statusCode: 422,
            message: $message ?? '担当コーチが割り当てられていません。',
            previous: $previous,
        );
    }
}
