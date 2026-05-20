<?php

declare(strict_types=1);

namespace App\UseCases\Announcement;

use App\Models\Announcement;

/**
 * 管理者お知らせの詳細表示用 Action。関連を eager load して view に渡す。
 */
final class ShowAction
{
    /**
     * @return array{announcement: Announcement}
     */
    public function __invoke(Announcement $announcement): array
    {
        $announcement->load(['createdBy', 'targetCertification', 'targetUser']);

        return [
            'announcement' => $announcement,
        ];
    }
}
