<?php

declare(strict_types=1);

namespace App\Exceptions\Content;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * SectionQuestion 作成・更新時に指定された出題分野が、所属 Section の資格(certification)と一致しない場合に throw される。
 */
final class QuestionCategoryMismatchException extends UnprocessableEntityHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('指定された出題分野は現在の資格に属していません。', $previous);
    }
}
