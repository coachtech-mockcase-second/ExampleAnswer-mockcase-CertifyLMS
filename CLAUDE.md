# Certify LMS — 模擬案件②

> COACHTECH受講生向け模擬案件②。「既存プロジェクトへの参画」を体験する。
> 本リポジトリは提供プロジェクト・模範解答プロジェクト・関連ドキュメントを一元管理する唯一の真実。

本模擬案件のプロダクト名: **Certify LMS**（マルチ資格対応の資格取得LMS）。

---

## ペルソナ（WHO）

本模擬案件は、Web エンジニア育成オンラインスクール **COACHTECH** の受講生向けに設計される。受講生はカリキュラムの最終評価フェーズに到達し、これまで以下を **新規構築型** で修了済み（既存プロジェクトへの参画経験はない）:

- **教材**: HTML/CSS → PHP → Laravel基礎〜応用 → API設計 → テスト
- **確認テスト**: ContactForm をゼロから構築
- **模擬案件①**: BookShelf をゼロから構築

### 前提知識（Basic範囲の中核）

| 領域 | 技術 |
|---|---|
| BE | PHP 8.2, Laravel 10（MVC・Eloquent・認証・ミドルウェア・テスト・API基礎）|
| DB | MySQL 8.0 |
| ORM | Eloquent（リレーション・Eager Loading・N+1対策・withCount）|
| 認証認可 | Fortify, Policy / Gate |
| バリデーション | FormRequest |
| テスト | PHPUnit（Feature / Unit）, RefreshDatabase, actingAs |
| FE | Blade, Tailwind CSS, Alpine.js |
| 環境 | Docker / Sail, Git / GitHub |

---

## コンセプト（WHY）

受講生は最高評価 **S** で卒業して **Pro生**（COACHTECH 提携のフリーランスエージェント所属生）認定を目指す。しかし近年、AI 出力をそのまま提出して S が取れてしまう問題が深刻化し、Pro生として企業に紹介された後に実務で詰むケースが多発。本模擬案件は「Pro生として最終評価される最後の関門」として、以下の3課題に応える。

| # | 課題 | アプローチ |
|---|---|---|
| 1 | **既存PJ参画の経験不足** — 前2つのテストは新規構築だった | 提供プロジェクトをクローンしコードリーディング前提で開発 |
| 2 | **要件ヒアリングの経験不足** — 実務では曖昧な要件が降ってくる | 50%要件 + コーチ（PM役）へのヒアリング誘導 |
| 3 | **AI丸投げによる理解なき実装** — Pro生でも企業で詰む | チケット曖昧化 + PR記述4項目必須 + 評価配点でAdvance必須化（AI丸投げではS不可）|

---

## ゴール（WHAT）

### 構築するプロダクト

**Certify LMS** — マルチ資格対応の資格取得LMS。

プロダクト固有の永続コンテキストは **`docs/steering/`**（メタ階層、構築側のみ参照）に Kiro 流で集約:

- **`product.md`** — テーマ / ロール / コンテンツ階層 / 主要UXフロー / Feature一覧
- **`tech.md`** — 技術スタック / Clean Architecture方針 / コード品質ルール / テスト方針 / Git運用
- **`structure.md`** — Laravel ディレクトリ構成 / 命名規則 / specs/ 作成ルール

各機能の詳細SDDは **`docs/specs/{name}/`** に展開。

### 扱う技術スコープ

受講生の前提知識（Basic範囲の中核）に対して、本模擬案件は以下の範囲を扱う:

#### Basic 拡張範囲（教材外だが既存テストで経験済み → 繰り返し成功体験）

- **メール送信**（Mail channel）
- **通知**（Notification + Database channel + Mail channel）

#### Advance 範囲（教材外、本模擬案件で初出 / 深掘り）

外部API連携（Google Calendar OAuth, Gemini API）/ **API キー認証 + GAS / Google Sheets 連携**（[[analytics-export]] Feature: `X-API-KEY` ヘッダ Middleware + 素データ取得 API + GAS で Sheet 出力 + Sheet 側で分析。BookShelf 経験の延長 + API キー認証 Middleware + FE/GAS 実装の複合題材。Basic/Advance 配置はチケット選定時に決定）/ Sanctum SPA認証（Cookie ベース、[[quiz-answering]] の自前 SPA で利用）/ Broadcasting・WebSocket（Pusher）/ Queue・Job 非同期化 / DBインデックス / キャッシュ / Eager Loading最適化

### チケット構成（受講生の課題）

3カテゴリ × 8種類:

| カテゴリ | 種類 |
|---|---|
| バグ修正 | データの不正 / アクセス制御の不備 / 機能の不全 |
| 機能開発 | 既存機能の修正 / 既存機能の拡張 / 新規機能の構築（テスト必須）|
| リファクタリング | コード構造 / パフォーマンス |

**配分（Step 3 で確定）**: 18-21 チケット（Basic 13-15 / Advance 5-6）
**評価配点**: Basic 65-70% / Advance 30-35%（採点項目 140-160 / 配点 170-200）
**ベースライン**: BookShelf 比 5-15% 減（既存PJ参画のコードリーディング負荷を吸収）
**S取得必須**: Advance 内に3チケット必須化（AI / コーチ / ドキュメント丸投げで埋まらない構成）
- 候補: 公開API SPA（Sanctum）/ 面談予約UI（Google Calendar OAuth）/ リアルタイム通信（Broadcasting）

チケット個別定義（1チケット = 1Markdownセクション）は **`関連ドキュメント/要件シート_詳細度100%.md`**（コーチ用）と **`_詳細度50%.md`**（受講生用）に集約。

---

## アプローチ（HOW）

### ワークフロー

**構築フロー**: 模範解答PJ を先に完成させ、**引き算で提供PJ を作る** 方式。完成形 specs/ を起点とするので整合性が高く、要件シートは「完成形のこの部分を提供時こう変える」という diff 指示として書ける。

| Step | 内容 | 主な成果物 |
|---|---|---|
| 1 | 設計：プロダクト定義 + Feature一覧 | `docs/steering/`（メタ階層、構築側のみ） |
| 2 | 設計：**Feature 完成形の SDD**（feature ごと requirements/design/tasks）| `docs/specs/{name}/`（メタ階層、構築側のみ） |
| 3 | **模範解答PJ実装** + **要件シート定義**（順序不問、両方とも `docs/specs/` を起点）| `模範解答プロジェクト/` + `関連ドキュメント/要件シート_詳細度100%.md` |
| 4 | **模範解答PJ → 提供PJ 変換**（要件シートに従い引き算 / バグ化 / 巻き戻し）+ Bladeロック有効化 🔒 + 動作確認 | `提供プロジェクト/` コード + README.md |
| 5 | 残りドキュメント（50%要件 / 評価シート / 完全手順書 / 復習教材、※通しプレイ検証は手順書作成中に都度実施）| 関連ドキュメント/ 全部 |
| 6 | 配置 → AssignedProject リポへ（`docs/` `.claude/` `要件シート_詳細度100%.md` は **含めない**、提供PJ + 50%要件等のみ）| 公開 |

### Step 4 引き算戦略（要件シートが指示する変換タイプ）

| 要件カテゴリ | 模範解答の状態 | 提供PJへの変換 |
|---|---|---|
| **新規機能開発** | 完全実装 | **Blade のみ残してロジック削除**（Controller method / Action / Service / Model 関連を削除）|
| **バグ修正** | 正しい実装 | **指定箇所をバグった実装に置換**（要件シートに具体的 diff 記述）|
| **既存機能改修・拡張** | 拡張版 | **拡張前の状態に巻き戻し**（diff スタイル指示）|
| **リファクタリング** | リファクタ後 | **リファクタ前の状態に巻き戻し**（コード重複・密結合状態に意図的に汚す）|

### Step 3（要件定義）の修了条件と5観点

#### 頂点 (Outcome): 修了条件 — Pro生 Junior Engineer 像

ContactForm（基礎構築）→ BookShelf（応用＋外部API）→ **Certify LMS（既存PJ参画＋曖昧要件＋AI耐性）** を経て、企業に紹介された初日から戦力になる人材を送り出す。チケット集合は以下の **8 カテゴリ・28 能力項目** すべてを養成できなければならない。

| カテゴリ | 能力項目 |
|---|---|
| **A. 既存PJ参画** | A1: README / docs / コードから全体像把握 / A2: 既存パターンで新規実装 / A3: 既存テスト参考に新テスト追加 / A4: 既存命名・migration・seeding 規則準拠 |
| **B. 要件ヒアリング・仕様判断** | B1: 50%要件から不足箇所抽出 / B2: コーチに的を絞った質問 / B3: ヒアリング結果を実装に反映 / B4: やる / やらない / 後回しの線引き |
| **C. コード品質担保** | C1: FormRequest / Policy / Enum / SoftDeletes / `DB::transaction` 適切使用 / C2: N+1 発見・解消 / C3: Pint・命名・コメント整理 / C4: Feature/Unit テスト記述とカバレッジ意識 |
| **D. チーム協働** | D1: PR 7セクション記述 / D2: 動作確認動画・スクショ / D3: commit Step/機能単位 / D4: ブランチ運用理解 |
| **E. 外部システム連携** | E1: 公式ドキュメントから API/OAuth/Sanctum/Broadcasting 実装 / E2: `.env` / `Http::fake()` 扱い / E3: GAS / Google Sheets / Calendar / Pusher / Gemini 連携 |
| **F. 障害対応**（バグ修正カテゴリの育成目的）| F1: バグレポートから再現 / F2: 原因特定・影響範囲判断 / F3: Hotfix と恒久対応の切り分け / F4: 修正前後の比較テスト |
| **G. パフォーマンス意識**（リファクタリングカテゴリの育成目的）| G1: N+1 / index / cache 不在の発見 / G2: 改善前後の計測・報告 |
| **H. AI 適切協働** | H1: AI と自分の判断分担 / H2: AI 出力の検証習慣 / H3: AI で詰むパターン認識 |

#### 点検手段 (Means): 5観点

修了条件 28 項目をチケット集合が確実に養成するかを以下の **5観点** で点検する。

| # | 観点 | 何を見るか | 合格ライン |
|---|---|---|---|
| **①** | **能力カバレッジ** | 修了条件 28 項目をチケット集合が網羅するか | 全 28 項目に対し最低 1 チケットがマップされる / 同一項目を 2-3 チケットで反復養成（成功体験設計）|
| **②** | **量** | カバレッジ達成に必要な最小量 | 18-21 チケット / 140-160 採点項目 / 170-200 点（BookShelf 比 5-15% 減、コードリーディング負荷込み）|
| **③** | **質** | 1チケットあたりの能力養成密度 | 1チケット = 平均 **2-4 能力項目**を同時養成 / 横断 spec ≥ 2 / 既習再登場が過半数 |
| **④** | **構成** | 評価軸・配点・依存関係 | 評価シート 7 大項目 + 14 中項目すべてに対応チケットあり / S 取得 80% に Advance 3 件必須 / 依存ゼロで並列着手可 / Basic 65-70% / Advance 30-35% |
| **⑤** | **自走耐性** | 「丸投げ」を許さない設計 | 仕掛けスコア合計 ≥ 35 / Basic 各 ≥ 1 / Advance 各 ≥ 2 / S 必須 3 件各 ≥ 3 |

#### ⑤ 自走耐性 — 「丸投げ」排除設計

旧「AI耐性」を拡張し、AI / コーチ / ドキュメント / 過去模擬案件いずれの丸投げも通過 NG とする。**仕掛けスコア** は以下のいずれかを持てば +1（1チケットの上限なし）。

| 丸投げパターン | 排除する仕掛け |
|---|---|
| **AI 丸投げ** | 外部 API 依存（Google Calendar OAuth / Pusher / Gemini / GAS）/ 横断改修（3 spec 以上）/ 動作確認動画必須（動的機能 = タイマー / 状態遷移 / リアルタイム / モーダル / バッチ）/ ドメイン判断（資格LMS固有の合格判定・分野配分・弱点分析等）|
| **コーチ丸投げ** | 50%要件で曖昧化 → 受講生がヒアリング項目を抽出 → コーチに的を絞った質問ができる前提を養成（B カテゴリ育成） |
| **ドキュメント丸写し** | 「既存テストを参考に」「既存 Policy と同じ書き方で」= 該当ファイルを自分で見つけて読む（A カテゴリ育成） |
| **過去模擬案件丸写し** | BookShelf 既習パターン再登場だが題材ドメインが違う（書籍 → 資格）/ 適用判断は自分 |
| **PR 7セクション記述必須**（関連チケット / 調査 / 原因分析 / 実装 / 自動テスト / 動作確認 / レビュー観点、`tech.md` 参照）| AI 出力をそのままコピペできない / 動作確認は実機操作が必要で AI 生成不可 |

#### フレームワーク全体像

```
            ┌──────────────────────────────────────┐
            │ 修了条件: Pro生 Junior Engineer 像 │
            │   A〜H 8カテゴリ × 28 能力項目      │
            └──────────────────────────────────────┘
                            ▲
                            │ 実現手段として点検
       ┌──────────┬─────────┼─────────┬───────────┐
   ① 能力      ② 量      ③ 質      ④ 構成    ⑤ 自走耐性
   カバレッジ                                  (AI+コーチ+
                                              丸写し排除)
```

- **① は他4観点の頂点**: ②③④⑤がすべて満たされても①が満たされなければ意味がない
- **②③④ は集合チェック**（チケット全体を俯瞰）
- **⑤ は個別+集合**（各チケットの仕掛けスコア + 集合の総和）
- **① は個別+集合**（各能力項目 × 各チケットのマトリクス）

### 成果物

| # | 成果物 | 説明 |
|---|---|---|
| 1 | 提供プロジェクト | 受講生クローン用既存PJ。全Blade完成 / 実装済み機能（バグ込み）/ 未実装機能はBladeのみ + 最小 README。**完成形仕様（docs/）は含まない**（受講生は要件シートで作業）|
| 2 | 模範解答プロジェクト | 提供版コピー + 全チケット実装後の完成版（Basic/Advance両ブランチ）|
| 3 | 要件シート | 100%版（コーチ用）/ 50%版（受講生用）。1チケット = 1セクション |
| 4 | 評価シート | 採点基準 |
| 5 | 完全手順書 | Basic / Advance |
| 6 | 復習教材 | Basic / Advance |
| 7 | `docs/`（メタ階層）| 構築側のみ参照する **完成形仕様**（steering + specs）。受講生に渡さない |

### 構築原則

- **steering/ と specs/ が設計の唯一の入力** — 仕様変更は必ず先に `docs/` を更新してから実装
- **`docs/` はメタ階層に集約、構築側のみ参照** — 受講生には完成形仕様を見せない。受講生は提供PJコード + 要件シートで作業
- **specs/ = Feature 完成形の SDD（= 模範解答仕様書）** — 完成形を完全記述。提供PJ時点の差分（未実装 / バグ込み / 改修対象）は **要件シート** が示す（specs = What it should be / 要件 = How to get there）
- **模範解答PJ 先行構築（引き算方式）** — 完成形 specs を起点に模範解答PJ を完成させ、要件シートに従い提供PJ に **引き算変換**（削除 / バグ化 / 巻き戻し）。足し算より整合性が高く、要件シートは diff 指示として書ける
- **新規機能は自己完結ページ** — 既存ページから参照なし。ナビは `Route::has()` で制御
- **全Blade提供 + ロック** — Basic既存・Basic新規・Advance のすべての Blade を提供プロジェクトに含める。受講生はコードリーディング + **ロジック・API・JSの実装** が担当（ContactForm / BookShelf 同様）。例外: Advance の自前FE SPA など、提供PJに痕跡を残さないケースは Feature 単位で個別判断

### 構築ツール

| ツール | 用途 | タイミング |
|---|---|---|
| **Claude Design + ハンドオフ機能** | Design System + Hero Screens 4-6枚 のデザイン → Claude Code への自動ハンドオフ。COACHTECH LMS 流の高品質 UI 起点 | **Step 3 Wave 0a**（User が別環境で実施）|
| [frontend-design プラグイン](https://github.com/anthropics/claude-code/blob/main/plugins/frontend-design/skills/frontend-design/SKILL.md) | Blade UI品質向上（AIスロップ回避）。Wave 0b 以降で Design System に倣って残りの画面を生成する補助 | Step 3 Wave 0b 以降 |
| Laravel Pint hook (PostToolUse) | PHP自動整形 | Step 3以降（`.claude/settings.json` で設定済み）|
| Blade ロック hook (PreToolUse) | `.blade.php` 編集をブロック。提供PJ への変換後 / 受講生作業中の誤改修ガード | Step 4以降（変換完了後の提供PJ にロック適用）|
| **Skill `spec-generate`** | 1 Feature の spec 3点セット生成（自己完結・直列）| Step 2 |
| **Skill `feature-implement`** | 1 Feature の Laravel 実装（自己完結・直列）| Step 3 |
| **Skill `worktree-spawn`** | 並列実装用 git worktree 作成 + 別 Claude セッション起動手順 | Step 2 / Step 3（並列ピーク時）|

## 実装プラン（Skills が参照する Certify LMS 固有設定）

`.claude/skills/worktree-spawn/` は **プロジェクト固有の Feature 分配・実装ディレクトリを本セクションから読み取る**。Skills 自体は汎用、本セクションが Certify LMS としての具体定義。

### 実装ディレクトリ

- **`模範解答プロジェクト/`** — Step 3 で先行構築、`docs/specs/` と完全整合
- 提供プロジェクト/ は Step 4 で模範解答PJ から引き算変換

### 構築フェーズ

| フェーズ | 内容 | 担当 |
|---|---|---|
| **Wave 0a** Claude Design（共通UI設計） | Design System（カラー / フォント / スペーシング / Button / Form / Card / Modal / Alert / Nav）+ Hero Screens 4-6枚（受講生Dash / mock-exam受験 / 弱点ヒートマップ / qa-board / コーチDash / 管理者Dash）。**指示書は `.claude/rules/frontend-ui-foundation.md`「Wave 0a への指示書サマリ」** | **User**（Claude Design Web UI、別環境）|
| **Wave 0b** ハンドオフ → Laravel/共通UI 実装 | Wave 0a のハンドオフコードを受け取り、Laravel 初期セットアップ + Sanctum/Fortify + 共通 Model (User/UserStatusLog) + `resources/views/layouts/` + `resources/views/components/` (Button/Form/Card/Modal/Alert/Nav) を Design System 準拠で実装 + tailwind.config.js / Vite 設定。**完成判定は `.claude/rules/frontend-ui-foundation.md`「Wave 0b の完成判定基準」** | Claude Code（主セッション、直列） |
| **Feature 実装フェーズ** | 16 Feature を Wave 0b の共通基盤を利用しつつ実装。**進行順・並列度は進めつつ決定**。原則として `auth` / `user-management` を最初に直列で実装（後続 Feature が依存）、それ以降は独立 Feature を `worktree-spawn` で並列、依存ある Feature は順次 | Claude Code（主セッション + worktree並列）|

### Feature 一覧（16個）

1. `auth` / 2. `user-management` / 3. `certification-management` / 4. `content-management` / 5. `enrollment` / 6. `learning` / 7. `quiz-answering` / 8. `mock-exam` / 9. `mentoring` / 10. `chat` / 11. `qa-board` / 12. `analytics-export` / 13. `notification` / 14. `dashboard` / 15. `ai-chat` / 16. `settings-profile`

依存関係の目安:
- **後続の前提**: `auth`, `user-management`（最初に実装）
- **独立 Feature**（並列向き）: `certification-management`, `content-management`, `enrollment`, `learning`, `quiz-answering`, `mock-exam`, `chat`, `qa-board`, `settings-profile`, `analytics-export`, `ai-chat`
- **集計依存 Feature**（後半 or 直列）: `notification`, `dashboard`, `mentoring`

### Wave 0b で確定する基盤資産（Feature 実装フェーズでは編集禁止）

- `composer.json` / `package.json`（全依存を一括追加してフリーズ）
- `bootstrap/providers.php`（Service Provider は Package Auto-Discovery 利用）
- `routes/web.php` / `routes/api.php`（基盤ルート登録）
- 共通 Model（`User`, `UserStatusLog`）+ Migration
- 共通 Blade レイアウト / コンポーネント

### 並列性の物理保証

- **worktree**: `worktree-spawn` Skill で Feature ごとに独立 worktree 作成、各 worktree で別 Claude セッション
- **DB**: 各 worktree に独立 SQLite（`模範解答プロジェクト/database/database_{name}.sqlite`）
- **依存**: composer / npm は Wave 0b で確定済み、Feature 実装フェーズの worktree では編集禁止
- **routes**: 各 worktree で `routes/web.php` を編集、マージ時に標準的な Git 手動衝突解決

### Step 2 / Step 3 の進め方

**直列 + 並列のハイブリッドで、進めながら判断**:

- まず `auth` → `user-management` を主セッションで `/spec-generate` or `/feature-implement` 直列実行（後続が依存）
- それ以降は独立 Feature を `/worktree-spawn` で 4-6 並列起動、依存ある Feature は順次
- 並列度の実用上限は 4-6（業界標準、`worktree-spawn` SKILL.md 参照）

### リポジトリ・ブランチ

| リポ | 用途 | 公開 | ブランチ構成 |
|---|---|---|---|
| ExampleAnswer-mockcase-CertifyLMS（本リポ）| 全成果物一元管理（構築側メタリポ）| ❌ | **`main` 1本**（Basic/Advance の区別は `docs/steering/product.md` の範囲定義 + 模範解答PJ のコード内で表現）|
| AssignedProject-mockcase-CertifyLMS | 受講生クローン用 | ✅ | `basic`（メイン）/ `advance`（basic から分岐、Advance 純粋追加）|

---

## プロジェクトマップ（MAP）

### Skills

Skills は **3個のみ**（Subagent は撤回、シンプル構成）。並列は git worktree + 別 Claude セッションで実現。

| Skill | 役割 | 並列性 |
|---|---|---|
| **`spec-generate <feature>`** | 1 Feature の spec 3点セット生成（自己完結、直列）| なし（worktree-spawn 経由で並列化）|
| **`feature-implement <feature>`** | 1 Feature の次の未完了 Step を実装（自己完結、直列）| なし（同上）|
| **`worktree-spawn <feature[,feature,...]>`** | 並列実装用 git worktree 作成 + 各 worktree でのセッション起動手順提示 | 並列の入口 |

**呼出パターン**:

```
ユーザーの典型的な使い方:

「auth の spec 作って」
  → /spec-generate auth
  → docs/specs/auth/{requirements,design,tasks}.md 生成

「mock-exam を実装して」
  → /feature-implement mock-exam
  → 模範解答プロジェクト/ に該当 Step 実装

「複数 Feature を並列で進めたい」
  → /worktree-spawn certification-management,content-management,enrollment,learning
  → 各 Feature 用 worktree を作成
  → 各 worktree でターミナルを開き `claude` 起動 → /spec-generate or /feature-implement
  → 4 セッション並列稼働、各が独立コンテキスト
```

**Subagent を使わない理由**: 同セッション内 subagent は親のコンテキストを圧迫する。worktree + 別 Claude セッションのほうが真の並列で、Anthropic 公式推奨（`claude --worktree`）。

### 参考リポジトリ

| 用途 | パス |
|---|---|
| 教材 | `/Users/yotaro/pj-ct-newtext` |
| 確認テスト（ContactForm）| `/Users/yotaro/ExampleAnswer-ConfirmationTest-ContactForm` |
| 模擬案件①（BookShelf）| `/Users/yotaro/ExampleAnswer-mockcase-BookShelf` |
| ifield LMS（spec構造の参考）| `/Users/yotaro/ifield-lms` |
| COACHTECH LMS（ドメイン知識の参考）| `/Users/yotaro/lms` |

### フォルダ構成

```
ExampleAnswer-mockcase-CertifyLMS/
├── CLAUDE.md                            # 本ファイル（WHO/WHY/WHAT/HOW/MAP）
├── .claude/                             # 構築側 Claude 設定
│   ├── settings.local.json
│   └── rules/                           # Laravel 実装ルール（paths frontmatter で auto-load）
│       ├── README.md
│       ├── backend-*.md                 #   models / http / usecases / services / repositories / policies / tests / exceptions
│       └── frontend-*.md                #   blade（API契約）/ ui-foundation（サイドバー・Wave 0a/0b 指示書）/ javascript / tailwind
├── docs/                                # ★ メタ階層: 構築側のみ参照する完成形仕様（受講生には渡さない）
│   ├── steering/                        #   LMSプロダクト永続コンテキスト（Kiro流）
│   │   ├── product.md                   #     プロダクト定義（16 Feature 完成形）
│   │   ├── tech.md                      #     技術スタック・規約
│   │   └── structure.md                 #     ディレクトリ・命名規則
│   └── specs/                           #   Feature 完成形 SDD（kebab-case、番号なし、16ディレクトリ）
│       └── {name}/                      #     例: auth, certification-management, ...
│           ├── requirements.md
│           ├── design.md
│           └── tasks.md
├── 模範解答プロジェクト/                   # ★ Step 3 で先行構築（specs を満たす完成版、コード = docs/specs と整合）
│   └── （Laravel PJ）                    #   完成形フル実装
├── 提供プロジェクト/                      # ★ Step 4 で模範解答PJ から引き算変換して作成（受講生クローン用 = AssignedProject 配置範囲）
│   ├── README.md                        #   セットアップ手順（Step 4 で作成）
│   └── （Laravel PJ）                    #   完成形 - 要件分（新規機能=Bladeのみ / バグ修正=ロジック歪曲 / 改修・リファクタ=巻き戻し）
└── 関連ドキュメント/                       # 受講生・コーチ向け配布物
    ├── 要件シート_詳細度100%.md           # 全チケットの正解（コーチ用）
    ├── 要件シート_詳細度50%.md            # 受講生用（50%要件、ヒアリング誘導、AssignedProject リポにコピー）
    ├── 評価シート.md
    ├── 完全手順書_Basic.md / _Advance.md
    └── 復習教材/
```

**AssignedProject リポに配置されるもの**: `提供プロジェクト/` の中身 + `関連ドキュメント/要件シート_詳細度50%.md` 等の受講生向け配布物。**`docs/` `.claude/` `関連ドキュメント/要件シート_詳細度100%.md` は含めない**（構築側メタ情報）。
