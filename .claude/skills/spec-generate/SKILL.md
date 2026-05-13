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

### 📦 実装パターン: Laravel コミュニティ標準を主軸、COACHTECH LMS は必要時の補助参照

設計の **主軸は Laravel コミュニティ標準 + Eloquent / FormRequest / Policy / Action / Service の標準慣習**。`.claude/rules/` 配下の規約（`backend-*.md` / `frontend-*.md`）と `tech.md` / `structure.md` を最上位の指針とする。

`/Users/yotaro/lms/backend/` の **COACHTECH LMS は「Laravel 標準寄せの具体例」を確認するための補助参照**。Certify と同じ Clean Architecture（軽量版）+ Eloquent + Action/Service 構成のため Feature の具体実装例として読めるが、**忠実準拠は目的に反する**:

- COACHTECH 流が Laravel 標準とズレている箇所（独自命名 / 過剰な polymorphic / Workspace 中間テーブル前提 / morphTo 濫用 等）は **標準寄せに修正** する
- Certify 独自の方針（ULID 主キー / SoftDeletes 標準 / `admin`/`coach`/`student` 3ロール / 教育PJスコープ）と矛盾する箇所は **採用しない**
- COACHTECH に存在しない / 部分対応の領域は **Laravel コミュニティ標準 + LMS 業界慣習（Moodle / Canvas 等）** で補完する

**参考にするタイミング**: 既知の Laravel 標準と `.claude/rules/` だけで設計判断が定まる場合は調査不要。判断に迷う点・Feature 固有のパターン（状態遷移ログ / 招待フロー / 集計 Service 等）で具体例を確認したい時のみ、以下で必要範囲だけ読む。

| ステップ | コマンド例 |
|---|---|
| Model 探索 | `ls /Users/yotaro/lms/backend/app/Models/` |
| 関連コード横断検索 | `grep -rli "{キーワード}" /Users/yotaro/lms/backend/app/` |
| Migration 探索 | `find /Users/yotaro/lms/backend/database/migrations -name "*{keyword}*"` |

参考にした場合は **「観察した COACHTECH パターン」「Laravel 標準との差分」「Certify への適用判断」** を完了報告に簡潔に記す（判断の透明性）。`design.md` 内に「参考実装」セクションは設けず、設計内容に直接織り込む。参考にしなかった場合は完了報告でも省略してよい。

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

**生成方針**: Laravel コミュニティ標準 + `.claude/rules/` の規約を主軸に設計する。COACHTECH LMS は判断に迷う点で必要時のみ参考にし、観察したパターンは **設計内容そのものに織り込む**（design.md 内に「参考実装」セクションは設けない、参考にした場合のみ調査結果のサマリを完了報告で伝える）。

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
2. **TaskCreate で 5 タスク作成**（前提読み込み + steering 棚卸し / requirements / design / tasks / セルフレビューループ の 5 ステップ）。各ステップ着手時に in_progress、完了時に completed。セルフレビューループは内部で最大 5 ラウンドを回すが、TaskList 上は 1 タスクで扱う。
3. **前提読み込み**（CLAUDE.md / docs/steering/ × 3 / .claude/rules/ 該当ファイル / iField 同名 spec / 依存先 specs）を **1ターンで並列 Read**。順次 Read だとレイテンシが積み上がる。
4. **steering 既出事実の棚卸し**（design.md を書く前に必須）:
   - `product.md` の **該当 Feature の表行**（主モデル / 概要 / Advance 連携）を抜き出す
   - `product.md` の **「## ステータス遷移」** で本 Feature が所有するエンティティの state diagram を抜き出す
   - `product.md` の **「## 集計責務マトリクス」** で本 Feature が所有する Service を抜き出す
   - これらは **既出の事実** として扱い、spec で勝手に変更しない。逸脱しそうな設計判断が出たら **作業を止めてユーザーに確認**（「## ユーザー確認の方針」参照）
5. **requirements.md を生成**（ハイブリッド EARS、product.md 起点、サブ領域単位で機能要件をグルーピング、ステップ 4 の棚卸し事実を必ず取り込む）
6. **design.md を生成**（Laravel コミュニティ標準 + `.claude/rules/` を主軸に設計。判断に迷う点・Feature 固有のパターン（状態遷移ログ / 招待フロー / 集計 Service 等）で具体例を確認したい時のみ COACHTECH LMS を必要範囲だけ参考する — 参考方法は「## 参考にする既存実装」セクションを参照。観察パターンは設計内容に **織り込み**、design.md 内に「参考実装」セクションは設けない。ステップ 4 の state diagram を必ず転記し、矛盾しない設計にする。Certify 固有の差異は本文中に併記、参考にした場合は調査結果のサマリを完了報告で伝える）
7. **tasks.md を生成**（design.md のコンポーネントを Step 順にチェックボックス化、各タスク末尾に関連要件 ID を inline 注釈）
8. **セルフレビューループ実行**（「## セルフレビューループ」参照）。事前条件チェック → ラウンド 1〜5 を順に回す → 収束しなければ +2 サイクルまで延長 → それでも収束しなければ作業停止 + AskUserQuestion。ラウンド毎の修正件数と内容を内部メモに記録（完了報告で使う）。
9. **完了報告**（「## 完了報告フォーマット」のテンプレに従う、セルフレビューでの修正サマリを含める）

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

## セルフレビューループ（最大 5 ラウンド + 必要に応じ +2 サイクル）

生成した 3 ファイルは **必須読み込みファイル群を「正」として** 段階的にレビューし、不整合は同ターン内で Edit して矯正する。**観点を 5 つに分解し、各ラウンドで該当ファイルだけを再 Read** することで、(a) 1 回の頭で全観点を見ようとして起きる粗さ、(b) コンテキスト圧迫、の両方を避ける。

> Why ループ式: 一発生成では「steering 反映 / 要件トレース / Mermaid 構文 / 命名 / スコープ」を同時に考えるため、どこかが必ず甘くなる。観点を分けて再 Read しながら段階的に締めると、生成時に見落とした歪みが浮き上がる。各 spec は受講生・コーチが読む model output として残るので、**過剰修正は禁止**（既に整合している箇所を言い回しだけ書き換える等は NG）、観点項目に該当する **明確な不整合のみ修正**。

### 各ラウンドの進め方

1. **再 Read**: 当該ラウンドの「対象ファイル」を並列 Read（既に context にあっても、観点項目を verbatim 引用で照合するため再 Read する）
2. **照合**: 観点項目を 1 つずつ生成物と突き合わせる
3. **修正**: 不整合があれば Edit で同ターン内に反映。「修正なし」も明示的に記録（後段の収束判定に使う）
4. **波及確認**: 修正があった場合、後段ラウンドの観点を壊していないかを次ラウンドで自然に拾う

### 事前条件（ラウンド 0、これが満たされていなければループに入らない）

- [ ] 3 ファイルが `docs/specs/{name}/` に存在（requirements.md / design.md / tasks.md）
- [ ] 各ファイルが空でない（最低でも requirements 60 行 / design 150 行 / tasks 80 行を目安）

### ラウンド 1: steering 整合性レビュー（最優先）

steering と spec が乖離したら他 Feature の整合性まで連鎖して崩れるため、最初に潰す。

- **再 Read**: `docs/steering/product.md`（該当 Feature 行 + 「## ステータス遷移」 + 「## 集計責務マトリクス」） / `docs/steering/tech.md`（アーキテクチャ方針） / `docs/steering/structure.md`（命名・配置）
- **観点**:
  - [ ] product.md の **Feature 一覧表の該当行**（主モデル / 概要 / 主ロール / 提供状態 / Advance 連携）と spec のエンティティ構成・主要カラム・Controller 配置が一致
  - [ ] product.md の **state diagram** で本 Feature 所有エンティティが定義されている場合、spec の状態遷移と Enum 値・遷移先・トリガが完全一致。所有 state diagram が無い場合は spec で独自定義可だが、product.md の他箇所（Feature 一覧表の概要欄等）の言及と矛盾しない
  - [ ] product.md の **集計責務マトリクス** と spec の Service 所有が一致（本 Feature 所有でない Service を勝手に作っていない、本 Feature 所有の Service が漏れていない）
  - [ ] tech.md のアーキテクチャ方針（Clean Architecture 軽量版 / Action 命名 / Repository は外部 API 限定 / PR 7 セクション 等）に違反する設計をしていない
  - [ ] structure.md のディレクトリ構成・specs 作成ルールに違反していない
- **不整合検出時**:
  - 軽微（用語ゆれ・主モデル列挙漏れ等）→ spec 側を Edit で修正
  - 重大（steering 逸脱の設計判断）→ **作業を停止し AskUserQuestion**（steering を直すか spec を直すかはユーザー判断）

### ラウンド 2: 要件トレース三角測量

requirements → design → tasks の三角形で **逆引きが両方向に成立** しているかを点検する。

- **再 Read**: 生成した 3 ファイル（requirements.md / design.md / tasks.md）
- **観点**:
  - [ ] 要件 ID `REQ-{name}-NNN` / `NFR-{name}-NNN` が 10 刻みで採番され、重複・予期せぬ欠番がない（サブ領域間の意図的なジャンプは OK）
  - [ ] requirements.md の **すべての REQ / NFR** が design.md の「関連要件マッピング」表に登場する（漏れがあれば未対応要件を示す）
  - [ ] design.md の「関連要件マッピング」表の各行で示された **実装ポイント**（クラス / メソッド / migration / Blade）が tasks.md にチェックボックスとして存在する
  - [ ] tasks.md の各タスクに関連要件 ID が inline 注釈で付いている（テスト系・整形系・動作確認系は省略可）
  - [ ] design.md で言及している Action / Service / Policy / FormRequest / Migration / Blade / Exception が tasks.md の Step 1-6 に過不足なく登場する
- **不整合検出時**: 漏れている側に Edit で追記。REQ を削るのは要件削減なので原則 NG（spec を再度設計する必要があるサイン）。

### ラウンド 3: Mermaid・EARS 記述スタイル

可読性とパース安定性に直結する、生成時のうっかりを潰す。Mermaid は壊れているとレンダリング自体が落ちるので必ず修正。

- **再 Read**: 生成した design.md（Mermaid ブロック中心）+ requirements.md（EARS 行中心）+ 本 SKILL.md の「## 記述言語」セクション
- **観点**:
  - [ ] `stateDiagram-v2` のラベルは単行、ラベル内に `:` を含まない（`draft: draft（下書き）` の外側 `:` のみ）
  - [ ] `erDiagram` のカラム説明文に `:` `,` を含まない（Mermaid パーサが誤認する）
  - [ ] `flowchart` / `sequenceDiagram` のノードラベル内に裸の `[` `]` `{` `}`（特に `[[...]]` の wikilink 表記）を含めない。特殊文字を含むラベルは **ダブルクオートで囲む**（例: `Foo["text with / and *"]`）
  - [ ] `sequenceDiagram` / `flowchart` のテキスト改行は `<br/>` を使う
  - [ ] EARS のすべての行で **構造キーワード `shall` / `when` / `if` / `while` が英語のまま** 残っている（「〜の時、システムは〜する」のように日本語訳されていない）
  - [ ] 主語スタイルが 1 spec 内で統一（`the system` 主体 or `the {Module}` 主体）
  - [ ] 他 Feature への参照は `[[feature-name]]` wikilink で書かれ、フラットなテキストになっていない
- **不整合検出時**: Edit で書き換え。Mermaid は壊れていれば優先度最高

### ラウンド 4: 命名・用語整合

structure.md と `.claude/rules/` の規約、および依存先 Feature spec との用語ゆれを潰す。

- **再 Read**: `docs/steering/structure.md` + `.claude/rules/backend-models.md` / `backend-http.md` / `backend-usecases.md` / `backend-services.md` / `backend-policies.md` / `backend-repositories.md` + 依存先 Feature の `docs/specs/{dep}/design.md`（あれば、用語整合のため）
- **観点**:
  - [ ] クラス名 **PascalCase**、テーブル名 **snake_case 複数形**、カラム名 **snake_case 単数形**、ファイル名 **kebab-case**
  - [ ] `{Entity}Controller` / `{Action}Action` / `{Feature}Service` / `{Entity}Policy` / `{Action}Request` / `{Entity}{Reason}Exception` の命名規則を厳格遵守
  - [ ] **Controller method 名 = Action クラス名（PascalCase 化）** 規約（`backend-usecases.md`）に違反していない（`index() → IndexAction` / `approveCompletion() → ApproveCompletionAction`）
  - [ ] Feature 横断で他 Feature の Action を Controller から直接 DI していない（規約上、呼出元 Feature 配下に同名ラッパー Action を作る）
  - [ ] Enum 値・カラム名・テーブル名が product.md の表記と完全一致（`exam_date` / `passed_at` / `current_term` / `basic_learning` / `mock_practice` 等の生表現を勝手にリネームしていない）
  - [ ] 依存先 Feature の design.md と同一エンティティ・サービスを参照する箇所の表記が一致（`UserStatusChangeService` / `EnrollmentStatusLog` 等を別名に変えていない）
  - [ ] Repository を DB 専用に作っていない（`backend-repositories.md` 規約、外部 API 連携時のみ採用）
- **不整合検出時**: Edit でリネーム。依存先 spec で使われている表記に **本 spec を寄せる**（依存先 spec を書き換えるのは本 Skill のスコープ外）

### ラウンド 5: スコープ・関連 Feature リンクの最終確認

scope creep と Feature 間整合の最後の砦。

- **再 Read**: requirements.md の「## スコープ外」「## 関連 Feature」 + 依存先 Feature の `docs/specs/{dep}/design.md`（あれば、「## 関連要件マッピング」「## コンポーネント」の連携記述） + `.claude/rules/backend-exceptions.md`
- **観点**:
  - [ ] `docs/specs/{name}/` 配下以外のファイルを編集していない（脳内 Edit ログ追跡、必要に応じ `git status` を Bash で確認）
  - [ ] product.md / tech.md / structure.md / `.claude/rules/` を本 Skill 実行中に変更していない（仕様変更が必要なら作業停止 + AskUserQuestion）
  - [ ] requirements.md の「## スコープ外」に書いた項目が design.md / tasks.md にうっかり紛れ込んでいない
  - [ ] 「## 関連 Feature」の **依存先** に挙げた Feature の spec が既存なら、その design.md と本 spec の連携記述が矛盾しない（本 Feature が「呼ぶ側」と書いていれば、依存先 spec も「呼ばれる側」として整合する記述が望ましい）
  - [ ] 想定例外（`app/Exceptions/{Domain}/*Exception.php`）が `backend-exceptions.md` の親クラス対応表に沿った HTTP ステータスを返す設計になっている（`NotFoundHttpException` 404 / `ConflictHttpException` 409 / `AccessDeniedHttpException` 403 等）
- **不整合検出時**: 本 spec 側を Edit で修正。依存先 Feature 側の不整合は **次の Feature 実装時の課題** として完了報告に記録（依存先 spec を書き換えないのが本 Skill の制約）

### ループ終了判定

| 状況 | アクション |
|---|---|
| ラウンド 5 完了 + 全ラウンドで「修正なし」または最後の数ラウンドが「修正なし」で安定 | 完了報告へ |
| ラウンド 5 で **まだ修正が発生** | ラウンド 1 から **追加 1 サイクル**（最大 +2 サイクル = 計 7 ラウンドまで）。修正が連鎖しないか確認する |
| +2 サイクル後もまだ収束しない | 設計に根本的な問題がある可能性。**作業を止めて AskUserQuestion**（要件の見直し / 設計やり直し / 別 Feature への分割 等の選択肢を提示） |

> Why 追加サイクル: 命名修正（R4）が REQ ID 参照（R2）を壊す / Mermaid 修正（R3）が概念整合（R1）を壊す等の波及がありうる。原則 5 ラウンドで足りるが、収束まで回す。逆に「無理に修正点を作る」過剰修正は禁止 — 既に整合している箇所をいじって新たな歪みを入れない。

## 完了報告フォーマット

実行完了時、以下のテンプレでユーザーに報告する:

```
`docs/specs/{name}/` に 3 ファイル生成完了（requirements: {N} 行 / design: {N} 行 / tasks: {N} 行 / 合計 {N} 行）。

### 主要な設計判断

- **{論点 1}**: {採用した方針}。{なぜ}（Why の言語化、代替案との比較）
- **{論点 2}**: {採用した方針}。{なぜ}
- **{論点 3}**: {採用した方針}。{なぜ}

### セルフレビューループでの修正サマリ

{Nラウンド × Mサイクル} 回したうちで修正が発生した箇所のみ記載。すべて「修正なし」だったラウンドは省略してよい。

| ラウンド | 観点 | 修正点 |
|---|---|---|
| R1 steering 整合性 | {例: product.md の state diagram と spec の Enum 値ズレ} | {例: `CertificationStatus::Draft` → product.md 記述の `draft` に揃えた} |
| R2 要件トレース | {例: REQ-xxx-042 が design 表に未登場} | {例: design.md の関連要件マッピングに 1 行追加} |
| R3 Mermaid/EARS | {例: flowchart ノードラベルに wikilink 表記} | {例: ノード名をダブルクオート保護に変更} |
| R4 命名・用語 | (修正なし) | — |
| R5 スコープ/リンク | {例: 依存先 spec の Service 名と表記ズレ} | {例: `UserStatusChangeService` で表記統一} |

> ループ収束判定: {例: 標準 5 ラウンドで収束 / +1 サイクル要した（R4 修正の波及で R2 が再度壊れた）/ ...}

### COACHTECH LMS 参考の有無（参考にした場合のみ記載、しなかった場合は本セクションごと省略）

| 観察パターン | Laravel 標準との差分 | Certify への適用判断 |
|---|---|---|
| {COACHTECH の実装パターン} | {Laravel 標準・コミュニティ慣習との比較} | {Certify での扱い（採用 / 標準寄せに修正 / 不採用）} |
| ... | ... | ... |

### Certify 固有の差異（必要時のみ）

- {差異 1}: COACHTECH は ... 、Certify は ...（ULID / SoftDeletes / 教育PJスコープ等が論点になった場合のみ）
- {差異 2}: ...

`/feature-implement {name}` で Step 1 から順次実装に移れます。
```

**Why このフォーマット**:

- **設計判断の Why**: PR の「## 原因分析 / 設計判断」欄に直結。受講生が AI 丸投げで埋められない箇所（`tech.md` PR 規約参照）
- **セルフレビューでの修正サマリ**: 「セルフレビューが形骸化していないか」をユーザーが点検できるようにする透明性レイヤ。修正点ゼロのラウンドは省略可だが、**全ラウンドが「修正なし」というのは初回生成が完璧だったケース** であり、稀。何かしらの修正が記録されているのが通常運用
- **COACHTECH 参考の有無**: COACHTECH は **Laravel 標準寄せの補助参照** であり、忠実準拠は目的外。参考にした際に「Laravel 標準との差分」と「Certify への適用判断」を明示することで、丸ごとコピーするリスクを抑え、設計の透明性を担保する。参考にしなかった場合（既知の Laravel 標準と `.claude/rules/` だけで判断が定まった場合）は本セクションごと省略してよい
- **Certify 固有の差異**: ULID / SoftDeletes / 教育PJスコープ等の論点が出た場合のみ記す。論点なしなら省略
