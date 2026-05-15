<?php

namespace App\UseCases\Question;

use App\Enums\ContentStatus;
use App\Enums\QuestionDifficulty;
use App\Models\Certification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class IndexAction
{
    public function __invoke(
        Certification $certification,
        ?string $categoryId,
        ?QuestionDifficulty $difficulty,
        ?ContentStatus $status,
        bool $standaloneOnly,
        int $perPage = 20,
    ): LengthAwarePaginator {
        return $certification->questions()
            ->with(['section.chapter.part', 'category', 'options' => fn ($q) => $q->ordered()])
            ->when($categoryId, fn ($q) => $q->byCategory($categoryId))
            ->when($difficulty, fn ($q) => $q->difficulty($difficulty))
            ->when($status, fn ($q) => $q->where('status', $status->value))
            ->when($standaloneOnly, fn ($q) => $q->standalone())
            ->orderByDesc('updated_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
