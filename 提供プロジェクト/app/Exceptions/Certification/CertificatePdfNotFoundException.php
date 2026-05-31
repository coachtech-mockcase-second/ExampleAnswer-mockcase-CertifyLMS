<?php

declare(strict_types=1);

namespace App\Exceptions\Certification;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 修了証 PDF ファイルが Storage 上に存在しない際の例外（HTTP 404）。
 * `Certificate\DownloadAction` が `Storage::disk('private')->exists()` の結果から throw する。
 */
final class CertificatePdfNotFoundException extends NotFoundHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('修了証 PDF ファイルが見つかりません。管理者にお問い合わせください。', $previous);
    }
}
