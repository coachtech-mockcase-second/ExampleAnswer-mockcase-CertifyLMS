<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\UseCases\Enrollment\ReceiveCertificateAction;
use Illuminate\Http\RedirectResponse;

/**
 * 受講生による「修了証を受け取る」自己発火 Controller。
 *
 * 認可は EnrollmentPolicy::receiveCertificate(本人 + status==Learning) で完結。
 * 成功時は修了証詳細画面(certificates.show) へリダイレクト。
 */
class ReceiveCertificateController extends Controller
{
    public function store(Enrollment $enrollment, ReceiveCertificateAction $action): RedirectResponse
    {
        $this->authorize('receiveCertificate', $enrollment);

        $certificate = $action($enrollment);

        return redirect()
            ->route('enrollments.show', $enrollment)
            ->with('success', '修了証を発行しました。おめでとうございます！')
            ->with('certificate_download_url', route('certificates.download', $certificate));
    }
}
