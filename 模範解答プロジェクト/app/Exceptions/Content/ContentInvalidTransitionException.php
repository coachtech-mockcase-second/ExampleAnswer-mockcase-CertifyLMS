<?php

declare(strict_types=1);

namespace App\Exceptions\Content;

use App\Enums\ContentStatus;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ContentInvalidTransitionException extends ConflictHttpException
{
    public function __construct(
        public readonly string $entity,
        public readonly ContentStatus $from,
        public readonly ContentStatus $to,
        ?\Throwable $previous = null,
    ) {
        $message = sprintf(
            '%sの現在の状態（%s）からはこの操作（%s）を行えません。',
            $entity,
            $from->label(),
            $to->label(),
        );

        parent::__construct($message, $previous);
    }
}
