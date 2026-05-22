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
| 1 | **既存PJ参画の経験不足** — 前2つのテストは新規構築だった | 提供プロジェクトを GitHub Template から自リポ生成し、コードリーディング前提で開発 |
| 2 | **要件ヒアリングの経験不足** — 実務では曖昧な要件が降ってくる | 30%要件 + コーチ（PM役）へのヒアリング誘導 |
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

外部 API 連携（Google Calendar OAuth, Gemini API）/ **Sanctum SPA Cookie 認証 + JS フロント**（[[notification]] の通知 API、`Sanctum::stateful()` + `auth:sanctum` + JS `fetch`、BE-FE 別オリジン構成への参画練習目的、実装は同一オリジン模擬）/ Broadcasting・WebSocket（Pusher）/ Queue・Job 非同期化 / DBインデックス / キャッシュ / Eager Loading 最適化

### チケット構成（受講生の課題）

3 種別 × 計 27 件で構成。種別ごとのサブカテゴリと件数（要件シート定義中、変動可能性あり）:

| 種別 | 件数 | サブカテゴリ別件数 |
|---|---:|---|
| **Story（機能開発）** | 12 | 新規機能の構築 4 / 既存機能の拡張 8 |
| **Bug（バグ修正）** | 8 | 認可系 2 / 計算・表示系 5 / 並行性系 1 |
| **Task（リファクタリング・既存改修）** | 7 | パフォーマンス 4 / リファクタリング 3 |

> 評価配点・S 取得条件・件数感の詳細は「Step 3（要件定義）の修了条件」を参照（本セクションでは種別構成のみを示す）。

チケット個別定義（1チケット = 1Markdownセクション）は **`関連ドキュメント/要件シート_詳細度100%/`**（コーチ用）と **`_詳細度30%/`**（受講生用）に集約。

---

## アプローチ（HOW）

### ワークフロー

**構築フロー**: 模範解答PJ を先に完成させ、**引き算で提供PJ を作る** 方式。完成形 specs/ を起点とするので整合性が高く、要件シートは「完成形のこの部分を提供時こう変える」という diff 指示として書ける。

| Step | 内容 | 主な成果物 |
|---|---|---|
| 1 | 設計：プロダクト定義 + Feature一覧 | `docs/steering/`（メタ階層、構築側のみ） |
| 2 | 設計：**Feature 完成形の SDD**（feature ごと requirements/design/tasks）| `docs/specs/{name}/`（メタ階層、構築側のみ） |
| 3 | **模範解答PJ実装** + **要件シート定義**（順序不問、両方とも `docs/specs/` を起点）| `模範解答プロジェクト/` + `関連ドキュメント/要件シート_詳細度100%/` |
| 4 | **模範解答PJ → 提供PJ 変換**（要件シートに従い引き算 / バグ化 / 巻き戻し）+ Bladeロック有効化 🔒 + 動作確認 | `提供プロジェクト/` コード + README.md |
| 5 | 残りドキュメント（30%要件 / 評価シート / 完全手順書 / 復習教材、※通しプレイ検証は手順書作成中に都度実施）| 関連ドキュメント/ 全部 |
| 6 | 配置 → AssignedProject リポへ（`docs/` `.claude/` `要件シート_詳細度100%/` は **含めない**、提供PJ + 30%要件等のみ）+ **GitHub Settings > Template repository をオン**（受講生は "Use this template" で自リポ生成）| 公開 |

### Step 4 引き算戦略（要件シートが指示する変換タイプ）

| 要件カテゴリ | 模範解答の状態 | 提供PJへの変換 |
|---|---|---|
| **新規機能開発** | 完全実装 | **Blade のみ残してロジック削除**（Controller method / Action / Service / Model 関連を削除）|
| **バグ修正** | 正しい実装 | **指定箇所をバグった実装に置換**（要件シートに具体的 diff 記述）|
| **既存機能改修・拡張** | 拡張版 | **拡張前の状態に巻き戻し**（diff スタイル指示）|
| **リファクタリング** | リファクタ後 | **リファクタ前の状態に巻き戻し**（コード重複・密結合状態に意図的に汚す）|

### Step 3（要件定義）の修了条件

要件定義（チケット集合 + 評価シート）の完成判定は以下の **3 条件** で行う。設計過程で迷ったら、この 3 条件に立ち返って判断する。

#### 1. 量 — 想定学習時間 ≒ 225 時間（BookShelf 比 1.5 倍 / 旧 250h 想定の 90%）

BookShelf（150 時間 = 純粋構築工数）に対する **相対指標** として、本模擬案件は **約 1.5 倍（≒ 225h）** を目標に置く。BookShelf の構築工数に **既存 PJ 参画特有のコードリーディング・既存パターン把握・ONBOARDING 読解** を加算すると素のままでは 250h 規模に膨らむが、**「学習負荷を抑える設計」として 90% = 225h に圧縮** する（コードリーディングは初期一回 + 各チケット冒頭で薄く繰り返す前提）。

「総工数見積もり」は **チケット着手から PR 提出までの工数合計**（既存コード読み込み + 設計判断 + 実装 + テスト + 動作確認 + PR 7 セクション記述）を指す。チケット粒度・件数は時間配分から逆算し、要件シート定義の最後で各チケットに工数を仮置き → 合計が **225h ± 10%（約 202.5〜247.5h）** に収まるかを点検する。

#### 2. 難易度 — Basic / Advance の技術範囲

| 難易度 | 範囲 |
|---|---|
| **Basic** | **教材範囲内** + **ContactForm / BookShelf で扱った技術範囲**（教材外でも既習扱い）|
| **Advance** | **指定なし** — 教材範囲外・新規技術 OK |

> Basic は「BookShelf で扱った技術を必ず再登場させる」という意味ではなく、「BookShelf までで経験済みの技術範囲の **内側** に収まる」という上限制約。題材は資格 LMS ドメインに置換され、既習パターンの **再適用判断** が求められる。
> Advance は教材範囲外の新規技術（Google Calendar OAuth / Pusher Broadcasting / Gemini API / GAS 連携 / Queue / キャッシュ / インデックス最適化 等）を AI 丸投げで詰みやすい題材として配置する。

#### 3. 評価設計 — 配点レンジ + 評価ライン

評価方式: 全体配点に対する **取得率** で判定する。**60% 以上 = A 評価 / 80% 以上 = S 評価**。

| 配点 | 範囲 | 設計意図 |
|---|---|---|
| **Basic 配点** | **60〜80%**（下限 60%・上限 80%）| 下限 60% で「教材範囲を真面目にこなせば A を保証」/ 上限 80% で「Advance なしで S 取得不可」を担保 |
| **Advance 配点** | **20〜40%**（上記の補数）| S 評価（80%）到達には Advance から最低 **(80% − Basic 配点)** の取得が必須 |

> **下限 60% の意味**: Basic 配点を 60% 未満に置くと、Basic 100% でも A 評価に届かず、「教材範囲を真面目にやれば A」という保証が崩れる。
> **上限 80% の意味**: Basic 配点を 80% 超に置くと、Basic 100% で S 評価に届いてしまい、Advance なしで S 取得可能になる（AI 耐性が崩れる）。
> 実運用は **Basic 60〜70% / Advance 30〜40%** の範囲で、Basic 工数比とのバランスで決める（工数比と配点比を完全に揃える必要はなく、Advance に時間をかけさせて AI 耐性を強制する意図で「Basic コスパ良・Advance コスパ悪」の差をつけて良い）。
> **S 取得必須 Advance チケットの指定は行わない**（配点設計だけで AI 耐性を担保する: Basic 配点 ≤ 80% の制約上、S 80% 到達には Advance チケットから最低限の取得が必須となるため、特定の必須チケットを指定する必要がない）。

**評価シートの大項目構造**（3 大項目）:

| 大項目 | 中項目 | 評価行の中身 |
|---|---|---|
| **① チケット完了** | **各チケット**（`{Feature略称}-{番号}: 概要` 形式、同 Feature を隣接配置）| 受け入れ条件 + テスト pass + PR 7 セクション完備 + 動的機能なら動作確認動画 |
| **② 横断コード品質** | Pint 整形 / 命名 / Eloquent / N+1 / Clean Architecture 準拠（既存パターン踏襲）/ 型宣言（応用）等 | 全コードベース横断の品質指標 |
| **③ 横断ドキュメント** | README 改修 / ONBOARDING 改修 / 全 PR の 7 セクション記述率 / カバレッジ目標 等 | 横断ドキュメント品質 |

評価項目の粒度は **基本的に チケット完了条件 / 受け入れ条件レベルの振る舞いベース**（実装方法は問わず「機能が要件通り動くか」を評価）。理由は ① 曖昧要件をヒアリングで詳細化する **実務フローの再現**（コードレベルで縛ると設計判断の余地が失われる）/ ② コードレベル評価は AI 丸投げで満たしやすく **AI 耐性が弱い**（振る舞いベース + 動作確認動画 + PR 記述で実機証明を強制）/ ③ Certify LMS は既存パターン踏襲が前提なので **コードレベル採点は二重化** になる / ④ Pro 生に求められる **実装方法の判断力**（複数の選択肢から状況に合うものを選ぶ力）を養うため。

ただし、大項目 ② 横断コード品質 だけは振る舞いに依らない **最低限の品質安全網**（Pint / 命名 / N+1 / Clean Architecture からの明らかな逸脱がないか）として配置する。「**明らかに不適切でないことを担保するチェックリスト**」レベルにとどめ、配点を抑えてここだけで A/S が決まらないバランスにする。

「テスト」「PR 7 セクション」「動作確認動画」は **各チケットの完了条件として ① に内包** する（独立大項目化しない、1 チケット = 1 PR で粒度が揃うため）。

---

#### 補足: AI 耐性と PR 品質設計

修了条件には直接含めないが、要件シート設計・採点運用時の前提として記しておく:

- **PR 7 セクション必須**（関連チケット / 調査内容 / 原因分析・設計判断 / 実装内容 / 自動テスト / 動作確認 / レビュー観点・自己評価）。詳細は `docs/steering/tech.md` 参照
- **動作確認**: 動的機能（タイマー / 状態遷移 / リアルタイム / モーダル / 非同期更新）は **動画必須**、静的 UI はスクショ、バグ修正は修正前後比較
- これらは AI 出力をそのままコピペできない設計の中核。各チケットで「調査内容」「動作確認」が記述可能なよう、要件設計時に意識する
- **100% 版要件シートはコーチが受講生のヒアリングに即答できる粒度で記述** する。チケットを Markdown セクション化（`## チケット名`）することで、コーチがファイル内検索で即答可能な構造になる（30% 版はヒアリング誘導のため、受け入れ条件・実装方針を抽象化変換）

### 成果物

| # | 成果物 | 説明 |
|---|---|---|
| 1 | 提供プロジェクト | **受講生 Template 用既存PJ**（GitHub Template Repository、受講生は "Use this template" で自リポ生成）。全Blade完成 / 実装済み機能（バグ込み）/ 未実装機能はBladeのみ + **`README.md`**（プロジェクト概要・セットアップ手順・使用技術・**自リポ生成手順（Use this template）・PR 運用フロー（feature/xxx → basic、1 チケット = 1 PR、PR 7 セクション必須）**・提出方法）+ **`ONBOARDING.md`**（既存コードリーディングガイド・主要ドメインモデル・既存パターン典型例の所在）。**完成形仕様（docs/）は含まない**（受講生は要件シート + 提供PJコード + ONBOARDING + コーチへのヒアリングで詳細化）|
| 2 | 模範解答プロジェクト | 提供版コピー + 全チケット実装後の完成版（Basic/Advance両ブランチ）|
| 3 | 要件シート | **100%版（コーチ用）/ 30%版（受講生用）、4 ファイル構成**（`01_概要.md` + `10_Story.md` + `20_Bug.md` + `30_Task.md`）。1 チケット = 1 Markdown セクション。100%版はコーチが受講生のヒアリングに即答できる粒度で記述。「テーブル仕様 / API 仕様 / バリデーション / 画面遷移」等の詳細設計書は **含まない**（提供 PJ コード + ONBOARDING の責務）|
| 4 | 評価シート | **3 大項目構造**（① チケット完了 / ② 横断コード品質 / ③ 横断ドキュメント）。配点 Basic 60% / Advance 40%、受け入れ条件レベルの振る舞いベースで評価 |
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
- **詳細仕様の所在分離** — **テーブル仕様 / API 仕様 / バリデーション / 画面遷移** 等の詳細設計書は **要件シートに含めない**。要件シートは抽象チケットのみで、受講生は既存コード（提供 PJ）+ `ONBOARDING.md` + コーチへのヒアリングで詳細化する（**実務 PJ 参画体験の核**。BookShelf 等の新規構築型 PJ と異なる Certify LMS 固有のコンセプト）

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
| **Feature 実装フェーズ** | 18 Feature を Wave 0b の共通基盤を利用しつつ実装。**進行順・並列度は進めつつ決定**。原則として `auth` / `user-management` を最初に直列で実装（後続 Feature が依存）、それ以降は独立 Feature を `worktree-spawn` で並列、依存ある Feature は順次 | Claude Code（主セッション + worktree並列）|

### Feature 一覧（18個）

1. `auth` / 2. `user-management` / 3. `certification-management` / 4. `content-management` / 5. `enrollment` / 6. `learning` / 7. `quiz-answering` / 8. `mock-exam` / 9. `mentoring` / 10. `chat` / 11. `qa-board` / 12. `notification` / 13. `dashboard` / 14. `ai-chat` / 15. `settings-profile` / 16. `plan-management` / 17. `meeting-quota` / 18. `default-enrollment`

依存関係の目安:
- **後続の前提**: `auth`, `user-management`, `plan-management`（最初に実装）
- **独立 Feature**（並列向き）: `certification-management`, `content-management`, `enrollment`, `learning`, `quiz-answering`, `mock-exam`, `chat`, `qa-board`, `settings-profile`, `ai-chat`, `meeting-quota`, `default-enrollment`
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
| AssignedProject-mockcase-CertifyLMS | **受講生 Template 用**（GitHub Template Repository）| ✅ | `basic`（メイン）/ `advance`（basic から分岐、Advance 純粋追加）|

**受講生の PR 運用フロー**:

- 受講生は AssignedProject の "Use this template" ボタンで **自分の GitHub リポを生成**（`basic` / `advance` 両ブランチをコピー）。`git clone` ではない（clone だと PR 送信先リポがなくなり評価対象 PR が成立しない）
- 自リポ内で各チケット = `feature/{ticket-id}` ブランチを切り、`basic`（または Advance フェーズでは `advance`）に向けて PR を提出。1 チケット = 1 PR、PR 7 セクション必須
- Template から生成された自リポなので fork ラベルが付かず、**卒業後はそのまま Pro 生のポートフォリオ** として残せる

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

### 教材・模試 執筆

学習コンテンツは 2 系統で執筆し、Markdown + YAML で `模範解答プロジェクト/database/seeders/` 配下にファイル配置する:

| 系統 | 配置先 | Seeder | スキーマ |
|---|---|---|---|
| **教材** (Part > Chapter > Section + Section 紐づき演習問題) | `database/seeders/contents/{資格スラッグ}/` | `ContentMarkdownSeeder` | Markdown フロントマター + `.questions.yaml` |
| **模試** (MockExam フラット + 問題セット) | `database/seeders/mock-exams/{資格スラッグ}/` | `MockExamYamlSeeder` | 1 ファイル = 1 模試の YAML |

- **執筆規約・スキーマ・新資格追加手順**: `docs/steering/content-authoring.md` を参照(教材 = 第 1 章 / 模試 = 第 2 章、ディレクトリ命名 / フロントマター / cascade visibility / 受講生向け文言規約 / トラブルシュート)
- **資格本体 + 出題分野マスタは別 Seeder**: コンテンツを投入する前に `CertificationSeeder` で資格本体を、同 Seeder か `CertificationCategorySeeder` で `QuestionCategory` マスタを先に作る(教材の `.questions.yaml` も模試 YAML も同じマスタを参照)
- **取り込み**: `sail artisan migrate:fresh --seed` で一括取り込み(`DatabaseSeeder` で `CertificationSeeder` → `ContentMarkdownSeeder` → `MockExamYamlSeeder` の順)

コンテンツデータ本体(`*.md` / `*.questions.yaml` / `_meta.yaml` / 模試 `*.yaml`)は `模範解答プロジェクト/` 配下にあるため Step 4 引き算変換後の提供プロジェクトにも残り、受講生がローカルで `migrate:fresh --seed` を走らせると同じ教材・模試が入る。執筆規約 `docs/steering/content-authoring.md` は構築側メタ階層に属するため受講生には渡らない。

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
│   │   ├── product.md                   #     プロダクト定義（18 Feature 完成形）
│   │   ├── tech.md                      #     技術スタック・規約
│   │   └── structure.md                 #     ディレクトリ・命名規則
│   └── specs/                           #   Feature 完成形 SDD（kebab-case、番号なし、18ディレクトリ）
│       └── {name}/                      #     例: auth, certification-management, ...
│           ├── requirements.md
│           ├── design.md
│           └── tasks.md
├── 模範解答プロジェクト/                   # ★ Step 3 で先行構築（specs を満たす完成版、コード = docs/specs と整合）
│   └── （Laravel PJ）                    #   完成形フル実装
├── 提供プロジェクト/                      # ★ Step 4 で模範解答PJ から引き算変換して作成（受講生 Template 用 = AssignedProject 配置範囲、GitHub Template Repository として公開）
│   ├── README.md                        #   プロジェクト概要 / セットアップ手順 / 使用技術 / 開発環境 URL / 提出方法
│   ├── ONBOARDING.md                    #   既存コードリーディングガイド: プロジェクト構造 / 主要ドメインモデル / ER 図概要 / 既存パターン典型例の所在
│   └── （Laravel PJ）                    #   完成形 - 要件分（新規機能=Bladeのみ / バグ修正=ロジック歪曲 / 改修・リファクタ=巻き戻し）
└── 関連ドキュメント/                       # 受講生・コーチ向け配布物
    ├── 要件シート_詳細度100%/             # コーチ用（4 ファイル構成、1 チケット = 1 Markdown セクション）
    │   ├── 01_概要.md                     #   ターム内容 + 開発プロセス + 環境構築手順
    │   ├── 10_Story.md                    #   機能開発チケット集合
    │   ├── 20_Bug.md                      #   バグ修正チケット集合
    │   └── 30_Task.md                     #   リファクタリング・既存改修チケット集合
    ├── 要件シート_詳細度30%/              # 受講生用（上記 4 ファイル × 抽象化変換、AssignedProject リポにコピー）
    ├── 評価シート.md                       # 3 大項目構造（チケット完了 / 横断コード品質 / 横断ドキュメント）
    ├── 完全手順書_Basic.md / _Advance.md
    └── 復習教材/
```

**AssignedProject リポに配置されるもの**: `提供プロジェクト/` の中身 + `関連ドキュメント/要件シート_詳細度30%/` 等の受講生向け配布物。**`docs/` `.claude/` `関連ドキュメント/要件シート_詳細度100%/` は含めない**（構築側メタ情報）。
