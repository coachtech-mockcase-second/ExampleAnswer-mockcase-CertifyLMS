# ManaBase — プロダクト定義

## プロダクト背景

### サービス概要

ManaBaseは、プログラミングスクール向けのオンライン学習プラットフォーム（LMS）。コーチがコースを作成・管理し、受講生がコースを受講・学習し、管理者がプラットフォーム全体を運営する。

### 開発チームと経緯

| 項目 | 設定 |
|------|------|
| チーム規模 | エンジニア2-3名の小規模チーム |
| プロジェクト年齢 | ローンチから約1.5年 |
| 初期スコープ | コース閲覧 + 認証 + 基本的な管理画面 |
| 成長経緯 | ローンチ後に受講登録・進捗管理・チャット・ダッシュボード等を段階的に追加 |

### アーキテクチャの成熟度

プロジェクトの成長に伴い、コードの成熟度にムラがある。これは実務プロジェクトとして自然な状態であり、リファクタリングチケットの素材にもなる。

| 時期 | 対象機能 | コードの特徴 |
|------|---------|------------|
| 初期（~6ヶ月） | 認証、Course CRUD、カテゴリ管理 | Plain MVC。一部Fat Controller。テストあり |
| 中期（6-12ヶ月） | ダッシュボード、検索、進捗管理 | Service層を部分的に導入。テストが不足 |
| 後期（12ヶ月~） | チャット、管理者機能の拡充 | より整理されたコード。Service層使用 |

**混在するパターン:**
- 初期コードの一部はFat Controller（Service未分離）
- 中期以降はビジネスロジックをService層に分離
- Enumは後から導入（初期コードは文字列定数）
- カスタムMiddlewareでロール制御（教材範囲外だが既存コードとして存在）
- 一部のモデルにAccessor/Mutator、Query Scope

### データのリアリティ

| 項目 | 設定 |
|------|------|
| ロール分布 | admin 2名、coach 5名、student 50名 |
| コース数 | 10-15件（公開/非公開/アーカイブ混在） |
| Chapter/Section | コースあたり3-5 Chapter、Chapterあたり3-8 Section |
| エッジケースデータ | Chapter 0件のコース、受講者0のコース、非公開コース、大量受講者のコース |
| チャット | 活発なルーム、空のルーム、長期間やりとりのないルーム |

### ドキュメントの状態

| ドキュメント | 状態 |
|------------|------|
| README | セットアップ手順は正確。アーキテクチャ説明は簡素。コーディング規約あり |
| コード内コメント | 複雑な業務ルール（進捗計算、状態遷移）にはコメントあり。自明な箇所にはなし |
| TODO/FIXME | 1-2箇所残っている（「後でリファクタする」「パフォーマンス改善の余地あり」等） |

---

## ロール

| ロール | DB値 | 説明 |
|--------|------|------|
| 管理者 | `admin` | プラットフォーム全体の管理 |
| コーチ | `coach` | コース作成・教材管理・受講生サポート |
| 受講生 | `student` | コース受講・学習 |

ロール管理は `users` テーブルの `role` カラム（enum: admin / coach / student）で実装。

---

## コンテンツ階層

```
Course（コース）
  └── Chapter（章）
        └── Section（節）
```

- Section が最小コンテンツ単位（Markdown本文）
- 進捗管理は Section 単位（読了ボタン → Chapter/Course完了率%自動計算）

---

## Feature一覧

> チケット制約なし。プロダクトとして自然な規模で設計する。
> 各featureの詳細は `spec/features/{feature-name}/` 配下の requirements.md + design.md + tasks.md で定義。

（Feature一覧は再設計中）

**分類の定義:**

| 分類 | 仮PJでの状態 | provided/ での状態 |
|------|-------------|-------------------|
| 提供版 | backend あり（正常動作） | バグ・リファクタ対象を含む |
| Basic新規 | backend あり（正常動作） | backend 削除（Blade のみ残す） |
| Advance | backend あり（正常動作） | backend 削除（Blade のみ残す） |

---

## コーディング規約

提供プロジェクトの README に転記し、受講生がこれに従って実装する。既存コードもこの規約に従って書かれている（ただしプロダクト背景の「アーキテクチャの成熟度」の通り、初期コードの一部は規約に完全には従っていない）。

### 命名規則

| 対象 | ルール | 例 |
|------|--------|---|
| 変数/メソッド | camelCase | `$courseCount`, `getEnrolledStudents()` |
| クラス | PascalCase | `CourseController`, `StoreFeedbackRequest` |
| DBテーブル | snake_case（複数形） | `chat_messages`, `category_course` |
| DBカラム | snake_case（単数形） | `user_id`, `completed_at` |
| モデル | PascalCase（単数形） | `Course`, `ChatMessage` |
| コントローラ | PascalCase + Controller | `CourseController` |
| FormRequest | PascalCase | `StoreCourseRequest`, `UpdateCourseRequest` |
| Policy | PascalCase + Policy | `CoursePolicy` |
| Service | PascalCase + Service | `ProgressService`, `DashboardService` |
| Enum | PascalCase | `CourseStatus`, `UserRole` |
| Middleware | PascalCase | `EnsureUserRole` |
| マイグレーション | snake_case | `create_courses_table` |
| シーダー | PascalCase + Seeder | `CourseSeeder` |

### コード品質

| ルール | 詳細 |
|--------|------|
| コードフォーマット | Laravel Pint を使用。コミット前に `sail bin pint` を実行 |
| Eloquent ORM | DB操作にはEloquentを使用。クエリビルダや生SQLは原則使用しない |
| N+1対策 | `with()` によるEager Loadingを適切に使用する |
| コントローラの責務 | リクエストの受付とレスポンスの返却に専念。複雑なロジックはServiceクラスに分離 |
| バリデーション | FormRequestクラスに分離する |
| 認可 | Policyクラスで実装。コントローラで `$this->authorize()` を使用 |
| 設定値 | `.env` で管理。コード内にハードコーディングしない |
| 状態管理 | PHP Enumを使用する（CourseStatus, UserRole等） |
| Git運用 | チケットごとにブランチを作成し、mainにPRを出す |
| PR記述 | 調査内容・原因分析（または設計判断）・実装内容・テスト確認の4項目を必須記載 |

### 設計方針

| 方針 | 詳細 |
|------|------|
| ディレクトリ構成 | `app/Services/` にビジネスロジック。`app/Enums/` に状態定義。`app/Http/Middleware/` にカスタムミドルウェア |
| Service層 | 複雑なビジネスロジック（進捗計算、ダッシュボード集計等）はServiceクラスに分離 |
| ロール制御 | カスタムMiddleware `EnsureUserRole` でルートグループ単位で制御 |
| 状態遷移 | Course の公開状態（draft/published/archived）はEnumで定義し、遷移条件をServiceで管理 |
