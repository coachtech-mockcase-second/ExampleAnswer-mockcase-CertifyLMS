<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LearningSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * 学習セッションの close 処理を集中管理する Service。
 *
 * - `closeOpenSessions(User, asAutoClosed)`: 別 Section 表示時の auto-start から呼ばれる、当該ユーザーの全 open を閉じる
 * - `closeOne(LearningSession, asAutoClosed)`: 単一 session の close (Schedule Command の個別 close)
 * - `closeStaleSessions()`: Schedule Command 本体、started_at < now()-max_session_seconds の残骸を一括 close
 *
 * トランザクション境界は呼出元 Action 側で持つ前提で、本 Service 自体は囲まない。
 */
final class SessionCloseService
{
    public function closeOpenSessions(User $user, bool $asAutoClosed): int
    {
        $openSessions = LearningSession::query()
            ->forUser($user)
            ->open()
            ->lockForUpdate()
            ->get();

        $count = 0;
        foreach ($openSessions as $session) {
            $this->closeOne($session, $asAutoClosed);
            $count++;
        }

        return $count;
    }

    public function closeOne(LearningSession $session, bool $asAutoClosed): LearningSession
    {
        if ($session->ended_at !== null) {
            return $session;
        }

        $maxSeconds = (int) config('learning.max_session_seconds', 3600);
        $startedAt = CarbonImmutable::instance($session->started_at);
        $now = CarbonImmutable::now();
        $rawDuration = $now->diffInSeconds($startedAt);
        $duration = max(1, min($rawDuration, $maxSeconds));

        $endedAt = $duration < $rawDuration
            ? $startedAt->addSeconds($maxSeconds)
            : $now;

        $session->update([
            'ended_at' => $endedAt,
            'duration_seconds' => $duration,
            'auto_closed' => $asAutoClosed,
        ]);

        return $session->refresh();
    }

    public function closeStaleSessions(): int
    {
        $maxSeconds = (int) config('learning.max_session_seconds', 3600);
        $threshold = CarbonImmutable::now()->subSeconds($maxSeconds);

        return DB::transaction(function () use ($threshold, $maxSeconds) {
            $staleSessions = LearningSession::query()
                ->open()
                ->where('started_at', '<', $threshold)
                ->lockForUpdate()
                ->get();

            foreach ($staleSessions as $session) {
                $startedAt = CarbonImmutable::instance($session->started_at);
                $session->update([
                    'ended_at' => $startedAt->addSeconds($maxSeconds),
                    'duration_seconds' => $maxSeconds,
                    'auto_closed' => true,
                ]);
            }

            return $staleSessions->count();
        });
    }
}
