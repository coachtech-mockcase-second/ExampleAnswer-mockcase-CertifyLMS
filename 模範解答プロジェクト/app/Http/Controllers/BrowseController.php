<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\UseCases\Learning\IndexAction;
use App\UseCases\Learning\ShowChapterAction;
use App\UseCases\Learning\ShowEnrollmentAction;
use App\UseCases\Learning\ShowPartAction;
use App\UseCases\Learning\ShowSectionAction;
use App\UseCases\LearningSession\StartAction as StartSessionAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * 受講生向け教材ブラウジング Controller。
 * /learning(empty-state)→/learning/enrollments/{enrollment}→/learning/parts/{part}→
 * /learning/chapters/{chapter}→/learning/sections/{section} の 5 階層動線を提供する。
 *
 * showSection は表示と同時に LearningSession のサーバ側 auto-start を呼び、別 Section 遷移時の自動切替を行う
 * (JS / 公開 HTTP エンドポイント経由ではない)。auto-start の失敗は表示の妨げにしないため例外吸収する。
 */
class BrowseController extends Controller
{
    public function index(IndexAction $action): View
    {
        return view('learning.index', $action(auth()->user()));
    }

    public function showEnrollment(Enrollment $enrollment, Request $request, ShowEnrollmentAction $action): View
    {
        $this->authorize('view', $enrollment);

        $tab = $request->query('tab') === 'quizzes' ? 'quizzes' : 'contents';

        return view('learning.enrollments.show', $action($enrollment, $tab));
    }

    public function showPart(Part $part, ShowPartAction $action): View
    {
        $user = auth()->user();

        if (! $user?->can('learning.part.view', $part)) {
            abort(403);
        }

        return view('learning.parts.show', $action($part, $user));
    }

    public function showChapter(Chapter $chapter, ShowChapterAction $action): View
    {
        $user = auth()->user();

        if (! $user?->can('learning.chapter.view', $chapter)) {
            abort(403);
        }

        return view('learning.chapters.show', $action($chapter, $user));
    }

    public function showSection(Section $section, ShowSectionAction $action, StartSessionAction $startSession): View
    {
        $user = auth()->user();

        if (! $user?->can('learning.section.view', $section)) {
            abort(403);
        }

        $data = $action($section, $user);

        try {
            $startSession($user, $section);
        } catch (\Throwable $exception) {
            // セッション開始の失敗は学習行為の妨げを避けるためログのみ。
            // 残骸 open は Schedule Command(learning:close-stale-sessions)で後追い回収する。
            Log::warning('LearningSession auto-start failed', [
                'user_id' => $user->id,
                'section_id' => $section->id,
                'exception' => $exception->getMessage(),
            ]);
        }

        return view('learning.sections.show', $data);
    }
}
