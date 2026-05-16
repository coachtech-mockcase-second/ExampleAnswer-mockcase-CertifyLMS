<?php

declare(strict_types=1);

namespace App\UseCases\ContentSearch;

use App\Enums\ContentStatus;
use App\Models\Section;
use App\Models\User;
use App\Services\MarkdownRenderingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as PaginatorImpl;

class SearchAction
{
    public function __construct(private readonly MarkdownRenderingService $markdown) {}

    /**
     * @return array{paginator: LengthAwarePaginator, snippets: array<string, string>}
     */
    public function __invoke(
        User $student,
        string $certificationId,
        ?string $keyword,
        int $perPage = 20,
    ): array {
        if ($keyword === null || trim($keyword) === '') {
            return [
                'paginator' => new PaginatorImpl([], 0, $perPage),
                'snippets' => [],
            ];
        }

        $enrolled = $student->enrollments()
            ->where('certification_id', $certificationId)
            ->exists();
        if (! $enrolled) {
            return [
                'paginator' => new PaginatorImpl([], 0, $perPage),
                'snippets' => [],
            ];
        }

        $like = '%'.$keyword.'%';

        $paginator = Section::query()
            ->with(['chapter.part.certification'])
            ->whereHas(
                'chapter.part',
                fn ($q) => $q->where('certification_id', $certificationId)
                    ->where('status', ContentStatus::Published->value)
                    ->whereHas('certification', fn ($cq) => $cq->whereNull('deleted_at')),
            )
            ->whereHas('chapter', fn ($q) => $q->where('status', ContentStatus::Published->value))
            ->where('status', ContentStatus::Published->value)
            ->where(function ($q) use ($like) {
                $q->where('title', 'LIKE', $like)->orWhere('body', 'LIKE', $like);
            })
            ->orderBy('updated_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        $snippets = [];
        foreach ($paginator->items() as $section) {
            $snippets[$section->id] = $this->markdown->extractSnippet($section->body, $keyword);
        }

        return [
            'paginator' => $paginator,
            'snippets' => $snippets,
        ];
    }
}
