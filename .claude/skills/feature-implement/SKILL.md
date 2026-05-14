---
name: feature-implement
description: 1 Feature の Laravel 実装を docs/specs/{name}/tasks.md の Step 1 から末尾まで連続で進める。Claude Design ハンドオフ (api.anthropic.com/v1/design/h/...) があれば取り込んで「視覚の正」として参照（データ・スコープは spec / コードベースが正）。Blade を含む Step の後は Playwright で視覚検証して乖離を修正するサイクルを回す。$ARGUMENTS に Feature 名を渡す。並列で複数 Feature を実装したい場合は worktree-spawn Skill で別 Claude セッションを立ち上げて各セッションでこの Skill を使う
---

# feature-implement

`docs/specs/{name}/` の SDD と Claude Design ハンドオフに基づいて、**実装ディレクトリ**（CLAUDE.md「実装プラン」参照、Certify LMS では `模範解答プロジェクト/`）に Laravel コードを 1 Feature 分通しで実装する自己完結スキル。直列実行。

## 入力

- **`$ARGUMENTS`**: Feature 名（kebab-case）。例: `mock-exam`。無ければユーザーに確認する。
- **Claude Design ハンドオフ URL**（任意だが強く推奨）: Skill を起動したユーザープロンプト内の `https://api.anthropic.com/v1/design/h/<id>` を Skill が自動検出する。検出できなければ Phase 0 でユーザーに「Claude Design のハンドオフ URL を渡してください（無ければ "なし" と答えてください）」と確認する。

## 必須読み込み

1. `CLAUDE.md` — 「実装プラン」セクション（実装ディレクトリ確認）
2. `docs/specs/{name}/requirements.md` — 受け入れ基準
3. `docs/specs/{name}/design.md` — アーキテクチャ・データモデル
4. `docs/specs/{name}/tasks.md` — Step 順序とチェック状態
5. 依存先 Feature の `docs/specs/{dep}/design.md`（基盤 Feature 等、先に完了済み想定）
6. `.claude/rules/` 配下（paths frontmatter で自動適用）
7. **Claude Design ハンドオフ**: 渡されている場合 Phase 0 で展開・読み込み（後述）
8. 既存実装パターン（同レイヤーの近いファイル）

## 全体フロー（Phase 0 → 3）

```
Phase 0  Claude Design ハンドオフ取込み（任意、初回のみ）
   ↓
Phase 1  SDD 読み込み + 全 Step プラン把握
   ↓
Phase 2  tasks.md の Step を「次の [ ] を特定 → 実装 → テスト → Pint → 該当行 [x] 化」を順次反復
         （Blade を含む Step が完了した直後に Phase 3 を走らせる）
   ↓
Phase 3  Playwright 視覚検証サイクル（design ref と比較 → 乖離があれば修正 → 再撮影、最大 3 周）
   ↓
Phase 2 へ戻り、次の Step へ
   ↓
Phase 4  Feature 完了報告
```

> tasks.md の各 Step 完了ごとにユーザー承認を取らない（連続実行）。ただし「相談ポリシー」に該当する場合はその場で確認する。

---

## Phase 0: Claude Design ハンドオフ取込み

### URL 検出

Skill を起動したユーザープロンプト全体から、以下の正規表現に合致する URL を探す:

```
https?://api\.anthropic\.com/v1/design/h/[A-Za-z0-9_-]+
```

- 見つかった: 自動で fetch（次セクション）
- 見つからない: ユーザーに「Claude Design ハンドオフ URL を渡してください。無ければ `なし` と回答」と確認。`なし` の場合 Phase 0 をスキップして Phase 1 へ

### キャッシュ判定

`/tmp/claude-design-handoff/` に既に展開済みのバンドルがある場合:
- 該当ディレクトリ内の `README.md` を読み、対象プロダクト名が現プロジェクトと一致するか確認
- 一致すれば再 fetch せずキャッシュを使用
- 一致しなければ `rm -rf` で消してから新規 fetch

### Fetch + 展開

```bash
curl -s "<url>" -o /tmp/design-bundle.tar.gz
mkdir -p /tmp/claude-design-handoff
tar xzf /tmp/design-bundle.tar.gz -C /tmp/claude-design-handoff/
```

### 必須読み込み（順序固定）

ハンドオフ展開後、以下を順に Read する:

1. `<bundle>/README.md` — 構造の理解（必ず最初に読む。ハンドオフ自身に「コーディングエージェント向け指示書」が含まれる）
2. `<bundle>/chats/chat1.md` 等の **全チャット転写** — 「ユーザーが何を要望してどこに着地したか」が記録されている。**ここを読み飛ばすと色味やトーンの決定経緯を見落とす**
3. `<bundle>/project/HANDOFF.md` — Feature/コンポーネント ↔ プレビューファイルのマッピング表（Phase 3 視覚検証時の参照ガイド）
4. `<bundle>/project/README.md` — Voice & tone / Visual foundations / Microcopy パターン

### 対象 Feature への絞り込み

`HANDOFF.md` の「Feature カバレッジ マトリックス」を参照し、現 Feature が:
- **ヒーロー UI kit** を持つ場合: `<bundle>/project/ui_kits/{role}/{Screen}.html` を Read
- **コンポーネント中心** の場合: `<bundle>/project/preview/{component}.html` を Read
- **UI なし**（例: analytics-export API）: design ref は使わず Phase 3 もスキップ

読んだ内容は **視覚 (色 / タイポ / 間隔 / コンポーネント形状 / レイアウト構造) の唯一の正** とする。tailwind.config.js のトークン値 / `colors_and_type.css` の CSS 変数も整合確認のために参照する。

### Claude Design と spec / コードベースの責務分離（重要）

| 観点 | 正とする情報源 |
|---|---|
| **視覚** — 色 / タイポ / 間隔 / 角丸 / 影 / グラデ / コンポーネント形状 / レイアウト構造 | **Claude Design ハンドオフ** |
| **データ・スコープ** — 表示するフィールド / セクション / メニュー項目 / バッジ集計対象 / ロール別画面責務 | **spec (`docs/specs/{name}/`) + 既存コードベース** |
| **マイクロコピー** — ラベル / プレースホルダ / Empty state 文言 / Microcopy パターン | spec / `frontend-blade.md` を優先、無ければ Claude Design の `README.md` Microcopy 表 |

> **理由**: Claude Design は別環境で先行制作されており、デモ用に **spec にない機能** (例: 過剰なデモバッジ / 仮ナビ項目 / モックデータ表示) を含む可能性がある。デザイン上の質感 (Tropical Emerald、active 表示、ピル形ベル等) は Claude Design を写すが、「Claude Design に出ているから実装する」という判断はしない。spec に無い項目は **実装しない**（裏切られたら相談ポリシーへ）。

具体例:
- ❌ Claude Design のサイドバーに「AI 相談 (3)」とバッジが付いているが、現 Feature の spec にバッジ集計の指示がない → バッジを付けない
- ❌ Claude Design の TopBar に「保存検索」ドロップダウンがあるが、spec に無い → 実装しない
- ✅ Claude Design の TopBar 検索バーが pill 型・bg-ink-50・focus で primary 縁取り → 形と色を写す（spec で TopBar 検索の機能要件があれば実装、無ければ「空のプレースホルダ UI」だけ写して JS は付けない、または相談）
- ✅ Claude Design の active メニューが gradient bg + 左 3px バー → 写す（視覚の正）

---

## Phase 1: SDD 読み込み + 全 Step プラン把握

1. `docs/specs/{name}/requirements.md` を読み、受け入れ基準（REQ-* / NFR-*）を頭に入れる
2. `docs/specs/{name}/design.md` を読み、アーキテクチャ・データモデル・主要 Action / Service / Policy を把握
3. `docs/specs/{name}/tasks.md` を読み、**全 Step を一覧化**。各 Step が:
   - Blade を含むか（Phase 3 視覚検証の必要性判定）
   - 依存先 Feature の前提を必要とするか（不足していたらここで相談）
4. 依存先 Feature の `design.md` を必要に応じて Read
5. **TaskCreate で Step ごとにタスクを切る**（進捗の可視性のため、ユーザーが見たときに今どこかが分かるように）

---

## Phase 2: Step を順次実装（連続実行）

### 1 Step の中の処理

各 Step を以下のサブルーチンで進める。Step 完了の度にユーザー承認は取らない:

#### a. 次の未完了 Step を特定

`docs/specs/{name}/tasks.md` のチェックボックスを上から走査:
- `- [x]` = 完了
- `- [ ]` = 未完了

最初に `- [ ]` を含む Step を実装対象とし、一言でユーザーに進捗共有してから着手（例: "Step 4 (Action / Service) に着手します"）。

#### b. 実装前準備

- 変更対象既存コードを Read（未読のコードを変更しない、Edit / Write のガード）
- 類似既存ファイルをパターン参照（命名・構造・テスト形式）
- design ref の対応プレビューを再確認（Blade を含む Step の場合）

#### c. 実装

- `.claude/rules/` の規約に厳格に従う（paths frontmatter で自動ロード）
- 新規ファイルは既存の同種ファイルを参考に
- Action / Service / Model / Test / Blade すべて同セッションで生成
- PostToolUse hook（Pint）が PHP ファイル整形を自動実行

#### d. テスト実行（バックエンドの実装を含む Step）

```bash
cd {実装ディレクトリ} && ./vendor/bin/sail artisan test --filter={Entity}
```

失敗時は修正してから次へ。

#### e. Blade を含む Step の場合 → Phase 3 視覚検証へ

`resources/views/` 配下の編集を含む Step は Phase 3 を必ず通る。Phase 3 後に本フローに戻る。

#### f. tasks.md 更新

完了した行を `[ ]` → `[x]`。Edit ツールで該当行を書き換える。

#### g. 次の Step へ進む（承認なし）

全 Step が `[x]` になるまで a → g を繰り返す。

### Step → 主参照ルールのマップ

| Step | 主参照 rules | 主作業 |
|---|---|---|
| 1 Migration & Model | `backend-models.md` | ULID, SoftDeletes, fillable, casts, Enum, Factory |
| 2 Policy | `backend-policies.md` | viewAny/view/create/update/delete、ロール別 match |
| 3 HTTP 層 | `backend-http.md` | Controller 薄く / FormRequest / routes/web.php に追記 |
| 4 Action / Service | `backend-usecases.md` `backend-services.md` `backend-exceptions.md` | `{Action}Action.php`（Controller method 名と一致）、DB::transaction、ドメイン例外 |
| 5 Blade | `frontend-blade.md` `frontend-tailwind.md` `frontend-ui-foundation.md` | layouts/app 継承、@csrf、@can、コンポーネント、Tailwind utility。**Phase 3 視覚検証必須** |
| 6 テスト | `backend-tests.md` | RefreshDatabase + actingAs、各ロール認可分岐、ファクトリ |
| 7 動作確認 | — | Pint 整形 + テスト全通過 + Phase 3 視覚検証（再度）+ ブラウザ確認 |

---

## Phase 3: Playwright 視覚検証サイクル

Blade を含む Step（Step 5 / Step 7、または他 Step で Blade を触った場合）が完了したら、必ずここを通る。

### 前提

- Sail / Laravel 開発サーバが稼働している（`docker ps` で `laravel.test` コンテナ確認）
- Claude Design ハンドオフが Phase 0 で展開済み
- Playwright MCP ツール（`mcp__playwright__*`）が利用可能

### サイクル本体

```
1. design ref を HTTP サーバで配信（初回のみ）
2. design ref と実装の対応スクリーンショットを撮影
3. 並べて比較し、差分を列挙
4. 乖離があれば実装を修正
5. 修正後に再撮影 → 2 へ戻る
6. 収束 or 最大 3 周で打切り
```

### 1. design ref を HTTP サーバで配信

```bash
cd /tmp/claude-design-handoff/certify-lms-design-system/project && \
  python3 -m http.server 9000 &
```

すでに :9000 で配信中なら再起動しない（`lsof -i :9000` で確認）。

### 2. スクリーンショット撮影

Playwright で:
- 現状の Laravel 画面（例: `http://localhost:8000/login`）
- 対応する design ref（例: `http://localhost:9000/preview/login.html`）

両方を **同一ビューポート** で撮る（推奨: 1440×900 デスクトップ、必要に応じて 480×800 モバイル）。保存先は `<repo-root>/.tmp-design-compare/`（`.gitignore` 済）。

ファイル名は `cur-{screen}.png` / `ref-{screen}.png` で揃える。

### 3. 比較と差分抽出

Read ツールで両方の PNG を視認し、以下を観点に差分を抽出:

| 観点 | チェックポイント |
|---|---|
| **レイアウト構造** | グリッド配置（sidebar / topbar / main の位置関係）、垂直バランス |
| **タイポ** | フォントウェイト / サイズ / 行間 / 混植 (`<span>` で色違いタイポ等) |
| **カラー** | 背景グラデ / カード bg / アクティブ表示 / ボーダー色 |
| **間隔・密度** | padding / gap / 行間 |
| **構成要素** | 検索バー / 通知ベル / ロールピル / 法的注記等の有無 |
| **アクティブ表現** | 左 3px バー / グラデ bg / フォント色 |
| **マイクロコピー** | ラベル文言 / プレースホルダ / 注釈の差 |

### 4. 修正

差分に応じて Blade / コンポーネント / Tailwind 設定を修正。design ref 側の値（hex / px / weight）を尊重し、Tailwind トークン (`primary-600` 等) で表現できる範囲はトークン経由で書く。

**ただし、差分が「視覚」ではなく「データ・スコープ」由来の場合は写さない**（Phase 0 の責務分離参照）:
- design ref に出ているがコードベース側に spec も実装も無い項目（仮ナビ / モック数値 / デモバッジ）は **無視** し、Phase 3 の差分リストからも除外する
- design ref のモック数値（例: "5 件未読"）と現状の `0 件 / 非表示` は乖離ではない（バッジ集計が未実装なだけで、視覚側はバッジが表示されたときに正しい形状であれば OK）
- 迷ったら相談ポリシーへ

### 5. 再撮影 → ループ

再度 Phase 3 ステップ 2 へ。

### 6. 収束 / 打切り

- **収束判定**: 重要な視覚差分（レイアウト / メインカラー / 主要要素の有無）が無くなったらサイクル終了。微差（1〜2px のパディング・色相のごく僅かな違い）は許容する
- **打切り**: 3 周しても収束しない場合は「相談ポリシー」に従いユーザーに「どこまで合わせるか」を相談する

---

## Phase 4: Feature 完了報告

Feature の全 Step が `[x]` になったら最終チェック:

```bash
./vendor/bin/sail artisan test 2>&1 | grep -E "Tests:|FAILED"
./vendor/bin/sail bin pint --dirty
```

両方 PASS で以下を報告:

- 完了 Feature 名
- 実装した Step 一覧
- 変更ファイル一覧（パス + 行数）
- テスト結果サマリ（X tests / Y assertions / 全 PASS）
- Phase 3 視覚検証で fix した主な乖離（あれば）
- 次の推奨アクション（次の Feature / 残課題）

---

## 相談ポリシー（重要）

**承認なしで進める**:
- 既存パターンが明確で spec も明示している実装判断
- typo / 整形 / 命名の機械的選択
- tasks.md の Step 完了報告（一言進捗共有 OK、承認待ちはしない）
- design ref と spec が一致している場合の選択

**ユーザーに必ず確認する**（`AskUserQuestion` を使う）:
- spec が複数解釈可能で、どちらを取るかで挙動が変わる場合
- 既存パターンが見つからず、自分が新パターンを作る判断をした場合（特に他 Feature への波及がある場合）
- design ref と spec が**矛盾**する場合（どちらを優先？）
  - 既定の優先度: 視覚 = Claude Design / データ・スコープ = spec。範囲が曖昧な場合は確認
- Claude Design に出ているが spec に無い機能 (仮ナビ / デモバッジ / モックデータ) を写すべきか判断に迷う場合
  - 既定は「実装しない」が、本当に spec の漏れだった場合は spec 側を更新する選択肢もある
- Feature の **scope 拡大** が必要に思える場合（spec に無いが必要そうな実装の追加）
- **破壊的操作**: 既存ファイル削除 / DB スキーマの非互換変更 / 依存パッケージの追加・削除
- Phase 3 視覚検証が 3 周で収束せず「ここまで合わせる」かを判断したい場合

**確認時のフォーマット**:
- 2〜4 個の選択肢を `AskUserQuestion` で提示
- 推奨案を 1 番目に置き `(推奨)` を付ける
- 各選択肢に **どう挙動が変わるか / トレードオフ** を `description` に書く

---

## 参考にする既存実装

**主参考: COACHTECH LMS の `steering-execute` Skill**

- `/Users/yotaro/lms/.claude/skills/steering-execute/SKILL.md` — チェックボックス解析 → 次の未完了 Step 特定 → 既存パターン Read → 実装 → テスト → tasks 更新 → 完了報告 の流れ。本 Skill の Phase 2 サブルーチンはこれをほぼ踏襲

**補助参考: COACHTECH LMS の `backend-test-writer` agent**

- `/Users/yotaro/lms/.claude/agents/backend-test-writer.md` — UseCase（Action）作成と同時にテスト生成する SOP。本 Skill でも Step 4 で Action 実装と同時に Step 6 のテストを書く流れの参考

**補助参考: COACHTECH LMS の既存実装パターン**

- `/Users/yotaro/lms/backend/app/UseCases/` 配下を Grep して、似た規模の Feature（例: ChatMessage 系）の Action 構成と粒度感を確認

---

## 制約

- **実装ディレクトリ配下のみ編集**（`docs/` は読み取りのみ）
- 既存テストを壊さない（修正後に必ず `sail artisan test` 全通過確認）
- 並列実行したい場合は `worktree-spawn` Skill 経由で別 Claude セッションを立ち上げる
- Phase 3 視覚検証で生成されるスクショは `<repo-root>/.tmp-design-compare/` に保存（`.gitignore` 済）

## 完了基準

- 該当 Feature の `docs/specs/{name}/tasks.md` の全 Step が `[x]`
- 全テスト追加 + 全 PASS
- Pint 整形完了（`--dirty` で passed）
- Blade を含む Step では design ref との視覚乖離が許容範囲に収まっている
- 必要なら user-management 等の依存 Feature の前提（例: `UserStatusChangeService`）も同時実装済み
