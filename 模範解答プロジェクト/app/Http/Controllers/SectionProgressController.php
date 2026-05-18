<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Section;
use App\Models\SectionProgress;
use App\UseCases\SectionProgress\MarkReadAction;
use App\UseCases\SectionProgress\UnmarkReadAction;
use Illuminate\Http\RedirectResponse;

/**
 * Section 読了マークの POST / DELETE エンドポイントを提供する Controller。
 * 受講生本人のみ操作可、cascade visibility と Enrollment 状態の検証は Action 側のドメイン例外で扱う。
 *
 * 読了成功時は Section 詳細画面へ redirect し、session flash `section_just_completed=$section->id` を付与して
 * Blade 側で「読了おめでとうモーダル」を自動表示する。
 */
class SectionProgressController extends Controller
{
    public function markRead(Section $section, MarkReadAction $action): RedirectResponse
    {
        $this->authorize('create', SectionProgress::class);

        $action(auth()->user(), $section);

        return redirect()
            ->route('learning.sections.show', $section)
            ->with('section_just_completed', $section->id);
    }

    public function unmarkRead(Section $section, UnmarkReadAction $action): RedirectResponse
    {
        $action(auth()->user(), $section);

        return redirect()
            ->route('learning.sections.show', $section)
            ->with('success', '読了マークを取り消しました。');
    }
}
