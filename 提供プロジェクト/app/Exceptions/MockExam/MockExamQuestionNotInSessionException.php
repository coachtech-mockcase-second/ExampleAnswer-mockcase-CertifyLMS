<?php

declare(strict_types=1);

namespace App\Exceptions\MockExam;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * PATCH 解答時に送信された `mock_exam_question_id` がセッションの `generated_question_ids` に含まれない場合の例外(HTTP 422)。
 *
 * 不正なフォーム改ざんや、出題スナップショット範囲外への解答送信を防ぐ。
 */
final class MockExamQuestionNotInSessionException extends UnprocessableEntityHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('指定された問題はこの受験セッションの出題範囲に含まれていません。', $previous);
    }
}
