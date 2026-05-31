<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Models\Section;
use Illuminate\View\View;

/**
 * 教材 Section 閲覧画面で、フローティング AI 相談ウィジェット (layouts/app) に渡すページ文脈 (pageMeta) を供給する View Composer。
 *
 * ウィジェットは pageMeta から Section / 所属資格名を data-* 属性に焼き、メッセージ送信時の文脈として backend へ渡す。
 * この文脈供給は提供 frontend インフラの責務であり、Controller / Action (backend) には持たせない。
 */
final class SectionPageMetaComposer
{
    public function compose(View $view): void
    {
        $section = request()->route('section');

        if (! $section instanceof Section) {
            return;
        }

        $section->loadMissing('chapter.part.certification');

        $view->with('pageMeta', [
            'section_id' => $section->id,
            'section_title' => $section->title,
            'certification_name' => $section->chapter?->part?->certification?->name,
        ]);
    }
}
