<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\UseCases\Certificate\DownloadAction;
use App\UseCases\Certificate\ShowAction;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificateController extends Controller
{
    public function show(Certificate $certificate, ShowAction $action): View
    {
        $this->authorize('view', $certificate);

        return view('certificates.show', [
            'certificate' => $action($certificate),
        ]);
    }

    public function download(Certificate $certificate, DownloadAction $action): StreamedResponse
    {
        $this->authorize('download', $certificate);

        return $action($certificate);
    }
}
