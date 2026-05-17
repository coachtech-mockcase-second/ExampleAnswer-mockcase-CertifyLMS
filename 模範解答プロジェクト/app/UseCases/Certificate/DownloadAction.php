<?php

declare(strict_types=1);

namespace App\UseCases\Certificate;

use App\Exceptions\Certification\CertificatePdfNotFoundException;
use App\Models\Certificate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 修了証 PDF を Storage(private) からブラウザにストリーミングダウンロードするユースケース。
 * `pdf_path` のファイルが存在しない場合は CertificatePdfNotFoundException（404）。
 */
final class DownloadAction
{
    /**
     * @throws CertificatePdfNotFoundException PDF ファイルが Storage 上に存在しない
     */
    public function __invoke(Certificate $certificate): StreamedResponse
    {
        $disk = Storage::disk('private');

        if (! $disk->exists($certificate->pdf_path)) {
            throw new CertificatePdfNotFoundException;
        }

        return $disk->download(
            $certificate->pdf_path,
            "certificate-{$certificate->serial_no}.pdf",
            ['Content-Type' => 'application/pdf'],
        );
    }
}
