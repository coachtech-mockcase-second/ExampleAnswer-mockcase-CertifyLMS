<?php

declare(strict_types=1);

namespace App\UseCases\Dashboard\ViewModels;

use App\Models\ChatRoom;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\QaThread;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * コーチダッシュボード全体の ViewModel。Blade はプロパティアクセスのみで描画する。
 *
 * 担当受講生一覧は表示専用(ソートなし、最終活動日は担当受講生ごとに取得済)。
 * Service 例外で取得失敗したカウンタ / 一覧は nullable プロパティに null が入り、Blade で empty-state にフォールバックする。
 */
final readonly class CoachDashboardViewModel
{
    /**
     * @param EloquentCollection<int, Enrollment> $assignedEnrollments 担当資格に登録した受講生(certification.coaches 経由) + last_activity_at
     * @param EloquentCollection<int, Meeting> $todayAndTomorrowMeetings 今日 / 明日の面談予約
     * @param ?Collection<int, ChatRoom> $recentUnreadChatRooms 未読 chat ルーム上位 5(取得失敗時 null)
     * @param ?Collection<int, QaThread> $recentQaThreads 未回答 Q&A 上位 5(取得失敗時 null)
     */
    public function __construct(
        public EloquentCollection $assignedEnrollments,
        public EloquentCollection $todayAndTomorrowMeetings,
        public ?int $unreadChatCount,
        public ?Collection $recentUnreadChatRooms,
        public ?int $unansweredQaCount,
        public ?Collection $recentQaThreads,
    ) {}
}
