---
name: feature-implement
description: 1 Feature の Laravel 実装を docs/specs/{name}/tasks.md の Step 1 から末尾まで連続で進める。Claude Design ハンドオフ (api.anthropic.com/v1/design/h/...) があれば取り込んで「視覚の正」として参照（データ・スコープは spec / コードベースが正）。**Blade を書く Step では、実装前に該当 design ref ファイル（preview/*.html や ui_kits/{role}/*.html）を必ず Read してから書き始める** — 事後 Phase 3 で乖離を直すのではなく、事前に design ref を写すのが正しい順序。書いた後は Playwright で視覚検証して残った乖離を修正するサイクルを回す。**完了報告では必ずブラウザ動作確認チェックリスト（URL / ロール / 操作 / 期待 / DB・Mailpit 確認場所）を Markdown で出力**し、ユーザーが実機で通しシナリオを検証 + PR 動作確認の素材撮影に使えるようにする。$ARGUMENTS に Feature 名を渡す。並列で複数 Feature を実装したい場合は worktree-spawn Skill で別 Claude セッションを立ち上げて各セッションでこの Skill を使う
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

## 全体フロー（Phase 0 → 5 → 4）

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
Phase 5  自己レビュー（必須読み込み再確認 + 規約準拠 + Code Review プラグイン + 最終テスト / Pint）
   ↓
Phase 4  Feature 完了報告
```

> tasks.md の各 Step 完了ごとにユーザー承認を取らない（連続実行）。ただし「相談ポリシー」に該当する場合はその場で確認する。
>
> **Phase 4 完了報告に進む前に必ず Phase 5 自己レビューを完了させる**（順序: ... → Phase 3 → Phase 5 → Phase 4）。Phase 5 で規約違反 / 漏洩 / テスト失敗が見つかった場合は Phase 2 サブルーチンに戻して修正してから再度 Phase 5 を回す。

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

### 重要: Blade を書く直前にも必ず Read する（事前 > 事後）

Phase 0 で 1 度読んでも、Phase 2 Step 5 (Blade) を書く直前に **再度該当 design ref ファイルを Read** すること。理由:

- Phase 1 / Phase 2 で別の作業（Migration / Action / Policy）をしている間に視覚契約が頭から抜ける
- Blade を書く瞬間に design ref が頭にないと、spec のデータ・スコープしか見ずに「**既存パターンっぽい何か**」を書いてしまい、結果として Phase 3 視覚検証で乖離が大量発生して後から手直しになる
- Phase 3 視覚検証は事後の **保険** であって、事前に design ref を写すことで乖離を最小化するのが正しい順序

→ Phase 2 の `b. 実装前準備` で明示的に該当 design ref ファイルを Read してから Blade を書き始める（次節）。

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

**Blade を含む Step の場合は、加えて次を必ず実施**（事後 Phase 3 で乖離を直すのではなく、事前に design ref を写すのが正しい順序）:

1. `HANDOFF.md` の「Feature カバレッジ マトリックス」or「Hero Screens」表から、現在実装する画面に対応する design ref ファイルを特定する
   - 例: admin 一覧 → `preview/table.html` + `preview/modal-dropdown.html`
   - 例: 受講生ダッシュボード → `ui_kits/student/index.html`
   - 例: ログイン / オンボーディング → `preview/login.html`
   - 例: エラーページ → `preview/error-pages.html`
   - 例: 修了証 PDF → `preview/certificate.html`
2. 該当ファイルを **Read ツールで実際に開く**（記憶に頼らない、Phase 0 で 1 度読んでいても Blade を書く瞬間に再 Read する）
3. 視覚契約を頭に入れる:
   - レイアウト構造（grid 配置・カラム数・固定ヘッダ・サイドバー drawer 等）
   - 主要コンポーネントの色・形状（カード bg / ボーダー / シャドウ / 角丸 / active 表示 / focus リング）
   - 間隔・密度（padding / gap / 行間 / tabular-nums の使い所）
   - アイコン使用箇所と位置（Heroicons 名は `ICONOGRAPHY.md` 参照）
   - マイクロコピー（ラベル / Empty state 文言 / hint）— `frontend-blade.md` を優先しつつ design ref の README Microcopy 表で補完
4. spec の Blade セクションで描画データ・スコープを確認し、**design ref に無いが spec で必要な要素**（追加列・追加アクション）を頭に入れる
5. **design ref にあるが spec に無い要素**（仮ナビ / デモバッジ / モックデータ）は実装しないリストに入れる（責務分離原則）

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

#### f. Pint 整形 + 規約準拠確認

```bash
cd {実装ディレクトリ} && ./vendor/bin/sail bin pint --dirty
```

PostToolUse hook で都度自動整形されているはずだが、Step 完了時に明示的に実行して `pint.json` で定義された規約への準拠を確認する:
- `declare(strict_types=1)` 付与漏れ
- `phpdoc_align` / `phpdoc_indent` / `phpdoc_separation`
- `ordered_imports`（import 順）
- `return_type_declaration`

差分が残った場合は内容を確認してコミット対象に含める。Pint で自動化されない規約（`private readonly` / `@param array{...}` shape / `@throws` 宣言、`backend-types-and-docblocks.md` 参照）は手で確認。

#### g. tasks.md 更新

完了した行を `[ ]` → `[x]`。Edit ツールで該当行を書き換える。

#### h. 次の Step へ進む（承認なし）

全 Step が `[x]` になるまで a → h を繰り返す。

### Step → 主参照ルールのマップ

| Step | 主参照 rules | 主作業 |
|---|---|---|
| 1 Migration & Model | `backend-models.md` `backend-types-and-docblocks.md` | ULID, SoftDeletes, fillable, casts, Enum, Factory |
| 2 Policy | `backend-policies.md` `backend-types-and-docblocks.md` | viewAny/view/create/update/delete、ロール別 match |
| 3 HTTP 層 | `backend-http.md` `backend-types-and-docblocks.md` | Controller 薄く / FormRequest / routes/web.php に追記 |
| 4 Action / Service | `backend-usecases.md` `backend-services.md` `backend-exceptions.md` `backend-types-and-docblocks.md` | `{Action}Action.php`（Controller method 名と一致）、DB::transaction、ドメイン例外 |
| 5 Blade | **Claude Design ハンドオフ** (`preview/*.html` / `ui_kits/{role}/*.html`) を `b. 実装前準備` で **必ず Read** + `frontend-blade.md` `frontend-tailwind.md` `frontend-ui-foundation.md` | layouts/app 継承、@csrf、@can、コンポーネント、Tailwind utility。**実装前に design ref Read 必須 → 事後 Phase 3 視覚検証で最終確認** |
| 6 テスト | `backend-tests.md` `backend-types-and-docblocks.md` | RefreshDatabase + actingAs、各ロール認可分岐、ファクトリ |
| 7 動作確認 | — | Pint 整形 + テスト全通過 + Phase 3 視覚検証（再度）+ ブラウザ確認 |

> **`backend-types-and-docblocks.md` の位置付け**: PHP ファイル編集を含む全 Step（1/2/3/4/6）で共通参照。`declare(strict_types=1)` / `private readonly` / `@param array{...}` shape / `@throws` / クラス・メソッド DocBlock / 行内コメント Why 原則 / `final class` 採用方針を規定する。paths frontmatter で auto-load されるので、各 PHP ファイル編集時に自動的に頭に入る前提。Step f の Pint 整形時に「Pint で自動化される項目 / 手で書く項目」のチェック観点も同ファイルに集約。

---

## Phase 3: Playwright 視覚検証サイクル

Blade を含む Step（Step 5 / Step 7、または他 Step で Blade を触った場合）が完了したら、必ずここを通る。

### 前提

- Sail / Laravel 開発サーバが稼働している（`docker ps` で `laravel.test` コンテナ確認）
- Claude Design ハンドオフが Phase 0 で展開済み
- Playwright MCP ツール（`mcp__playwright__*`）が利用可能
- **新規 Blade ファイルを作成した直後の落とし穴**: 新しい Blade で使った Tailwind の `sm:flex-row` / `lg:grid-cols-2` 等の prefix クラスが、既存ビルドの CSS に含まれていない可能性がある（content scanner が新規ファイルを未認識）。Phase 3 で初めて気づくと「flex-row が効かない」「sm:items-end が効かない」等の異常レイアウトが発生する。Phase 3 に入る前に **`./vendor/bin/sail npm run build` を 1 度実行**（または `npm run dev` watcher を起動済み）してから比較撮影する

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

## Phase 5: 自己レビュー（Phase 4 完了報告の前に必ず実施）

Feature の全 Step が `[x]` になった直後、Phase 4 完了報告の **前** に自己レビューを行う。Phase 5 を省略すると、規約違反 / 受講生コンテキスト漏洩 / 実装と spec の乖離が後出しで発覚し、後段でリワークが発生する。

### a. 必須読み込みの再確認

本 Skill 冒頭の **「必須読み込み」セクション全件** を再度 Read し、実装後のコードと突き合わせる:

- `CLAUDE.md` 「実装プラン」セクション
- `docs/specs/{name}/requirements.md` / `design.md` / `tasks.md`
- 依存先 Feature の `docs/specs/{dep}/design.md`
- `.claude/rules/` 配下すべて（paths frontmatter で auto-load されるが、Phase 5 では明示的に全件再読する）
- Claude Design ハンドオフ（該当 Feature に視覚契約がある場合）
- 既存実装パターン（同レイヤーの近いファイル）

**特定の rules ファイルだけを部分参照しないこと**（コメント密度 / 命名 / 例外 / テスト / Blade / Tailwind 等の他規約が漏れて違反検出できなくなる）。「必須読み込み」全件を再読するのが原則。

### b. 規約準拠レビュー

Phase 5-a で再読した規約に従って、実装コード全体を自己レビューする。

**具体的なチェック観点（受講生コンテキスト漏洩 / 型・DocBlock 規約 / 例外 / Policy / FormRequest / Test / Blade / Tailwind 等）は規約側に集約されている**ため、本 Skill では再記載しない（規約と Skill の二重管理を避ける、規約変更時に Skill 側も更新する負荷を回避）。

参照する主な規約（必須読み込みに含まれる）:

- `backend-types-and-docblocks.md`（コードコメント禁止表現 / 型 / DocBlock / `final class` / `private readonly`）
- `backend-usecases.md` / `backend-services.md` / `backend-models.md` / `backend-http.md` / `backend-policies.md` / `backend-tests.md` / `backend-exceptions.md` / `backend-repositories.md`
- `frontend-blade.md` / `frontend-ui-foundation.md` / `frontend-javascript.md` / `frontend-tailwind.md`

各規約に該当する違反があれば Phase 2 のサブルーチンで修正してから Phase 5-c へ。検出方法は規約側に書かれた禁止表現 / 必須項目を grep / Read で確認すれば導出可能。

### c. Code Review プラグイン呼出（必須）

Anthropic 公式の [claude-plugins-official/code-review](https://github.com/anthropics/claude-plugins-official/tree/main/plugins/code-review) を `/code-review` で実行する。

**対象は GitHub PR ではなく、本 Feature のローカル変更（未コミット WIP 含む git working tree の差分）**。commit / push 前の品質担保ポイントとして組み込む。

指摘があれば Phase 2 のサブルーチンで修正して Phase 5-a に戻る。

### d. テスト + Pint 最終確認

```bash
./vendor/bin/sail artisan test 2>&1 | grep -E "Tests:|FAILED"
./vendor/bin/sail bin pint --test --dirty
```

- テスト全 PASS（既存テスト + 本 Feature 追加テストの両方）
- Pint `--test --dirty` で差分なし（既に整形済を確認）

Blade を含む Feature では Phase 3 視覚検証を再度 1 周回す。

### e. Phase 4 完了報告へ進む

a〜d がすべて pass で初めて Phase 4 完了報告へ進める。

a〜d のいずれかで違反 / 失敗があれば Phase 2 のサブルーチンに戻して修正し、再度 Phase 5-a から実施する。

---

## Phase 4: Feature 完了報告

> 本フェーズは **Phase 5 自己レビュー完了後** に実施する（順序: Phase 3 → Phase 5 → Phase 4）。

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
- **ブラウザ動作確認チェックリスト**（次節、ユーザーが実機で確認するため必ず出力）
- 次の推奨アクション（次の Feature / 残課題）

### ブラウザ動作確認チェックリスト（必須出力）

> **目的**: 自動テストでは検証できない実機の挙動（モーダル / Flash / 動的 UI / Mail / soft delete 後の見え方）をユーザーがブラウザで確認するためのチェックリスト。PR 動作確認の動画・スクショ撮影シナリオにもそのまま使える。
>
> **書き方の鉄則**: **1 項目 = 1 行**。URL・操作・期待・確認場所を `→` で一行にまとめる。手順の番号付きリストや複数行の詳細は書かない（読まれない）。`tasks.md` 末尾の Step に通しシナリオがあればそれをベースに圧縮、無ければ routes / requirements.md から抽出。

#### 出力フォーマット

````markdown
## 🧪 動作確認 — {feature-name}

### 前提

```bash
sail up -d
sail artisan migrate:fresh --seed   # 状態網羅 demo データ投入(structure.md Seeder 規約準拠)
sail npm run build                  # Blade を含む Step を実装した時は必須
```

- アプリ: http://localhost:8000
- Mailpit: http://localhost:8025
- phpMyAdmin: http://localhost:8081

**固定ログインアカウント**(全 `password='password'`、`UserSeeder` が投入):

| ロール | Email | 主な動作確認動線 |
|---|---|---|
| admin | `admin@certify-lms.test` | 管理者画面全般 / マスタ CRUD / 状態遷移 |
| coach | `coach@certify-lms.test` | 担当受講生 / 教材閲覧 / 面談 / chat / QA |
| coach (2 人目) | `coach2@certify-lms.test` | 複数コーチシナリオ(chat グループ / 自動割当) |
| student | `student@certify-lms.test` | 受講生フル動線(dashboard / 学習 / 演習 / 模試) |

**状態網羅 demo データ**(`UserSeeder` + `PlanSeeder` で既に揃っている前提):

- User: admin × 1 / coach × 2 / student × 16(invited × 2 / in_progress × 9 / graduated × 3 / withdrawn × 2)
- Plan: published × 3(1ヶ月 / 3ヶ月 / 6ヶ月) / draft × 1 / archived × 1
- 各 in_progress student は 3 種の Plan に分散 + 開始直後 / 中盤 / 期限直前を散らして紐づけ

→ 一覧フィルタ・status バッジ・graduated 表示・withdrawn の soft delete は既存の demo データで実機確認できる。本 Feature 独自の操作・状態遷移を以下のシナリオで追加検証する。

### 基本フロー
- [ ] `/admin/foo` (`admin@certify-lms.test`) → 一覧で各 status のレコードが並ぶ + フィルタ + ページネーション
- [ ] 新規作成 → `foos.status=draft` で INSERT + Flash トースト(右上 fixed、5 秒 auto-dismiss) + 詳細遷移
- [ ] 編集 → 値更新 / status 不変
- [ ] {状態遷移ボタン} → `foos.status` 更新 + カタログ反映

### エラー / 認可
- [ ] {不正状態で操作} → 409 + DB 不変
- [ ] `coach@certify-lms.test` / `student@certify-lms.test` で `/admin/foo` → 403
- [ ] 未ログインで `/foo` → /login リダイレクト

### 外部連携(該当時)
- [ ] Mailpit で {Mail} 受信(件名 + 本文リンク)
- [ ] phpMyAdmin で `{table}.{column}` 値確認
- [ ] `sail artisan {command}` 手動実行 → {期待}

### 視覚(Blade 含む時)
- [ ] `sail npm run build` 済 / モバイル幅(<lg)で drawer / デスクトップ幅(lg+)でサイドバー固定 / Toast 右上 fixed

### 既存破壊チェック
- [ ] 隣接 Feature(例 [[auth]] ログイン、[[user-management]] 一覧の各 status バッジ、[[plan-management]] Plan 一覧)が従来通り動く

> 動的機能(状態遷移 / トースト / モーダル / タイマー)は動画、静的(一覧 / 詳細 / フォーム)は静止画で PR 用素材を撮影(`tech.md` PR 規約)。
````

#### 組み立てのコツ

- **1 項目 = 1 行に圧縮**: 「URL (ロール) → 操作 → 期待 + 確認場所」を `→` 区切りで横並びに。手順の番号付きリストや複数行のネストは禁止(チェック項目として読み飛ばされる)
- **1 業務操作 = 1 チェック項目**: spec の「ロールごとのストーリー」「sequence diagram」から抽出。10 項目 ±α が目安、20 を超えたら粒度が細かすぎる
- **DB 確認を必ず織り込む**: 「Flash 表示」だけでは裏で何も起きていない実装も pass する → 「`foos.deleted_at != NULL`」のような具体的なカラム値を期待に書く
- **soft delete / リネームは事後の見え方も**: 削除後にデータが残るか / リネーム済み値が表示されるかを 1 行追加
- **認可は admin only への `coach@` / `student@` アクセスを必ず網羅**: spec の `REQ-*-083` 系を 1 行に集約
- **既存破壊チェックは 1〜2 行**: 隣接 Feature が壊れていないかを確認する最低限のシナリオを 1〜2 行入れる
- **Seeder demo データに乗る前提で書く**: 「状態網羅した demo は seed 済」を前提に、本 Feature **固有の操作** を中心にチェックを置く(全 status の存在確認は seed 結果として自動的に担保される)。本 Feature の Seeder で新規に投入する状態がある場合のみ、その状態を見るチェックを追加する
- **ロール別アカウントを使い分ける**: admin 専用画面の動作確認は `admin@`、coach 視点は `coach@`(複数コーチシナリオなら `coach2@` も)、受講生視点は `student@` を明示する。「coach / student」のような汎用ロール表記ではなく、固定アカウントの email を書くことで読み手が即座にログインして検証できる

#### 取り扱い

会話の最後にプレーンな Markdown として出力する（GitHub PR description / Notion / Slack にそのまま貼れる形）。コードフェンスで囲まなくてよい。

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
