<?php

namespace App\Exceptions\Certification;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CertificatePdfNotFoundException extends NotFoundHttpException
{
    public function __construct(
        string $message = '修了証 PDF ファイルが見つかりません。管理者にお問い合わせください。',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $previous);
    }
}
