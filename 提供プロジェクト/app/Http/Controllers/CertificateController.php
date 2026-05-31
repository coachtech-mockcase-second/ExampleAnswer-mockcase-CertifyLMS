<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\UseCases\Certificate\DownloadAction;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 修了証 PDF の配信 Controller。受講生本人 / admin 全件 / coach 担当資格分のみ DL 可能。
 * `auth` のみ適用され、graduated 受講生でも DL 可能（修了証は永続データ）。
 */
class CertificateController extends Controller
{
    public function download(Certificate $certificate, DownloadAction $action): StreamedResponse
    {
        $this->authorize('download', $certificate);

        return $action($certificate);
    }
}
