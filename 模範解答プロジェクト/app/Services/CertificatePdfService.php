<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Certificate;
use App\UseCases\Certificate\IssueAction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * 修了証 PDF を Blade テンプレート（`certificates/pdf.blade.php`）から DomPDF でレンダリングし、
 * Storage(private) の `certificates/{ulid}.pdf` パスに保存する Service。
 *
 * `final` 不採用: `IssueAction` のテストで `Mockery::mock(CertificatePdfService::class)` するため
 * （Mockery は final クラスを mock できない業界慣習に従う）。
 *
 * PDF 生成失敗時の Storage rollback は呼出側（`IssueAction`）の try-catch で行うため、本 Service は副作用ロールバックを持たない。
 *
 * @see IssueAction
 */
class CertificatePdfService
{
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
