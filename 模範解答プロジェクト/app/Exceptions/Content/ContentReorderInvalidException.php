<?php

declare(strict_types=1);

namespace App\Exceptions\Content;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * 並び替え(reorder)ペイロードのバリデーション失敗時に throw される。
 * 全 ID が同一親配下を参照し、order 値が 1..N の連番で、id 重複がないことが必須条件。
 */
final class ContentReorderInvalidException extends UnprocessableEntityHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('並び順の指定が不正です。同一親配下の全 ID を 1..N の連番で指定してください。', $previous);
    }
}
