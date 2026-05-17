<?php

declare(strict_types=1);

namespace App\Exceptions\Plan;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Plan の status 遷移違反(draft → archived 直接遷移など)が試行された際に throw される。
 */
class PlanInvalidTransitionException extends ConflictHttpException
{
    public function __construct(
        string $message = 'このプランは現在のステータスから要求された状態への遷移を許可していません。',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $previous);
    }
}
