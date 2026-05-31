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
 * 成功時は受講登録詳細画面(enrollments.show) へリダイレクトする。修了証 PDF の DL 導線は
 * 同画面の修了証受領パネル(修了済の常設表示)が担うため、ここでは成功フラッシュのみ渡す。
 */
class ReceiveCertificateController extends Controller
{
    public function store(Enrollment $enrollment, ReceiveCertificateAction $action): RedirectResponse
    {
        $this->authorize('receiveCertificate', $enrollment);

        $action($enrollment);

        return redirect()
            ->route('enrollments.show', $enrollment)
            ->with('success', '修了証を発行しました。おめでとうございます！');
    }
}
