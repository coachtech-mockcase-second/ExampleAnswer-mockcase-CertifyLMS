<?php

declare(strict_types=1);

namespace App\Exceptions\MockExam;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 模試に問題が紐づいていない状態でセッション作成を試みた際の例外(HTTP 409)。
 *
 * 受験開始時点で `mock_exam_questions` が 0 件なら出題スナップショットが組成できないためセッションを作成しない。
 */
final class MockExamHasNoQuestionsException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('この模試にはまだ問題が登録されていません。受験を開始できません。', $previous);
    }
}
