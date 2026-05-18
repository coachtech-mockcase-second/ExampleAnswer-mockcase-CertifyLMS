<?php

declare(strict_types=1);

namespace App\Exceptions\MockExam;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * 送信された `selected_option_id` が `mock_exam_question_id` で示される問題の選択肢に属していない場合の例外(HTTP 422)。
 *
 * 不正なフォーム改ざんや、別問題の選択肢 ID を送り付ける攻撃を防ぐ。
 */
final class MockExamOptionMismatchException extends UnprocessableEntityHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('選択肢が問題と一致しません。最新の出題画面から再度解答してください。', $previous);
    }
}
