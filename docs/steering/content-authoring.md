# 教材・模試 執筆規約

> Certify LMS の学習コンテンツを **`模範解答プロジェクト/database/seeders/`** 配下に Markdown + YAML で執筆するための規約。
> 対象は 2 系統:
>
> 1. **教材** (Part → Chapter → Section → Section 紐づき演習問題) — `database/seeders/contents/{資格スラッグ}/` 配下、`ContentMarkdownSeeder` で取り込み
> 2. **模試** (MockExam → MockExamQuestion → MockExamQuestionOption) — `database/seeders/mock-exams/{資格スラッグ}/` 配下、`MockExamYamlSeeder` で取り込み(実装予定)
>
> 本ドキュメントは構築側メタ階層に属する(受講生には渡さない)。コンテンツファイル本体 (`*.md` / `*.questions.yaml` / `*.yaml` / `_meta.yaml`) は `模範解答プロジェクト/` 配下にあるため、Step 4 引き算変換後の提供プロジェクトにも残り、受講生がローカルで `sail artisan migrate:fresh --seed` を走らせると同じ教材・模試が入る。

---

# 第 1 章. 教材執筆規約

---

## 全体像

教材は **資格 1 つにつき 1 ディレクトリ** で管理する。`ContentMarkdownSeeder` (実装予定) が `database/seeders/contents/` 配下をすべて walk し、Part / Chapter / Section / SectionQuestion / SectionQuestionOption を順次 INSERT する。

```
模範解答プロジェクト/database/seeders/contents/
├── README.md                              # 規約のショートカット(任意、最小スキーマ抜粋を置く)
├── kihonjoho/                             # ← 資格スラッグ(1 ディレクトリ = 1 資格)
│   ├── _meta.yaml                         # この資格メタ(CertificationSeeder への参照)
│   ├── 01-第1部 基礎理論/                 # Part フォルダ
│   │   ├── _meta.yaml                     # Part メタ
│   │   ├── 01-第1章 進数と論理演算/       # Chapter フォルダ
│   │   │   ├── _meta.yaml                 # Chapter メタ
│   │   │   ├── 01-2進数の表現.md          # Section 本文(フロントマター + Markdown)
│   │   │   ├── 01-2進数の表現.questions.yaml   # この Section の演習問題
│   │   │   ├── 02-論理演算とブール代数.md
│   │   │   └── 02-論理演算とブール代数.questions.yaml
│   │   └── 02-第2章 アルゴリズムとデータ構造/
│   │       └── ...
│   └── 02-第2部 コンピュータシステム/
│       └── ...
└── oyojoho/
    └── ...
```

---

## ディレクトリ・ファイル命名規約

### フォルダ名は `NN-タイトル` 形式

| 階層 | フォルダ名例 | 説明 |
|---|---|---|
| 資格 | `kihonjoho/` `oyojoho/` `cissp/` | スラッグ(英小文字 + ハイフン)、Seeder の identity key。`_meta.yaml` で `certification: "資格名"` を指定して CertificationSeeder で作った資格と紐づける |
| Part | `01-第1部 基礎理論` | 先頭 `NN-` が `order` (DB の `parts.order` カラム)、残りが `title` (DB の `parts.title`)。**ハイフンの直後に半角スペースを 1 つ**置いてタイトルに続ける |
| Chapter | `01-第1章 進数と論理演算` | 同上 (`chapters.order` / `chapters.title`) |

### ファイル名は `NN-タイトル.md` / `NN-タイトル.questions.yaml`

| 種類 | ファイル名例 | 紐づくモデル |
|---|---|---|
| Section 本文 | `01-2進数の表現.md` | `sections.order=1` / `sections.title="2進数の表現"` / `sections.body=Markdown 本文全体` |
| Section 演習問題 | `01-2進数の表現.questions.yaml` | 同 Section に紐づく `section_questions` + `section_question_options` |

**Section の演習問題ファイルは Markdown と同じ `NN-タイトル` を共有** する (`.questions.yaml` を付けるだけ)。演習問題が無い Section では `.questions.yaml` を作らなくて良い。

### `NN-` の桁数

- `01` `02` ... `09` `10` `11` ... と **2 桁ゼロ埋め** を推奨。**`1-` `10-` だと辞書順で `10-` が `2-` より先に来てしまう** ので 2 桁にする。
- 既存配置との順序差し替えが頻発する想定なら 3 桁 (`001-` 等) も可。

---

## 各階層の `_meta.yaml` スキーマ

### 資格ルート `{slug}/_meta.yaml`

```yaml
certification: "基本情報技術者試験"   # 必須。CertificationSeeder で作成済の `name` と完全一致
```

ContentMarkdownSeeder が `Certification::where('name', $value)->firstOrFail()` で resolve する。**先に CertificationSeeder + CertificationCategorySeeder + QuestionCategory マスタを作っておく** こと(章 §「新資格を追加する手順」参照)。

### Part `_meta.yaml`

```yaml
status: published                # default: draft。draft / published のいずれか
description: "進数 / 論理演算 / アルゴリズムを学ぶ"   # 任意、Part カードに表示される
published_at: 2026-05-01         # 任意(status=published 時に自動で `now()` が入る)
```

### Chapter `_meta.yaml`

```yaml
status: published                # default: draft
published_at: 2026-05-01         # 任意
```

Chapter は `description` カラムを持たないので不要。

### 共通: cascade visibility

- **Part が draft** だと配下 Chapter / Section / SectionQuestion がすべて publish 済でも受講生には非公開扱いになる(cascade visibility、`SectionQuizPolicy` 等で実装)
- **Section だけ draft / Part は published** のような状態は、受講生に「学習中の見えない Section」が混じる demo 状態を作りたい時に有効。Seeder のリアルな状態網羅にも使える

---

## Section Markdown ファイルのスキーマ

ファイル形式: **YAML フロントマター + Markdown 本文**

```markdown
---
status: published
description: "進数変換と補数表現の基礎を学ぶ"   # 任意、Section 詳細画面のサブタイトル
published_at: 2026-05-01                       # 任意
---

## 2 進数の表現

コンピュータが扱うデータは **2 進数** で表現されます。

- 進数変換: 10 進数 ⇄ 2 進数 ⇄ 16 進数
- 補数表現: 1 の補数 / 2 の補数
- シフト演算: 算術シフト / 論理シフト

## 進数間の変換例

10 進数 25 → 2 進数 `11001`、16 進数 `0x19`

...
```

### フロントマターのフィールド

| キー | 必須/任意 | 説明 |
|---|---|---|
| `status` | 任意 (default: draft) | `draft` / `published` |
| `description` | 任意 | Section の short description。詳細画面のサブタイトル / 一覧の補足説明に使われる |
| `published_at` | 任意 | 公開日時。省略時、`status=published` なら自動で `now()` |

`title` はフロントマターに書かない。**ファイル名 `NN-タイトル.md` から自動抽出** する(NN を除いた残部分)。

### 本文の書き方

- **`#` 見出し (H1) は使わない**(タイトルはファイル名で表現済)
- **`##` から開始**(H2 以下を入れ子に)
- Markdown は league/commonmark (Wave 0b で導入済) でレンダリング。CommonMark + Tables + GFM 風記法に対応
- コードブロックは ` ``` ` で囲む(言語タグも書ける、ハイライト未対応だが将来追加可能)
- リスト・強調・リンク・テーブルは普通の Markdown 通り
- 画像はこの Seeder では扱わない(`SectionImage` モデル経由のアップロードフロー / 画像最初の MVP では文中に Markdown image 構文を直書きしても OK だが、画像ファイル本体は別途 `storage/app/public/` 等に配置が必要)

### Markdown サンプル

```markdown
## 論理演算

基本的な論理演算には **AND / OR / NOT / XOR** があります。

| A | B | AND | OR | XOR |
|---|---|-----|----|----|
| 0 | 0 | 0 | 0 | 0 |
| 0 | 1 | 0 | 1 | 1 |
| 1 | 0 | 0 | 1 | 1 |
| 1 | 1 | 1 | 1 | 0 |

## ブール代数

論理演算を代数的に扱う体系。回路設計やプログラムの条件式の最適化に応用されます。

> 補足: ド・モルガンの法則は試験頻出。
```

---

## 演習問題 `.questions.yaml` のスキーマ

ファイル名: `01-2進数の表現.questions.yaml` (同 Section の Markdown と同じベース名)

形式: **配列**。1 要素 = 1 SectionQuestion。

```yaml
- body: "10 進数 25 を 2 進数で表現するとどれか?"
  category: "テクノロジー系"
  status: published
  explanation: |
    25 = 16 + 8 + 1 = 2^4 + 2^3 + 2^0
    したがって 2 進数表現は 11001 になります。
  options:
    - { body: "10011", correct: false }
    - { body: "11001", correct: true }
    - { body: "10101", correct: false }
    - { body: "11010", correct: false }

- body: "16 進数 0xFF を 10 進数で表現するとどれか?"
  category: "テクノロジー系"
  status: published
  explanation: |
    0xFF = 15 × 16 + 15 = 240 + 15 = 255
  options:
    - { body: "255", correct: true }
    - { body: "256", correct: false }
    - { body: "127", correct: false }
    - { body: "128", correct: false }
```

### フィールド

| キー | 必須/任意 | 説明 |
|---|---|---|
| `body` | **必須** | 問題本文(Markdown 不可、プレーンテキスト or 軽い inline HTML 程度)。改行を含めたい場合は YAML の `|` を使う |
| `category` | **必須** | `QuestionCategory.name` と完全一致する文字列。所属資格配下のマスタを `where("certification_id", ...)->where("name", $value)` で resolve。**見つからないと Seeder が throw** |
| `status` | 任意 (default: draft) | `draft` / `published` |
| `explanation` | 任意 | 結果画面で表示される解説。YAML の `|` で改行を保持できる |
| `options` | **必須** | 選択肢配列。**最低 2 件、推奨 4 件**。`{body: "...", correct: true/false}` の集合。`correct: true` は **必ず 1 つだけ**(単一正答モデル) |

### 並び順

- SectionQuestion の `order` = YAML 配列のインデックス(0 始まり)
- SectionQuestionOption の `order` = options 配列のインデックス(0 始まり)
- 表示上は ContentMarkdownSeeder が `order ASC` で並べるので、**書いた順がそのまま表示順**

### 制約

| 制約 | 違反時 |
|---|---|
| `options` が `correct: true` を 1 つ以上含む | ContentMarkdownSeeder で `RuntimeException` |
| `options` の `correct: true` が 2 つ以上 | 同上(単一正答モデル) |
| `options` 件数 < 2 | 同上 |
| `category` が QuestionCategory マスタに存在しない | 同上(資格内のマスタに見つからないと throw) |
| `body` が空文字 | 同上 |

---

## 新資格を追加する手順 (チェックリスト)

新資格を投入する流れ。**必ず資格本体・カテゴリマスタ → 教材 の順序で進める**。

### 1. 資格本体を `CertificationSeeder` に追加

`database/seeders/CertificationSeeder.php` を編集:

```php
Certification::factory()->create([
    'name' => 'CISSP',
    'category_id' => $itCategory->id,    // CertificationCategorySeeder で作成済のカテゴリ
    'difficulty' => CertificationDifficulty::Advanced->value,
    'description' => '情報セキュリティの上位資格',
    'status' => CertificationStatus::Published->value,
    'created_by_user_id' => $admin->id,
    'updated_by_user_id' => $admin->id,
    'published_at' => now(),
]);
```

### 2. 出題分野マスタを資格に紐づけて作成

同 `CertificationSeeder` 内、または `ContentSeeder` (廃止予定) の `seedQuestionCategoriesForXxx()` パターンに倣う形で、新資格用の `QuestionCategory` を生成:

```php
QuestionCategory::factory()->forCertification($cissp)->state([
    'name' => 'セキュリティ運用',
    'slug' => 'security-operation',
    'sort_order' => 10,
])->create();

QuestionCategory::factory()->forCertification($cissp)->state([
    'name' => 'リスクマネジメント',
    'slug' => 'risk-management',
    'sort_order' => 20,
])->create();
```

`.questions.yaml` の `category` フィールドはここで作った `name` (例: `"セキュリティ運用"`) と完全一致させる。

### 3. 教材ディレクトリを切る

```
database/seeders/contents/cissp/
├── _meta.yaml                      # certification: "CISSP"
├── 01-第1部 セキュリティ運用/
│   ├── _meta.yaml                  # status: published / description: "..."
│   ├── 01-第1章 監視と検知/
│   │   ├── _meta.yaml              # status: published
│   │   ├── 01-SIEM の役割.md
│   │   ├── 01-SIEM の役割.questions.yaml
│   │   └── ...
│   └── ...
└── ...
```

### 4. 取り込み確認

```bash
sail artisan migrate:fresh --seed
sail artisan tinker --execute='echo "questions=" . \App\Models\SectionQuestion::count() . PHP_EOL;'
```

教材画面 (`/learning/enrollments/{enrollment}`) で表示されることを実機確認。

### 5. テスト全 PASS

```bash
sail artisan test 2>&1 | tail -5
```

教材追加は既存テストに影響しないはずだが、念のため確認。

---

## 受講生向けコンテキスト規約 (重要)

Section 本文 / 演習問題は **受講生が読むテキスト** なので、`frontend-blade.md` の「ユーザー向け文言の規約」に準じる:

### 禁止される露出

| カテゴリ | 禁止例 | 業務用語への置換 |
|---|---|---|
| **構築側メタ語** | `[[feature-name]]` wikilink / `docs/specs/` パス / `Step N` / `Phase X` / `v3 改修` / `P1-X` / `COACHTECH` / `Pro 生` / `模擬案件` / `2026-05-XX` | 削除、または「最新仕様で」等の自然語に置換 |
| **DB スキーマ用語** | `section_questions` / `SectionQuestionAnswer` / カラム名(`granted_by_user_id` 等) | 削除、または「演習問題」「解答履歴」等の業務用語へ |
| **Enum 機械値** | `admin_grant` / `learning` / `passed` 等の snake_case | `Enum->label()` 相当の日本語表記へ |
| **改修フェーズ情報** | 「v3 で追加」「Step 4 で公開」等の構築側履歴 | 削除 |

### 良例 / 悪例

```markdown
<!-- ❌ 悪い(構築側メタ語が露出) -->
## ハッシュ法 (v3 改修で追加)

[[content-management]] の SectionQuestion で詳しく演習できます。

<!-- ✅ 良い(業務用語のみ) -->
## ハッシュ法

このセクションの演習問題でアルゴリズムの理解度を確認しましょう。
```

```yaml
# ❌ 悪い(Enum 機械値が問題本文に露出)
- body: "section_questions.status が published のものだけ受講生に表示される。正しいか?"

# ✅ 良い(業務用語に置換)
- body: "「公開中」状態の演習問題のみ受講生に表示される。正しいか?"
```

### 業務用語の SSoT

業務用語の整合は `docs/steering/product.md` の語彙を参照。新業務用語が必要なら先に `product.md` に追加してから教材で使う。

---

## 取り込み・確認手順

### フル取り込み

```bash
cd 模範解答プロジェクト
sail artisan migrate:fresh --seed
```

`DatabaseSeeder` が以下を順次実行:

1. `UserSeeder` (固定アカウント + 状態網羅 demo 受講生)
2. `PlanSeeder` / `MeetingQuotaPlanSeeder`
3. `CertificationCategorySeeder` / `CertificationSeeder` (資格 + 出題分野マスタ)
4. `EnrollmentSeeder` (固定 student / demo 受講生の受講登録)
5. `ContentMarkdownSeeder` (本ディレクトリの教材すべて) ← 教材投入
6. `LearningSeeder` (Section 読了 / 学習時間 demo)
7. `QuizAnsweringSeeder` (解答ログ / Attempt サマリ demo)

### 部分取り込み (教材だけ再投入したい時)

```bash
sail artisan db:seed --class=ContentMarkdownSeeder
```

ただし、教材の参照(`SectionProgress` / `SectionQuestionAttempt` / `SectionQuestionAnswer` / `LearningSession`)が既存レコードと整合性を保つ保証はないので、開発中は `migrate:fresh --seed` で全リセットが安全。

### 動作確認

```bash
# レコード数の確認
sail artisan tinker --execute='
echo "Parts: " . \App\Models\Part::count() . PHP_EOL;
echo "Chapters: " . \App\Models\Chapter::count() . PHP_EOL;
echo "Sections: " . \App\Models\Section::count() . PHP_EOL;
echo "SectionQuestions: " . \App\Models\SectionQuestion::count() . PHP_EOL;
echo "Published SectionQuestions: " . \App\Models\SectionQuestion::where("status","published")->count() . PHP_EOL;
'

# 実機ブラウザ確認
# 1. http://localhost:8000/login で student@certify-lms.test / password
# 2. /learning/enrollments/{enrollment} で Part > Chapter > Section が表示される
# 3. Section 詳細で本文が Markdown レンダリングされる
# 4. /quiz/sections/{section} で演習問題が並ぶ
# 5. 解答送信 → 結果画面で正答 / 解説が表示される
```

---

## ContentMarkdownSeeder の挙動 (参考)

実装側 Claude が `database/seeders/ContentMarkdownSeeder.php` で以下を行う:

1. `database/seeders/contents/` 配下を `Symfony\Component\Finder\Finder` で walk
2. 各 `{slug}/_meta.yaml` を読み、`Certification::where('name', ...)->firstOrFail()` で資格を解決
3. Part フォルダを `order ASC` で順次処理、`_meta.yaml` + フォルダ名から `Part::create()`
4. Chapter フォルダ同様
5. Section `.md` ファイル: 先頭の `---` で囲まれた YAML フロントマターをパース → 残りを `body` に → `Section::create()`
6. 対応する `.questions.yaml` があれば配列を loop して `SectionQuestion::create()` + `SectionQuestionOption::create()`
7. `category` の resolve に失敗、`options` の制約違反、フロントマターのスキーマ違反は即 `RuntimeException`(早期失敗 + 詳細メッセージで該当ファイルパスを表示)

YAML パースは Laravel 同梱の `Symfony\Component\Yaml\Yaml::parse()`、フロントマター切り出しは正規表現 `/^---\n(.*?)\n---\n(.*)$/s` で十分(別パッケージ不要)。

---

## トラブルシュート

| 症状 | 原因 | 対処 |
|---|---|---|
| `Certification not found: "..."` | `_meta.yaml` の `certification` が CertificationSeeder で作った `name` と不一致 | name を完全一致させる(全角半角・スペースに注意) |
| `QuestionCategory not found: "..."` | `.questions.yaml` の `category` が QuestionCategory マスタに無い | CertificationSeeder か別 Seeder でカテゴリを先に作る |
| `Single correct option required, got N` | `options` の `correct: true` が 0 個 or 2 個以上 | 必ず 1 つだけにする |
| Section 本文が Markdown としてレンダリングされない | フロントマターの YAML 構文エラー | `status: published` 等のコロン後にスペースがあるか確認 |
| 表示順がおかしい | フォルダ / ファイル名の `NN-` が桁数不揃い | 全ファイル名を 2 桁ゼロ埋め (`01-` `02-` ...) に統一 |
| 教材が一切表示されない | Part / Chapter / Section のいずれかが draft 状態 | cascade visibility に注意。`status: published` を全階層に設定 |

---

---

# 第 2 章. 模試執筆規約

模試 (MockExam) は **資格直下のフラットな問題セット** で、Section 紐づき演習問題と異なり教材階層 (Part / Chapter / Section) には属さない。1 模試 = 1 YAML ファイル。

## 全体像

```
模範解答プロジェクト/database/seeders/mock-exams/
├── kihonjoho/                              # ← 教材と同じ資格スラッグ
│   ├── 01-基本情報模試 第1回.yaml          # 1 ファイル = 1 MockExam
│   ├── 02-基本情報模試 第2回.yaml
│   └── 03-基本情報模試 直前演習.yaml
├── oyojoho/
│   └── 01-応用情報模試 第1回.yaml
└── cissp/
    └── 01-CISSP 練習試験.yaml
```

- ディレクトリ: `mock-exams/{資格スラッグ}/` — 教材の `contents/{資格スラッグ}/` と同じスラッグ命名を採用(同一資格の教材・模試を視覚的に対応付け)
- ファイル名: `NN-模試タイトル.yaml` — 教材の Section ファイル名と同じ `NN-タイトル` 形式。`NN` が `mock_exams.order`、残部分が `mock_exams.title`

教材と異なり **資格 `_meta.yaml` は不要**。資格は YAML 内の `certification:` フィールドで指定する(後述)。

---

## 模試ファイルのスキーマ

ファイル名: `01-基本情報模試 第1回.yaml`

形式: **オブジェクト**(教材の questions.yaml と違って配列ではない、模試 1 件を表すオブジェクト)

```yaml
certification: "基本情報技術者試験"
description: "基本情報技術者試験の総合模試。基礎理論からセキュリティまで全範囲をカバー。"
status: published                  # default: draft (DB の is_published に対応)
passing_score: 60                  # 100 点満点中の合格ライン(任意、default: 60)
published_at: 2026-05-01           # 任意

questions:
  - body: "10 進数 25 を 2 進数で表現するとどれか?"
    category: "テクノロジー系"
    explanation: |
      25 = 16 + 8 + 1 = 2^4 + 2^3 + 2^0
      したがって 2 進数表現は 11001 になります。
    options:
      - { body: "10011", correct: false }
      - { body: "11001", correct: true }
      - { body: "10101", correct: false }
      - { body: "11010", correct: false }

  - body: "16 進数 0xFF を 10 進数で表現するとどれか?"
    category: "テクノロジー系"
    explanation: |
      0xFF = 15 × 16 + 15 = 240 + 15 = 255
    options:
      - { body: "255", correct: true }
      - { body: "256", correct: false }
      - { body: "127", correct: false }
      - { body: "128", correct: false }

  - body: "共通鍵暗号方式の特徴として正しいものはどれか?"
    category: "情報セキュリティ"
    explanation: "..."
    options:
      - ...
```

### トップレベルフィールド

| キー | 必須/任意 | 説明 |
|---|---|---|
| `certification` | **必須** | `Certification.name` と完全一致する文字列。`CertificationSeeder` で作成済の資格名を参照(教材 `_meta.yaml` と同じ規則) |
| `description` | 任意 | 模試一覧 / 受験前画面に表示される説明文 |
| `status` | 任意 (default: draft) | `draft` / `published` (DB の `is_published` boolean に変換) |
| `passing_score` | 任意 (default: 60) | 0-100 の整数。受験結果画面の合否判定基準 |
| `published_at` | 任意 | 公開日時。省略時、`status=published` なら自動で `now()` |
| `questions` | **必須** | 問題配列(最低 1 問)。各要素のスキーマは下記 |

### `title` はファイル名から自動抽出

教材 Section と同じく、`title` は **ファイル名の `NN-` を除いた残部分** から自動抽出する。YAML 内に `title:` フィールドを書かない。

- ファイル `01-基本情報模試 第1回.yaml` → `MockExam.title = "基本情報模試 第1回"`、`MockExam.order = 1`

### `questions` 配列の要素スキーマ

| キー | 必須/任意 | 説明 |
|---|---|---|
| `body` | **必須** | 問題本文。プレーンテキスト or 軽い inline 記号。改行は YAML の `|` |
| `category` | **必須** | `QuestionCategory.name` と完全一致。所属資格配下のマスタを `where("certification_id", ...)` で resolve。教材の演習問題と同じ QuestionCategory マスタを共有する |
| `explanation` | 任意 | 結果画面で表示される解説。`|` で改行保持 |
| `options` | **必須** | 選択肢配列。最低 2 件、推奨 4 件。`{body: "...", correct: true/false}`。`correct: true` は **1 つだけ**(単一正答モデル) |

### 模試の `status` と教材の差異

| 項目 | 教材 (Section) | 模試 (MockExam) |
|---|---|---|
| DB カラム | `status` (`draft` / `published` の string enum) | `is_published` (boolean) |
| YAML 表現 | `status: published` | `status: published` (Seeder が boolean に変換) |
| cascade visibility | 親 Part / Chapter が published でないと配下も非公開 | 単独で完結(階層なし)。`is_published=true` ならカタログに表示 |

### 並び順

- `MockExam.order` = ファイル名の `NN`
- `MockExamQuestion.order` = `questions` 配列のインデックス(0 始まり)
- `MockExamQuestionOption.order` = `options` 配列のインデックス(0 始まり)

### 制約

| 制約 | 違反時 |
|---|---|
| `certification` が CertificationSeeder で作成済の `name` と一致 | `RuntimeException` |
| `questions` の各要素の `category` が QuestionCategory マスタに存在 | 同上(資格内マスタに見つからないと throw) |
| `options` の `correct: true` がちょうど 1 つ | 同上(0 個 or 2 個以上はエラー) |
| `options` 件数 ≥ 2 | 同上 |
| `passing_score` が 0-100 の整数 | 同上 |
| `questions` 件数 ≥ 1 | 同上(空の模試は登録不可) |

---

## 新模試を追加する手順 (チェックリスト)

### 既存資格に模試を追加する場合

1. `database/seeders/mock-exams/{資格スラッグ}/NN-模試タイトル.yaml` を作成
2. `certification` フィールドに既存の資格名を指定(`Certification.name` と完全一致)
3. `questions[].category` は既存の `QuestionCategory.name` を参照(マスタは教材と共有)
4. `sail artisan migrate:fresh --seed` で取り込み確認

### 新資格 + 新模試を一緒に追加する場合

教材の「新資格を追加する手順」と同じ順序:

1. `CertificationSeeder` に資格本体を追加
2. `QuestionCategory` マスタを資格に紐づけて作成(教材 / 模試で共有)
3. 教材 `database/seeders/contents/{slug}/` を切る(必要なら)
4. **模試** `database/seeders/mock-exams/{slug}/01-...yaml` を切る
5. `sail artisan migrate:fresh --seed`

### 動作確認

```bash
# 件数確認
sail artisan tinker --execute='
echo "MockExams: " . \App\Models\MockExam::count() . PHP_EOL;
echo "Published: " . \App\Models\MockExam::where("is_published", true)->count() . PHP_EOL;
echo "Questions: " . \App\Models\MockExamQuestion::count() . PHP_EOL;
'

# 実機確認
# 1. student@certify-lms.test でログイン
# 2. /mock-exams で模試カタログ表示確認
# 3. 受験開始 → 全問解答 → 結果画面で採点 + 弱点ヒートマップ確認
```

---

## MockExamYamlSeeder の挙動 (参考)

実装側 Claude が `database/seeders/MockExamYamlSeeder.php` で以下を行う:

1. `database/seeders/mock-exams/` 配下を `Finder` で walk(資格スラッグディレクトリ → YAML ファイル)
2. 各 YAML を `Symfony\Component\Yaml\Yaml::parseFile()` で読み込み
3. `certification` から `Certification::where('name', ...)->firstOrFail()`
4. ファイル名 `NN-タイトル.yaml` から `order` / `title` 抽出
5. `MockExam::create()` で本体作成、`status: published` を `is_published: true` に変換
6. `questions` 配列を loop して `MockExamQuestion::create()` + `MockExamQuestionOption::create()`
7. category 解決失敗 / options 制約違反 / passing_score 範囲外は即 `RuntimeException`(ファイルパスと問題インデックス番号を含む詳細メッセージ)

既存の `MockExamSeeder` (ハードコード版がある場合) は廃止 or YAML 化リファレンスに変換する判断は実装フェーズで決定する。

`DatabaseSeeder` での実行順:

1. UserSeeder
2. PlanSeeder / MeetingQuotaPlanSeeder
3. CertificationCategorySeeder
4. CertificationSeeder (資格本体 + QuestionCategory マスタ)
5. EnrollmentSeeder
6. **ContentMarkdownSeeder** (教材) ← QuestionCategory に依存
7. **MockExamYamlSeeder** (模試) ← QuestionCategory に依存、教材とは独立 ← New
8. LearningSeeder (読了 / 学習時間 demo)
9. QuizAnsweringSeeder (Section 演習の解答 demo)
10. (模試 demo Seeder があれば後続に追加)

---

## 模試の受講生向けコンテキスト規約

第 1 章「受講生向けコンテキスト規約」と同じルールが模試にも適用される:

- 問題本文・解説に **構築側メタ語**(`[[feature-name]]` / `Step N` / `v3 改修` / `COACHTECH` 等)を入れない
- **DB スキーマ用語**(`mock_exams` / `MockExamQuestion` / カラム名)を出さない
- **Enum 機械値**(`section_quiz` / `weak_drill` 等の snake_case)を出さない
- 業務用語(受講生 / コーチ / 管理者 / 模試 / 出題分野 / 合格点 / 受験 等)のみで記述

### 良例 / 悪例

```yaml
# ❌ 悪い(構築側メタ語が問題本文に露出)
- body: "v3 改修で MockExam モデルに passing_score カラムが追加された。これは正しいか?"

# ✅ 良い(業務シナリオベース、内部用語なし)
- body: "ソフトウェア工学において、要件定義の段階で利用者から要求事項を聞き取る活動を何と呼ぶか?"
```

---

## 模試固有のトラブルシュート

教材と共通のエラー(`Certification not found` / `QuestionCategory not found` / `Single correct option required` 等)は第 1 章「トラブルシュート」を参照。模試固有のものは下記:

| 症状 | 原因 | 対処 |
|---|---|---|
| `passing_score must be 0-100` | `passing_score: 150` 等の範囲外 | 0-100 の整数に修正 |
| 模試カタログに表示されない | `status: draft` または `is_published: false` | `status: published` に変更 |
| `questions must not be empty` | `questions: []` または `questions:` のみ | 最低 1 問は記述する |
| 問題順序が指定と違う | 配列要素の順序が YAML パーサで保持されていない | `Symfony\Yaml::parse` は配列順序を保持するため発生しないはず。再現したら Seeder のバグとして報告 |
| 教材の演習問題と模試の問題が混在 | カテゴリ集計画面で `SectionQuestion` と `MockExamQuestion` を区別せず表示している | これは Seeder ではなく集計 Service / Blade 側の問題(spec 上、苦手分野ドリルは `SectionQuestion` のみ出題で `MockExamQuestion` 不含) |

---

## 関連ドキュメント

- 業務用語の SSoT: `docs/steering/product.md`
- ディレクトリ・命名規則: `docs/steering/structure.md`
- 技術スタック・規約: `docs/steering/tech.md`
- ユーザー向け文言規約: `.claude/rules/frontend-blade.md` 「ユーザー向け文言の規約」
- Eloquent Model 規約: `.claude/rules/backend-models.md`
- mock-exam 仕様: `docs/specs/mock-exam/{requirements,design,tasks}.md`
