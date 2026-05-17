<?php

declare(strict_types=1);

namespace App\Exceptions\Certification;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * 担当コーチ割当でコーチロール以外のユーザーが指定された際の例外（HTTP 422）。
 * `CertificationCoachAssignment\AttachAction` が role 検証で throw する。
 */
final class NotCoachUserException extends UnprocessableEntityHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('指定したユーザーはコーチではありません。', $previous);
    }
}
