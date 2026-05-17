<?php

declare(strict_types=1);

namespace App\UseCases\User;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class IndexAction
{
    public function __invoke(
        ?string $keyword,
        ?UserRole $role,
        ?UserStatus $status,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = User::query();

        if ($status === UserStatus::Withdrawn) {
            $query->withTrashed();
        }

        if ($keyword !== null && $keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'LIKE', "%{$keyword}%")
                    ->orWhere('email', 'LIKE', "%{$keyword}%");
            });
        }

        if ($role !== null) {
            $query->where('role', $role->value);
        }

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        $driver = $query->getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $query->orderByRaw("FIELD(status, 'in_progress', 'invited', 'graduated', 'withdrawn')");
        } else {
            $query->orderByRaw(
                "CASE status WHEN 'in_progress' THEN 1 WHEN 'invited' THEN 2 WHEN 'graduated' THEN 3 WHEN 'withdrawn' THEN 4 ELSE 5 END"
            );
        }

        return $query
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
