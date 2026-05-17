<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CertificationDifficulty;
use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Models\Certification;
use App\Models\CertificationCategory;
use App\Models\CertificationCoachAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * 開発用 資格マスタ + 担当コーチ割当シーダー。
 *
 * **設計思想（Seeder 業界標準: 状態網羅 + カテゴリ分散 + 担当割当）**:
 *
 * 1. 資格を **status 網羅** で投入(published × 5 + draft × 1 + archived × 1)。
 *    admin 一覧画面の status フィルタ・状態遷移ボタンの活性条件・受講生カタログの公開判定を実機確認するため。
 *
 * 2. **カテゴリ分散**: 全 6 カテゴリ(IT 系 / 語学 / ビジネス / 会計・金融 / マネジメント / デザイン)に資格を散らし、
 *    受講生カタログのカテゴリフィルタが効くことを実機確認する。
 *
 * 3. **担当コーチ割当**: 固定 coach 2 名 (coach@ / coach2@) を主要資格に分担して attach。
 *    Coach 視点で「自分の担当資格」のみ参照可能になる Policy 動作を実機確認する。
 */
final class CertificationSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()
            ->where('role', UserRole::Admin->value)
            ->orderBy('created_at')
            ->first();

        if ($admin === null) {
            $this->command?->warn('CertificationSeeder: 管理者 User が存在しません。先に UserSeeder を実行してください。');

            return;
        }

        $categories = CertificationCategory::query()->ordered()->get()->keyBy('slug');

        if ($categories->isEmpty()) {
            $this->command?->warn('CertificationSeeder: 資格分類マスタが存在しません。先に CertificationCategorySeeder を実行してください。');

            return;
        }

        $publishedCerts = $this->createPublishedCertifications($admin, $categories);
        $this->createNonPublishedCertifications($admin, $categories);
        $this->assignCoaches($publishedCerts, $admin);
    }

    /**
     * 受講生カタログに並ぶ published 資格を 5 種、複数カテゴリ × 難易度で投入する。
     *
     * @param  \Illuminate\Support\Collection<string, CertificationCategory>  $categories
     * @return array<int, Certification>
     */
    private function createPublishedCertifications(User $admin, $categories): array
    {
        $data = [
            [
                'name' => '基本情報技術者試験',
                'category_slug' => 'tech',
                'difficulty' => CertificationDifficulty::Beginner,
                'description' => "IT エンジニアを志す方の登竜門。アルゴリズム / ネットワーク / データベース / セキュリティ等の基礎を体系的に学ぶ。\n春期・秋期に実施。",
            ],
            [
                'name' => '応用情報技術者試験',
                'category_slug' => 'tech',
                'difficulty' => CertificationDifficulty::Intermediate,
                'description' => "基本情報の上位資格。実務的なシステム設計 / プロジェクトマネジメント / 経営戦略まで踏み込む。\n論述形式の午後試験で実戦力を測定。",
            ],
            [
                'name' => 'TOEIC L&R 800 点コース',
                'category_slug' => 'language',
                'difficulty' => CertificationDifficulty::Intermediate,
                'description' => 'ビジネス英語の指標として国内で最も使われる試験。800 点突破を目標に Listening / Reading 双方を強化。',
            ],
            [
                'name' => '日商簿記 2 級',
                'category_slug' => 'accounting',
                'difficulty' => CertificationDifficulty::Beginner,
                'description' => '企業会計の基礎。商業簿記と工業簿記の両方を扱い、財務諸表の読解力を養う。',
            ],
            [
                'name' => 'PMP',
                'category_slug' => 'management',
                'difficulty' => CertificationDifficulty::Advanced,
                'description' => '国際的なプロジェクトマネジメント資格。PMBOK ガイドに基づくスコープ / スケジュール / コスト / リスク管理を学ぶ。',
            ],
        ];

        $certifications = [];
        foreach ($data as $row) {
            $certifications[] = Certification::factory()
                ->published()
                ->state([
                    'name' => $row['name'],
                    'category_id' => $categories->get($row['category_slug'])->id,
                    'difficulty' => $row['difficulty']->value,
                    'description' => $row['description'],
                    'created_by_user_id' => $admin->id,
                    'updated_by_user_id' => $admin->id,
                ])
                ->create();
        }

        return $certifications;
    }

    /**
     * draft / archived 資格を少数投入(admin の status フィルタ・受講生カタログでの非表示を実機確認するため)。
     *
     * @param  \Illuminate\Support\Collection<string, CertificationCategory>  $categories
     */
    private function createNonPublishedCertifications(User $admin, $categories): void
    {
        Certification::factory()
            ->draft()
            ->state([
                'name' => 'AWS Certified Solutions Architect (準備中)',
                'category_id' => $categories->get('tech')->id,
                'difficulty' => CertificationDifficulty::Advanced->value,
                'description' => '次期リリース予定。問題セット作成中のため受講登録は不可。',
                'created_by_user_id' => $admin->id,
                'updated_by_user_id' => $admin->id,
            ])
            ->create();

        Certification::factory()
            ->archived()
            ->state([
                'name' => '販売終了: Webクリエイター能力認定試験',
                'category_id' => $categories->get('design')->id,
                'difficulty' => CertificationDifficulty::Beginner->value,
                'description' => '提供終了。既存受講生の学習継続は可能。',
                'created_by_user_id' => $admin->id,
                'updated_by_user_id' => $admin->id,
            ])
            ->create();
    }

    /**
     * 固定 coach 2 名 (coach@ / coach2@) を主要 published 資格に分担して attach する。
     *
     * @param  array<int, Certification>  $publishedCerts
     */
    private function assignCoaches(array $publishedCerts, User $admin): void
    {
        $coach1 = User::query()->where('email', 'coach@certify-lms.test')->first();
        $coach2 = User::query()->where('email', 'coach2@certify-lms.test')->first();

        if ($coach1 === null || $coach2 === null) {
            $this->command?->warn('CertificationSeeder: 固定コーチアカウントが存在しません。担当割当をスキップします。');

            return;
        }

        // coach1 (技術系コーチ): 基本情報 / 応用情報 / TOEIC を担当
        foreach ([0, 1, 2] as $index) {
            $this->createAssignment($publishedCerts[$index], $coach1, $admin);
        }

        // coach2 (ビジネス系コーチ): TOEIC / 日商簿記 / PMP を担当(TOEIC は両コーチが担当する複数指導者シナリオ)
        foreach ([2, 3, 4] as $index) {
            $this->createAssignment($publishedCerts[$index], $coach2, $admin);
        }
    }

    private function createAssignment(Certification $certification, User $coach, User $admin): void
    {
        CertificationCoachAssignment::create([
            'id' => (string) Str::ulid(),
            'certification_id' => $certification->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);
    }
}
