<?php

declare(strict_types=1);

namespace App\Exceptions\Mentoring;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

/**
 * 面談予約の状態遷移制約に違反したアクションを実行しようとした場合の例外。
 *
 * 例: canceled / completed 状態の Meeting をキャンセルしようとした、canceled 状態の Meeting にメモを残そうとした。
 * HTTP 409 として Handler が redirect+flash に変換する。
 */
final class MeetingStatusTransitionException extends ConflictHttpException
{
    public static function forCancel(): self
    {
        return new self('この面談は予約済以外の状態のためキャンセルできません。');
    }

    public static function forMemo(): self
    {
        return new self('キャンセル済の面談にはメモを残せません。');
    }

    private function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, $previous);
    }
}
