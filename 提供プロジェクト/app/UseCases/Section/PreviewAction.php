<?php

declare(strict_types=1);

namespace App\UseCases\Section;

use App\Models\Section;
use App\Services\MarkdownRenderingService;

/**
 * Section 編集中の Markdown プレビュー生成ユースケース。
 * MarkdownRenderingService 経由でサニタイズ済 HTML を返却するのみで Storage / DB に副作用を持たない。
 * Section パラメータは認可委譲(SectionPolicy::preview)のために受け取る。
 */
final class PreviewAction
{
    public function __construct(private readonly MarkdownRenderingService $markdown) {}

    public function __invoke(Section $section, string $markdown): string
    {
        return $this->markdown->toHtml($markdown);
    }
}
