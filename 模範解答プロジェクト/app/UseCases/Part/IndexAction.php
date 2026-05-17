<?php

declare(strict_types=1);

namespace App\UseCases\Part;

use App\Models\Certification;
use App\Models\Part;
use Illuminate\Database\Eloquent\Collection;

/**
 * 指定資格配下の Part 一覧を、配下 Chapter の Section 件数付きで Eager Load して返すユースケース。
 */
final class IndexAction
{
    /**
     * @return Collection<int, Part>
     */
    public function __invoke(Certification $certification): Collection
    {
        return $certification->parts()
            ->with(['chapters' => fn ($q) => $q->ordered()->withCount('sections')])
            ->ordered()
            ->get();
    }
}
