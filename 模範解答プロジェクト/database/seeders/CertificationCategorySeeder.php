<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CertificationCategory;
use Illuminate\Database\Seeder;

/**
 * 開発用 資格分類マスタシーダー。
 *
 * **設計思想（Seeder 業界標準: 業務ドメイン語彙の固定投入）**:
 *
 * 1. 受講生カタログのフィルタ・admin の分類管理画面で実際に複数選択肢から選べる状態を作る。
 * 2. `slug` は URL に出るため業務語に対応する半角英数固定、`sort_order` で表示順を制御。
 */
final class CertificationCategorySeeder extends Seeder
{
    public function run(): void
    {
        // ビジネス / マーケティング / その他 は資格を紐づけず、資格 0 件の分類(削除可能)を複数用意する。
        // 分類削除の動作確認(資格が紐づく分類は削除拒否 / 紐づかない分類は削除可)を実機で再現するため。
        $data = [
            ['name' => 'IT 系', 'slug' => 'tech', 'sort_order' => 10],
            ['name' => '語学', 'slug' => 'language', 'sort_order' => 20],
            ['name' => 'ビジネス', 'slug' => 'business', 'sort_order' => 30],
            ['name' => '会計・金融', 'slug' => 'accounting', 'sort_order' => 40],
            ['name' => 'マネジメント', 'slug' => 'management', 'sort_order' => 50],
            ['name' => 'デザイン', 'slug' => 'design', 'sort_order' => 60],
            ['name' => 'マーケティング', 'slug' => 'marketing', 'sort_order' => 70],
            ['name' => 'その他', 'slug' => 'other', 'sort_order' => 80],
        ];

        foreach ($data as $row) {
            CertificationCategory::create($row);
        }
    }
}
