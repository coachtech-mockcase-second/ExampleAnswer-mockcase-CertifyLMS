<?php

declare(strict_types=1);

namespace App\Exceptions\Plan;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Plan の削除が不可能な状態(published / archived、または既存 User の plan_id が参照中)で DELETE が要求された際に throw される。
 */
class PlanNotDeletableException extends ConflictHttpException
{
    public function __construct(
        string $message = 'このプランは削除できません。下書き状態かつ受講者が紐づいていないプランのみ削除できます。',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $previous);
    }
}
