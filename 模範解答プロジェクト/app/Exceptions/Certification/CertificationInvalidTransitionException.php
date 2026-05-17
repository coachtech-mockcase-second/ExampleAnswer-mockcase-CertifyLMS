<?php

declare(strict_types=1);

namespace App\Exceptions\Certification;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 資格マスタの公開状態遷移（publish / unpublish / archive）が不正な開始状態から呼ばれた際の例外（HTTP 409）。
 * バリエーションごとに static factory（`forPublish` / `forUnpublish` / `forArchive`）でメッセージを生成する。
 */
final class CertificationInvalidTransitionException extends ConflictHttpException
{
    public static function forPublish(): self
    {
        return new self('下書き状態の資格のみ公開できます。');
    }

    public static function forUnpublish(): self
    {
        return new self('公開中の資格のみ公開停止できます。');
    }

    public static function forArchive(): self
    {
        return new self('公開中の資格のみアーカイブできます。');
    }

    private function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, $previous);
    }
}
