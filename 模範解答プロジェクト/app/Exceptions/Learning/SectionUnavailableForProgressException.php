<?php

declare(strict_types=1);

namespace App\Exceptions\Learning;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Section の読了マーク / 取消が不可能な状態(Section / 親 Chapter / 親 Part のいずれかが Draft または SoftDelete 済)
 * で操作が試みられた際の例外。HTTP 409 Conflict にマップされる。
 */
final class SectionUnavailableForProgressException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('対象の Section は公開状態でないため、読了マークを操作できません。', $previous);
    }
}
