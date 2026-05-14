<?php

namespace App\Exceptions\Certification;

use App\Enums\CertificationStatus;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CertificationInvalidTransitionException extends ConflictHttpException
{
    public function __construct(
        public readonly CertificationStatus $from,
        public readonly CertificationStatus $to,
        ?\Throwable $previous = null,
    ) {
        $message = sprintf(
            '現在の状態（%s）からはこの操作（%s）を行えません。',
            $from->label(),
            $to->label(),
        );

        parent::__construct($message, $previous);
    }
}
