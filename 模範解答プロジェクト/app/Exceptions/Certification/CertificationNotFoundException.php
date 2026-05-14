<?php

namespace App\Exceptions\Certification;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CertificationNotFoundException extends NotFoundHttpException
{
    public function __construct(
        string $message = '資格が見つかりません。',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $previous);
    }
}
