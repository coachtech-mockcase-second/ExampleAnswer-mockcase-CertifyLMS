<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Enums\CertificationStatus;
use App\Enums\QaThreadStatus;
use App\Enums\UserRole;
use App\Http\Controllers\QaThreadController;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 質問掲示板の一覧取得ユースケース。
 *
 * ロールごとに取得対象を絞る:
 *
 * - admin: 全資格・全状態 (ただし本 Action は公開 (`/qa-board`) 経由のみ、admin はそもそも `/admin/qa-board` を使う想定なので実質呼ばれない)
 * - coach: 担当資格のスレッドのみ。担当外資格を `certification_id` フィルタで指定された場合は Controller / Policy 側で 403 を返す前提
 * - student: 公開資格 (`status = published`) のスレッドのみ
 *
 * フィルタは `certification_id` / `status` (resolved / unresolved) / `keyword` (title / body / replies.body の LIKE)。
 * `with(['certification', 'user'])` と `withCount('replies')` で一覧表示の N+1 を排除する。
 *
 * @see QaThreadController::index()
 */
final class IndexAction
{
    /**
     * @param array{certification_id?: ?string, status?: ?string, keyword?: ?string} $filters
     *
     * @return LengthAwarePaginator<QaThread>
     */
    public function __invoke(User $viewer, array $filters): LengthAwarePaginator
    {
        $query = QaThread::query()
            ->with(['certification', 'user'])
            ->withCount('replies')
            ->orderByDesc('created_at');

        if ($viewer->role === UserRole::Coach) {
            $query->whereIn('certification_id', $viewer->coachingCertificationIds());
        } elseif ($viewer->role === UserRole::Student) {
            $query->whereHas('certification', function ($q): void {
                $q->where('status', CertificationStatus::Published);
            });
        }

        if (! empty($filters['certification_id'])) {
            $query->where('certification_id', $filters['certification_id']);
        }

        if (! empty($filters['status'])) {
            $statusEnum = match ($filters['status']) {
                'resolved' => QaThreadStatus::Resolved,
                'unresolved' => QaThreadStatus::Open,
                default => null,
            };

            if ($statusEnum !== null) {
                $query->where('status', $statusEnum);
            }
        }

        if (! empty($filters['keyword'])) {
            $keyword = $filters['keyword'];
            $query->where(function ($q) use ($keyword): void {
                $q->where('title', 'like', "%{$keyword}%")
                    ->orWhere('body', 'like', "%{$keyword}%")
                    ->orWhereHas('replies', function ($qr) use ($keyword): void {
                        $qr->where('body', 'like', "%{$keyword}%");
                    });
            });
        }

        return $query->paginate(20)->withQueryString();
    }
}
