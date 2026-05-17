<?php

declare(strict_types=1);

namespace App\Exceptions\Certification;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 資格マスタが見つからない際の例外（HTTP 404）。
 */
final class CertificationNotFoundException extends NotFoundHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('資格が見つかりません。', $previous);
    }
}
