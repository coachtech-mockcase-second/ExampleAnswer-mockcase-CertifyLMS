# ManaBase — 模擬案件③

## WHO

### プロジェクトチーム

- **作成者**: yotaro（COACHTECHカリキュラム設計）
- **構築支援**: Claude Code

### 対象者（受講生ペルソナ）

COACHTECHの受講生。以下を修了済みの状態でこの模擬案件に取り組む:

- **教材**: HTML/CSS → PHP → Laravel基礎〜応用 → API設計 → テスト（約15-19週）
- **確認テスト**: お問い合わせフォーム（ContactForm）をゼロから構築
- **模擬案件①**: 書籍レビューアプリ（BookShelf）をゼロから構築

**習得済みスキル**: Laravel CRUD, Eloquent（リレーション・Eager Loading）, Fortify認証, Policy認可, FormRequest, PHPUnit, REST API基礎, Docker/Sail, Git/GitHub

**応用（教材範囲外）**: Sanctum API認証, 外部API連携, ポリモーフィックリレーション, DBインデックス戦略, キャッシュ, キュー/ジョブ 等

---

## WHY

### 位置づけ

| 課題 | 形式 | 特徴 |
|------|------|------|
| 確認テスト | ゼロから新規作成 | 単機能・シンプル |
| 模擬案件① BookShelf | ゼロから新規作成 | 複数テーブル・基本CRUD |
| **模擬案件③ ManaBase（本PJ）** | **既存プロジェクトをクローン** | **コードリーディング前提・実務タスク** |

### 目的

- **「既存プロジェクトへの参画」を体験させる**（前2つはゼロから構築だった）
- 他人が書いたコードを読み解き、仕様を理解する力を鍛える
- バグ修正・機能開発・リファクタリングという実務の一連のタスクを経験させる

### 設計原則

1. **Bladeが仕様書** — Blade（フロント）は全て提供済み。受講生はBladeを読んで「バックエンドが何を返すべきか」を把握する
2. **コードリーディングは自然に組み込む** — 独立チケットにせず、各タスクの前提として自然に要求する
3. **テストは機能開発に含む** — 独立カテゴリにせず、機能開発タスクの必須条件とする
4. **お手本が存在する** — 提供プロジェクトにサンプルテスト1〜2本とお手本機能を含め、パターンを示す
5. **詳細度50%の要件** — あえて完璧でない要件を渡し、コーチ（PM）へのヒアリングを促す
6. **コーディング規約に従う体験** — READMEに規約・設計方針を記載し、受講生は既存コードとともにそれに従って実装する

---

## WHAT

### テーマ

**ManaBase** — オンライン学習プラットフォーム（LMS）

- 3ロール: 管理者（admin）、コーチ（coach）、受講生（student）
- コンテンツ階層: Course → Chapter → Section
- 受講生にとって馴染みのあるドメイン（自分が使ってきたLMSと同種）

### タスクカテゴリ

| カテゴリ | 内容 | 例 |
|---------|------|----|
| バグ修正 | 既存コードの不具合を特定・修正 | N+1, 認可漏れ, バリデーション不備, データ起因 |
| 機能開発 | 既存機能の修正 + 新規機能の開発（テスト必須） | レビュー, お気に入り, お知らせ, 学習ノート |
| リファクタリング | 既存コードの品質改善 | Fat Controller分割, Scope抽出, 定数化 |

- 各カテゴリ内に**個別タスク**があり、**タスク単位でブランチを切りPRを出す**（実務と同じ粒度）
- 機能開発タスクでは**テスト実装が必須**

### Basic / Advance

- **Basic**: 教材の習得範囲内で解けるタスク群 → basicブランチ
- **Advance**: 教材範囲外の技術を用いるタスク群 → advanceブランチ（Basic + Advance）
- **Advance純粋追加性**: AdvanceはBasic完成版のコードを一切変更せずに追加できなければならない

判定基準の詳細は `spec/overview.md` で定義（作成時）

### 技術スタック

Laravel 10, PHP 8.2, MySQL 8.0, Docker/Sail, Tailwind CSS, Alpine.js, Fortify

### 成果物一覧

| 成果物 | 説明 | 配置先 |
|--------|------|--------|
| 提供プロジェクト | 受講生がクローンするPJ（バグ込み・一部未実装） | AssignedProjectリポ |
| 模範解答コード | Basic/Advanceブランチの完成版 | 本リポ `answer/` |
| 要件定義書（100%） | コーチ用・全仕様記載 | 本リポ `docs/` |
| 要件定義書（50%） | 受講生用・ヒアリング前提 | 本リポ `docs/` |
| 評価項目シート | 採点基準 | 本リポ `docs/` |
| 完全手順書 | Basic / Advance | 本リポ `docs/` |
| 復習教材 | Basic / Advance | 本リポ `docs/` |

---

## HOW

### リポジトリ構成

| リポジトリ | 用途 | 公開 |
|-----------|------|------|
| ExampleAnswer-mockcase-ManaBase（本リポ） | 全成果物の一元管理（唯一の真実） | ❌ 非公開 |
| AssignedProject-mockcase-ManaBase | 受講生がクローンする提供プロジェクト | ✅ 公開 |

- **Blade専用リポジトリは設けない**（既存PJクローン形式のため、Bladeは最初からプロジェクトに含まれる）
- 開発中は本リポで全成果物を一元管理し、完成後にAssignedProjectへ展開する

### ブランチ運用

| ブランチ | 内容 |
|---------|------|
| `basic` | 提供版構築 → Basic完成版（模範解答） |
| `advance` | basicから分岐 → Basic + Advance実装のみ追加 |

```
basic:   [提供版(劣化)] ────→ [Basic完成版(模範解答)]
                                    │
advance:                            └──→ [+ Advance実装のみ追加]
```

### 提供プロジェクトと模範解答の管理

`provided/` と `answer/` を**別ディレクトリ**で管理する。

- `provided/`: 受講生に渡す状態（バグ込み・一部未実装）。**いつでも直接編集可能**
- `answer/`: 全タスク完了状態の完成版コード

ブラッシュアップ時は `provided/` を直接編集し、必要に応じて `answer/` も更新する。
2つのディレクトリの差分が「受講生が行うべき作業の全体像」となる。

### Blade提供とタスクの対応

| タスク種別 | Blade | ルート | バックエンド | 受講生の作業 |
|-----------|-------|--------|------------|------------|
| バグ修正 | 存在する | 存在する | バグがある | バックエンドの修正 |
| 既存機能修正 | 存在する | 存在する | 不完全 | Blade読解 → バックエンド修正 |
| 新規機能開発 | **存在する** | **存在しない** | 存在しない | Blade読解 → migration+model+controller+route+test |
| リファクタリング | 変更なし | 変更なし | 動くが汚い | バックエンドのみ改善 |
| Advance | advanceブランチで出現 | 存在しない | 存在しない | Blade読解 → 実装 |

**新規機能のBladeは提供PJに最初から含まれる**が、ルートが存在しないためブラウザからはアクセスできない。受講生はBladeファイルを発見し、`action`属性やroute名、`$変数名`を読んで仕様を逆算する。

### 構築ワークフロー

```
Step 1  spec/ を作成（構築の唯一の入力）
          overview.md → database.md → routes.md → tasks.md → provided-state.md

Step 2  正常動作するプロジェクトを構築（provided/ に Laravel PJ）
          全機能が正常動作する綺麗なコード

Step 3  Bladeを全て確定・ロック 🔒
          以降 Blade は原則変更禁止

Step 4  提供版を構築（バグ仕込み・スタブ化）
          spec/provided-state.md に従い 1件ずつ劣化
          spec/coding-standards.md の内容を README に転記

Step 5  provided/ を確定
          → AssignedProject への配置素材が確定

Step 6  模範解答を構築
          provided/ をコピー → answer/ として全タスクを実装
          完全手順書に沿って通しプレイ検証（手順書の不備を修正）
          Basic → Advance の順
          ゲート: Advance実装がBasicコードを変更していないか検証

Step 7  ドキュメント作成
          要件定義書（100% → 50%）、評価項目、復習教材

Step 8  配置
          provided/ を AssignedProject リポジトリへ展開
          advance ブランチの Blade 差分を AssignedProject の advance ブランチへ
```

### 仕様管理のルール

- `spec/` が設計の正（Single Source of Truth）
- 仕様を変更する場合は**必ず spec/ を先に更新してから実装を変更する**
- spec/ を更新せずに実装を変更してはならない
- 構築セッションは spec/ を唯一の入力として行う（設計セッションと構築セッションは分ける）

### ブラッシュアップのルール

提供プロジェクトの修正が必要になった場合:

1. `spec/provided-state.md` を先に更新する
2. `provided/` を直接編集する
3. 変更が模範解答に影響する場合は `answer/` も更新する
4. 関連ドキュメント（手順書・要件書等）を更新する

### 構築時の注意

- **Bladeロック後**はバックエンドの設計を変えてもBladeは変えない（工数爆発防止）
- **劣化は1件ずつ**行う（複数同時は意図がぶれる）
- **設計の記述粒度が実装の密度を決める** — テーブル定義はカラム・型・制約まで、シーダーはデータ件数・内容まで明記する
- **デザインシステムを先に固定** — 共通レイアウトBlade・カラーパレット・コンポーネントスタイルを定義してからClaude Codeに各ページを実装させる

### 関連リポジトリ

| 用途 | ローカルパス |
|------|------------|
| 教材 | `/Users/yotaro/pj-ct-newtext` |
| BookShelf（模擬案件①） | `/Users/yotaro/ExampleAnswer-mockcase-BookShelf` |
| 確認テスト | `/Users/yotaro/ExampleAnswer-ConfirmationTest-ContactForm` |

---

## MAP

### ディレクトリ構成

```
ExampleAnswer-mockcase-ManaBase/
├── CLAUDE.md                       # 本ファイル（哲学層: WHO/WHY/WHAT/HOW/MAP）
├── spec/                           # 設計層（構築の唯一の入力）
│   ├── overview.md                 #   全体像・ロール・機能一覧・エンティティ設計
│   ├── database.md                 #   DB設計（カラム・型・制約・インデックス）
│   ├── routes.md                   #   ルーティング設計
│   ├── tasks.md                    #   タスクカタログ（バグ・機能開発・リファクタ）
│   ├── provided-state.md           #   提供版の状態定義（何が壊れ、何が未実装か）
│   └── coding-standards.md         #   コーディング規約（提供PJのREADMEに転記）
├── provided/                       # 受講生に渡すLaravelプロジェクト（いつでも編集可能）
│   ├── app/                        #   ← 構築フェーズで生成
│   ├── resources/views/            #   ← Blade（全て提供）
│   ├── tests/                      #   ← サンプルテスト 1〜2本
│   ├── README.md                   #   ← 受講生向け（タスク概要・コーディング規約）
│   └── ...                         #   ← Laravel標準構成
├── answer/                         # 全タスク完了後の完成版コード
│   ├── app/                        #   ← provided からコピー後、全タスク実装
│   ├── resources/views/            #   ← Blade（provided と同一）
│   ├── tests/                      #   ← サンプルテスト + 模範解答テスト
│   └── ...                         #   ← Laravel標準構成
├── docs/                           # ドキュメント成果物
│   ├── requirements-100.md         #   要件定義書（100%・コーチ用）
│   ├── requirements-50.md          #   要件定義書（50%・受講生用）
│   ├── evaluation.md               #   評価項目シート
│   ├── guide-basic.md              #   完全手順書（Basic）
│   ├── guide-advance.md            #   完全手順書（Advance）
│   ├── review-basic.md             #   復習教材（Basic）
│   └── review-advance.md           #   復習教材（Advance）
├── .gitignore
└── memo/                           # 旧資料（gitignore済み、yotaroが明示した場合のみ参照）
```

### spec/ ファイルの作成ルール

- **必要になった時点で作成する**（空ファイルを事前に作らない）
- 各ファイルは独立して読めるようにする
- 作成順: overview.md → database.md → routes.md → tasks.md → provided-state.md → coding-standards.md
