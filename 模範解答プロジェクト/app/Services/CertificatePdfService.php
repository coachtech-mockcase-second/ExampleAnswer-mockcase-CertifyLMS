<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Certificate;
use App\UseCases\Certificate\IssueAction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * 修了証 PDF を Blade テンプレートからレンダリングし、Storage(private) に保存する Service。
 * PDF 生成失敗時は IssueAction 側で try-catch + Storage rollback されることを前提に、本 Service 自体は副作用のロールバックを持たない。
 *
 * `final` 不採用: IssueActionTest で `Mockery::mock(CertificatePdfService::class)` を使うため（Mockery は final クラスを mock できない）。
 * Service 共通の `final` 規約は `backend-types-and-docblocks.md` で「Mockery でテストする場合は不採用可」の例外を許容。
 *
 * @see IssueAction::__invoke()
 */
class CertificatePdfService
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
