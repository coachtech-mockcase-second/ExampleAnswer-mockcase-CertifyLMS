<?php

namespace App\Exceptions\Certification;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CertificationNotDeletableException extends ConflictHttpException
{
    public function __construct(
        string $message = '公開中またはアーカイブ済の資格は削除できません。先にアーカイブから再下書き化してください。',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $previous);
    }
}
