<?php

declare(strict_types=1);

namespace App\Services;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\NodeIterator;
use League\CommonMark\Parser\MarkdownParser;
use League\CommonMark\Renderer\HtmlRenderer;

class MarkdownRenderingService
{
    private readonly MarkdownConverter $converter;

    public function __construct()
    {
        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'renderer' => [
                'soft_break' => "<br />\n",
            ],
        ]);

        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new TableExtension);

        $this->converter = new MarkdownConverter($environment);
    }

    public function toHtml(string $markdown): string
    {
        $environment = $this->converter->getEnvironment();
        $parser = new MarkdownParser($environment);
        $document = $parser->parse($markdown);

        foreach ((new NodeIterator($document)) as $node) {
            if ($node instanceof Image) {
                $url = (string) $node->getUrl();
                if (! $this->isAllowedImageUrl($url)) {
                    $node->setUrl('');
                }
            }

            if ($node instanceof Link) {
                $url = (string) $node->getUrl();
                if ($this->isExternalLink($url)) {
                    $node->data->set('attributes/target', '_blank');
                    $node->data->set('attributes/rel', 'nofollow noopener noreferrer');
                }
            }
        }

        $renderer = new HtmlRenderer($environment);

        return $renderer->renderDocument($document)->getContent();
    }

    public function extractSnippet(string $body, string $keyword, int $padding = 80): string
    {
        if ($keyword === '') {
            return mb_substr($body, 0, $padding * 2);
        }

        $position = mb_stripos($body, $keyword);
        if ($position === false) {
            return mb_substr($body, 0, $padding * 2);
        }

        $start = max(0, $position - $padding);
        $end = min(mb_strlen($body), $position + mb_strlen($keyword) + $padding);

        $prefix = $start > 0 ? '…' : '';
        $suffix = $end < mb_strlen($body) ? '…' : '';

        return $prefix.mb_substr($body, $start, $end - $start).$suffix;
    }

    private function isAllowedImageUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        if (str_starts_with($url, '/storage/section-images/')) {
            return true;
        }

        if (str_starts_with($url, 'https://')) {
            return true;
        }

        return false;
    }

    private function isExternalLink(string $url): bool
    {
        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://');
    }
}
