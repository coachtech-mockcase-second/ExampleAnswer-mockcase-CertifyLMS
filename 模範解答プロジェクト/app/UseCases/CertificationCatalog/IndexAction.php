<?php

namespace App\UseCases\CertificationCatalog;

use App\Enums\EnrollmentStatus;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class IndexAction
{
    /**
     * @param  array{category_id?: string|null, difficulty?: string|null, tab?: string|null}  $filter
     * @return array{catalog: Collection, enrolled: Collection, enrolled_ids: \Illuminate\Support\Collection}
     */
    public function __invoke(User $student, array $filter): array
    {
        $base = fn (): Builder => Certification::query()
            ->published()
            ->with(['category', 'coaches'])
            ->when(
                $filter['category_id'] ?? null,
                fn (Builder $q, string $id) => $q->where('category_id', $id),
            )
            ->when(
                $filter['difficulty'] ?? null,
                fn (Builder $q, string $d) => $q->where('difficulty', $d),
            );

        $enrolledIds = $student->enrollments()
            ->whereIn('status', [
                EnrollmentStatus::Learning->value,
                EnrollmentStatus::Paused->value,
                EnrollmentStatus::Passed->value,
                EnrollmentStatus::Failed->value,
            ])
            ->pluck('certification_id');

        $catalog = $base()->orderBy('name')->get();
        $enrolled = $base()->whereIn('id', $enrolledIds)->orderBy('name')->get();

        return [
            'catalog' => $catalog,
            'enrolled' => $enrolled,
            'enrolled_ids' => $enrolledIds,
        ];
    }
}
