<?php

declare(strict_types=1);

namespace App\UseCases\LearningSession;

use App\Services\SessionCloseService;

/**
 * 滞留 open セッションを一括 close する Action。Schedule Command `learning:close-stale-sessions` のエントリポイント。
 * SessionCloseService に処理を委譲し、close した件数を返す。
 */
final class CloseStaleSessionsAction
{
    public function __construct(
        private readonly SessionCloseService $sessionCloseService,
    ) {}

    public function __invoke(): int
    {
        return $this->sessionCloseService->closeStaleSessions();
    }
}
