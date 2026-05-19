<?php

declare(strict_types=1);

namespace App\UseCases\Admin\AdminAnnouncement;

use App\Models\AdminAnnouncement;

/**
 * 管理者お知らせの詳細表示用 Action。関連を eager load して view に渡す。
 */
final class ShowAction
{
    /**
     * @return array{announcement: AdminAnnouncement}
     */
    public function __invoke(AdminAnnouncement $announcement): array
    {
        $announcement->load(['createdBy', 'targetCertification', 'targetUser']);

        return [
            'announcement' => $announcement,
        ];
    }
}
