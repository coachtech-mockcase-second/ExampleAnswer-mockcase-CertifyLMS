<?php

namespace App\UseCases\Certificate;

use App\Enums\EnrollmentStatus;
use App\Exceptions\Certification\EnrollmentNotPassedException;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\CertificatePdfGenerator;
use App\Services\CertificateSerialNumberService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IssueAction
{
    public function __construct(
        private CertificateSerialNumberService $serialService,
        private CertificatePdfGenerator $pdfGenerator,
    ) {}

    /**
     * 修了証を発行する。
     * 冪等性: 同一 Enrollment で 2 回呼ばれた場合、既存 Certificate を返却し副作用なし。
     *
     * @throws EnrollmentNotPassedException Enrollment が status=passed でない / passed_at が null
     */
    public function __invoke(Enrollment $enrollment, User $admin): Certificate
    {
        if ($enrollment->status !== EnrollmentStatus::Passed || $enrollment->passed_at === null) {
            throw new EnrollmentNotPassedException();
        }

        return DB::transaction(function () use ($enrollment, $admin) {
            $existing = Certificate::lockForUpdate()
                ->where('enrollment_id', $enrollment->id)
                ->first();

            if ($existing) {
                return $existing;
            }

            $certificate = Certificate::create([
                'user_id' => $enrollment->user_id,
                'enrollment_id' => $enrollment->id,
                'certification_id' => $enrollment->certification_id,
                'serial_no' => $this->serialService->generate(),
                'issued_at' => now(),
                'pdf_path' => 'certificates/'.Str::ulid().'.pdf',
                'issued_by_user_id' => $admin->id,
            ]);

            $this->pdfGenerator->generate($certificate);

            return $certificate;
        });
    }
}
