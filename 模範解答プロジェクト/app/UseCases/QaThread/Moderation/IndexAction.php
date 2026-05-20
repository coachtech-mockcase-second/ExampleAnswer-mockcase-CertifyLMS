<?php

declare(strict_types=1);

namespace App\UseCases\QaThread\Moderation;

use App\Enums\QaThreadStatus;
use App\Models\QaThread;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * admin モデレーション用の質問掲示板一覧取得ユースケース。
 *
 * 公開用 IndexAction との違い:
 * - `Certification.status = published` フィルタを行わない (公開停止 / draft / archived も対象)
 * - `with_trashed=true` で SoftDelete 済スレッドも含めて取得 (履歴閲覧)
 * - 担当資格による絞り込みは行わない (admin は全資格に越境可能)
 */
final class IndexAction
{
    /**
     * @param array{certification_id?: ?string, status?: ?string, keyword?: ?string} $filters
     *
     * @return LengthAwarePaginator<QaThread>
     */
    public function __invoke(array $filters, bool $withTrashed = false): LengthAwarePaginator
    {
        $query = QaThread::query()
            ->with(['certification', 'user'])
            ->withCount('replies')
            ->orderByDesc('created_at');

        if ($withTrashed) {
            $query->withTrashed();
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
