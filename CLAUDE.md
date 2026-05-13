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

- **Sanctum 公開API**（BookShelf応用で経験）
- **メール送信**（Mail channel）
- **通知**（Notification + Database channel + Mail channel）

#### Advance 範囲（教材外、本模擬案件で初出 / 深掘り）

外部API連携（Google Calendar OAuth, Gemini API）/ Sanctum SPA認証 / Broadcasting・WebSocket（Pusher）/ Queue・Job 非同期化 / DBインデックス / キャッシュ / Eager Loading最適化

### チケット構成（受講生の課題）

3カテゴリ × 8種類:

| カテゴリ | 種類 |
|---|---|
| バグ修正 | データの不正 / アクセス制御の不備 / 機能の不全 |
| 機能開発 | 既存機能の修正 / 既存機能の拡張 / 新規機能の構築（テスト必須）|
| リファクタリング | コード構造 / パフォーマンス |

**配分（仮、ワークフロー Step 3 で確定）**: Basic 15+α / Advance 6+α = 21+
**評価配点**: Basic 70% / Advance 30%
**S取得必須**: Advance 内に3チケット必須化（AI丸投げで埋まらない構成）
- 候補: 公開API SPA（Sanctum）/ 面談予約UI(Google Calendar OAuth）/ リアルタイム通知UI（Broadcasting）

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

### Step 3（要件定義）の4観点

要件シート定義時、各チケットを以下の **4観点** で点検する。網羅性チェックリストとしても、過不足判定の枠組みとしても使う。

| 観点 | 何を見るか | チェック項目 |
|---|---|---|
| **① 量** | チケット規模と総量 | BookShelf 相対のボリューム / Basic 15+α・Advance 6+α 枠内に収まるか |
| **② 質** | チケットの教育的密度 | 難易度 / コードリーディング負荷（横断ファイル数）/ 学習効果の連鎖（前後チケットへの波及・他チケットの理解を助けるか）|
| **③ 構成** | チケット集合の構造 | 評価項目との対応 / 配点（Basic 70% / Advance 30%、S取得 80% には Advance 内 3チケット必須）/ 3カテゴリ比率（バグ / 機能開発 / リファクタ）/ チケット間依存関係（前後関係 / 並列可能性）|
| **④ AI耐性** | AI 丸投げ排除設計 | AI 出力をそのまま提出して S が取れない構造になっているか（下記の具体手段で点検）|

#### ④ AI耐性の具体的な仕掛け

| 手段 | 効果 |
|---|---|
| OAuth・外部API依存（Google Calendar / Pusher / Gemini） | ドキュメント理解 + 環境設定 + トークン管理の壁 |
| 複数ファイル横断改修 | AI コンテキスト窓を超える / 全体把握が必要 |
| 既存パターン準拠強制（「既存テストを参考に」等） | AI にプロジェクト固有コンテキストを伝達困難 |
| 動作確認 + テスト追加必須 | コードのみでは正解判定不可、ブラウザでの挙動確認が必要 |
| **PR 7セクション記述必須**（関連チケット / 調査 / 原因分析 / 実装 / 自動テスト / **動作確認（手順 + スクショ or 動画、動的機能は動画必須）** / レビュー観点、`tech.md` 参照）| AI 出力をそのままコピペできない / 動作確認は実機操作が必要で AI 生成不可 |
| ドメイン知識依存（資格LMS固有判断） | 模擬試験の分野配分ロジック / 弱点分析の集計式 等、ドメイン理解が必須 |

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
| [frontend-design プラグイン](https://github.com/anthropics/claude-code/blob/main/plugins/frontend-design/skills/frontend-design/SKILL.md) | Blade UI品質向上（AIスロップ回避）| Step 3（模範解答PJ実装中の Blade 作成）|
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

### Wave 分配（並列実装のグループ化）

| Wave | 性質 | 内容 | 担当 | 並列度 |
|---|---|---|---|---|
| **Wave 0a** Claude Design（共通UI設計） | デザイン基盤 | Design System（カラー / フォント / スペーシング / Button / Form / Card / Modal / Alert / Nav）+ Hero Screens 4-6枚（受講生Dash / mock-exam受験 / 弱点ヒートマップ / qa-board / コーチDash / 管理者Dash） | **User**（Claude Design Web UI、別環境）| 直列 |
| **Wave 0b** ハンドオフ → 共通UI実装 | Laravel 基盤 + 共通 Blade | Wave 0a のハンドオフコードを受け取り、Laravel 初期セットアップ + Sanctum/Fortify + 共通 Model (User/UserStatusLog) + `resources/views/layouts/` + `resources/views/components/` (Button/Form/Card/Modal/Alert/Nav) を Design System 準拠で実装 + tailwind.config.js / Vite 設定 | Claude Code（主セッション直接） | 直列 |
| **Wave 1** 基盤 Feature（直列） | 認証 + ユーザー管理 | `auth` / `user-management`（Wave 0b の共通基盤を利用）| Claude Code（主セッション） | 1 |
| **Wave 2** 独立 Feature（並列） | 学習系・コンテンツ系 | `certification-management` / `content-management` / `enrollment` / `learning` / `quiz-answering` / `mock-exam` | Claude Code（worktree並列） | 4-6 |
| **Wave 3** 横串（半並列） | 通信・補助・横断 | `mentoring` / `chat` / `qa-board` / `notification` / `dashboard` / `settings-profile` / `public-api` / `ai-chat` | Claude Code（worktree並列） | 2-3 |

### Wave 1 で確定する基盤資産（Wave 2 以降は編集禁止）

- `composer.json` / `package.json`（全依存を一括追加してフリーズ）
- `bootstrap/providers.php`（Service Provider は Package Auto-Discovery 利用）
- `routes/web.php`（基盤 Feature のルート登録）
- `routes/api.php`（Sanctum 公開API ベース）
- 共通 Model（`User`, `UserStatusLog`）+ Migration

### 並列性の物理保証

- **worktree**: `worktree-spawn` Skill で Feature ごとに独立 worktree 作成、各 worktree で別 Claude セッション
- **DB**: 各 worktree に独立 SQLite（`模範解答プロジェクト/database/database_{name}.sqlite`）
- **依存**: composer / npm は Wave 1 でフリーズ、worktree では編集禁止
- **routes**: 各 worktree で `routes/web.php` を編集、マージ時に標準的な Git 手動衝突解決

### Step 2（specs 展開）の進行順

| Wave | 対象 | 進め方 |
|---|---|---|
| 1 | auth, user-management | 主セッションで `/spec-generate` を直列実行 |
| 2-a | certification-management, content-management, enrollment, learning | `/worktree-spawn cert-mgmt,content-mgmt,enrollment,learning` → 4 worktree 並列 |
| 2-b | quiz-answering, mock-exam | `/worktree-spawn quiz-answering,mock-exam` → 2 worktree 並列 |
| 3-a | mentoring, chat, qa-board, notification | `/worktree-spawn ...` → 4 worktree 並列 |
| 3-b | dashboard, settings-profile, public-api, ai-chat | `/worktree-spawn ...` → 4 worktree 並列 |

### リポジトリ・ブランチ

| リポ | 用途 | 公開 |
|---|---|---|
| ExampleAnswer-mockcase-CertifyLMS（本リポ）| 全成果物一元管理 | ❌ |
| AssignedProject-mockcase-CertifyLMS | 受講生クローン用 | ✅ |

- `basic`: Basic完成版（メイン）
- `advance`: basicから分岐、Advance純粋追加

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

「Wave 2 を並列で進めたい」
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
│       └── frontend-*.md                #   blade / javascript / tailwind
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
