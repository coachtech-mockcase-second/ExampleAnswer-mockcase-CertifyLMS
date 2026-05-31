<?php

declare(strict_types=1);

namespace App\Exceptions\QuizAnswering;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 苦手分野ドリルの URL に含まれる QuestionCategory が、当該 Enrollment の資格と一致しない場合に発火する例外。
 *
 * 他資格のカテゴリ ID への手打ちアクセスをガードする。
 */
final class WeakDrillCategoryMismatchException extends NotFoundHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('指定の出題分野が見つかりません。', $previous);
    }
}
