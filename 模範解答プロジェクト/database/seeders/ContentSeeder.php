<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ContentStatus;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Part;
use App\Models\QuestionCategory;
use App\Models\Section;
use App\Models\SectionQuestion;
use App\Models\SectionQuestionOption;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * 開発用 教材階層 + 演習問題 + 出題分野マスタ シーダー。
 *
 * **設計思想(Seeder 業界標準: 階層 + 状態網羅 + 検索キーワード ヒット)**:
 *
 * 1. **階層完全投入**: Part → Chapter → Section → SectionQuestion → SectionQuestionOption を実体配置。
 *    admin 教材階層画面・SectionQuestion 一覧・受講生検索の実機動作を確認できる状態を作る。
 *
 * 2. **状態網羅**: published / draft を Part / Section / SectionQuestion で混在させる。
 *    admin 一覧の status バッジ・公開遷移ボタン・cascade visibility(親 Part draft → 配下 Section 非公開扱い)を実機検証可能。
 *
 * 3. **検索ヒット用 Markdown 本文**: Section.body に具体的なキーワード(「進数」「アルゴリズム」「暗号化」等)を含める。
 *    student として /contents/search にアクセスした際にヒット結果が返る demo データを保証。
 *
 * 4. **資格分散**: 基本情報技術者試験(IT 系) と 応用情報技術者試験(IT 系) の 2 資格に投入。
 *    複数資格にまたがる QuestionCategory マスタ・教材階層動作を確認可能。
 *
 * 5. **SectionQuestion 公開可能条件**: published SectionQuestion は options 4 件 + 正答 1 件で投入(REQ 規約準拠)。
 */
final class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $kihonjoho = Certification::query()->where('name', '基本情報技術者試験')->first();
        $oyojoho = Certification::query()->where('name', '応用情報技術者試験')->first();

        if ($kihonjoho === null || $oyojoho === null) {
            $this->command?->warn('ContentSeeder: 必須資格(基本情報技術者試験 / 応用情報技術者試験)が存在しません。先に CertificationSeeder を実行してください。');

            return;
        }

        $kihonCategories = $this->seedQuestionCategoriesForKihonjoho($kihonjoho);
        $this->seedKihonjohoContents($kihonjoho, $kihonCategories);

        $oyoCategories = $this->seedQuestionCategoriesForOyojoho($oyojoho);
        $this->seedOyojohoContents($oyojoho, $oyoCategories);
    }

    /**
     * @return array<string, QuestionCategory>
     */
    private function seedQuestionCategoriesForKihonjoho(Certification $certification): array
    {
        $data = [
            ['name' => 'テクノロジー系', 'slug' => 'technology', 'sort_order' => 10],
            ['name' => 'マネジメント系', 'slug' => 'management', 'sort_order' => 20],
            ['name' => 'ストラテジ系', 'slug' => 'strategy', 'sort_order' => 30],
        ];

        $categories = [];
        foreach ($data as $row) {
            $categories[$row['slug']] = QuestionCategory::factory()
                ->forCertification($certification)
                ->state($row)
                ->create();
        }

        return $categories;
    }

    /**
     * @return array<string, QuestionCategory>
     */
    private function seedQuestionCategoriesForOyojoho(Certification $certification): array
    {
        return [
            'security' => QuestionCategory::factory()
                ->forCertification($certification)
                ->state(['name' => '情報セキュリティ', 'slug' => 'security', 'sort_order' => 10])
                ->create(),
        ];
    }

    /**
     * @param  array<string, QuestionCategory>  $categories
     */
    private function seedKihonjohoContents(Certification $certification, array $categories): void
    {
        // Part 1 (published) — 公開階層の代表ケース
        $part1 = Part::factory()
            ->forCertification($certification)
            ->published()
            ->state(['title' => '第1部 基礎理論', 'description' => '進数 / 論理演算 / アルゴリズムを学ぶ', 'order' => 1])
            ->create();

        $chapter1 = Chapter::factory()
            ->forPart($part1)
            ->published()
            ->state(['title' => '第1章 進数と論理演算', 'order' => 1])
            ->create();

        $section1 = Section::factory()
            ->forChapter($chapter1)
            ->published()
            ->state([
                'title' => '1.1 2 進数の表現',
                'body' => "## 2 進数の表現\n\nコンピュータが扱うデータは **2 進数** で表現されます。\n\n- 進数変換: 10 進数 ⇄ 2 進数 ⇄ 16 進数\n- 補数表現: 1 の補数 / 2 の補数\n- シフト演算: 算術シフト / 論理シフト\n\n## 進数間の変換例\n\n10 進数 25 → 2 進数 `11001`、16 進数 `0x19`",
                'order' => 1,
            ])
            ->create();

        $this->seedQuestionsForSection1($section1, $categories['technology']);

        Section::factory()
            ->forChapter($chapter1)
            ->published()
            ->state([
                'title' => '1.2 論理演算とブール代数',
                'body' => "## 論理演算\n\n基本的な論理演算には **AND / OR / NOT / XOR** があります。\n\n| A | B | AND | OR | XOR |\n|---|---|-----|----|----|\n| 0 | 0 | 0 | 0 | 0 |\n| 0 | 1 | 0 | 1 | 1 |\n| 1 | 0 | 0 | 1 | 1 |\n| 1 | 1 | 1 | 1 | 0 |\n\n## ブール代数\n\n論理演算を代数的に扱う体系。回路設計やプログラムの条件式の最適化に応用されます。",
                'order' => 2,
            ])
            ->create();

        $chapter2 = Chapter::factory()
            ->forPart($part1)
            ->published()
            ->state(['title' => '第2章 アルゴリズムとデータ構造', 'order' => 2])
            ->create();

        $section3 = Section::factory()
            ->forChapter($chapter2)
            ->published()
            ->state([
                'title' => '2.1 アルゴリズムの基本',
                'body' => "## アルゴリズムとは\n\n**アルゴリズム** とは、問題を解くための明確な手順のことです。\n\n## 計算量\n\nアルゴリズムの効率を評価する指標として **オーダー記法 (O 記法)** を使います。\n\n- O(1): 定数時間 (ハッシュテーブルへのアクセス等)\n- O(log n): 対数時間 (二分探索等)\n- O(n): 線形時間 (線形探索等)\n- O(n^2): 二乗時間 (バブルソート等)\n- O(n log n): 線形対数時間 (マージソート / クイックソート等)",
                'order' => 1,
            ])
            ->create();

        $this->seedQuestionsForSection3($section3, $categories['technology']);

        Section::factory()
            ->forChapter($chapter2)
            ->draft()
            ->state([
                'title' => '2.2 探索アルゴリズム',
                'body' => "## 線形探索 / 二分探索\n\n(作成中)",
                'order' => 2,
            ])
            ->create();

        // Part 2 (draft) — cascade visibility 検証用
        // 親 Part が draft の場合、配下 Section が published でも受講生からは非公開扱いになることを実機確認可能にする
        $part2 = Part::factory()
            ->forCertification($certification)
            ->draft()
            ->state(['title' => '第2部 コンピュータシステム', 'description' => '(編集中)', 'order' => 2])
            ->create();

        $chapter3 = Chapter::factory()
            ->forPart($part2)
            ->published()
            ->state(['title' => '第3章 ハードウェア', 'order' => 1])
            ->create();

        Section::factory()
            ->forChapter($chapter3)
            ->published()
            ->state([
                'title' => '3.1 CPU の動作',
                'body' => "## CPU の構成\n\n**CPU (Central Processing Unit)** は演算装置と制御装置からなります。\n\n- **レジスタ**: 高速な記憶装置\n- **キャッシュメモリ**: L1 / L2 / L3 と階層化\n- **パイプライン処理**: 命令を並列実行して高速化",
                'order' => 1,
            ])
            ->create();
    }

    /**
     * @param  array<string, QuestionCategory>  $categories
     */
    private function seedOyojohoContents(Certification $certification, array $categories): void
    {
        $part = Part::factory()
            ->forCertification($certification)
            ->published()
            ->state(['title' => '第1部 セキュリティ', 'description' => '暗号化 / 認証 / 脆弱性対策を学ぶ', 'order' => 1])
            ->create();

        $chapter = Chapter::factory()
            ->forPart($part)
            ->published()
            ->state(['title' => '第1章 セキュリティ基礎', 'order' => 1])
            ->create();

        Section::factory()
            ->forChapter($chapter)
            ->published()
            ->state([
                'title' => '1.1 暗号化技術',
                'body' => "## 暗号化の分類\n\n- **共通鍵暗号**: AES, DES。鍵を送受信者間で共有。\n- **公開鍵暗号**: RSA, ECC。公開鍵と秘密鍵のペア。\n- **ハッシュ関数**: SHA-256, MD5。一方向性で改ざん検知に利用。\n\n## デジタル署名\n\nハッシュ関数 + 公開鍵暗号を組み合わせて、文書の真正性と改ざん検知を実現します。",
                'order' => 1,
            ])
            ->create();
    }

    private function seedQuestionsForSection1(Section $section, QuestionCategory $category): void
    {
        $q1 = SectionQuestion::factory()
            ->forSection($section)
            ->forCategory($category)
            ->published()
            ->state([
                'body' => '10 進数 25 を 2 進数で表現するとどれか?',
                'explanation' => '25 = 16 + 8 + 1 = 2^4 + 2^3 + 2^0 = 11001',
                'order' => 0,
            ])
            ->create();
        $this->seedOptions($q1, [
            ['body' => '10011', 'is_correct' => false],
            ['body' => '11001', 'is_correct' => true],
            ['body' => '10101', 'is_correct' => false],
            ['body' => '11010', 'is_correct' => false],
        ]);

        $q2 = SectionQuestion::factory()
            ->forSection($section)
            ->forCategory($category)
            ->draft()
            ->state([
                'body' => '16 進数 0xFF を 10 進数で表現するとどれか?',
                'explanation' => '0xFF = 15 * 16 + 15 = 255',
                'order' => 1,
            ])
            ->create();
        $this->seedOptions($q2, [
            ['body' => '255', 'is_correct' => true],
            ['body' => '256', 'is_correct' => false],
            ['body' => '127', 'is_correct' => false],
            ['body' => '128', 'is_correct' => false],
        ]);
    }

    private function seedQuestionsForSection3(Section $section, QuestionCategory $category): void
    {
        $q = SectionQuestion::factory()
            ->forSection($section)
            ->forCategory($category)
            ->published()
            ->state([
                'body' => '計算量が O(n^2) のアルゴリズムはどれか?',
                'explanation' => 'バブルソートは隣接要素を順次比較するため O(n^2)。ハッシュ検索は O(1)、二分探索は O(log n)。',
                'order' => 0,
            ])
            ->create();
        $this->seedOptions($q, [
            ['body' => 'バブルソート', 'is_correct' => true],
            ['body' => 'ハッシュテーブルへの単純検索', 'is_correct' => false],
            ['body' => '二分探索', 'is_correct' => false],
            ['body' => 'スタックへの push 操作', 'is_correct' => false],
        ]);
    }

    /**
     * @param  array<int, array{body: string, is_correct: bool}>  $options
     */
    private function seedOptions(SectionQuestion $question, array $options): void
    {
        foreach ($options as $idx => $opt) {
            SectionQuestionOption::create([
                'id' => (string) Str::ulid(),
                'section_question_id' => $question->id,
                'body' => $opt['body'],
                'is_correct' => $opt['is_correct'],
                'order' => $idx,
            ]);
        }
    }
}
