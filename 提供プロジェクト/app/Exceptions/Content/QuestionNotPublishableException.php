<?php

declare(strict_types=1);

namespace App\Exceptions\Content;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * SectionQuestion 公開時、選択肢が 2 件未満 または is_correct=true がちょうど 1 件でない場合に throw される。
 */
final class QuestionNotPublishableException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('公開には選択肢 2 件以上 + 正答 1 件が必要です。', $previous);
    }
}
