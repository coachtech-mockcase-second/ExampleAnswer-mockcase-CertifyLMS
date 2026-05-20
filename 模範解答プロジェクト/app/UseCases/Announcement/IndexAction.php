<?php

declare(strict_types=1);

namespace App\UseCases\Announcement;

use App\Models\Announcement;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * 管理者お知らせの配信履歴一覧を返す Action。
 * created_at 降順で paginate、関連 (createdBy / targetCertification / targetUser) を eager load する。
 */
final class IndexAction
{
    /**
     * @return array{announcements: LengthAwarePaginator}
     */
    public function __invoke(): array
    {
        $announcements = Announcement::query()
            ->with(['createdBy', 'targetCertification', 'targetUser'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return [
            'announcements' => $announcements,
        ];
    }
}
