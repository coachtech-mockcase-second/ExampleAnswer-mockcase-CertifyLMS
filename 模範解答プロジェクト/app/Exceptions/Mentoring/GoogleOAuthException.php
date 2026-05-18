<?php

declare(strict_types=1);

namespace App\Exceptions\Mentoring;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Throwable;

/**
 * Google OAuth フロー中の検証失敗を表す例外。callback の `state` 不一致、`refresh_token` 欠落、
 * code 交換失敗など、ユーザーに再連携を促すケースで投げる。HTTP 400 で扱う。
 */
final class GoogleOAuthException extends BadRequestHttpException
{
    public static function stateMismatch(): self
    {
        return new self('Google 認証情報の検証に失敗しました。再度連携をお試しください。');
    }

    public static function missingRefreshToken(): self
    {
        return new self('Google から永続トークンを取得できませんでした。Google アカウントの権限を取り消してから再度連携してください。');
    }

    private function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, $previous);
    }
}
