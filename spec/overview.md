# ManaBase — プロダクト定義

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

各featureの詳細は `spec/features/{feature-name}/` 配下の requirements.md + design.md + tasks.md で定義。

| feature名 | 含む機能 | 分類 |
|-----------|---------|------|
| `auth` | 認証（Fortify）、ロール別アクセス制御 | 提供版 |
| `admin` | ダッシュボード、カテゴリ管理、ユーザー管理、教材管理、全チャット閲覧 | 提供版 |
| `course-management` | Course CRUD、Chapter CRUD、Section CRUD、並び順管理、公開/非公開 | 提供版 |
| `learning` | 受講登録/解除、Section閲覧、進捗管理、Next/Prev、サイドバー目次 | 提供版 |
| `search-browse` | Course一覧・検索、コーチ一覧、ランキング、新着表示 | 提供版 |
| `chat` | チャットルーム、メッセージ送受信 | 提供版 |
| `profile` | プロフィール編集 | 提供版 |
| `feedback` | コースフィードバック（Course完了後、コーチ閲覧用） | Basic新規 |
| `favorites` | お気に入り登録/解除、一覧 | Basic新規 |
| `notes` | 学習ノートCRUD | Basic新規 |
| `announcements` | お知らせCRUD、既読管理 | Basic新規 |
| `api` | Sanctum認証、Course API | Advance |
| `chatbot` | Gemini API連携チャットボット | Advance |

**分類の定義:**

| 分類 | 仮PJでの状態 | provided/ での状態 |
|------|-------------|-------------------|
| 提供版 | backend あり（正常動作） | バグ・リファクタ対象を含む |
| Basic新規 | backend あり（正常動作） | backend 削除（Blade のみ残す） |
| Advance | backend あり（正常動作） | backend 削除（Blade のみ残す） |

---

## コーディング規約

提供プロジェクトの README に転記し、受講生がこれに従って実装する。既存コードもこの規約に従って書かれている。

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
| マイグレーション | snake_case | `create_courses_table` |
| シーダー | PascalCase + Seeder | `CourseSeeder` |

### コード品質

| ルール | 詳細 |
|--------|------|
| コードフォーマット | Laravel Pint を使用。コミット前に `sail bin pint` を実行 |
| Eloquent ORM | DB操作にはEloquentを使用。クエリビルダや生SQLは原則使用しない |
| N+1対策 | `with()` によるEager Loadingを適切に使用する |
| コントローラの責務 | リクエストの受付とレスポンスの返却に専念。複雑なロジックはモデルに記述 |
| バリデーション | FormRequestクラスに分離する |
| 認可 | Policyクラスで実装。コントローラで `$this->authorize()` を使用 |
| 設定値 | `.env` で管理。コード内にハードコーディングしない |
| Git運用 | チケットごとにブランチを作成し、mainにPRを出す |
| PR記述 | 調査内容・原因分析（または設計判断）・実装内容・テスト確認の4項目を必須記載 |
