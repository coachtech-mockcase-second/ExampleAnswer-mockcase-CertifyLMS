<?php

declare(strict_types=1);

namespace App\UseCases\AdminMockExamSession;

use App\Enums\MockExamSessionStatus;
use App\Enums\UserRole;
use App\Models\MockExamSession;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * admin / coach 用の模試受験セッション一覧を取得するユースケース。
 *
 * - admin: 全セッション
 * - coach: 担当資格(certification.coaches)配下のセッションのみ
 * フィルタ: certification_id / user_id / status / pass
 */
final class IndexAction
{
    public function __invoke(
        User $auth,
        ?string $certificationId = null,
        ?string $userId = null,
        ?MockExamSessionStatus $status = null,
        ?bool $pass = null,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = MockExamSession::query()
            ->with(['mockExam.certification', 'user', 'enrollment']);

        if ($auth->role === UserRole::Coach) {
            $query->whereHas(
                'mockExam.certification.coaches',
                fn ($q) => $q->where('users.id', $auth->id),
            );
        }

        if ($certificationId !== null && $certificationId !== '') {
            $query->whereHas('mockExam', fn ($q) => $q->where('certification_id', $certificationId));
        }

        if ($userId !== null && $userId !== '') {
            $query->where('user_id', $userId);
        }

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        if ($pass !== null) {
            $query->where('pass', $pass);
        }

        return $query
            ->orderByDesc('updated_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
