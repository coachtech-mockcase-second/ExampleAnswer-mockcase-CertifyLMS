# ExampleAnswer-mockcase-CertifyLMS

> COACHTECH **模擬案件②（Certify LMS）** の構築側メタリポジトリ。

マルチ資格対応の資格取得 LMS「**Certify LMS**」を題材とする COACHTECH 模擬案件② の **唯一の真実源（Single Source of Truth）**。模擬案件のプロダクト仕様・模範解答実装・受講生配布物・採点ロジックを一元管理する。受講生 Template として公開される `AssignedProject-mockcase-CertifyLMS` の上流に位置する。

> 受講生は公開リポ `AssignedProject-mockcase-CertifyLMS` を GitHub の "Use this template" で自リポ生成して作業する。

---

## 模擬案件② の設計意図

模擬案件② は COACHTECH カリキュラム最終評価フェーズ（Pro 生認定）の関門。新規構築型の課題（確認テスト ContactForm / 模擬案件① BookShelf）では扱えなかった以下 3 課題への解として設計される。

| # | 受講生の課題 | 本案件のアプローチ |
|---|---|---|
| 1 | 既存 PJ 参画の経験不足 | GitHub Template + コードリーディング前提の構成 |
| 2 | 要件ヒアリングの経験不足 | 30% 要件 + コーチ（PM 役）ヒアリング誘導 |
| 3 | AI 丸投げによる理解なき実装 | チケット曖昧化 + PR 7 セクション必須 + Basic 配点 ≤ 80% で Advance 必須化 |

設計哲学・全体像は [`CLAUDE.md`](./CLAUDE.md) を参照。

---

## プロダクト「Certify LMS」

**プラン受講型** の資格取得 LMS。受講生は LMS 外でプランを購入し、admin の招待でログイン → プラン期間内に複数資格を並行学習できる。

| 項目 | 内容 |
|---|---|
| ロール | 受講生（student） / コーチ（coach） / 管理者（admin） |
| ドメイン中核 | 目標受験日 / 合格点ゴール / 問題演習中心の学習設計 / 苦手分野克服戦略 |
| Feature 数 | 18 |
| 想定総工数 | 225 時間 ± 10%（BookShelf 比 1.5 倍） |
| 評価配点 | Basic 60〜80% / Advance 20〜40%（S 評価 = 取得率 80% 以上、到達には Advance 必須） |

機能・データモデル・ロール権限・UX フローの詳細は [`docs/steering/product.md`](./docs/steering/product.md)。

---

## ディレクトリ構成

```
ExampleAnswer-mockcase-CertifyLMS/
├── CLAUDE.md                      # プロジェクトのメタ哲学（WHO/WHY/WHAT/HOW/MAP）
├── .claude/                       # 構築側 Claude Code 設定（Skills / rules）
├── docs/                          # メタ階層: 構築側のみ参照する完成形仕様
│   ├── steering/                  #   product / tech / structure / content-authoring
│   └── specs/                     #   Feature 完成形 SDD × 18 ディレクトリ
├── 模範解答プロジェクト/            # 完成版 Laravel PJ（全チケット実装後の状態、specs と整合）
├── 提供プロジェクト/                # 模範解答 PJ から引き算変換した受講生 Template 用 Laravel PJ
└── 関連ドキュメント/                # 受講生・コーチ向け配布物
    ├── 要件シート_詳細度100%/        #   コーチ用詳細要件（SSoT、評価シート・30% 版の派生元）
    ├── 要件シート_詳細度30%/         #   受講生用抽象化要件（100% から派生、後工程で生成）
    ├── 評価シート.md                 #   採点シート（100% から派生、後工程で生成）
    ├── 完全手順書_{Basic,Advance}.md
    └── 復習教材/
```

**AssignedProject（受講生 Template）に配置されるのは `提供プロジェクト/` 配下 + 受講生向け関連ドキュメント のみ**。`docs/` `.claude/` `要件シート_詳細度100%/` などの構築側メタ情報は公開リポに渡らない。

---

## 構築ワークフロー（6 Step）

模範解答 PJ を先行構築 → 要件シートに従って引き算で提供 PJ を作る **引き算方式**。

- [x] **Step 1** — steering 作成（product / tech / structure / content-authoring）+ Feature 18 個確定
- [x] **Step 2** — 全 Feature × spec 3 点セット（requirements / design / tasks）生成
- [x] **Step 3a** — Wave 0a / 0b: Claude Design ハンドオフ → Laravel 初期化 + Sanctum/Fortify + 共通 UI 基盤
- [x] **Step 3b** — 模範解答 PJ Feature 実装（18 Feature 完了）
- [ ] **Step 3c** — 要件シート 100% 版詳細化（全 40 チケット、進行中）
- [ ] **Step 4** — 模範解答 PJ → 提供 PJ 引き算変換 + Blade ロック + 動作確認
- [ ] **Step 5** — 残ドキュメント生成（30% 要件 / 評価シート / 完全手順書 / 復習教材）
- [ ] **Step 6** — AssignedProject リポへの配置 + GitHub Template Repository 化

要件シートの詳細進捗は [`関連ドキュメント/要件シート_詳細度100%/README.md`](./関連ドキュメント/要件シート_詳細度100%/README.md) を参照。

---

## チケット構成（全 40 件）

| 種別 | 内容 | Basic | Advance | 計 |
|---|---|---:|---:|---:|
| **Story** | 新規機能の構築 / 既存機能の拡張 | 9 | 5 | 14 |
| **Bug** | 仕込み済バグの解消 | 16 | 3 | 19 |
| **Task** | リファクタ・パフォーマンス改善 | 3 | 4 | 7 |
| **計** | | **28** | **12** | **40** |

1 チケット = 1 PR、PR 記述は **7 セクション必須**（関連チケット / 調査内容 / 原因分析・設計判断 / 実装内容 / 自動テスト / 動作確認 / レビュー観点・自己評価）。動的機能の動作確認は動画必須。

---

## 主要技術スタック

| 領域 | 採用 |
|---|---|
| Backend | PHP 8.2 / Laravel 10 / MySQL 8.0 / Eloquent |
| Frontend | Blade + Tailwind CSS + 素の JavaScript（Vite ビルド） |
| 認証 | Laravel Fortify（Basic） / Sanctum SPA Cookie 認証（Advance） |
| 非同期・リアルタイム | Queue / Job / Broadcasting + Pusher（Advance） |
| 外部 API | Google Calendar OAuth / Gemini API / Stripe（Advance） |
| 開発環境 | Docker / Laravel Sail |

技術選定の意図・規約・PR 7 セクションの詳細は [`docs/steering/tech.md`](./docs/steering/tech.md)。

---

## 主要ドキュメント

| ドキュメント | 内容 |
|---|---|
| [`CLAUDE.md`](./CLAUDE.md) | プロジェクトのメタ哲学（WHO / WHY / WHAT / HOW / MAP） |
| [`docs/steering/product.md`](./docs/steering/product.md) | Certify LMS プロダクト定義（事業モデル / ドメイン構造 / 全 Feature） |
| [`docs/steering/tech.md`](./docs/steering/tech.md) | 技術スタック / Clean Architecture 方針 / PR 規約 |
| [`docs/steering/structure.md`](./docs/steering/structure.md) | Laravel ディレクトリ構成 / 命名規則 |
| [`docs/steering/content-authoring.md`](./docs/steering/content-authoring.md) | 教材・模試の執筆規約 |
| [`docs/specs/{feature}/`](./docs/specs/) | Feature 完成形 SDD（requirements / design / tasks）× 18 |
| [`.claude/rules/README.md`](./.claude/rules/README.md) | Laravel 実装ルール集（Claude 行動指示） |
| [`関連ドキュメント/要件シート_詳細度100%/README.md`](./関連ドキュメント/要件シート_詳細度100%/README.md) | 要件シート規約 + 詳細化進捗トラッカー |

---

## 関連リポジトリ

| リポジトリ | 用途 | 公開状態 |
|---|---|---|
| **`ExampleAnswer-mockcase-CertifyLMS`**（本リポ） | 全成果物一元管理（構築側メタリポ） | 非公開 |
| `AssignedProject-mockcase-CertifyLMS` | 受講生 Template 用（GitHub Template Repository） | 公開予定（Step 6 で配置） |
