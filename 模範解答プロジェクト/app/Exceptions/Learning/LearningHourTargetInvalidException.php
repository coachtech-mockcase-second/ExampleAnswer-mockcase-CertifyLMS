<?php

declare(strict_types=1);

namespace App\Exceptions\Learning;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * 学習時間目標の入力値が 1..9999h の範囲外である際の例外。HTTP 422 Unprocessable Entity にマップされる。
 * FormRequest が一次ガードを担い、Action 側の二重ガードで内部呼出経路の保険として throw する。
 */
final class LearningHourTargetInvalidException extends UnprocessableEntityHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('合計目標時間は 1 〜 9999 の整数で指定してください。', $previous);
    }
}
