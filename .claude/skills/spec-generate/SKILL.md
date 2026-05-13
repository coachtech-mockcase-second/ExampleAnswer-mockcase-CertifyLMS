---
name: spec-generate
description: 1 Feature の docs/specs/{name}/{requirements,design,tasks}.md の3点セットを生成する。$ARGUMENTS に Feature 名（例 mock-exam）を渡す。自己完結・直列。並列で複数 Feature を生成したい場合は worktree-spawn Skill で別 Claude セッションを立ち上げて各セッションでこの Skill を使う
---

# spec-generate

1 Feature の **完成形 SDD（spec 3点セット）** を自己完結で生成するスキル。直列実行。

## 入力

`$ARGUMENTS`: Feature 名（kebab-case）。例: `mock-exam`, `enrollment`, `auth`
無ければユーザーに確認する。

## 記述言語

3 ドキュメントは **日本語ベース** で記述する（模範解答仕様としてコーチ・受講生が読む可能性を想定）。

EARS は **構造キーワード（`shall` / `when` / `if` / `while`）だけを英語のまま残し、述語は日本語**で書く「ハイブリッド形式」を採用する。iField LMS の Kiro 流に倣う。

**書き方の型**:

- `The {主語} shall {日本語述語}。`
- `When {日本語条件}, the {主語} shall {日本語述語}。`
- `If {日本語条件}, then the {主語} shall {日本語述語}。`
- `While {日本語条件}, the {主語} shall {日本語述語}。`

**例**:

- The system shall ULID 主キー / `email` UNIQUE を備えた `users` テーブルを提供する。
- When ユーザーが招待 URL にアクセスした際, the system shall URL 署名・有効期限・`Invitation.status` を検証する。
- If 招待トークンが期限切れの場合, then the system shall HTTP 410 Gone で拒否する。

主語は `the system` / `the {Feature} Module` / `the {Entity} Controller` 等、文脈に応じて使い分ける。**1 spec 内では主語スタイルを統一**する（混在を避ける）。

> Why ハイブリッド形式: EARS のキーワードはトレーサビリティと網羅性チェックの拠り所。日本語訳すると（「〜の時、システムは〜する」等）パターン認識が崩れ、要件カウントや not / when / if の分布分析がやりにくくなる。一方、述語まで英語にすると受講生・コーチへの説明コストが上がる。iField LMS / Kiro / 大手 SI のテンプレも同じ折衷を採用している。

## 必須読み込み

実行前に Read:

1. `CLAUDE.md` — 「実装プラン」セクション（Feature 一覧・依存関係を確認）
2. `docs/steering/product.md` — 該当 Feature の説明 + 関連 UXフロー + stateDiagram
3. `docs/steering/tech.md` — Clean Architecture / 命名規則 / Action命名 / PR規約
4. `docs/steering/structure.md` — ディレクトリ / 命名規則 / specs/ 作成ルール
5. `.claude/rules/` 配下のルール（paths frontmatter で自動ロード）
6. 依存先 Feature の `docs/specs/{dep}/design.md`（あれば）

## 参考にする既存実装

**実装パターン**（design.md の中身）と **SDD ドキュメント構造**（spec の書き方）は別軸で参考にする。

### 📦 実装パターン: COACHTECH LMS

`/Users/yotaro/lms/backend/` — 本番運用中の Laravel LMS。Certify と同じ Clean Architecture（軽量版）+ Eloquent + Action/Service 構成、ディレクトリ・命名規則も近い。

**design.md 生成前に毎回調査**:

| ステップ | コマンド例 |
|---|---|
| Model 探索 | `ls /Users/yotaro/lms/backend/app/Models/` |
| 関連コード横断検索 | `grep -rli "{キーワード}" /Users/yotaro/lms/backend/app/` |
| Migration 探索 | `find /Users/yotaro/lms/backend/database/migrations -name "*{keyword}*"` |

- **対応実装あり** → Model / Controller / Action / Migration を Read し、観察パターンを design.md 先頭「参考実装」セクションに明記
- **対応実装なし** → LMS 業界標準（Laravel コミュニティ標準 + Moodle / Canvas 等の慣習）+ 周辺の類推可能な実装で補完し、「業界標準に準拠」と明記

> Certify は **ULID 採用・SoftDeletes 標準・教育PJスコープ** という差異あり。COACHTECH の設計をそのままコピーせず、`structure.md` / `tech.md` / `.claude/rules/` に翻訳する。

### 📝 SDD ドキュメント構造

| 参考 | 何を学ぶか |
|---|---|
| **iField LMS** (`/Users/yotaro/ifield-lms/.kiro/specs/`) | Kiro 流 5ファイル構成 + 各ファイルの構造・粒度・密度（大型 Feature で要件 10+ / 設計 200+ 行 / tasks 50+ チェックボックス）|
| **COACHTECH LMS の steering Skill** (`/Users/yotaro/lms/.claude/skills/steering/SKILL.md`) | `1-requirements.md` / `2-design.md` / `3-tasklist.md` の段階構造 + タスク粒度（1 タスク = 1 コミット）|

**iField LMS の優先参照ルール**:

`ls /Users/yotaro/ifield-lms/.kiro/specs/` で利用可能な spec を確認。**生成中の Feature と同名 / 類似の spec があれば、それを最優先で読む**:

| 生成中の Feature | 優先参照（あれば） |
|---|---|
| `auth` | `auth/` |
| `user-management` | `user-management/` |
| `dashboard` | `dashboard/` |
| `mock-exam` | `mock-projects/` (試験系として類似) |
| `quiz-answering` | `quiz/` |
| `content-management` | `contents-and-quizzes/` / `content-sync-workflow/` / `content-version-management/` |
| `settings-profile` | `settings-profile/` |
| 上記以外 | `contents-and-quizzes/`（大型 Feature の代表サンプル）|

> 同名 / 類似 Feature の spec を読むと「データモデル粒度」「EARS 要件数の目安」「コンポーネント分割の流儀」が直接学べる。Certify と iField はスタック（Laravel vs Next.js + Supabase）が違うので**実装は流用しない**が、**spec の書きぶり**は流用してよい。

## 生成する3ドキュメント

### 1. `docs/specs/{name}/requirements.md`

EARS形式（ハイブリッド: 構造キーワードのみ英語、述語は日本語。「## 記述言語」参照）の受け入れ基準。

```markdown
# {Feature 名} 要件定義

## 概要
（Feature の役割、product.md の該当箇所のサマリ、3-5行）

## ロールごとのストーリー
- 受講生（student）: …
- コーチ（coach）: …
- 管理者（admin）: …

## 受け入れ基準（EARS形式）

### 機能要件 — {サブ領域 1}
- **REQ-{name}-001**: The system shall {日本語述語}。
- **REQ-{name}-002**: When {日本語条件}, the system shall {日本語述語}。
- **REQ-{name}-003**: If {日本語条件}, then the system shall {日本語述語}。

### 機能要件 — {サブ領域 2}
- **REQ-{name}-010**: …
- **REQ-{name}-011**: …

### 非機能要件
- **NFR-{name}-001**: The system shall {日本語述語}。

## スコープ外
- {対象外項目}（[[他の Feature]] 等で扱う旨）

## 関連 Feature
- **依存元**（本 Feature を利用する）: [[other-feature]] — 利用の仕方
- **依存先**（本 Feature が前提とする）: なし、または [[base-feature]]
```

要件 ID 規約:

- `REQ-{name}-{NNN}` 形式（NNN は3桁。サブ領域ごとに 001/010/020/030… と10刻みで採番、間に追加要件を入れる余地を残す）
- `NFR-{name}-{NNN}` で非機能要件を区別
- design.md の「関連要件マッピング」と tasks.md のタスク末尾注釈から **必ずトレース可能** にする
- 他 Feature への参照は `[[feature-name]]` wikilink（memory システムと整合、関連付け navigation 可）

### 2. `docs/specs/{name}/design.md`

**🔴 生成前提**: 「参考にする既存実装 → 調査手順」で COACHTECH LMS を調査済みであること。観察したパターンは **設計内容そのものに織り込む**（design.md 内に「参考実装」セクションは設けない、調査結果のサマリは完了報告で伝える）。

```markdown
# {Feature 名} 設計

## アーキテクチャ概要
（Mermaid sequenceDiagram or flowchart）

## データモデル
- Eloquent モデル一覧（structure.md 準拠、ULID + SoftDeletes）
- リレーション図（Mermaid erDiagram）
- 主要カラム + Enum

## 状態遷移
（該当する場合のみ。stateDiagram-v2 単行ラベル、`:` をラベル内で使わない）

## コンポーネント

### Controller
- {Entity}Controller — メソッド一覧（index/show/store/update/destroy + カスタム）

### Action（UseCase）

各 Action は **PHP シグネチャを明示** する。曖昧な振る舞い（force flag、optional behavior）は **必ず引数で表現** し、文章で「呼び出し側が指定する」とぼかさない。Action の責務とトランザクション境界も併記。

```php
// app/UseCases/{Entity}/{Action}Action.php
class {Action}Action
{
    public function __construct(
        private {Dep1} $dep1,
        private {Dep2} $dep2,
    ) {}

    public function __invoke({Type1} $arg1, {Type2} $arg2, bool $force = false): {ReturnType}
    {
        // 整合性チェック → DB::transaction で状態変更 → 戻り値
    }
}
```

- **IndexAction / ShowAction / StoreAction / UpdateAction / DestroyAction**: CRUD 系。Controller method 名と一致
- **{Custom}Action**: 業務操作。`Fetch{Name}Action`（取得系）/ 動詞 + Action（操作系）
- 各 Action 末尾に **責務 1 行 + 例外 1 行** を併記

### Service
- {Feature}Service — 共有計算ロジック（あれば）。公開メソッドはシグネチャを明示

### Policy
- {Entity}Policy — viewAny / view / create / update / delete + カスタム
- 各メソッドのロール別判定ルールを箇条書き

### FormRequest
- StoreRequest / UpdateRequest — バリデーション・認可
- 主要 rule とエラーメッセージの方針

### Resource（API のみ）
- {Entity}Resource

## Blade ビュー
- 画面一覧（index / show / form / etc.）
- 主要コンポーネント

## エラーハンドリング
- 想定例外（app/Exceptions/{Domain}/ 配下）
- 状態整合性違反時の例外
- 列挙攻撃等のセキュリティ配慮（該当時）

## 関連要件マッピング

| 要件ID | 実装ポイント |
|---|---|
| REQ-{name}-001 | {file path / class / method} |
| REQ-{name}-002 | {file path / class / method} |
| NFR-{name}-001 | {file path / config} |

**すべての主要要件**（NFR 含む）が表に出現すること。逆引きで未実装の REQ を検出できるようにする。
```

### 3. `docs/specs/{name}/tasks.md`

各タスクには **関連要件 ID を inline 注釈**（`（REQ-{name}-XXX, REQ-{name}-YYY）`）で付ける。これにより tasks → requirements の逆引きが成立し、PR レビュー時に「どの要件を満たすコミットか」が一目でわかる。

```markdown
# {Feature 名} タスクリスト

> 1タスク = 1コミット粒度。Step 単位で順次実装し、完了したものから `[x]` に更新する。
> 関連要件 ID は `requirements.md` の `REQ-{name}-NNN` / `NFR-{name}-NNN` を参照。

## Step 1: Migration & Model
- [ ] migration: create_{table}_table（ULID, SoftDeletes 必須）（REQ-{name}-XXX）
- [ ] Model: {Entity}（fillable, casts, リレーション, スコープ）（REQ-{name}-XXX）
- [ ] Enum: {EnumName}（label() 含む）（REQ-{name}-XXX）
- [ ] Factory: {state1}() / {state2}() state 提供

## Step 2: Policy
- [ ] {Entity}Policy（viewAny / view / create / update / delete）（REQ-{name}-XXX）
- [ ] AuthServiceProvider に登録 or 自動検出確認

## Step 3: HTTP 層
- [ ] {Entity}Controller スケルトン（薄く保つ）（REQ-{name}-XXX）
- [ ] StoreRequest / UpdateRequest（rules + authorize）（REQ-{name}-XXX）
- [ ] {Entity}Resource（API の場合）
- [ ] routes/web.php / routes/api.php にルート定義（REQ-{name}-XXX）

## Step 4: Action / Service / Exception
- [ ] IndexAction / ShowAction / StoreAction / UpdateAction / DestroyAction（REQ-{name}-XXX）
- [ ] カスタム Action（Controller method 名と一致）（REQ-{name}-XXX）
- [ ] {Feature}Service（共有ロジック必要時）（REQ-{name}-XXX）
- [ ] ドメイン例外（app/Exceptions/{Domain}/）（NFR-{name}-XXX）

## Step 5: Blade ビュー
- [ ] resources/views/{feature}/index.blade.php
- [ ] show / form / etc.
- [ ] Blade コンポーネント（必要時）

## Step 6: テスト
- [ ] tests/Feature/Http/{Entity}/{Action}Test.php（正常系 + バリデーション失敗 + 認可漏れ）
  - `test_{role}_can_{action}_{resource}`
  - `test_{role}_cannot_{action}_other_users_{resource}`
- [ ] tests/Feature/UseCases/{Entity}/{Action}ActionTest.php（カスタム Action の正常系 + 異常系）
- [ ] tests/Unit/Services/{Feature}ServiceTest.php（純粋ロジック、あれば）
- [ ] tests/Unit/Policies/{Entity}PolicyTest.php（ロール×操作の真偽値網羅）

## Step 7: 動作確認 & 整形
- [ ] `sail artisan test --filter={Entity}` 通過
- [ ] `sail bin pint --dirty` 整形
- [ ] ブラウザでの主要画面動作確認（通しシナリオを箇条書き）
- [ ] Schedule Command / Queue Job の動作確認（該当時、`sail artisan {command}` 手動実行）
```

タスクは Step 単位グループ + チェックボックス。1 タスク = 1 コミット粒度。**全タスク末尾に関連要件 ID を inline 注釈**で付ける（テスト系・整形系など要件 ID 不要なタスクは省略可）。

**コマンドは Sail プレフィックス必須**: 開発環境は Laravel Sail。tasks.md / 完了報告に書くコマンドはすべて `sail artisan ...` / `sail npm ...` / `sail bin pint` 形式（`tech.md` の「コマンド慣習」セクション参照）。`php artisan` / `vendor/bin/pint` をホスト側で直叩く書き方は使わない。

## 処理フロー

1. `$ARGUMENTS` で Feature 名取得
2. **TaskCreate で 5タスク作成**（requirements / steering 整合性チェック / COACHTECH 調査 / design / tasks の5ステップ）。各ステップ着手時に in_progress、完了時に completed。
3. **前提読み込み**（CLAUDE.md / docs/steering/ × 3 / .claude/rules/ 該当ファイル / iField 同名 spec / 依存先 specs）を **1ターンで並列 Read**。順次 Read だとレイテンシが積み上がる。
4. **steering 既出事実の棚卸し**（design.md を書く前に必須）:
   - `product.md` の **該当 Feature の表行**（主モデル / 概要 / Advance 連携）を抜き出す
   - `product.md` の **「## ステータス遷移」** で本 Feature が所有するエンティティの state diagram を抜き出す
   - `product.md` の **「## 集計責務マトリクス」** で本 Feature が所有する Service を抜き出す
   - これらは **既出の事実** として扱い、spec で勝手に変更しない。逸脱しそうな設計判断が出たら **作業を止めてユーザーに確認**（「## ユーザー確認の方針」参照）
5. **requirements.md を生成**（ハイブリッド EARS、product.md 起点、サブ領域単位で機能要件をグルーピング、ステップ 4 の棚卸し事実を必ず取り込む）
6. **design.md 生成前に COACHTECH LMS を調査**（並列で）:
   - `ls` / `grep` / `find` で対応する Model / Controller / Action / Migration を探索（「参考にする既存実装 → 調査手順」参照）
   - 対応実装ありなら Read して観察パターンをメモ化
   - 対応実装なし or 部分対応なら、流用できる **設計パターン**（`URL::temporarySignedRoute` / `Password::broker` / `Auth::guard` の使い方等）だけを抽出する
7. **design.md を生成**（観察パターンは設計内容に **織り込み**、design.md 内に「参考実装」セクションは設けない。ステップ 4 の state diagram を必ず転記し、矛盾しない設計にする。Certify 固有の差異は本文中に併記、調査結果のサマリは完了報告で伝える）
8. **tasks.md を生成**（design.md のコンポーネントを Step 順にチェックボックス化、各タスク末尾に関連要件 ID を inline 注釈）
9. **完了前セルフチェック**（「## 完了基準」のリストを 1 項目ずつ確認、特に steering 整合性を最優先）
10. **完了報告**（「## 完了報告フォーマット」のテンプレに従う）

## 制約

- **`docs/specs/{name}/` 配下以外のファイルを編集しない**
- product.md / tech.md / structure.md / .claude/rules/ との整合性
- 命名は structure.md の規約に厳格に従う
- 1 Skill 実行 = 1 Feature

## ユーザー確認の方針

仕様 / 設計判断で **不整合 / 曖昧さ / 提案したい変更** が出てきたら、勝手に進めず作業を止めてユーザーに確認する。

### 🔴 最優先で停止すべきケース — steering との矛盾

以下は **無条件で停止 + 確認**。spec で独自判断して steering を逸脱しない:

- `product.md` の **state diagram** と矛盾する状態モデルを設計しようとしている
- `product.md` の **Feature 一覧表** の「主モデル」「概要」と異なるエンティティ構成を提案しようとしている
- `product.md` の **集計責務マトリクス** と異なる Service 所有 Feature を提案しようとしている
- `tech.md` のアーキテクチャ方針（Clean Architecture 軽量版、Action 命名 等）に反する設計をしようとしている
- `structure.md` の命名規則・ディレクトリ構成に反する配置をしようとしている

> Why: steering は **複数 Feature を横断する一貫性の源**。1 つの spec で独断したら他 Feature の spec / 実装と矛盾する。steering 自体が間違っていると判断したら、まず steering を直してから spec に着手するのが正しい順序。

### その他の確認ケース

- **不整合**: 既存 specs と矛盾する要求が見つかった
- **曖昧さ**: EARS の主語が定まらない、状態遷移の起点 / 終点が不明、命名候補が複数ある
- **設計判断の拮抗**: 複数の妥当な設計案があり、Why の決定打がない（COACHTECH 流 と Certify 流が衝突する等）
- **提案**: steering ドキュメントへの追加・修正提案、新 Feature 分割提案、依存関係の見直し
- **スコープ越え**: `docs/specs/{name}/` 配下を超えた変更が必要そうな状況

確認の仕方: 選択肢を 2-3 個用意して `AskUserQuestion` で聞くのが最短。曖昧なまま進めて手戻りするより、止まる方が安い。

> Why: 模範解答仕様は受講生・コーチが読む model output。後から「ここの曖昧さがバグった」だと教材品質が下がる。小さな疑問のうちに解消する文化を Skill レベルで強制する。

## 完了基準

完了報告前に **1 項目ずつ自己点検** する:

### ファイル

- [ ] 3 ファイルが `docs/specs/{name}/` に存在（requirements.md / design.md / tasks.md）
- [ ] 各ファイルが空でない（最低でも requirements 60行 / design 150行 / tasks 80行を目安）

### 要件トレース

- [ ] 要件 ID `REQ-{name}-NNN` / `NFR-{name}-NNN` 体系で採番されている（重複なし、10刻みで採番）
- [ ] design.md の「関連要件マッピング」で **すべての主要要件**（NFR 含む）が実装ポイントに対応
- [ ] tasks.md の各タスクに関連要件 ID が inline 注釈（テスト系・整形系は除く）

### 記述スタイル

- [ ] EARS のハイブリッド形式（構造キーワード英語 + 述語日本語）で統一
- [ ] 主語スタイルが 1 spec 内で統一（`the system` 主体、`the {Module}` 主体のどちらか）
- [ ] 他 Feature への参照は `[[feature-name]]` wikilink

### Mermaid

- [ ] `stateDiagram-v2` のラベルは **単行**（`xxx: xxx（日本語）`）、ラベル内に `:` を含まない
- [ ] `erDiagram` のカラム説明文に `:` `,` を含めない（Mermaid パーサが誤認）
- [ ] `sequenceDiagram` / `flowchart` 内のテキストに改行は `<br/>` を使う

### 命名・用語整合

- [ ] `structure.md` の命名規則に厳格適合（kebab-case ファイル名、PascalCase クラス名、snake_case テーブル名、`{Entity}Controller` / `{Action}Action` / `{Feature}Service` / `{Entity}Policy`）
- [ ] Enum 値・カラム名・テーブル名が `product.md` の表現と一致（用語ゆれを避ける）
- [ ] 既存 specs（基盤 Feature）と命名・構造が整合

### steering 整合性（最優先）

- [ ] `product.md` の **該当 Feature 行**（主モデル / 概要）と spec のエンティティ構成が一致
- [ ] `product.md` の **state diagram** と spec の状態遷移が一致（Enum 値・遷移先・トリガが完全一致）
- [ ] `product.md` の **集計責務マトリクス** と spec の Service 所有が一致
- [ ] `tech.md` のアーキテクチャ方針・命名規則・PR 規約に違反していない
- [ ] `structure.md` のディレクトリ構成・命名規則に違反していない
- [ ] **steering との矛盾が 1 件でもあれば作業を止めてユーザー確認**（独断で spec を steering と乖離させない）

### スコープ

- [ ] `docs/specs/{name}/` 配下以外のファイルを編集していない
- [ ] product.md / tech.md / structure.md / `.claude/rules/` を **変更していない**（仕様変更が必要なら作業を止めてユーザーに確認）

## 完了報告フォーマット

実行完了時、以下のテンプレでユーザーに報告する:

```
`docs/specs/{name}/` に 3 ファイル生成完了（requirements: {N} 行 / design: {N} 行 / tasks: {N} 行 / 合計 {N} 行）。

### 主要な設計判断

- **{論点 1}**: {採用した方針}。{なぜ}（Why の言語化、代替案との比較）
- **{論点 2}**: {採用した方針}。{なぜ}
- **{論点 3}**: {採用した方針}。{なぜ}

### COACHTECH LMS 調査結果のサマリ

| 観察パターン | Certify への適用 |
|---|---|
| {COACHTECH の実装パターン} | {Certify での扱い、差異も明記} |
| ... | ... |

### Certify 固有の差異

- {差異 1}: COACHTECH は ... 、Certify は ...
- {差異 2}: ...

`/feature-implement {name}` で Step 1 から順次実装に移れます。
```

**Why このフォーマット**:

- **設計判断の Why**: PR の「## 原因分析 / 設計判断」欄に直結。受講生が AI 丸投げで埋められない箇所（`tech.md` PR 規約参照）
- **COACHTECH 調査結果**: design.md には観察結果を「織り込む」だけで「参考実装」セクションは設けない方針なので、報告は調査の透明性を担保する場
- **Certify 固有の差異**: 「ULID / SoftDeletes 標準化」「教育PJスコープ」等が常に明示されることで、COACHTECH を丸ごとコピーするリスクを抑える
