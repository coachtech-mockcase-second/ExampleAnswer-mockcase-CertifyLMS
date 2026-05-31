<?php

declare(strict_types=1);

namespace App\Exceptions\MockExam;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * 模試問題の作成・更新時に、`is_correct = true` の選択肢がちょうど 1 件でない場合の例外(HTTP 422)。
 *
 * 採点ロジックは「正答の選択肢が選ばれたか」のみで判定するため、正答ゼロ / 正答複数の組成は許容しない。
 */
final class QuestionInvalidOptionsException extends UnprocessableEntityHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('正答の選択肢をちょうど 1 つ指定してください。', $previous);
    }
}
