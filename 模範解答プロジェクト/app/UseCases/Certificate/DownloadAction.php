<?php

namespace App\UseCases\Certificate;

use App\Exceptions\Certification\CertificatePdfNotFoundException;
use App\Models\Certificate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadAction
{
    public function __invoke(Certificate $certificate): StreamedResponse
    {
        $disk = Storage::disk('private');

        if (! $disk->exists($certificate->pdf_path)) {
            throw new CertificatePdfNotFoundException();
        }

        return $disk->download(
            $certificate->pdf_path,
            "certificate-{$certificate->serial_no}.pdf",
            ['Content-Type' => 'application/pdf'],
        );
    }
}
