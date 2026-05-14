<?php

namespace App\Services;

use App\Models\Certificate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class CertificatePdfGenerator
{
    /**
     * Certificate に対応する PDF を Blade テンプレートから生成し、Storage(private) に保存する。
     */
    public function generate(Certificate $certificate): void
    {
        $certificate->loadMissing(['user', 'certification.category', 'enrollment']);

        $pdf = Pdf::loadView('certificates.pdf', ['certificate' => $certificate])
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont' => 'IPAGothic',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
            ]);

        Storage::disk('private')->put(
            $certificate->pdf_path,
            $pdf->output(),
        );
    }
}
