<?php

declare(strict_types=1);

namespace App\UseCases\AnalyticsExport\User;

use App\Enums\UserStatus;
use App\Http\Controllers\Api\UserController;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * 運用エクスポート API のユーザー一覧取得ユースケース。
 *
 * `withdrawn` を除外し、`?role` / `?status` でフィルタした User を `created_at ASC` で
 * ページングして返す。並び順は Sheet 取り込み時の追記順序を安定化させるため固定。
 *
 * @see UserController::index()
 */
final class IndexAction
{
    /**
     * @param array{role?: string|null, status?: string|null, per_page?: int|null, page?: int|null} $validated
     *
     * @return LengthAwarePaginator<User>
     */
    public function __invoke(array $validated): LengthAwarePaginator
    {
        $perPage = (int) ($validated['per_page'] ?? 100);

        return User::query()
            ->whereNull('deleted_at')
            ->where('status', '!=', UserStatus::Withdrawn->value)
            ->when(
                $validated['role'] ?? null,
                fn ($query, $role) => $query->where('role', $role),
            )
            ->when(
                $validated['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status),
            )
            ->orderBy('created_at')
            ->paginate($perPage);
    }
}
