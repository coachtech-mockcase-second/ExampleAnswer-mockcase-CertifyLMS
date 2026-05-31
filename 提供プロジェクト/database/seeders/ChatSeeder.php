<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ChatMember;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Enrollment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * 開発用 chat シーダー。
 *
 * **設計思想(Seeder 業界標準: 状態網羅 + 固定アカウント)**:
 *
 * 1. **全 Enrollment に ChatRoom を eager 生成**: 受講登録時の同期生成(Enrollment\StoreAction 経由)を模した
 *    初期状態を一括で作る。受講生本人 + 当該資格の担当コーチ集合を ChatMember として登録。
 *
 * 2. **固定 student の最初の Enrollment にサンプル会話を投入**: 受講生 / コーチ画面で UI バッジ / 既読 / 並び順を
 *    安定して確認できるよう、コーチ → 受講生 → コーチ の往復メッセージを 4〜6 件 + 受講生未読が残るよう
 *    最後はコーチ発言で終える。
 *
 * 3. **demo データの一部に未読を残す**: 一覧の未読バッジ表示 / 「未読あり」フィルタ動作の即時確認用。
 *
 * 依存順序: `UserSeeder` → `CertificationSeeder`(担当コーチ割当含む)→ `EnrollmentSeeder` → 本 Seeder。
 */
final class ChatSeeder extends Seeder
{
    public function run(): void
    {
        $enrollments = Enrollment::query()->with('certification.coaches')->get();

        if ($enrollments->isEmpty()) {
            $this->command?->warn('ChatSeeder: Enrollment が存在しません。先に EnrollmentSeeder を実行してください。');

            return;
        }

        foreach ($enrollments as $enrollment) {
            $this->createRoomWithMembers($enrollment);
        }

        $this->seedFixedStudentConversation();
        $this->seedUnreadDemo();
    }

    private function createRoomWithMembers(Enrollment $enrollment): void
    {
        $room = ChatRoom::create([
            'enrollment_id' => $enrollment->id,
            'last_message_at' => null,
        ]);

        $now = now();

        ChatMember::create([
            'chat_room_id' => $room->id,
            'user_id' => $enrollment->user_id,
            'last_read_at' => null,
            'joined_at' => $now,
        ]);

        foreach ($enrollment->certification->coaches as $coach) {
            ChatMember::create([
                'chat_room_id' => $room->id,
                'user_id' => $coach->id,
                'last_read_at' => null,
                'joined_at' => $now,
            ]);
        }
    }

    private function seedFixedStudentConversation(): void
    {
        $student = User::query()->where('email', 'student@certify-lms.test')->first();
        $coach = User::query()->where('email', 'coach@certify-lms.test')->first();
        if ($student === null || $coach === null) {
            return;
        }

        $enrollment = Enrollment::query()
            ->where('user_id', $student->id)
            ->orderBy('created_at')
            ->first();
        if ($enrollment === null) {
            return;
        }

        $room = ChatRoom::query()->where('enrollment_id', $enrollment->id)->first();
        if ($room === null) {
            return;
        }

        $script = [
            ['user' => $student, 'body' => 'おはようございます。来週の mock-exam に向けて、アルゴリズム分野の進め方を相談させてください。', 'minutes_ago' => 180],
            ['user' => $coach, 'body' => 'おはようございます。直近の演習結果を見ると 2 分探索木の比較回数が苦手そうですね。まず章末の例題から潰しましょう。', 'minutes_ago' => 170],
            ['user' => $student, 'body' => 'ありがとうございます。例題を解いてから過去問に進む流れで進めてみます。', 'minutes_ago' => 90],
            ['user' => $coach, 'body' => '良い順番です。詰まったら理解できなかった選択肢を共有してください。一緒に解説します。', 'minutes_ago' => 5],
        ];

        foreach ($script as $line) {
            $createdAt = Carbon::now()->subMinutes($line['minutes_ago']);
            $message = ChatMessage::create([
                'chat_room_id' => $room->id,
                'sender_user_id' => $line['user']->id,
                'body' => $line['body'],
            ]);
            $message->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
            $room->update(['last_message_at' => $createdAt]);
        }

        ChatMember::query()
            ->where('chat_room_id', $room->id)
            ->where('user_id', $coach->id)
            ->update(['last_read_at' => Carbon::now()->subMinutes(5)]);
    }

    /**
     * 固定 student の 2 件目以降の Enrollment にも未読会話を 1-2 件挟み、
     * 一覧の「未読あり」状態と複数ルームの並び順を視認できるようにする。
     */
    private function seedUnreadDemo(): void
    {
        $student = User::query()->where('email', 'student@certify-lms.test')->first();
        if ($student === null) {
            return;
        }

        $extraEnrollments = Enrollment::query()
            ->where('user_id', $student->id)
            ->orderBy('created_at')
            ->skip(1)
            ->take(2)
            ->get();

        foreach ($extraEnrollments as $enrollment) {
            $coach = $enrollment->certification->coaches->first();
            if ($coach === null) {
                continue;
            }

            $room = ChatRoom::query()->where('enrollment_id', $enrollment->id)->first();
            if ($room === null) {
                continue;
            }

            $createdAt = Carbon::now()->subHours(2);
            $message = ChatMessage::create([
                'chat_room_id' => $room->id,
                'sender_user_id' => $coach->id,
                'body' => '前回の演習レビューを送ります。時間あるときに目を通しておいてください。',
            ]);
            $message->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
            $room->update(['last_message_at' => $createdAt]);
        }
    }
}
