<?php

declare(strict_types=1);

namespace App\Exceptions\MockExam;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 模試が公開停止されている / SoftDelete されているなど、受験不可能な状態でアクセスされた際の例外(HTTP 409)。
 *
 * セッション作成済みでも、開始時点で模試が非公開化されていれば start を拒否する。
 */
final class MockExamUnavailableException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('この模試は現在受験できません。公開状態を確認してください。', $previous);
    }
}
