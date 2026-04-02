# ManaBase — 模擬案件②

> COACHTECHの受講生が「既存プロジェクトへの参画」を体験するための模擬案件。
> 本リポジトリは模範解答コード・提供プロジェクト・関連ドキュメントを一元管理する唯一の真実。

## ペルソナ（WHO）

COACHTECHの受講生。以下を修了済みの状態でこの模擬案件に取り組む:

- **教材**: HTML/CSS → PHP → Laravel基礎〜応用 → API設計 → テスト（約15-19週）
- **確認テスト**: お問い合わせフォーム（ContactForm）をゼロから構築
- **模擬案件①**: 書籍レビューアプリ（BookShelf）をゼロから構築

### 前提知識

- **バックエンド**: PHP 8.2, Laravel 10（MVC・Eloquent・認証・ミドルウェア・テスト・API基礎）
- **データベース**: MySQL 8.0, SQL（CRUD・JOIN・正規化・マイグレーション・シーダー）
- **ORM**: Eloquent（リレーション・Eager Loading・N+1対策・withCount）
- **認証・認可**: Fortify（認証）, Policy / Gate（認可）
- **バリデーション**: FormRequest, カスタムメッセージ
- **テスト**: PHPUnit（Feature / Unit）, RefreshDatabase, actingAs
- **フロントエンド**: Blade, Tailwind CSS, Alpine.js の基本
- **開発環境**: Docker / Sail, Git / GitHub（ブランチ・PR・Issue駆動開発）

### 教材範囲外

詳細な判定基準は `spec/overview.md` で定義。主な範囲外技術:

- **API認証**: Sanctum（概念のみ教材でカバー、実装は範囲外）
- **外部API連携**: Gemini等の実装
- **リレーション応用**: ポリモーフィック, Has-many-through
- **パフォーマンス**: DBインデックス戦略, キャッシュ, スロークエリ最適化
- **テスト応用**: モッキング・スタブ, ブラウザテスト（Dusk）
- **その他**: キュー/ジョブ, WebSocket/Broadcasting, ファイルストレージ, メール送信, OAuth, レート制限

### 技術スタック

Laravel 10 / PHP 8.2 / MySQL 8.0 / Docker・Sail / Tailwind CSS / Alpine.js / Fortify

---

## コンセプト（WHY）

確認テストと模擬案件①は「ゼロから新規作成」する形式だった。しかし実務では既存プロジェクトに参画し、他人のコードを読み解きながら開発を進めることがほとんどである。本模擬案件ではこの乖離を埋める。

受講生にはバグや未実装箇所を含む既存プロジェクトを提供する。コードリーディングを前提に「バグ修正」「機能開発」「リファクタリング」を体験させ、READMEのコーディング規約と既存コードのパターンに倣って実装させる。Blade（フロント）は全て提供済みで、受講生はBladeの `action` 属性や変数名を読んでバックエンドの仕様を逆算する。

また、あえて詳細度50%の要件を渡し、コーチ（PM）へのヒアリングで仕様を明確にするプロセスも組み込む。完璧な要件が降ってくることは実務では稀であり、エンジニア自ら要件を引き出す力を鍛える。

---

## ゴール（WHAT）

この模擬案件の成果物:

1. **提供プロジェクト** — 受講生がクローンする既存プロジェクト（バグ込み・一部未実装・Blade全提供）
2. **模範解答コード** — 全チケット完了後の完成版（Basic / Advance）
3. **要件定義書** — 100%版（コーチ用）と 50%版（受講生用・ヒアリング前提）。プロジェクト概要1シート + チケット要件1シートの2シート構成
4. **評価項目シート** — 採点基準
5. **完全手順書** — Basic / Advance それぞれの全実装手順
6. **復習教材** — Basic / Advance それぞれの学習振り返り教材

### テーマ

**ManaBase** — オンライン学習プラットフォーム（LMS）。3ロール（admin / coach / student）、コンテンツ階層（Course → Chapter → Section）。受講生にとって馴染みのあるドメイン。

### チケット構成

| カテゴリ | 種類 | 内容 |
|---------|------|------|
| バグ修正 | データの不正 | 出力結果が期待と異なる（集計誤り、N+1、データ起因の0除算等） |
| | アクセス制御の不備 | 見えてはいけないものが見える・操作できる（Policy未適用、認証漏れ等） |
| | 機能の不全 | 動かない・エラーになる（バリデーション不備、状態遷移条件の不備等） |
| 機能開発 | 既存機能の修正・拡張 | 既存のモデル・コントローラを修正（新規モデルは作らない） |
| | 新規機能の構築 | 新規モデル・コントローラ・ルートをゼロから作成（テスト必須） |
| リファクタリング | コード構造 | 可読性・保守性の改善（Fat Controller分割、Scope抽出、定数化等） |
| | パフォーマンス | 実行効率の改善（Advance: インデックス、キャッシュ等） |

- 各カテゴリ内に個別チケットがあり、チケット単位でブランチを切りPRを出す
- **Basic**: 教材の習得範囲内で解けるチケット群
- **Advance**: 教材範囲外の技術を用いるチケット群（Gemini APIチャットボット等）
- Advance は Basic完成版のコードを一切変更せずに追加できなければならない（純粋追加性）

各チケットの詳細は `spec/tickets.md` で定義。

---

## 構築アプローチ（HOW）

### 設計原則

1. **Bladeが仕様書** — Bladeの `action`, route名, `$変数名` から受講生が仕様を逆算する
2. **新規機能は自己完結ページ** — 新規機能のBladeは独立ページとして設計し、既存ページからのデータ参照を持たない。ナビのリンクのみ `Route::has()` で制御し、ルート定義時に自動表示される
3. **コードリーディングは自然に組み込む** — 独立チケットにせず、各チケットの前提として要求する
4. **お手本が存在する** — サンプルテストとお手本機能を含め、パターンを示す
5. **コーディング規約に従う体験** — READMEに規約を記載し、既存コードのパターンに従って実装させる
6. **AIだけでは解けない設計** — チケットは意図的に曖昧にし、受講生自身による問題特定・ヒアリング・既存コード理解を要求する。PRには調査内容と判断理由の記述を必須とする
7. **spec/ が唯一の真実** — 仕様変更は必ず spec/ を先に更新してから実装に反映する

### 構築ワークフロー

| Step | 内容 | 成果物 |
|------|------|--------|
| 1 | 設計 | spec/ 一式 |
| 2 | 仮PJ構築（全機能動作する完全版） | answer/ に全機能正常動作するPJ |
| 3 | Blade確定・ロック 🔒 | Blade確定 |
| 4 | 提供版構築（answer/ → provided/ にコピー → tickets.md に従い劣化） | provided/ 完成 |
| 5 | 完全手順書作成 | docs/guide-basic.md, guide-advance.md |
| 6 | 通しプレイ検証（provided/ → answer/ を再構築） | answer/ 確定 + 手順書修正 |
| 7 | ドキュメント作成 | docs/ 残り（evaluation → requirements → review） |
| 8 | 配置 | AssignedProjectリポへ展開 |

### リポジトリ構成

| リポジトリ | 用途 | 公開 |
|-----------|------|------|
| ExampleAnswer-mockcase-ManaBase（本リポ） | 全成果物の一元管理 | ❌ |
| AssignedProject-mockcase-ManaBase | 受講生がクローンするPJ | ✅ |

Blade専用リポジトリは設けない（既存PJクローン形式のため不要）。

### ブランチ運用

- `basic`: メインブランチ。Basic完成版
- `advance`: basicから分岐。Basic + Advance実装のみ追加

---

## プロジェクトマップ（MAP）

### 参考リポジトリ

| 用途 | ローカルパス |
|------|------------|
| 教材 | `/Users/yotaro/pj-ct-newtext` |
| 確認テスト | `/Users/yotaro/ExampleAnswer-ConfirmationTest-ContactForm` |
| BookShelf（模擬案件①） | `/Users/yotaro/ExampleAnswer-mockcase-BookShelf` |
| CourseHub（簡素すぎの反面教師） | `/Users/yotaro/pro-cc-coursehub` |
| ifield LMS（機能・spec構造の参考） | `/Users/yotaro/ifield-lms` |
| COACHTECH LMS（ドメイン知識の参考） | `/Users/yotaro/lms` |

### フォルダ構成

```
ExampleAnswer-mockcase-ManaBase/
├── CLAUDE.md                    # 本ファイル（哲学: WHO/WHY/WHAT/HOW/MAP）
├── spec/                        # 設計層（構築の唯一の入力）
│   ├── overview.md              #   LMS全体像 + 技術スタック + 規約 + チケット全体像
│   ├── features/                #   機能単位のSDD的仕様（3ファイル/feature）
│   │   └── {feature-name}/
│   │       ├── requirements.md  #     受け入れ基準（何を満たすべきか）
│   │       ├── design.md        #     技術設計（どう実装すべきか）
│   │       └── tasks.md         #     実装タスク（どの順で何を作るか）
│   ├── database.md              #   横断: 全テーブル定義 + シーダー仕様
│   ├── routes.md                #   横断: 全ルート定義 + Bladeマッピング
│   └── tickets.md               #   全チケット一覧（受講生向け + 劣化アクション）
├── provided/                    # 提供プロジェクト（バグ込み・一部未実装・Blade全提供）
│   └── ...                      #   answer/ から派生し劣化したLaravel PJ
├── answer/                      # 模範解答（仮PJ構築 → 通しプレイで確定）
│   └── ...                      #   全機能が正常動作するLaravel PJ
├── docs/                        # ドキュメント成果物
│   ├── requirements-100.md      #   要件定義書（100%・コーチ用・2シート構成）
│   ├── requirements-50.md       #   要件定義書（50%・受講生用・2シート構成）
│   ├── evaluation.md            #   評価項目シート
│   ├── guide-basic.md           #   完全手順書（Basic）
│   ├── guide-advance.md         #   完全手順書（Advance）
│   ├── review-basic.md          #   復習教材（Basic）
│   └── review-advance.md        #   復習教材（Advance）
├── .gitignore
└── memo/                        # 旧資料（gitignore済み）
```

### spec/ の構造

- **overview.md**: LMS全体像、技術スタック、コーディング規約、チケット全体像（種類・配分・機能領域との対応）
- **features/**: 機能単位のSDD的仕様。全featureで requirements.md + design.md + tasks.md の3ファイルを統一的に作成
- **database.md**: 全テーブル定義（カラム・型・制約）+ シーダー仕様。features/ のDB面を1箇所に集約
- **routes.md**: 全ルート定義 + Bladeマッピング。features/ のルート面を1箇所に集約
- **tickets.md**: 全チケット一覧。受講生向け記述 + 劣化アクションを統合

### spec/ ファイルの作成ルール

- 必要になった時点で作成する（空ファイルを事前に作らない）
- 各ファイルは独立して読めるようにする
- features/ の仕様が仮PJ構築の入力となり、全成果物の派生元となる（SDD的アプローチ）
- 作成順: overview.md → features/ → database.md → routes.md → tickets.md
