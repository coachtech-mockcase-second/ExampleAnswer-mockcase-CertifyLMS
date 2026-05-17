<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Certificate;
use App\UseCases\Certificate\IssueAction;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Mpdf\Mpdf;

/**
 * 修了証 PDF を Blade テンプレート（`certificates/pdf.blade.php`）から mpdf でレンダリングし、
 * Storage(private) の `certificates/{ulid}.pdf` パスに保存する Service。
 *
 * mpdf を採用する理由は日本語フォント (CJK Extension A) を組み込みで持つこと。`autoScriptToLang`
 * と `autoLangToFont` を有効にすると、mpdf が文字スクリプトを判定して日本語/英数を自動でフォント切替する。
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

        $html = View::make('certificates.pdf', ['certificate' => $certificate])->render();

        $tempDir = storage_path('app/mpdf-temp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 22,
            'margin_right' => 22,
            'margin_top' => 22,
            'margin_bottom' => 22,
            'default_font' => 'sun-exta',
            'tempDir' => $tempDir,
        ]);

        $mpdf->WriteHTML($html);

        Storage::disk('private')->put(
            $certificate->pdf_path,
            $mpdf->Output('', 'S'),
        );
    }
}
