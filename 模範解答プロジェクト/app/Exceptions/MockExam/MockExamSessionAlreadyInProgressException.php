<?php

declare(strict_types=1);

namespace App\Exceptions\MockExam;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 同じ受講登録 × 同じ模試で進行中(NotStarted / InProgress / Submitted) のセッションが既に存在する状態で
 * 新規セッション作成を試みた際の例外(HTTP 409)。
 *
 * 受講生は同一模試の進行中セッションを 1 件だけ持つ。続きから再開するか、キャンセルしてから新規作成する。
 */
final class MockExamSessionAlreadyInProgressException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('進行中の受験セッションが既に存在します。続きから再開するか、キャンセルしてください。', $previous);
    }
}
