<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * 開発用シードユーザー。
 *
 * **設計思想（Seeder 業界標準: 状態網羅 + 固定アカウント）**:
 *
 * 1. **固定アカウント**(deterministic email + password='password'): 動作確認・スクショ撮影で安定して参照できる「決まったユーザー」。
 *    - admin@certify-lms.test (admin)
 *    - coach@certify-lms.test / coach2@certify-lms.test (coach、複数指導者シナリオ)
 *    - student@certify-lms.test (student、in_progress、Plan は PlanSeeder で紐づけ)
 *
 * 2. **状態網羅 demo データ**(Factory 生成): admin / coach 視点で「一覧画面に各 status が並ぶ」「フィルタが効く」を担保する。
 *    - student × invited × 2 (招待中、Plan 未確定)
 *    - student × in_progress × 8 (受講中、PlanSeeder で異なる Plan / 期間進捗に紐づけ)
 *    - student × graduated × 3 (卒業、修了証 DL のみ可、プラン機能ロック)
 *    - student × withdrawn × 2 (退会、soft delete)
 *
 * Plan / Enrollment 等への紐づけは依存 Seeder(PlanSeeder / EnrollmentSeeder 等) で行う。
 * 本 Seeder は **User + UserStatus** の網羅性のみ担保する(単一責任)。
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $this->createFixedAccounts();
        $this->createDemoStudents();
    }

    /**
     * 固定アカウント(deterministic email + password='password')。
     * 動作確認・PR スクショ・要件シート例示で安定して参照される。
     */
    private function createFixedAccounts(): void
    {
        $defaultPassword = Hash::make('password');
        $now = now();

        User::factory()
            ->admin()
            ->state([
                'name' => '管理者',
                'email' => 'admin@certify-lms.test',
                'password' => $defaultPassword,
                'status' => UserStatus::InProgress->value,
                'profile_setup_completed' => true,
                'email_verified_at' => $now,
            ])
            ->create();

        User::factory()
            ->coach()
            ->state([
                'name' => 'コーチ太郎',
                'email' => 'coach@certify-lms.test',
                'password' => $defaultPassword,
                'status' => UserStatus::InProgress->value,
                'bio' => '5 年以上のコーチング経験。基本情報・応用情報を中心に指導。',
                'profile_setup_completed' => true,
                'email_verified_at' => $now,
            ])
            ->create();

        User::factory()
            ->coach()
            ->state([
                'name' => 'コーチ花子',
                'email' => 'coach2@certify-lms.test',
                'password' => $defaultPassword,
                'status' => UserStatus::InProgress->value,
                'bio' => 'PMP・AWS 系を中心にビジネスサイドの資格を指導。',
                'profile_setup_completed' => true,
                'email_verified_at' => $now,
            ])
            ->create();

        User::factory()
            ->student()
            ->state([
                'name' => '受講生花子',
                'email' => 'student@certify-lms.test',
                'password' => $defaultPassword,
                'status' => UserStatus::InProgress->value,
                'bio' => '基本情報を 3 ヶ月で合格目標。',
                'profile_setup_completed' => true,
                'email_verified_at' => $now,
            ])
            ->create();
    }

    /**
     * 状態網羅 demo データ(Factory 生成、ランダムな name / email)。
     * 一覧画面のフィルタ・並び順・各 status のバッジ表示を実機確認するために投入する。
     */
    private function createDemoStudents(): void
    {
        // 招待中(invited): 招待発行されたがまだオンボーディング未完了。Plan 未確定、name / password NULL。
        User::factory()
            ->student()
            ->invited()
            ->count(2)
            ->create();

        // 受講中(in_progress): プラン進行中。Plan / 期限の多様化は PlanSeeder で実施。
        // ここでは name + email_verified だけ確定。
        User::factory()
            ->student()
            ->inProgress()
            ->count(8)
            ->create();

        // 卒業(graduated): 期限満了で自動卒業 or 修了証受領済み。プラン機能はロック、ログインのみ可。
        User::factory()
            ->student()
            ->graduated()
            ->count(3)
            ->create([
                'plan_expires_at' => now()->subDays(fake()->numberBetween(1, 90)),
            ]);

        // 退会(withdrawn): 手動退会 or 招待期限切れで cascade 退会。SoftDelete 適用。
        User::factory()
            ->student()
            ->withdrawn()
            ->count(2)
            ->create([
                'deleted_at' => now()->subDays(fake()->numberBetween(1, 60)),
                'email' => fn () => 'withdrawn_'.now()->timestamp.'_'.fake()->unique()->randomNumber(5).'@certify-lms.test',
            ]);
    }
}
