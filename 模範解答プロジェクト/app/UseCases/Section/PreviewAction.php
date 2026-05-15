<?php

namespace App\UseCases\Section;

use App\Models\Section;
use App\Services\MarkdownRenderingService;

class PreviewAction
{
    public function __construct(private readonly MarkdownRenderingService $markdown) {}

    public function __invoke(Section $section, string $markdown): string
    {
        return $this->markdown->toHtml($markdown);
    }
}
