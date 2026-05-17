---
name: feature-implement
description: 1 Feature の Laravel 実装を docs/specs/{name}/tasks.md の Step 1 から末尾まで連続で進める。Claude Design ハンドオフ (api.anthropic.com/v1/design/h/...) があれば取り込んで「視覚の正」として参照（データ・スコープは spec / コードベースが正）。**Claude Design ハンドオフ URL が渡された場合のみ Phase 3 視覚検証サイクル(Blade を書く Step で実装前に design ref を Read → 実装後に Playwright で乖離検証 → 修正)を回す**。ハンドオフ無しの場合は Phase 3 全体をスキップする。**Feature 全 Step 完了後は Phase 4 で Claude 自身が Playwright を使った E2E 動作検証(3 ロール通し + 認可・バリデーション失敗ケース)を実施**し、完了報告では「Claude が動作確認した項目」を構造化出力する。外部 CLI 等で自動化不能な項目だけ「自動化不能 / ユーザー追加確認推奨」として分離する。$ARGUMENTS に Feature 名を渡す。並列で複数 Feature を実装したい場合は worktree-spawn Skill で別 Claude セッションを立ち上げて各セッションでこの Skill を使う
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

## 全体フロー（Phase 0 → 6 まで真直）

```
Phase 0  Claude Design ハンドオフ取込み              (任意、初回のみ)
   ↓
Phase 1  SDD 読み込み + 全 Step プラン把握 + TaskCreate
   ↓
Phase 2  tasks.md の Step 実装ループ                  (連続実行、各 Step 完了ごと承認待ちなし)
            ├ a. 次の未完了 Step を特定 → 着手宣言
            ├ b. 実装前準備(既存コード Read、Blade かつハンドオフあり時は design ref 再 Read)
            ├ c. 実装 + Pint 自動整形(hook)
            ├ d. ユニット / フィーチャテスト実行 → 合格まで修正
            ├ e. Blade 含む Step かつ ハンドオフあり時 → Phase 3 視覚検証(サブループ)へ
            │    (ハンドオフなし時は e をスキップして f へ)
            ├ f. Pint 整形 + 規約準拠の最終確認
            └ g. tasks.md 該当行 [x] 化 → 次 Step へ
   ↓
Phase 3  Playwright 視覚検証サイクル                  ※ Claude Design ハンドオフあり時のみ実施
         (Step 単位、Blade 含む Step ごと)             ※ ハンドオフなし時は Phase 3 全体をスキップ
         design ref と比較 → 乖離があれば修正 → 再撮影(最大 3 周)
         → 戻って Phase 2 の次 Step へ
   ↓ (全 Step が [x] になったら次のフェーズへ)
Phase 4  Playwright E2E 動作検証サイクル ★            (Feature 単位、1 ラウンド + 必要なら修正後再実行)
         spec からシナリオ抽出 → 3 ロール通し実行 → DB / Mail / リダイレクト判定
         → Fail があれば Phase 2 サブルーチンで修正 → 再実行(最大 3 周)
   ↓
Phase 5  自己レビュー                                 (必須読み込み再確認 + 規約準拠 + /code-review + 最終 test / Pint)
   ↓
Phase 6  Feature 完了報告                             (Claude 動作確認済の項目 + 自動化不能項目を構造化出力)
```

> tasks.md の各 Step 完了ごとにユーザー承認を取らない（連続実行）。ただし「相談ポリシー」に該当する場合はその場で確認する。
>
> **Phase 3 と Phase 4 はいずれも修正サイクル**（乖離 / Fail → 修正 → 再実行、最大 3 周）。3 周で収束しない場合は「相談ポリシー」に従いユーザーに判断を仰ぐ。
>
> Phase 5 で規約違反 / 漏洩 / テスト失敗が見つかった場合は Phase 2 サブルーチンに戻して修正 → Phase 4 E2E 再実行 → Phase 5 再実行の順で巻き戻す。

---

## Phase 0: Claude Design ハンドオフ取込み

### URL 検出

Skill を起動したユーザープロンプト全体から、以下の正規表現に合致する URL を探す:

```
https?://api\.anthropic\.com/v1/design/h/[A-Za-z0-9_-]+
```

- 見つかった: 自動で fetch（次セクション）
- 見つからない: ユーザーに「Claude Design ハンドオフ URL を渡してください。無ければ `なし` と回答」と確認。`なし` の場合 **Phase 0 全体をスキップして Phase 1 へ進む + 以降の Phase 3 視覚検証サイクル全体もスキップする**(視覚比較の正である design ref が無いため、視覚検証は意味をなさない)。Blade 実装時の `b. 実装前準備` での design ref 再 Read も同様にスキップし、`frontend-blade.md` / `frontend-ui-foundation.md` / `frontend-tailwind.md` の rules だけを根拠に Blade を書く。Phase 4 E2E 動作検証以降のフェーズには通常通り進む

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

> **Phase 3 視覚検証スキップの判定軸 (2 種類)**:
> - **ハンドオフ自体が無い**(URL 検出セクションで `なし` 回答): Feature 全体で Phase 3 を実施しない(rules だけで Blade を書く)
> - **ハンドオフはあるが対象 Feature の UI が無い**(本セクションの "UI なし" ケース): その Feature だけ Phase 3 をスキップ(他 Feature を並行作業する worktree では通常通り Phase 3 実施)

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

**Blade を含む Step かつ Claude Design ハンドオフが Phase 0 で展開済みの場合のみ、加えて次を必ず実施**(事後 Phase 3 で乖離を直すのではなく、事前に design ref を写すのが正しい順序):

> ハンドオフ無し時(Phase 0 で `なし` と回答された場合)は、design ref が存在しないため本ブロック全体をスキップし、`frontend-blade.md` / `frontend-ui-foundation.md` / `frontend-tailwind.md` の rules だけを根拠に Blade を書く。

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

#### e. Blade を含む Step の場合 → Phase 3 視覚検証へ(ハンドオフあり時のみ)

`resources/views/` 配下の編集を含む Step **かつ Claude Design ハンドオフが Phase 0 で展開済み** の場合のみ、Phase 3 を必ず通る。Phase 3 後に本フローに戻る。

ハンドオフ無し時(Phase 0 で `なし` と回答された場合)は本サブステップ全体をスキップして `f. Pint 整形 + 規約準拠の最終確認` へ進む。実装の確からしさは Phase 4 E2E 動作検証で改めて検証する。

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
| 5 Blade | `frontend-blade.md` `frontend-tailwind.md` `frontend-ui-foundation.md` + **Claude Design ハンドオフあり時のみ** (`preview/*.html` / `ui_kits/{role}/*.html`) を `b. 実装前準備` で **必ず Read** | layouts/app 継承、@csrf、@can、コンポーネント、Tailwind utility。**ハンドオフあり時は実装前に design ref Read 必須 → 事後 Phase 3 視覚検証で最終確認**。ハンドオフ無し時は rules だけで Blade を書く |
| 6 テスト | `backend-tests.md` `backend-types-and-docblocks.md` | RefreshDatabase + actingAs、各ロール認可分岐、ファクトリ |
| 7 動作確認 | — | Pint 整形 + テスト全通過 + (ハンドオフあり時) Phase 3 視覚検証(該当 Step)。Feature 全 Step 完了後に Phase 4 E2E 動作検証へ引き継ぐ(Step 単位で実機ブラウザ確認はしない) |

> **`backend-types-and-docblocks.md` の位置付け**: PHP ファイル編集を含む全 Step（1/2/3/4/6）で共通参照。`declare(strict_types=1)` / `private readonly` / `@param array{...}` shape / `@throws` / クラス・メソッド DocBlock / 行内コメント Why 原則 / `final class` 採用方針を規定する。paths frontmatter で auto-load されるので、各 PHP ファイル編集時に自動的に頭に入る前提。Step f の Pint 整形時に「Pint で自動化される項目 / 手で書く項目」のチェック観点も同ファイルに集約。

---

## Phase 3: Playwright 視覚検証サイクル

> **本 Phase は Claude Design ハンドオフ URL が渡された場合のみ実施する**。Phase 0 でユーザーが `なし` と回答した場合は本 Phase 全体をスキップし、Phase 2 の Blade 含む Step ごとの引き渡し(`e. Phase 3 視覚検証へ`)も発火しない。視覚比較の根拠となる design ref が存在しないため、視覚検証は意味をなさない。実装の確からしさは Phase 4 E2E 動作検証で代替的に検証する(レイアウト崩れ等は E2E のスクショ判定で気づける範囲のみカバー)。

Claude Design ハンドオフが Phase 0 で展開済みの環境で、Blade を含む Step（Step 5 / Step 7、または他 Step で Blade を触った場合）が完了したら、必ずここを通る。

### 前提

- Sail / Laravel 開発サーバが稼働している（`docker ps` で `laravel.test` コンテナ確認）
- **Claude Design ハンドオフが Phase 0 で展開済み**(無ければ本 Phase 自体をスキップ、上記注記参照)
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

## Phase 4: Playwright E2E 動作検証サイクル

Feature の全 Step が `[x]` になり Phase 3 視覚検証(該当 Step ごと)が収束した直後に **Feature 単位で 1 ラウンド** 実施する。ユーザーが実機で動作確認する負荷を Claude 側に巻き取るためのフェーズで、人間に渡るのは「Claude が動作確認済 / 自動化不能で要追加確認」の 2 リスト(Phase 6 で出力)。

### 目的

- 自動テスト(Feature テスト)では検証できない実機の挙動を Claude 自身が Playwright で網羅検証する
- DB 状態 / Flash トースト / リダイレクト先 / 認可 403 / バリデーションエラー / 視覚崩れ等を **シナリオ通し** で確認
- Fail を発見したら Phase 2 サブルーチンで修正 → 再実行のサイクルを回す(最大 3 周)
- 外部依存(Stripe Webhook / Mail / OAuth / Pusher 等で自動化不能)な動線は **明示分離** して Phase 6 完了報告で「自動化不能 / ユーザー追加確認推奨」セクションに送る

### 前提

- Phase 2 が全 Step 完了 + Phase 3 視覚検証が収束済み(`tasks.md` 全 `[x]`)
- Sail / Laravel が稼働(`docker ps` で `laravel.test` コンテナ確認)
- `sail npm run build` 済(直近の Blade 変更を反映)
- `migrate:fresh --seed` で **状態網羅 demo データ** 投入済み(`structure.md` Seeder 規約準拠 — 各 status のレコードが揃っている前提)
- Playwright MCP ツール(`mcp__playwright__*`)が利用可能

> 注: テスト DB(`RefreshDatabase` で破壊)と動作確認 DB(Seeder 投入後の状態)は別物。Phase 5 自己レビューでの `sail artisan test` が DB を壊すので、本 Phase の冒頭で **必ず `migrate:fresh --seed` を実行** してから検証を始める。

### a. シナリオ抽出

検証対象シナリオは、**Feature ごとに動的に** 以下から抽出する(ハードコードしない):

1. **`docs/specs/{name}/requirements.md` の REQ-***
   - HTTP-observable な受け入れ基準(画面遷移 / DB 変化 / Flash / 認可結果 / バリデーション結果を含むもの)を全部リストアップ
   - REQ-* のうち「自動化不能」マーカー(後述「自動化不能項目の判断」)に該当するものは別リストへ
2. **`docs/specs/{name}/design.md` の sequence diagram / ロールごとのストーリー**
   - 通しシナリオ(複数 Action を跨ぐもの)を抽出。例: admin が SKU 作成 → 公開 → 受講生が購入動線へ進む
3. **`docs/specs/{name}/tasks.md` 末尾の「動作確認」Step**
   - もし spec 側に通しシナリオが書かれていれば、それをそのままシナリオ候補に
4. **既存破壊チェック**: 隣接 Feature(本 Feature が触った rules / 共通 Model / route で関連しそうな Feature)のハッピーパスを 1〜2 ケース必ず追加。例: 本 Feature で `User` に列追加した場合、`auth` のログイン / `user-management` の一覧表示が壊れていないかを 1 ケース確認

抽出したシナリオを **3 カテゴリ** に分類:

| カテゴリ | 内容 | 必須 |
|---|---|---|
| **基本フロー(ハッピーパス)** | 3 ロール(admin / coach / student)それぞれの主要動線、各 1-3 ケース | ✅ |
| **エラー / 認可** | ロール違い 403 / バリデーション 422 / 状態遷移違反 409 / 未ログイン redirect | ✅ |
| **既存破壊チェック** | 隣接 Feature のハッピーパスが従来通り動くか | ✅ |
| **外部連携(該当時のみ)** | Mail / Webhook / Schedule Command / 外部 API | ⚠️ 自動化不能の場合は分離 |

> シナリオ数の目安: **基本フロー 5-8 件 + エラー / 認可 3-5 件 + 既存破壊 1-2 件 = 計 10-15 件**。20 件を超えるなら spec が肥大化しているか、粒度が細かすぎる。

### b. 自動化不能項目の判断(Feature ごと動的)

以下に該当するシナリオは Phase 4 では **検証せず**、Phase 6 完了報告の「⚠️ 自動化不能 / ユーザー追加確認推奨」セクションに分離する。判断は Feature ごとに行う(ハードコードしない):

| パターン | 判断軸 | 例 |
|---|---|---|
| **外部 CLI 必須** | ローカル Sail だけで完結せず、別ターミナルで CLI を起動し続ける必要がある | `stripe listen` (Stripe Webhook) / `ngrok` 経由の OAuth コールバック |
| **外部 SaaS の管理画面操作必須** | 検証に外部 SaaS の操作が要る(Stripe Dashboard / Google Calendar / Pusher 等) | Stripe Webhook イベントの再送 / Calendar OAuth 同意フロー |
| **Mail 本文の視覚確認** | Mailpit GUI で件名 / リンク / レイアウトを目視する必要がある | 招待メール / パスワードリセット / 通知メール |
| **タイミング依存の UX** | アニメーション / トースト auto-dismiss / リアルタイム通信の体感 | 5 秒後 toast 消える / Pusher の即時反映 |
| **モバイル幅レイアウト** | デスクトップ Playwright の viewport 切替で見える範囲はカバーするが、実機タッチ操作は対象外 | iPhone Safari での tap 操作 |

判断ルール:
- **自動化できるか迷ったら、まず試す**(Playwright で書ける動作は全部書く)
- **書けないとわかった時点で分離**。書く前から「これは無理」と諦めない
- 分離した項目は Phase 6 完了報告で **具体的な確認手順** を添えてユーザーに渡す(URL / 操作 / 期待 / 確認場所)

### c. Playwright での実行

シナリオ 1 件ごとに以下を実行:

1. **状態リセット**(必要時のみ): 別シナリオで DB が変わっている場合、`sail artisan migrate:fresh --seed` で巻き戻す。冪等なシナリオなら省略可
2. **ログイン**: `mcp__playwright__browser_navigate` で `/login` → 該当ロールの email/password を入力 → submit
3. **操作**: シナリオ通りに画面遷移 / フォーム送信 / ボタンクリック
4. **判定** (以下を組み合わせる):
   - **HTTP ステータス**: 200 / 302 / 403 / 404 / 409 / 422 を Playwright で確認
   - **URL 遷移先**: `browser_navigate` 後の URL を取得して assert
   - **画面表示**: `browser_snapshot` / `browser_take_screenshot` で Flash トースト / バッジ / Empty state 等を確認
   - **DB 状態**: `./vendor/bin/sail mysql certify_lms -e "SELECT ..."` で該当行の status / カラム値 / soft delete 状態を確認
   - **Mailpit**: 該当時のみ。`curl http://localhost:8025/api/v1/messages` で受信メール件名 / 件数を確認(本文の視覚は自動化不能扱い、分離)
5. **ログアウト**: 次シナリオが別ロールなら `/logout` を POST(or `$this->browser_evaluate` で fetch)

シナリオごとに「✓ Pass / ✗ Fail (理由)」を構造化して記録する(Phase 6 で出力)。

### d. Fail 発生時の修正サイクル

Fail を 1 件でも発見したら:

1. 原因切り分け: 実装バグか / シナリオの組み立て間違いか
2. 実装バグなら **Phase 2 のサブルーチン** に戻して修正(Action / Controller / Policy 等)
   - 修正後 `sail artisan test --filter={Entity}` でユニット / フィーチャテストが全 Pass を確認
3. 修正完了したら Phase 4 を **該当シナリオから** 再実行(全シナリオを最初から走らせ直さない、コスト過大)
4. ただし修正が **他シナリオに波及する可能性** がある場合(共通 Service / Model 改修等)は **全シナリオ再実行**

### e. 収束 / 打切り

- **収束判定**: 全シナリオが Pass(または自動化不能項目として明示分離)になったら終了 → Phase 5 自己レビューへ
- **打切り**: 3 周しても残 Fail がある場合は「相談ポリシー」に従いユーザーに判断を仰ぐ(打切り選択肢: Fail のまま Phase 5 へ進む / 根本修正を継続 / 該当機能を scope 外に外す)

### f. 検証結果の記録

シナリオ単位で以下を構造化記録(Phase 6 完了報告で出力する素材):

```
✅ admin が新規 SKU 作成
  URL: /admin/meeting-quota-plans/create (admin@)
  操作: name + meeting_count + price 入力 → 「下書きとして保存」
  期待: /admin/meeting-quota-plans/{id} へリダイレクト + Flash「追加面談プランを作成しました。」
  DB:  meeting_quota_plans に status='draft' + created_by_user_id=admin.id で INSERT
  証跡: scrshot=cur-admin-mq-create-success.png / DB=確認済(1 行追加)

✗ student が published 以外の SKU を購入
  → Phase 2 で修正 → 再実行で ✓
```

スクショは `<repo-root>/.tmp-design-compare/` に保存(`.gitignore` 済)。

---

## Phase 5: 自己レビュー（Phase 6 完了報告の前に必ず実施）

Feature の全 Step が `[x]` + Phase 4 E2E 動作検証が収束した直後、Phase 6 完了報告の **前** に自己レビューを行う。Phase 5 を省略すると、規約違反 / 受講生コンテキスト漏洩 / 実装と spec の乖離が後出しで発覚し、後段でリワークが発生する。

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

### e. Phase 6 完了報告へ進む

a〜d がすべて pass で初めて Phase 6 完了報告へ進める。

a〜d のいずれかで違反 / 失敗があれば Phase 2 のサブルーチンに戻して修正し、Phase 4 E2E → Phase 5 自己レビューを再実行する(順序: Phase 2 → Phase 3 (該当 Step のみ) → Phase 4 → Phase 5 → Phase 6)。

---

## Phase 6: Feature 完了報告

> 本フェーズは **Phase 5 自己レビュー完了後** に実施する(順序: Phase 4 → Phase 5 → Phase 6)。

Feature の全 Step が `[x]` + Phase 4 E2E 動作検証収束 + Phase 5 自己レビュー Pass で最終チェック:

```bash
./vendor/bin/sail artisan test 2>&1 | grep -E "Tests:|FAILED"
./vendor/bin/sail bin pint --dirty
```

両方 PASS で以下を報告:

- 完了 Feature 名
- 実装した Step 一覧
- 変更ファイル一覧(パス + 行数)
- テスト結果サマリ(X tests / Y assertions / 全 PASS)
- Phase 3 視覚検証で fix した主な乖離(あれば)
- Phase 4 E2E 動作検証サマリ(Pass 件数 / 自動化不能件数 / 修正で fix した Fail 件数)
- **🤖 Claude 動作確認済**(次節、Phase 4 で Pass した全シナリオを構造化出力)
- **⚠️ 自動化不能 / ユーザー追加確認推奨**(次節、Phase 4-b で分離した外部依存シナリオのみ)
- 次の推奨アクション(次の Feature / 残課題)

### 🤖 Claude 動作確認済(必須出力)

> **目的**: Phase 4 E2E 動作検証で Pass した全シナリオを「Claude が確認した事実」として構造化し、ユーザーは追従確認(本当にそうなっているか軽くチェック)するだけで完結できる状態を作る。
>
> **書き方の鉄則**: **1 項目 = 1 行**。URL・ロール・操作・期待・確認場所(DB / Flash / screenshot) を `→` で一行にまとめる。Phase 4-f で記録した内容をそのまま転写。手順の番号付きリストや複数行の詳細は書かない(読まれない)。

#### 出力フォーマット

````markdown
## 🧪 動作確認 — {feature-name}

### 検証環境

```bash
sail up -d
sail artisan migrate:fresh --seed   # Phase 4 冒頭で実施済 / 状態網羅 demo データ投入(structure.md Seeder 規約準拠)
sail npm run build                  # Blade を含む Step を実装した時は実施済
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

**状態網羅 demo データ**(`UserSeeder` + `PlanSeeder` 等で揃っている前提):

- User: admin × 1 / coach × 2 / student × 16(invited × 2 / in_progress × 9 / graduated × 3 / withdrawn × 2)
- Plan: published × 3(1ヶ月 / 3ヶ月 / 6ヶ月) / draft × 1 / archived × 1
- 各 in_progress student は 3 種の Plan に分散 + 開始直後 / 中盤 / 期限直前を散らして紐づけ

### 🤖 Claude 動作確認済

#### 基本フロー(ハッピーパス)
- [x] `/admin/foo` (`admin@`) → 一覧で各 status のレコードが並ぶ + フィルタ + ページネーション動作 [DB:状態網羅 seed / screenshot:cur-admin-foo-index.png]
- [x] 新規作成 (`admin@`) → `foos.status=draft` で INSERT + Flash「foo を作成しました」表示 + `/admin/foo/{id}` へリダイレクト [DB:1 行追加確認 / screenshot:cur-admin-foo-create-success.png]
- [x] 編集 (`admin@`) → 値更新 / status 不変 [DB:該当行 updated_at 更新確認]
- [x] {状態遷移ボタン} (`admin@`) → `foos.status` 更新 + 一覧の status バッジ反映 [DB:status='published' 確認]
- [x] {受講生動線} (`student@`) → ... [...]

#### エラー / 認可
- [x] {不正状態で状態遷移} (`admin@`) → 409 Conflict + Flash error 表示 + DB 不変 [HTTP:409 / DB:status 不変確認]
- [x] `coach@` で `/admin/foo` → 403 Forbidden [HTTP:403]
- [x] `student@` で `/admin/foo` → 403 Forbidden [HTTP:403]
- [x] 未ログインで `/foo` → `/login` リダイレクト [HTTP:302]
- [x] バリデーション失敗(`name` 空文字) → 422 + 該当 input にエラー表示 [HTTP:422]

#### 既存破壊チェック
- [x] `auth` ログイン(admin / coach / student 各ロール)が従来通り通過 [HTTP:302 → /dashboard]
- [x] `user-management` の `/admin/users` 一覧が status バッジ表示込みで従来通り描画 [screenshot:cur-existing-users-index.png]

### ⚠️ 自動化不能 / ユーザー追加確認推奨

> Phase 4 で自動化困難と判断されたシナリオ。実機で軽く確認していただきたい項目。本 Feature では以下が該当(該当しない Feature ではこのセクション自体を「該当なし」とする):

- **Stripe Webhook 経由の決済完了処理**: 別ターミナルで `stripe listen --forward-to localhost:8000/webhooks/stripe` を起動 → student で購入(`4242 4242 4242 4242`) → CLI に `checkout.session.completed` ログ → `/meeting-quota/history` で残数加算を確認
- **Mailpit 招待メール本文の視覚確認**: http://localhost:8025 で件名「【Certify LMS】ご招待」+ 本文の招待 URL クリック可能を確認
- **モバイル幅レイアウト**: iPhone Safari 等で `/dashboard` を開き、サイドバー drawer / hamburger 動作確認(Playwright viewport 切替で抜けるレイアウト崩れがないか)
````

#### 「🤖 Claude 動作確認済」を埋める時のコツ

- **1 項目 = 1 行に圧縮**: 「URL (ロール) → 操作 → 期待 + 確認場所」を `→` 区切りで横並びに。手順の番号付きリストや複数行のネストは禁止(チェック項目として読み飛ばされる)
- **必ず証跡を `[...]` で末尾に**: `[DB:該当行確認]` / `[HTTP:403]` / `[screenshot:cur-xxx.png]` / `[Mailpit:該当メール件名]` 等。Claude が **何で確認したか** を明示し、ユーザーが追従確認する場所を示す
- **1 業務操作 = 1 チェック項目**: spec の「ロールごとのストーリー」「sequence diagram」「REQ-*」から抽出。10 項目 ±α が目安、20 を超えたら粒度が細かすぎる
- **DB 確認を必ず織り込む**: 「Flash 表示」だけでは裏で何も起きていない実装も pass する → 「`foos.deleted_at != NULL`」のような具体的なカラム値を期待に書く
- **soft delete / リネームは事後の見え方も**: 削除後にデータが残るか / リネーム済み値が表示されるかを 1 行追加
- **認可は admin only への `coach@` / `student@` アクセスを必ず網羅**: spec の認可 REQ を 1 行ずつに集約
- **既存破壊チェックは 1〜2 行**: 隣接 Feature が壊れていないかを確認する最低限のシナリオを 1〜2 行入れる
- **Seeder demo データに乗る前提で書く**: 「状態網羅した demo は seed 済」を前提に、本 Feature **固有の操作** を中心にチェックを置く(全 status の存在確認は seed 結果として自動的に担保される)。本 Feature の Seeder で新規に投入する状態がある場合のみ、その状態を見るチェックを追加する
- **ロール別アカウントを使い分ける**: admin 専用画面の動作確認は `admin@`、coach 視点は `coach@`(複数コーチシナリオなら `coach2@` も)、受講生視点は `student@` を明示する。「coach / student」のような汎用ロール表記ではなく、固定アカウントの email を書くことで読み手が即座にログインして検証できる
- **チェックボックスは `[x]` で出力**(Claude が確認した事実なので、デフォルトで checked)。ユーザーが「自分で再確認していない」項目だけ `[ ]` に書き換えて使う想定

#### 「⚠️ 自動化不能」を埋める判断軸

Feature ごとに **動的判断** する(ハードコードしない)。判断は Phase 4-b で実施済。Phase 6 ではその結果を転写するだけ。

- **該当 Feature の REQ-* / design.md sequence diagram** を見て、Phase 4-b の表(外部 CLI 必須 / 外部 SaaS 管理画面操作 / Mail 本文視覚 / タイミング依存 / モバイル幅実機)に当てはまるシナリオを抽出
- 該当なしの Feature では「⚠️ 自動化不能 / ユーザー追加確認推奨: 該当なし」とだけ書く(セクション省略不可、明示が重要)
- 抽出した各項目には **具体的な確認手順**(別ターミナルでこのコマンド起動 / この URL を開く / この文言を確認) を添える(ユーザーがその場で実行できる粒度に)

#### 取り扱い

会話の最後にプレーンな Markdown として出力する(GitHub PR description / Notion / Slack にそのまま貼れる形)。コードフェンスで囲まなくてよい。

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
- Phase 3 視覚検証 or Phase 4 E2E 動作検証が 3 周で収束しない場合(選択肢: 「ここまで合わせる / Fail 件として明示分離」「Fail のまま Phase 5 へ進む」「該当機能を scope 外に外す」「根本修正を継続」のいずれを取るか)
- Phase 4 E2E で「自動化不能項目」として分離すべきか判断に迷うシナリオがある場合(無理に自動化すると不安定 / そもそも検証手段が外部 CLI 必須等)

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
- Pint 整形完了(`--dirty` で passed)
- **Claude Design ハンドオフあり時のみ**: Blade を含む Step で design ref との視覚乖離が許容範囲に収まっている(Phase 3 収束)。ハンドオフ無し時はこの項目は対象外(Phase 3 全体をスキップしているため、視覚側の最終確認は Phase 4 E2E のスクショ判定で気づける範囲のみ)
- **Phase 4 E2E 動作検証で 3 ロール通しシナリオ + 認可 / バリデーション失敗ケース + 既存破壊チェックが全て Pass**(自動化不能項目は Phase 6 完了報告の「⚠️ 自動化不能」セクションに明示分離されていれば OK)
- Phase 5 自己レビューで規約違反 / 受講生コンテキスト漏洩 / `/code-review` 指摘ゼロ
- Phase 6 完了報告で「🤖 Claude 動作確認済」と「⚠️ 自動化不能 / ユーザー追加確認推奨」の 2 セクションが構造化出力されている
- 必要なら user-management 等の依存 Feature の前提(例: `UserStatusChangeService`)も同時実装済み
