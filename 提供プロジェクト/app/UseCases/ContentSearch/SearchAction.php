<?php

declare(strict_types=1);

namespace App\UseCases\ContentSearch;

use App\Enums\ContentStatus;
use App\Models\Section;
use App\Models\User;
use App\Services\MarkdownRenderingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as PaginatorImpl;

/**
 * 受講生向け教材内 Section 全文検索ユースケース。
 *
 * 検索結果は以下の条件で絞り込む(cascade visibility):
 * - 受講生が `enrollments` で登録済の資格内
 * - Section / 親 Chapter / 親 Part がすべて Published 状態
 * - 親 Certification が SoftDelete されていない
 * 空キーワード / 未登録資格指定時は結果ゼロ件のレスポンスを返し、不要な検索クエリを発行しない。
 */
final class SearchAction
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
                    ->where('status', ContentStatus::Published->value),
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
