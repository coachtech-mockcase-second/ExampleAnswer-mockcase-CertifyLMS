# ManaBase — 模擬案件②

> COACHTECHの受講生が「既存プロジェクトへの参画」を体験するための模擬案件。
> 本リポジトリは模範解答コード・提供プロジェクト・関連ドキュメントを一元管理する唯一の真実。

## ペルソナ（WHO）

COACHTECHの受講生。以下を修了済み:

- **教材**: HTML/CSS → PHP → Laravel基礎〜応用 → API設計 → テスト
- **確認テスト**: お問い合わせフォーム（ContactForm）をゼロから構築
- **模擬案件①**: 書籍レビューアプリ（BookShelf）をゼロから構築

### 前提知識（= Basic範囲）

| 領域 | 技術 |
|------|------|
| バックエンド | PHP 8.2, Laravel 10（MVC・Eloquent・認証・ミドルウェア・テスト・API基礎） |
| DB | MySQL 8.0（CRUD・JOIN・正規化・マイグレーション・シーダー） |
| ORM | Eloquent（リレーション・Eager Loading・N+1対策・withCount） |
| 認証・認可 | Fortify, Policy / Gate |
| バリデーション | FormRequest, カスタムメッセージ |
| テスト | PHPUnit（Feature / Unit）, RefreshDatabase, actingAs |
| フロントエンド | Blade, Tailwind CSS, Alpine.js |
| 開発環境 | Docker / Sail, Git / GitHub（ブランチ・PR） |

### 教材範囲外（= Advance範囲）

Sanctum API認証, 外部API連携, ポリモーフィックリレーション, Has-many-through, DBインデックス, キャッシュ, スロークエリ最適化, モッキング・スタブ, キュー/ジョブ, ファイルストレージ, メール送信, OAuth, レート制限

Basic/Advanceの判定基準は上記の通り。

### 技術スタック

Laravel 10 / PHP 8.2 / MySQL 8.0 / Docker・Sail / Tailwind CSS / Alpine.js / Fortify

---

## コンセプト（WHY）

| # | 課題 | アプローチ |
|---|------|-----------|
| 1 | **既存PJ参画の経験不足** — 前2つのテストは基礎力養成のため新規作成で行ったが、実務は既存PJへの参画がほとんど | 既存PJをクローンし、コードリーディング前提で開発させる |
| 2 | **要件ヒアリングの経験不足** — 実務では完璧な要件が降ってくることはほぼなく、曖昧な状態で依頼が飛んでくる | 詳細度50%の要件を渡し、コーチ（PM）へのヒアリングを促す |
| 3 | **AIによる理解なき実装** — AIでコードを生成し理解なく提出することで、企業・受講生双方が困っている | チケットを曖昧にし、PRにプロセス記述（調査・原因分析・設計判断）を必須とする |

---

## ゴール（WHAT）

### テーマ

**ManaBase** — オンライン学習プラットフォーム（LMS）。3ロール（admin / coach / student）、コンテンツ階層（Course → Chapter → Section）。

### 受講生が鍛える力

既存プロジェクトのコーディング規約と設計方針に従い、コードリーディングを前提にバグ修正・機能開発・リファクタリングの実務タスクを行う。加えて:

- **ヒアリング** — 50%要件からコーチ（PM）に質問し、仕様を明確化する
- **自力での問題特定** — 曖昧なチケットから自分で問題を特定し、調査・判断のプロセスを言語化する

### チケット構成

3カテゴリ × 8種類。詳細は `spec/tickets.md` を参照。

| カテゴリ | 種類 |
|---------|------|
| バグ修正 | データの不正 / アクセス制御の不備 / 機能の不全 |
| 機能開発 | 既存機能の修正 / 既存機能の拡張 / 新規機能の構築（テスト必須） |
| リファクタリング | コード構造 / パフォーマンス |

- チケット単位でブランチを切りPRを出す
- **Basic**: 教材範囲内 / **Advance**: 教材範囲外
- Advance は Basic完成版に対して純粋追加のみ

---

## 構築アプローチ（HOW）

### 成果物

| # | 成果物 | 説明 |
|---|--------|------|
| 1 | 提供プロジェクト | 受講生がクローンする既存PJ（バグ込み・一部未実装・Blade全提供） |
| 2 | 模範解答コード | 全チケット完了後の完成版（Basic / Advance） |
| 3 | 要件定義書 | 100%版（コーチ用）/ 50%版（受講生用）。2シート構成 |
| 4 | 評価項目シート | 採点基準 |
| 5 | 完全手順書 | Basic / Advance |
| 6 | 復習教材 | Basic / Advance |

### 構築の原則

- **spec/ が唯一の真実** — 仕様変更は必ず spec/ を先に更新してから実装
- **新規機能は自己完結ページ** — 既存ページからの参照なし。ナビのみ `Route::has()` で制御（Bladeエラー防止）

### 構築ワークフロー

| Step | 内容 | 成果物 |
|------|------|--------|
| 1 | 設計 | spec/ 一式 |
| 2 | 仮PJ構築（全機能動作する完全版） | answer/ |
| 3 | Blade確定・ロック 🔒 | Blade確定 |
| 4 | 提供版構築（answer/ → provided/ → 劣化） | provided/ |
| 5 | 完全手順書作成 | docs/guide-*.md |
| 6 | 通しプレイ検証（provided/ → answer/ 再構築） | answer/ 確定 |
| 7 | ドキュメント作成 | docs/ 残り |
| 8 | 配置 | AssignedProjectリポ |

### リポジトリ・ブランチ

| リポジトリ | 用途 | 公開 |
|-----------|------|------|
| ExampleAnswer-mockcase-ManaBase（本リポ） | 全成果物の一元管理 | ❌ |
| AssignedProject-mockcase-ManaBase | 受講生がクローンするPJ | ✅ |

- `basic`: メインブランチ（Basic完成版）
- `advance`: basicから分岐（Basic + Advance追加のみ）
- Blade専用リポは不要（既存PJクローン形式）

---

## プロジェクトマップ（MAP）

### 参考リポジトリ

| 用途 | パス |
|------|------|
| 教材 | `/Users/yotaro/pj-ct-newtext` |
| 確認テスト | `/Users/yotaro/ExampleAnswer-ConfirmationTest-ContactForm` |
| BookShelf（模擬案件①） | `/Users/yotaro/ExampleAnswer-mockcase-BookShelf` |
| CourseHub（簡素すぎの反面教師） | `/Users/yotaro/pro-cc-coursehub` |
| ifield LMS（機能・spec構造の参考） | `/Users/yotaro/ifield-lms` |
| COACHTECH LMS（ドメイン知識の参考） | `/Users/yotaro/lms` |

### フォルダ構成

```
ExampleAnswer-mockcase-ManaBase/
├── CLAUDE.md              # 本ファイル（哲学: WHO/WHY/WHAT/HOW/MAP）
├── spec/                  # 設計層（構築の唯一の入力）
│   ├── overview.md        #   プロダクト定義（ロール・階層・Feature一覧・規約）
│   ├── features/          #   機能単位のSDD的仕様
│   │   └── {feature}/
│   │       ├── requirements.md
│   │       ├── design.md
│   │       └── tasks.md
│   ├── database.md        #   データ層（エンティティ・リレーション・テーブル定義・シーダー）
│   ├── routes.md          #   HTTP層（ルート定義・Bladeマッピング）
│   └── tickets.md         #   チケット設計（全体像・お手本・AI対策・個別チケット）
├── provided/              # 提供PJ（answer/ から派生→劣化）
├── answer/                # 模範解答（仮PJ → 通しプレイで確定）
├── docs/                  # ドキュメント成果物
│   ├── requirements-*.md  #   要件定義書（100% / 50%）
│   ├── evaluation.md      #   評価項目シート
│   ├── guide-*.md         #   完全手順書（Basic / Advance）
│   └── review-*.md        #   復習教材（Basic / Advance）
└── memo/                  # 旧資料（gitignore済み）
```

### spec/ 作成ルール

- features/ の仕様が全成果物の派生元（SDD的アプローチ）
- 作成順: overview.md → features/ → database.md → routes.md → tickets.md
