<?php

namespace App\UseCases\Certification;

use App\Enums\CertificationDifficulty;
use App\Enums\CertificationStatus;
use App\Models\Certification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class IndexAction
{
    public function __invoke(
        ?string $keyword,
        ?CertificationStatus $status,
        ?string $categoryId,
        ?CertificationDifficulty $difficulty,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = Certification::query()
            ->with('category')
            ->withCount(['coaches', 'certificates']);

        $query->keyword($keyword);

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        if ($categoryId !== null && $categoryId !== '') {
            $query->where('category_id', $categoryId);
        }

        if ($difficulty !== null) {
            $query->where('difficulty', $difficulty->value);
        }

        $driver = $query->getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $query->orderByRaw("FIELD(status, 'published', 'draft', 'archived')");
        } else {
            $query->orderByRaw(
                "CASE status WHEN 'published' THEN 1 WHEN 'draft' THEN 2 WHEN 'archived' THEN 3 ELSE 4 END"
            );
        }

        return $query
            ->orderByDesc('updated_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
