<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\MockExamSessionStatus;
use App\Http\Requests\AdminMockExamSession\IndexRequest;
use App\Models\Certification;
use App\Models\MockExamSession;
use App\UseCases\AdminMockExamSession\IndexAction;
use App\UseCases\AdminMockExamSession\ShowAction;
use Illuminate\View\View;

/**
 * admin / coach 用の模試受験セッション閲覧 Controller。
 *
 * coach は担当資格(certification.coaches) 配下のみ閲覧可。
 */
class AdminMockExamSessionController extends Controller
{
    public function index(IndexRequest $request, IndexAction $action): View
    {
        $validated = $request->validated();
        $pass = isset($validated['pass'])
            ? in_array($validated['pass'], ['1', 'true', true], true)
            : null;
        $status = isset($validated['status'])
            ? MockExamSessionStatus::from($validated['status'])
            : null;

        $sessions = $action(
            auth: $request->user(),
            certificationId: $validated['certification_id'] ?? null,
            userId: $validated['user_id'] ?? null,
            status: $status,
            pass: $pass,
        );

        return view('admin.mock-exam-sessions.index', [
            'sessions' => $sessions,
            'certifications' => Certification::query()->orderBy('name')->get(),
            'certificationId' => $validated['certification_id'] ?? '',
            'userId' => $validated['user_id'] ?? '',
            'statusFilter' => $validated['status'] ?? '',
            'pass' => $validated['pass'] ?? '',
        ]);
    }

    public function show(MockExamSession $session, ShowAction $action): View
    {
        $this->authorize('view', $session);

        $data = $action($session);

        return view('admin.mock-exam-sessions.show', [
            'session' => $data['session'],
            'heatmap' => $data['heatmap'],
            'passProbabilityBand' => $data['passProbabilityBand'],
        ]);
    }
}
