<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AnnouncementTargetType;
use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 管理者お知らせの開発用シーダー。
 *
 * **設計思想(Seeder 業界標準: target_type 網羅 + 固定アカウント)**:
 *
 * 1. **target_type 網羅**: AllStudents / Certification / User の 3 種それぞれに 2-3 件投入し、
 *    admin 一覧画面のフィルタ・対象セレクタの動作確認をしやすくする。
 * 2. **固定 student に届く User 指定お知らせ**: student@certify-lms.test を target に持つお知らせを 1 件以上入れ、
 *    通知一覧 / お知らせ詳細の動線を実機確認可能にする。
 * 3. **dispatched_at の散らし**: 当日〜1 ヶ月前まで段階的に過去日付で散らし、降順並び順・既読期間フィルタが動くことを担保する。
 *
 * 本 Seeder は Announcement 本体のみを INSERT する。各 User への通知行は `NotificationSeeder` が別途投入する。
 *
 * 依存順序: `UserSeeder` → `CertificationSeeder` → `EnrollmentSeeder` → 本 Seeder。
 */
final class AnnouncementSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@certify-lms.test')->first();
        if ($admin === null) {
            $admin = User::query()->where('role', UserRole::Admin->value)->orderBy('created_at')->first();
        }

        if ($admin === null) {
            $this->command?->warn('AnnouncementSeeder: 管理者 User が存在しません。先に UserSeeder を実行してください。');

            return;
        }

        $this->seedAllStudentsAnnouncements($admin);
        $this->seedCertificationAnnouncements($admin);
        $this->seedUserAnnouncements($admin);
    }

    private function seedAllStudentsAnnouncements(User $admin): void
    {
        $activeStudentCount = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->count();

        $rows = [
            [
                'title' => '【重要】定期メンテナンスのお知らせ',
                'body' => "下記日程でシステム定期メンテナンスを実施します。学習進捗の保存は終了 10 分前までに完了させてください。\n\n日時: 今月末 深夜 2:00 - 4:00\n対象: 全機能(ログイン不可)",
                'daysAgo' => 1,
            ],
            [
                'title' => '新着教材リリースのお知らせ',
                'body' => "応用情報技術者試験向けの章末まとめ問題を追加しました。\n基本情報 → 応用情報 のステップアップを進めている方はぜひ取り組んでみてください。",
                'daysAgo' => 7,
            ],
            [
                'title' => '面談クォータ追加購入機能の改善',
                'body' => "追加面談の購入フローを刷新しました。決済完了後は自動的に残数が増えるため、購入直後すぐに予約が可能になります。",
                'daysAgo' => 14,
            ],
        ];

        foreach ($rows as $row) {
            $dispatchedAt = now()->subDays($row['daysAgo']);
            $announcement = Announcement::factory()
                ->allStudents()
                ->state([
                    'created_by_user_id' => $admin->id,
                    'title' => $row['title'],
                    'body' => $row['body'],
                    'dispatched_count' => $activeStudentCount,
                    'dispatched_at' => $dispatchedAt,
                ])
                ->create();

            $announcement->forceFill(['created_at' => $dispatchedAt, 'updated_at' => $dispatchedAt])->save();
        }
    }

    private function seedCertificationAnnouncements(User $admin): void
    {
        $certifications = Certification::query()
            ->where('status', CertificationStatus::Published->value)
            ->orderBy('created_at')
            ->take(3)
            ->get();

        $templates = [
            [
                'title' => '【基本情報技術者試験】春期試験の申込開始',
                'body' => "今期の試験申込が始まりました。受験予定の方は本日から 1 ヶ月以内に申込を済ませてください。\n申込忘れによる受験機会の損失を防ぐため、面談時にコーチへの共有もお願いします。",
                'daysAgo' => 5,
            ],
            [
                'title' => '【応用情報技術者試験】午後選択問題の傾向アップデート',
                'body' => "直近 3 期の出題傾向を分析した教材を追加しました。データベース / 情報セキュリティの選択を検討中の方は確認をお願いします。",
                'daysAgo' => 10,
            ],
            [
                'title' => '【TOEIC L&R 800 点コース】公式問題集の対応セクション拡充',
                'body' => "公式問題集 vol.10 の Part 5 / Part 7 演習を教材セクションに追加しました。\n直近受験予定の方は優先的に取り組んでみてください。",
                'daysAgo' => 21,
            ],
        ];

        foreach ($certifications as $i => $certification) {
            $template = $templates[$i] ?? $templates[0];
            $dispatchedAt = now()->subDays($template['daysAgo']);

            $enrolledCount = Enrollment::query()
                ->where('certification_id', $certification->id)
                ->count();

            $announcement = Announcement::factory()
                ->forCertification($certification)
                ->state([
                    'created_by_user_id' => $admin->id,
                    'title' => $template['title'],
                    'body' => $template['body'],
                    'dispatched_count' => $enrolledCount,
                    'dispatched_at' => $dispatchedAt,
                ])
                ->create();

            $announcement->forceFill(['created_at' => $dispatchedAt, 'updated_at' => $dispatchedAt])->save();
        }
    }

    private function seedUserAnnouncements(User $admin): void
    {
        $fixedStudent = User::query()->where('email', 'student@certify-lms.test')->first();
        $extraTargets = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->whereNot('email', 'student@certify-lms.test')
            ->orderBy('created_at')
            ->take(2)
            ->get();

        $targets = collect([$fixedStudent])->filter()->merge($extraTargets)->values();

        $templates = [
            [
                'title' => '担当コーチ変更のお知らせ',
                'body' => "ご担当コーチを別のコーチへ変更いたしました。次回面談から新しいコーチが対応いたします。\n引き継ぎ事項は事前に新コーチ宛にメモを添えております。",
                'daysAgo' => 2,
            ],
            [
                'title' => 'プラン期限満了のリマインド',
                'body' => "ご契約プランの満了日が近づいています。プラン延長をご希望の場合はお早めにお手続きください。\n面談残数は満了日まで利用可能です。",
                'daysAgo' => 4,
            ],
            [
                'title' => '【個別連絡】試験合格おめでとうございます',
                'body' => "合格のご報告を受け、Certify LMS チームより記念のメッセージをお届けします。\n修了証 PDF はダッシュボードからダウンロード可能です。",
                'daysAgo' => 9,
            ],
        ];

        foreach ($targets as $i => $target) {
            $template = $templates[$i % count($templates)];
            $dispatchedAt = now()->subDays($template['daysAgo']);

            $announcement = Announcement::factory()
                ->forUser($target)
                ->state([
                    'created_by_user_id' => $admin->id,
                    'title' => $template['title'],
                    'body' => $template['body'],
                    'dispatched_count' => 1,
                    'dispatched_at' => $dispatchedAt,
                ])
                ->create();

            $announcement->forceFill(['created_at' => $dispatchedAt, 'updated_at' => $dispatchedAt])->save();
        }
    }
}
