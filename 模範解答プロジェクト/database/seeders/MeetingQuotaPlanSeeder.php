<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MeetingQuotaPlanStatus;
use App\Enums\UserRole;
use App\Models\MeetingQuotaPlan;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 開発用 追加面談 SKU マスタシーダー。
 *
 * **設計思想(Seeder 業界標準: 状態網羅 + 固定 + 価格バリエーション)**:
 *
 * 1. SKU を **status 網羅** で投入(published × 3 + draft × 1 + archived × 1)。
 *    一覧画面のフィルタ・状態遷移ボタンの活性条件を実機確認するため。
 *
 * 2. published は **回数バリエーション** を 1 回 / 5 回 / 10 回パックで揃え、
 *    受講生の購入画面で実際に複数選択肢から選べる状態を作る。
 */
class MeetingQuotaPlanSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()
            ->where('role', UserRole::Admin->value)
            ->orderBy('created_at')
            ->first();

        if ($admin === null) {
            $this->command?->warn('MeetingQuotaPlanSeeder: 管理者 User が存在しません。先に UserSeeder を実行してください。');

            return;
        }

        $publishedData = [
            [
                'name' => '1 回パック',
                'description' => 'もう 1 回だけ面談したい方向け。',
                'meeting_count' => 1,
                'price' => 3000,
                'sort_order' => 10,
            ],
            [
                'name' => '5 回パック',
                'description' => '本試験前にまとめて相談したい方向け。20% OFF。',
                'meeting_count' => 5,
                'price' => 12000,
                'sort_order' => 20,
            ],
            [
                'name' => '10 回パック',
                'description' => '長期サポートが必要な方向け。30% OFF。',
                'meeting_count' => 10,
                'price' => 21000,
                'sort_order' => 30,
            ],
        ];

        foreach ($publishedData as $row) {
            MeetingQuotaPlan::create($row + [
                'status' => MeetingQuotaPlanStatus::Published->value,
                'created_by_user_id' => $admin->id,
                'updated_by_user_id' => $admin->id,
            ]);
        }

        MeetingQuotaPlan::factory()
            ->draft()
            ->withCount(3)
            ->withPrice(7000)
            ->state([
                'name' => '3 回パック(調整中)',
                'description' => '価格調整のため下書き中。',
                'sort_order' => 40,
                'created_by_user_id' => $admin->id,
                'updated_by_user_id' => $admin->id,
            ])
            ->create();

        MeetingQuotaPlan::factory()
            ->archived()
            ->withCount(20)
            ->withPrice(40000)
            ->state([
                'name' => '20 回パック(旧)',
                'description' => '販売終了。過去の購入履歴のみ参照可能。',
                'sort_order' => 50,
                'created_by_user_id' => $admin->id,
                'updated_by_user_id' => $admin->id,
            ])
            ->create();
    }
}
