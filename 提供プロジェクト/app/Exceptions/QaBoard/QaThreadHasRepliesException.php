<?php

declare(strict_types=1);

namespace App\Exceptions\QaBoard;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 回答が付いているスレッドを投稿者本人が削除しようとした場合の例外 (HTTP 409)。
 *
 * 回答件数は SoftDelete 済も含めて判定する (回答履歴の保持を優先し、投稿者削除の二次効果を避けるため)。
 * admin によるモデレーション削除は本例外の対象外で常に成功する。
 */
final class QaThreadHasRepliesException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('回答が付いているスレッドは削除できません。', $previous);
    }
}
