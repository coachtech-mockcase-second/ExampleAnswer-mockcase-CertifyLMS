<?php

declare(strict_types=1);

namespace App\Exceptions\Content;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * SectionQuestion 作成・更新時、選択肢のうち is_correct=true がちょうど 1 件でない(0 件 / 複数)場合に throw される。
 */
final class QuestionInvalidOptionsException extends UnprocessableEntityHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('選択肢のうち正答は 1 件のみ指定してください。', $previous);
    }
}
