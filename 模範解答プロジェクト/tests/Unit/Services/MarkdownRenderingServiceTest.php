<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\MarkdownRenderingService;
use Tests\TestCase;

class MarkdownRenderingServiceTest extends TestCase
{
    public function test_strips_script_tags(): void
    {
        $service = new MarkdownRenderingService;
        $html = $service->toHtml("<script>alert(1)</script>\n\n本文");

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('alert(1)', $html);
    }

    public function test_rejects_javascript_url(): void
    {
        $service = new MarkdownRenderingService;
        $html = $service->toHtml('[click](javascript:alert(1))');

        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_external_links_get_rel_and_target(): void
    {
        $service = new MarkdownRenderingService;
        $html = $service->toHtml('[link](https://example.com)');

        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('rel="nofollow noopener noreferrer"', $html);
    }

    public function test_code_block_rendered_as_pre_code(): void
    {
        $service = new MarkdownRenderingService;
        $html = $service->toHtml("```\necho 1;\n```");

        $this->assertStringContainsString('<pre>', $html);
        $this->assertStringContainsString('<code', $html);
    }

    public function test_image_with_storage_path_allowed(): void
    {
        $service = new MarkdownRenderingService;
        $html = $service->toHtml('![alt](/storage/section-images/foo.png)');

        $this->assertStringContainsString('src="/storage/section-images/foo.png"', $html);
    }

    public function test_image_with_javascript_url_blocked(): void
    {
        $service = new MarkdownRenderingService;
        $html = $service->toHtml('![alt](javascript:alert(1))');

        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_extract_snippet_includes_keyword_context(): void
    {
        $service = new MarkdownRenderingService;
        $body = str_repeat('A', 100).'KEYWORD'.str_repeat('B', 100);

        $snippet = $service->extractSnippet($body, 'KEYWORD', 20);

        $this->assertStringContainsString('KEYWORD', $snippet);
        $this->assertLessThan(80, mb_strlen($snippet));
    }

    public function test_extract_snippet_falls_back_when_keyword_missing(): void
    {
        $service = new MarkdownRenderingService;
        $body = 'short content';

        $snippet = $service->extractSnippet($body, 'absent', 80);

        $this->assertSame('short content', $snippet);
    }
}
