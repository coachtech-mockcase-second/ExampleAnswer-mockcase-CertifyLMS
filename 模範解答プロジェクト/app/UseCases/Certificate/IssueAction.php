<?php

declare(strict_types=1);

namespace App\UseCases\Certificate;

use App\Enums\EnrollmentStatus;
use App\Exceptions\Certification\CertificateAlreadyIssuedException;
use App\Exceptions\Certification\CertificateGenerationFailedException;
use App\Exceptions\Certification\EnrollmentNotPassedException;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Services\CertificatePdfService;
use App\Services\CertificateSerialNumberService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * 修了証を発行するユースケース。受講生自己発火型の修了処理 `\App\UseCases\Enrollment\ReceiveCertificateAction` から呼び出される。
 *
 * 業務分岐:
 * - Enrollment が `status=passed` + `passed_at != null` でない: EnrollmentNotPassedException（409）
 * - 同一 Enrollment に対する二重呼出: CertificateAlreadyIssuedException（409、事前 lockForUpdate + exists で検出）
 * - PDF 生成失敗: CertificateGenerationFailedException（500、DB ROLLBACK + Storage 保険削除あり）
 *
 * 採番 + INSERT + PDF 生成 + Storage 保存はすべて `DB::transaction()` 内で実行する。
 *
 * @see \App\Http\Controllers\CertificateController
 */
final class IssueAction
{
    public function __construct(
        private readonly CertificateSerialNumberService $serialService,
        private readonly CertificatePdfService $pdfService,
    ) {}

    /**
     * @throws EnrollmentNotPassedException 受講登録が修了状態ではない
     * @throws CertificateAlreadyIssuedException 同一 Enrollment で修了証が既発行
     * @throws CertificateGenerationFailedException PDF 生成失敗（DB は ROLLBACK 済、Storage は明示削除済）
     */
    public function __invoke(Enrollment $enrollment): Certificate
    {
        if ($enrollment->status !== EnrollmentStatus::Passed || $enrollment->passed_at === null) {
            throw new EnrollmentNotPassedException;
        }

        return DB::transaction(function () use ($enrollment) {
            // 二重発行ガード: lockForUpdate で同時呼出を直列化し、enrollment_id UNIQUE 違反を例外メッセージ判別ではなく事前 SELECT で確定検出する
            $existing = Certificate::query()
                ->where('enrollment_id', $enrollment->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                throw new CertificateAlreadyIssuedException;
            }

            $certificate = Certificate::create([
                'user_id' => $enrollment->user_id,
                'enrollment_id' => $enrollment->id,
                'certification_id' => $enrollment->certification_id,
                'serial_no' => $this->serialService->generate(),
                'pdf_path' => 'certificates/'.Str::ulid().'.pdf',
                'issued_at' => now(),
            ]);

            try {
                $this->pdfService->generate($certificate);
            } catch (\Throwable $e) {
                // PDF 生成失敗時の Storage 保険削除: DB は DB::transaction の ROLLBACK で巻き戻るが、
                // Storage に部分書き込みされた可能性のあるファイルを明示削除し orphan を残さない
                Storage::disk('private')->delete($certificate->pdf_path);
                throw new CertificateGenerationFailedException(previous: $e);
            }

            return $certificate;
        });
    }
}
