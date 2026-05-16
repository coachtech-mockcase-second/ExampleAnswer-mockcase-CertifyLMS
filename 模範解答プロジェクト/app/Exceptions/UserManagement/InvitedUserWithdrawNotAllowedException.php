<?php

declare(strict_types=1);

namespace App\Exceptions\UserManagement;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * 招待中（status=invited）のユーザーを `WithdrawAction` から退会させようとした際の例外。
 *
 * 招待中ユーザーの削除は「招待を取消」動線（[[auth]] `RevokeInvitationAction`）から行う設計のため、
 * 本 Action では受理しない。汎用 `HttpException(422, ...)` 直接 throw の置換（2026-05-16、P1-2 対応）。
 */
class InvitedUserWithdrawNotAllowedException extends UnprocessableEntityHttpException
{
    public function __construct(
        string $message = '招待中ユーザーは「招待を取消」から削除してください。',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $previous);
    }
}
