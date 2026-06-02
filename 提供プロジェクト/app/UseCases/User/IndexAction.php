<?php

declare(strict_types=1);

namespace App\UseCases\User;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 管理者向けユーザー一覧 (`UserController::index`) のクエリ Action。
 *
 * role / status / keyword フィルタを適用し、in_progress → invited → graduated → withdrawn のステータス
 * 優先順位 + created_at 降順で paginate する。`status=withdrawn` 指定時のみ soft delete 済の User を含める。
 */
final class IndexAction
{
    public function __invoke(
        ?string $keyword,
        ?UserRole $role,
        ?UserStatus $status,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = User::query();

        $query->withTrashed();

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
            ->with('plan')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
