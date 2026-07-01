<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EnrollmentStatus;
use App\Enums\MeetingQuotaTransactionType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CoachAvailability;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\MeetingMemo;
use App\Models\MeetingQuotaTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * 面談予約 Feature のデモデータシーダー。
 *
 * **設計思想**:
 *
 * - **CoachAvailability 投入**: 受講生が予約画面を開いたとき空き枠が表示される状態を作る。固定コーチに加え、
 *   デモコーチ全員に最低 1 件の枠を入れて自動コーチ割当の動作確認をしやすくする。
 * - **Meeting 状態網羅**: 履歴 UI の各ステータスバッジ・フィルタ・状態遷移ボタンが見える状態を作る。
 *   reserved(今後数日) / completed(過去、メモあり) / canceled(refund 流れ済) の 3 状態を散らす。
 * - **固定 student に最低 1 件ずつ**: 動作確認・スクショ撮影で安定して参照できるよう reserved × 1 / completed × 1 を確実に作る。
 *
 * 依存: UserSeeder → CertificationSeeder → EnrollmentSeeder の後に走る前提
 * (`certification_coach_assignments` と `enrollments` を参照する)。
 */
final class MentoringSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCoachAvailabilities();
        $this->seedFixedStudentMeetings();
        $this->seedNoQuotaStudentMeetings();
        $this->seedDemoMeetings();
    }

    /**
     * コーチの面談可能時間枠(月曜=1〜日曜=0、Carbon の dayOfWeek 仕様)を投入する。
     * 固定コーチには想定運用時間、demo コーチには平日 1 枠ずつを置く。
     */
    private function seedCoachAvailabilities(): void
    {
        $coach1 = User::query()->where('email', 'coach@certify-lms.test')->first();
        $coach2 = User::query()->where('email', 'coach2@certify-lms.test')->first();

        if ($coach1 !== null) {
            // 平日 19:00〜21:00 の繰り返し枠
            foreach ([1, 2, 3, 4, 5] as $dow) {
                CoachAvailability::factory()
                    ->forCoach($coach1)
                    ->onDay($dow)
                    ->timeRange('19:00:00', '21:00:00')
                    ->create();
            }
        }

        if ($coach2 !== null) {
            // 週末 10:00〜12:00 / 14:00〜16:00 の繰り返し枠(土 + 日)
            foreach ([0, 6] as $dow) {
                CoachAvailability::factory()
                    ->forCoach($coach2)
                    ->onDay($dow)
                    ->timeRange('10:00:00', '12:00:00')
                    ->create();
                CoachAvailability::factory()
                    ->forCoach($coach2)
                    ->onDay($dow)
                    ->timeRange('14:00:00', '16:00:00')
                    ->create();
            }
        }

        $demoCoaches = User::query()
            ->where('role', UserRole::Coach->value)
            ->where('status', UserStatus::InProgress->value)
            ->whereNotIn('email', ['coach@certify-lms.test', 'coach2@certify-lms.test'])
            ->get();

        foreach ($demoCoaches as $i => $coach) {
            CoachAvailability::factory()
                ->forCoach($coach)
                ->onDay(($i % 5) + 1)
                ->timeRange('13:00:00', '17:00:00')
                ->create();
        }
    }

    /**
     * 固定 student に reserved × 1 + completed × 1 を最低限投入し、UI 動作確認の起点を用意する。
     */
    private function seedFixedStudentMeetings(): void
    {
        $student = User::query()->where('email', 'student@certify-lms.test')->first();
        $coach = User::query()->where('email', 'coach@certify-lms.test')->first();

        if ($student === null || $coach === null) {
            return;
        }

        $enrollment = Enrollment::query()
            ->where('user_id', $student->id)
            ->where('status', EnrollmentStatus::Learning->value)
            ->orderBy('created_at')
            ->first();

        if ($enrollment === null) {
            return;
        }

        // 今後の reserved(コーチ稼働曜日=月曜の 19:00 に確実にぶつけるため、来週月曜を選ぶ)
        $upcoming = $this->nextWeekdayAt(targetDow: 1, hour: 19);
        $reserved = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->forEnrollment($enrollment)
            ->create([
                'scheduled_at' => $upcoming,
                'topic' => 'アルゴリズム分野の模試正答率が伸び悩んでいます。学習計画の見直しを相談したいです。',
            ]);

        $this->insertConsumedTransaction($student, $reserved);

        // 過去の completed(2 週間前の月曜 19:00)+ コーチメモ
        $past = $this->lastWeekdayAt(targetDow: 1, hour: 19, weeksAgo: 2);
        $completed = Meeting::factory()
            ->completed()
            ->forCoach($coach)
            ->forStudent($student)
            ->forEnrollment($enrollment)
            ->create([
                'scheduled_at' => $past,
                'completed_at' => $past->copy()->addHour()->addMinutes(5),
                'topic' => '学習計画の初回打合せをしたいです。',
            ]);

        $this->insertConsumedTransaction($student, $completed, occurredAt: $past->copy()->subDay());

        MeetingMemo::factory()->forMeeting($completed)->create([
            'body' => "初回面談で目標受験日と学習ペースをすり合わせました。\n\n次回までに過去問 1 年分を解いて結果を共有してもらう想定です。",
        ]);
    }

    /**
     * 面談残数 0 の受講生に、付与された面談回数と同数の完了済み面談を投入し残数を 0 にする。
     *
     * 「残数 0 での予約拒否」を実機確認するためのデータ。過去日の completed を max_meetings 件作り、
     * それぞれ消費トランザクションを起票する(付与 max_meetings − 消費 max_meetings = 残数 0)。
     */
    private function seedNoQuotaStudentMeetings(): void
    {
        $student = User::query()->where('email', 'student-noquota@certify-lms.test')->first();
        $coach = User::query()->where('email', 'coach@certify-lms.test')->first();

        if ($student === null || $coach === null) {
            return;
        }

        $enrollment = Enrollment::query()
            ->where('user_id', $student->id)
            ->where('status', EnrollmentStatus::Learning->value)
            ->orderBy('created_at')
            ->first();

        if ($enrollment === null) {
            return;
        }

        // 付与された面談回数と同数を消費して残数 0 にする。既存メンタリングデータと衝突しない遠い過去日 + 20:00 を使う。
        for ($i = 0; $i < $student->max_meetings; $i++) {
            $past = now()->copy()->startOfDay()->subDays(150 + $i * 10)->setTime(20, 0, 0);
            $completed = Meeting::factory()
                ->completed()
                ->forCoach($coach)
                ->forStudent($student)
                ->forEnrollment($enrollment)
                ->create([
                    'scheduled_at' => $past,
                    'completed_at' => $past->copy()->addHour(),
                    'topic' => '過去に実施済みの面談。',
                ]);

            $this->insertConsumedTransaction($student, $completed, occurredAt: $past->copy()->subDay());
        }
    }

    /**
     * 履歴 UI のバリエーション demo(reserved 複数 / completed 複数 / canceled 数件)を投入する。
     */
    private function seedDemoMeetings(): void
    {
        $coach1 = User::query()->where('email', 'coach@certify-lms.test')->first();
        $coach2 = User::query()->where('email', 'coach2@certify-lms.test')->first();
        $coaches = collect([$coach1, $coach2])->filter()->values();

        if ($coaches->isEmpty()) {
            return;
        }

        $demoEnrollments = Enrollment::query()
            ->where('status', EnrollmentStatus::Learning->value)
            ->whereHas('user', fn ($q) => $q->whereNotIn('email', ['student@certify-lms.test', 'student-noquota@certify-lms.test']))
            ->with('user')
            ->take(6)
            ->get();

        foreach ($demoEnrollments as $i => $enrollment) {
            $coach = $coaches->get($i % $coaches->count());

            // 同コーチ × 同時刻 UNIQUE を避けるため、coach 単位で日付オフセットを単調増加させる。
            // 平日コーチは 1 週間 + i 日後、週末コーチは土曜起点で各 demo を 1 週間ずつずらす。
            $reservedAt = now()->copy()->startOfDay()->addDays(8 + $i * 3)->setTime(19, 0, 0);
            $reserved = Meeting::factory()
                ->reserved()
                ->forCoach($coach)
                ->forStudent($enrollment->user)
                ->forEnrollment($enrollment)
                ->create(['scheduled_at' => $reservedAt]);
            $this->insertConsumedTransaction($enrollment->user, $reserved);

            // 固定 student の completed(-14 日 / coach1)と衝突しないよう -21 日起点で散らす
            $pastAt = now()->copy()->startOfDay()->subDays(21 + $i * 5)->setTime(19, 0, 0);
            $completed = Meeting::factory()
                ->completed()
                ->forCoach($coach)
                ->forStudent($enrollment->user)
                ->forEnrollment($enrollment)
                ->create([
                    'scheduled_at' => $pastAt,
                    'completed_at' => $pastAt->copy()->addHour()->addMinutes(2),
                ]);
            $this->insertConsumedTransaction($enrollment->user, $completed, occurredAt: $pastAt->copy()->subDay());

            if ($i % 2 === 0) {
                $canceledAt = now()->copy()->startOfDay()->subDays(50 + $i * 5)->setTime(19, 0, 0);
                $cancelerByCoach = $i % 4 === 0;

                $canceled = Meeting::factory()
                    ->canceled()
                    ->forCoach($coach)
                    ->forStudent($enrollment->user)
                    ->forEnrollment($enrollment)
                    ->create([
                        'scheduled_at' => $canceledAt,
                        'canceled_at' => $canceledAt->copy()->subDay(),
                        'canceled_by_user_id' => $cancelerByCoach ? $coach->id : $enrollment->user_id,
                    ]);

                $this->insertConsumedTransaction($enrollment->user, $canceled, occurredAt: $canceledAt->copy()->subDays(2));
                $this->insertRefundedTransaction($enrollment->user, $canceled);
            }
        }
    }

    private function insertConsumedTransaction(User $user, Meeting $meeting, ?Carbon $occurredAt = null): void
    {
        $transaction = MeetingQuotaTransaction::create([
            'user_id' => $user->id,
            'type' => MeetingQuotaTransactionType::Consumed->value,
            'amount' => -1,
            'related_meeting_id' => $meeting->id,
            'occurred_at' => $occurredAt ?? $meeting->created_at,
        ]);

        $meeting->update(['meeting_quota_transaction_id' => $transaction->id]);
    }

    private function insertRefundedTransaction(User $user, Meeting $meeting): void
    {
        MeetingQuotaTransaction::create([
            'user_id' => $user->id,
            'type' => MeetingQuotaTransactionType::Refunded->value,
            'amount' => 1,
            'related_meeting_id' => $meeting->id,
            'occurred_at' => $meeting->canceled_at ?? now(),
        ]);
    }

    private function nextWeekdayAt(int $targetDow, int $hour, int $weeksAhead = 1): Carbon
    {
        $date = now()->startOfDay();
        while ((int) $date->dayOfWeek !== $targetDow || $date->lessThan(now())) {
            $date = $date->addDay();
        }

        return $date->addWeeks($weeksAhead - 1)->setTime($hour, 0, 0);
    }

    private function lastWeekdayAt(int $targetDow, int $hour, int $weeksAgo): Carbon
    {
        $date = now()->startOfDay();
        while ((int) $date->dayOfWeek !== $targetDow) {
            $date = $date->subDay();
        }

        return $date->subWeeks($weeksAgo)->setTime($hour, 0, 0);
    }
}
