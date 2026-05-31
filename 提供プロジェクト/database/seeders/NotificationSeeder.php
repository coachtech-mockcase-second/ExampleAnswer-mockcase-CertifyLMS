<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MeetingReminderWindow;
use App\Enums\MeetingStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\ChatMessage;
use App\Models\Meeting;
use App\Models\QaReply;
use App\Models\User;
use App\Notifications\Announcement\AnnouncementNotification;
use App\Notifications\Chat\ChatMessageReceivedNotification;
use App\Notifications\Mentoring\MeetingCanceledNotification;
use App\Notifications\Mentoring\MeetingReminderNotification;
use App\Notifications\Mentoring\MeetingReservedNotification;
use App\Notifications\QaBoard\QaReplyReceivedNotification;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Database Notification の開発用シーダー。
 *
 * **設計思想(Seeder 業界標準: type 網羅 + 既読/未読 半々)**:
 *
 * 1. **type 網羅**: AnnouncementNotification / ChatMessageReceivedNotification / MeetingReservedNotification /
 *    MeetingReminderNotification / MeetingCanceledNotification / QaReplyReceivedNotification の 6 種を混在投入。
 *    サイドバーバッジ / TopBar ベルポップオーバー / 通知一覧画面の各表示パターンを実機確認できるようにする。
 * 2. **既読/未読 半々**: read_at を null と過去日付で半々に振り、未読バッジ件数 / 未読フィルタが動くことを担保する。
 * 3. **固定 student / 固定 coach への手厚い投入**: 動作確認・PR スクショで安定して参照できるよう、
 *    両アカウントには 6 種すべてのタイプを最低 1 件ずつ投入する。
 *
 * 実装上の選択: `Notification::send()` 経由ではなく `notifications` テーブルへの直接 INSERT で投入する。
 * 理由は (1) Queue / Mail / Broadcast 等の副作用を発火させない (2) 既存 Seeder で投入済のドメインデータに紐づく
 * 通知行を後置きで作成する用途に適する、の 2 点。
 *
 * 依存順序: `UserSeeder` → `CertificationSeeder` → `EnrollmentSeeder` → `MentoringSeeder` → `ChatSeeder`
 *   → `QaBoardSeeder` → `AnnouncementSeeder` → 本 Seeder。
 */
final class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $fixedStudent = User::query()->where('email', 'student@certify-lms.test')->first();
        $fixedCoach = User::query()->where('email', 'coach@certify-lms.test')->first();

        $demoStudents = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->whereNot('email', 'student@certify-lms.test')
            ->orderBy('created_at')
            ->take(6)
            ->get();

        $demoCoaches = User::query()
            ->where('role', UserRole::Coach->value)
            ->where('status', UserStatus::InProgress->value)
            ->whereNot('email', 'coach@certify-lms.test')
            ->orderBy('created_at')
            ->get();

        if ($fixedStudent !== null) {
            $this->seedAllTypesForStudent($fixedStudent);
        }
        if ($fixedCoach !== null) {
            $this->seedAllTypesForCoach($fixedCoach);
        }

        foreach ($demoStudents as $i => $student) {
            $this->seedMixedNotificationsForStudent($student, baseDaysAgo: $i + 1);
        }
        foreach ($demoCoaches as $i => $coach) {
            $this->seedMixedNotificationsForCoach($coach, baseDaysAgo: $i + 1);
        }
    }

    /**
     * 固定 student に 6 種のタイプを最低 1 件ずつ投入する。
     */
    private function seedAllTypesForStudent(User $student): void
    {
        $this->insertAnnouncementNotification($student, daysAgo: 1, read: false);
        $this->insertChatMessageNotification($student, daysAgo: 2, read: true);
        $this->insertMeetingReservedNotificationForStudent($student, daysAgo: 3, read: true);
        $this->insertMeetingReminderNotification($student, daysAgo: 1, read: false, window: MeetingReminderWindow::OneHourBefore);
        $this->insertMeetingCanceledNotification($student, daysAgo: 5, read: true);
        $this->insertQaReplyNotification($student, daysAgo: 4, read: false);
    }

    /**
     * 固定 coach に 6 種のタイプを最低 1 件ずつ投入する。
     * MeetingReserved はコーチ宛が定常運用、AnnouncementNotification はコーチも受け取らない仕様だが
     * dev 環境の type 網羅確認用に投入する。
     */
    private function seedAllTypesForCoach(User $coach): void
    {
        $this->insertAnnouncementNotification($coach, daysAgo: 2, read: true);
        $this->insertChatMessageNotification($coach, daysAgo: 1, read: false);
        $this->insertMeetingReservedNotificationForCoach($coach, daysAgo: 3, read: false);
        $this->insertMeetingReminderNotification($coach, daysAgo: 1, read: true, window: MeetingReminderWindow::Eve);
        $this->insertMeetingCanceledNotification($coach, daysAgo: 6, read: false);
        $this->insertQaReplyNotification($coach, daysAgo: 7, read: true);
    }

    private function seedMixedNotificationsForStudent(User $student, int $baseDaysAgo): void
    {
        $read = $baseDaysAgo % 2 === 0;
        $this->insertAnnouncementNotification($student, daysAgo: $baseDaysAgo, read: $read);
        $this->insertChatMessageNotification($student, daysAgo: $baseDaysAgo + 1, read: ! $read);
        $this->insertQaReplyNotification($student, daysAgo: $baseDaysAgo + 2, read: $read);
    }

    private function seedMixedNotificationsForCoach(User $coach, int $baseDaysAgo): void
    {
        $read = $baseDaysAgo % 2 === 0;
        $this->insertMeetingReservedNotificationForCoach($coach, daysAgo: $baseDaysAgo, read: $read);
        $this->insertChatMessageNotification($coach, daysAgo: $baseDaysAgo + 1, read: ! $read);
    }

    private function insertAnnouncementNotification(User $user, int $daysAgo, bool $read): void
    {
        $announcement = Announcement::query()
            ->orderByDesc('dispatched_at')
            ->first();

        if ($announcement === null) {
            return;
        }

        $data = [
            'notification_type' => 'admin_announcement',
            'title' => $announcement->title,
            'message' => mb_strimwidth(strip_tags($announcement->body), 0, 120, '…'),
            'admin_announcement_id' => $announcement->id,
            'body' => $announcement->body,
            'dispatched_at' => ($announcement->dispatched_at ?? now())->toIso8601String(),
            'target_type' => $announcement->target_type->value,
            'link_route' => 'notifications.show',
            'link_params' => [],
        ];

        $this->insertNotification(AnnouncementNotification::class, $user, $data, $daysAgo, $read);
    }

    private function insertChatMessageNotification(User $user, int $daysAgo, bool $read): void
    {
        $message = ChatMessage::query()
            ->whereHas('chatRoom.members', fn ($q) => $q->where('user_id', $user->id))
            ->where('sender_user_id', '!=', $user->id)
            ->with(['sender', 'chatRoom'])
            ->latest('created_at')
            ->first();

        if ($message === null) {
            return;
        }

        $data = [
            'notification_type' => 'chat_message_received',
            'title' => ($message->sender?->name ?? '送信者').' さんから新着メッセージ',
            'message' => mb_strimwidth($message->body, 0, 100, '…'),
            'chat_room_id' => $message->chat_room_id,
            'chat_message_id' => $message->id,
            'sender_user_id' => $message->sender_user_id,
            'sender_name' => $message->sender?->name,
            'sender_role' => $message->sender?->role?->value,
            'body_preview' => mb_strimwidth($message->body, 0, 100, '…'),
            'link_route' => 'chat.show',
            'link_params' => ['room' => $message->chat_room_id],
        ];

        $this->insertNotification(ChatMessageReceivedNotification::class, $user, $data, $daysAgo, $read);
    }

    private function insertMeetingReservedNotificationForCoach(User $coach, int $daysAgo, bool $read): void
    {
        $meeting = Meeting::query()
            ->where('coach_id', $coach->id)
            ->where('status', MeetingStatus::Reserved->value)
            ->with(['student', 'enrollment.certification'])
            ->latest('created_at')
            ->first();

        if ($meeting === null) {
            return;
        }

        $this->insertMeetingReservedData($coach, $meeting, $daysAgo, $read);
    }

    private function insertMeetingReservedNotificationForStudent(User $student, int $daysAgo, bool $read): void
    {
        $meeting = Meeting::query()
            ->where('student_id', $student->id)
            ->where('status', MeetingStatus::Reserved->value)
            ->with(['student', 'enrollment.certification'])
            ->latest('created_at')
            ->first();

        if ($meeting === null) {
            return;
        }

        $this->insertMeetingReservedData($student, $meeting, $daysAgo, $read);
    }

    private function insertMeetingReservedData(User $recipient, Meeting $meeting, int $daysAgo, bool $read): void
    {
        $studentName = $meeting->student?->name ?? '受講生';
        $certificationName = $meeting->enrollment?->certification?->name ?? '担当資格';

        $data = [
            'notification_type' => 'meeting_reserved',
            'title' => "{$studentName} さんから面談予約が入りました",
            'message' => $meeting->scheduled_at->translatedFormat('n月j日(D) H:i').'〜 / '.$certificationName,
            'meeting_id' => $meeting->id,
            'enrollment_id' => $meeting->enrollment_id,
            'coach_user_id' => $meeting->coach_id,
            'student_user_id' => $meeting->student_id,
            'student_name' => $studentName,
            'scheduled_at' => $meeting->scheduled_at->toIso8601String(),
            'topic' => $meeting->topic,
            'meeting_url_snapshot' => $meeting->meeting_url_snapshot,
            'link_route' => 'coach.meetings.index',
            'link_params' => [],
        ];

        $this->insertNotification(MeetingReservedNotification::class, $recipient, $data, $daysAgo, $read);
    }

    private function insertMeetingReminderNotification(User $user, int $daysAgo, bool $read, MeetingReminderWindow $window): void
    {
        $meeting = Meeting::query()
            ->where(fn ($q) => $q->where('coach_id', $user->id)->orWhere('student_id', $user->id))
            ->where('status', MeetingStatus::Reserved->value)
            ->with(['student', 'coach', 'enrollment.certification'])
            ->latest('created_at')
            ->first();

        if ($meeting === null) {
            return;
        }

        $certificationName = $meeting->enrollment?->certification?->name ?? '担当資格';
        $data = [
            'notification_type' => 'meeting_reminder',
            'title' => $window->label().': '.$meeting->scheduled_at->translatedFormat('n月j日(D) H:i'),
            'message' => $certificationName.' / '.$meeting->topic,
            'meeting_id' => $meeting->id,
            'enrollment_id' => $meeting->enrollment_id,
            'coach_user_id' => $meeting->coach_id,
            'student_user_id' => $meeting->student_id,
            'scheduled_at' => $meeting->scheduled_at->toIso8601String(),
            'topic' => $meeting->topic,
            'window' => $window->value,
            'link_route' => 'meetings.show',
            'link_params' => ['meeting' => $meeting->id],
        ];

        $this->insertNotification(MeetingReminderNotification::class, $user, $data, $daysAgo, $read);
    }

    private function insertMeetingCanceledNotification(User $user, int $daysAgo, bool $read): void
    {
        $meeting = Meeting::query()
            ->where(fn ($q) => $q->where('coach_id', $user->id)->orWhere('student_id', $user->id))
            ->where('status', MeetingStatus::Canceled->value)
            ->with(['student', 'coach', 'enrollment.certification'])
            ->latest('canceled_at')
            ->first();

        if ($meeting === null) {
            return;
        }

        $actorId = $meeting->canceled_by_user_id;
        $actor = $actorId !== null ? User::query()->find($actorId) : null;
        if ($actor === null) {
            $actor = $user->id === $meeting->student_id ? $meeting->coach : $meeting->student;
        }
        if ($actor === null) {
            return;
        }

        $certificationName = $meeting->enrollment?->certification?->name ?? '担当資格';
        $actorLabel = $actor->role === UserRole::Coach ? 'コーチ' : '受講生';

        $data = [
            'notification_type' => 'meeting_canceled',
            'title' => "{$actorLabel} {$actor->name} さんが面談をキャンセルしました",
            'message' => $meeting->scheduled_at->translatedFormat('n月j日(D) H:i').'〜 / '.$certificationName,
            'meeting_id' => $meeting->id,
            'enrollment_id' => $meeting->enrollment_id,
            'coach_user_id' => $meeting->coach_id,
            'student_user_id' => $meeting->student_id,
            'actor_user_id' => $actor->id,
            'actor_role' => $actor->role->value,
            'scheduled_at' => $meeting->scheduled_at->toIso8601String(),
            'topic' => $meeting->topic,
            'link_route' => $actor->role === UserRole::Coach ? 'meetings.index' : 'coach.meetings.index',
            'link_params' => [],
        ];

        $this->insertNotification(MeetingCanceledNotification::class, $user, $data, $daysAgo, $read);
    }

    private function insertQaReplyNotification(User $user, int $daysAgo, bool $read): void
    {
        // 受講生: 「自分が投稿した QaThread への新着回答」が来た想定で絞り込む。
        // コーチ: 担当資格絞り込みなしに、自己回答以外の最新 reply を 1 件選ぶ
        //   (dev データの type 網羅が目的、認可境界の厳密再現は Feature 動線で行う)。
        $reply = QaReply::query()
            ->where('user_id', '!=', $user->id)
            ->when(
                $user->role !== UserRole::Coach,
                fn ($q) => $q->whereHas('thread', fn ($tq) => $tq->where('user_id', $user->id)),
            )
            ->with(['user', 'thread'])
            ->latest('created_at')
            ->first();

        if ($reply === null) {
            $reply = QaReply::query()->with(['user', 'thread'])->latest('created_at')->first();
        }
        if ($reply === null) {
            return;
        }

        $data = [
            'notification_type' => 'qa_reply_received',
            'title' => ($reply->user?->name ?? '回答者').' さんからの新着回答',
            'message' => mb_strimwidth($reply->body, 0, 100, '…'),
            'qa_thread_id' => $reply->qa_thread_id,
            'qa_reply_id' => $reply->id,
            'replier_user_id' => $reply->user_id,
            'replier_name' => $reply->user?->name,
            'thread_title' => $reply->thread?->title,
            'body_preview' => mb_strimwidth($reply->body, 0, 60, '…'),
            'link_route' => 'qa-board.show',
            'link_params' => ['thread' => $reply->qa_thread_id],
        ];

        $this->insertNotification(QaReplyReceivedNotification::class, $user, $data, $daysAgo, $read);
    }

    /**
     * `notifications` テーブルへ 1 行 INSERT する。
     *
     * @param  array<string, mixed>  $data
     */
    private function insertNotification(string $type, User $notifiable, array $data, int $daysAgo, bool $read): void
    {
        $id = (string) Str::ulid();
        $createdAt = now()->subDays($daysAgo);
        $readAt = $read ? $createdAt->copy()->addHours(2) : null;

        // 通知詳細ページを遷移先とする自己完結型通知(お知らせ等)は、確定した通知 id を link_params に焼き込む
        // (実配信時に AnnouncementNotification::toDatabase が $this->id を入れるのと同じ snapshot を再現する)
        if (($data['link_route'] ?? null) === 'notifications.show') {
            $data['link_params'] = ['notification' => $id];
        }

        DB::table('notifications')->insert([
            'id' => $id,
            'type' => $type,
            'notifiable_type' => User::class,
            'notifiable_id' => $notifiable->id,
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'read_at' => $readAt instanceof Carbon ? $readAt->toDateTimeString() : null,
            'created_at' => $createdAt->toDateTimeString(),
            'updated_at' => $createdAt->toDateTimeString(),
        ]);
    }
}
