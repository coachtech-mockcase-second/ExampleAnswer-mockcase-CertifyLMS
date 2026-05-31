<?php

declare(strict_types=1);

namespace App\Exceptions\MockExam;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 模試マスタの公開操作が不正な状態から呼ばれた際の例外(HTTP 409)。
 *
 * - 問題が 1 件も組成されていない模試は公開できない
 * - 既に公開済の模試を再公開しようとした
 */
final class MockExamPublishNotAllowedException extends ConflictHttpException
{
    public static function forNoQuestions(): self
    {
        return new self('問題が登録されていない模試は公開できません。先に問題を 1 件以上追加してください。');
    }

    public static function forAlreadyPublished(): self
    {
        return new self('既に公開中の模試は再公開できません。');
    }

    public static function forNotPublished(): self
    {
        return new self('公開中の模試のみ公開停止できます。');
    }

    private function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, $previous);
    }
}
