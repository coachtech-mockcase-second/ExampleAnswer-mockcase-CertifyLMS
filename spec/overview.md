# ManaBase — LMS全体像

## テーマ

**ManaBase** — オンライン学習プラットフォーム（LMS）。

プログラミングスクールのような教育サービスを題材に、コーチがコースを作成・管理し、受講生がコースを受講・学習するシステム。COACHTECHの受講生にとって馴染みのあるドメインであり、要件理解がスムーズになる。

---

## ロール

| ロール | DB値 | 説明 |
|--------|------|------|
| 管理者 | `admin` | プラットフォーム全体の管理 |
| コーチ | `coach` | コース作成・教材管理・受講生サポート |
| 受講生 | `student` | コース受講・学習 |

ロール管理は `users` テーブルの `role` カラム（enum: admin / coach / student）で実装する。

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

## 機能一覧

### 機能分類

各機能を以下の3カテゴリに分類する。この分類が仮PJ構築・劣化・チケット設計の基盤となる。

| 分類 | 仮PJでの状態 | provided/ での状態 | 受講生の作業 |
|------|-------------|-------------------|------------|
| **提供版** | backend あり（正常動作） | バグ・リファクタ対象を含む | BUG/REF チケットの対象 |
| **Basic 新規** | backend あり（正常動作） | backend 削除（Blade のみ残す） | ゼロからbackendを構築 |
| **Advance** | backend あり（正常動作） | backend 削除（Blade のみ残す） | ゼロからbackendを構築 |

### 提供版機能（仮PJでbackend実装 → provided/ に含まれる）

#### 管理者機能

| 機能 | 概要 |
|------|------|
| ダッシュボード | 全体統計（総Course数、総ユーザー数、総受講登録数、全体平均進捗率、人気Course Top5） |
| カテゴリ管理 | CRUD |
| ユーザー一覧 | 全ロール表示、ロール・キーワードでフィルタ |
| 教材管理 | 全Courseの閲覧・公開ステータス変更 |
| 全チャット閲覧 | 受講生-コーチ間の全チャットを閲覧 |

#### コーチ機能

| 機能 | 概要 |
|------|------|
| ダッシュボード | コーチレポート（自分のCourse数、総受講者数、平均評価、各Courseの受講者数/平均進捗率） |
| Course管理 | CRUD、公開/非公開 |
| Chapter管理 | Course内のChapter CRUD、並び順管理 |
| Section管理 | Chapter内のSection CRUD、並び順管理、本文はMarkdown |
| 受講生の進捗確認 | Course別の受講生一覧と進捗率 |
| チャット | 受講生とのメッセージやり取り |

#### 受講生機能

| 機能 | 概要 |
|------|------|
| ダッシュボード | 受講生レポート（受講中Course数、全体進捗率、各Courseの進捗率、最近学習したSection） |
| Course一覧・検索 | キーワード、カテゴリフィルタ、ソート |
| Course詳細 | コーチ情報、Chapter/Section構成の閲覧 |
| コーチ一覧 | コーチプロフィール閲覧、コーチに紐づくCourse一覧 |
| Section閲覧 | Markdown本文の表示 |
| 進捗管理 | Section読了ボタン → Chapter/Course完了率%自動計算 |
| Next/Prevナビゲーション | Section間移動 |
| 左サイドバー目次 | Chapter → Section、完了チェックマーク付き |
| 受講登録/解除 | Courseへの受講登録と解除 |
| チャット | コーチへの質問 |

#### 共通機能

| 機能 | 概要 |
|------|------|
| 認証 | ユーザー登録/ログイン/ログアウト（Fortify）、ロール別アクセス制御 |
| プロフィール編集 | 名前、自己紹介 |
| 人気Courseランキング | 受講者数ベース |
| 新着Course表示 | 最新のCourse |

### Basic 新規機能（受講生がゼロから作る）

全て**自己完結ページ**として設計する。既存ページからのデータ参照を持たない。ナビのリンクのみ `Route::has()` で制御し、受講生がルートを定義した時点で自動表示される。

| 機能 | 概要 | 新規テーブル |
|------|------|------------|
| フィードバック | Course完了後に構造化フィードバック（自由記述、星なし）。1ユーザー1Course1件。コーチのみ閲覧可。専用ページ `/courses/{course}/feedback` | feedbacks |
| お気に入り | Courseのお気に入り登録/解除。専用ページ `/favorites` で一覧表示 | favorites |
| 学習ノート | Section単位で個人メモCRUD。専用ページ `/sections/{section}/notes` | notes |
| お知らせ | 管理者が全体向けお知らせCRUD、一覧・既読管理。専用ページ `/announcements` | announcements, announcement_user |

### Advance機能

| 機能 | 概要 | 技術要素 |
|------|------|---------|
| Sanctum API | Course一覧・詳細、受講進捗取得・更新のAPI | Sanctum認証、APIリソース |
| AIチャットボット | Gemini API連携。教材内容をコンテキストとして学習質問に回答 | 外部API連携 |
| パフォーマンスチューニング | DBインデックス追加、Eager Loading最適化、キャッシュ導入 | インデックス、キャッシュ |

---

## お手本機能

以下の提供版機能を「お手本」として、テスト付きで提供する。受講生はこのテストを読んでテストの書き方を学ぶ。

| 候補 | 理由 |
|------|------|
| 受講登録/解除 | シンプルなCRUDでテストの基本パターンを示しやすい |
| カテゴリ管理 | 管理者のCRUD操作 + 認可テストのお手本に適する |

確定は `spec/tickets.md` 作成時。

---

## エンティティ概要

カラム・型・制約の詳細は `spec/database.md` で定義する。ここでは全体構造のみ示す。

### エンティティ一覧

| エンティティ | 説明 | 分類 |
|-------------|------|------|
| User | 管理者/コーチ/受講生（roleカラム） | 提供版 |
| Course | コース（トップレベルコンテンツ） | 提供版 |
| Chapter | 章 | 提供版 |
| Section | 節（最小コンテンツ単位） | 提供版 |
| Category | カテゴリ | 提供版 |
| Enrollment | 受講登録（pivot: user-course） | 提供版 |
| ChatRoom | チャットルーム（1対1） | 提供版 |
| ChatMessage | チャットメッセージ | 提供版 |
| Feedback | フィードバック（自由記述、コーチ閲覧用） | Basic新規 |
| Favorite | お気に入り | Basic新規 |
| Note | 学習ノート | Basic新規 |
| Announcement | お知らせ | Basic新規 |

### ピボットテーブル

| テーブル | 用途 | 分類 |
|---------|------|------|
| section_user | 進捗管理（completed_at） | 提供版 |
| category_course | カテゴリ紐付 | 提供版 |
| announcement_user | お知らせ既読管理（read_at） | Basic新規 |

### 主なリレーション

```
User (coach) ──1:N──→ Course ──1:N──→ Chapter ──1:N──→ Section
User (student) ──M:N──→ Course  (via enrollments)
User (student) ──M:N──→ Section (via section_user / 進捗)
User ──1:N──→ Feedback ←──N:1── Course
User ──1:N──→ Favorite ←──N:1── Course
User ──1:N──→ Note ←──N:1── Section
Course ──M:N──→ Category (via category_course)
ChatRoom ──1:N──→ ChatMessage
User ──1:N──→ Announcement
User ──M:N──→ Announcement (via announcement_user / 既読)
```

**テーブル数**: 15（エンティティ12 + ピボット3）
Advance で `notifications`（Laravel標準）が追加される場合は +1。

---

## 技術スタック

| カテゴリ | 技術 | バージョン |
|---------|------|-----------|
| バックエンド | PHP | 8.2 |
| | Laravel | 10.x |
| | Laravel Fortify | 認証 |
| フロントエンド | Blade | - |
| | Tailwind CSS | ^3.4.0 |
| | Alpine.js | ^3.x |
| データベース | MySQL | 8.0 |
| 開発環境 | Docker / Laravel Sail | - |
| コード品質 | Laravel Pint | - |
| バージョン管理 | Git / GitHub | - |

### Advance 追加技術

| 技術 | 用途 |
|------|------|
| Laravel Sanctum | API認証 |
| Gemini API | AIチャットボット |

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
| Git運用 | 機能ごとにブランチを作成し、mainにPRを出す。コミットメッセージは何をしたかが明確にわかるように |

---

## Basic / Advance 判定基準

| 区分 | 基準 |
|------|------|
| Basic | 教材（pj-ct-newtext）で「実装まで」教えている技術で解けるもの |
| Advance | 教材で「概念のみ」または「触れていない」技術が必要なもの |

### 教材でカバーされている範囲（= Basic）

Controllers, Models & Eloquent, Migrations, Relationships（hasMany, belongsTo, belongsToMany）, Validation（FormRequest）, Middleware（auth, guest, custom）, Authentication（Fortify）, Authorization / Policies, Blade, Seeders & Factories, Eager Loading / N+1対策, PHPUnit（Feature / Unit）, RESTful API原則, コレクションメソッド（map, filter, sortBy, groupBy, sum, avg, count, pluck）

### 教材範囲外（= Advance）

Sanctum API認証, 外部API連携の実装, ポリモーフィックリレーション, Has-many-through, DBインデックス戦略, キャッシュ戦略, スロークエリ最適化, モッキング・スタブ（高度なテスト）, キュー/ジョブ, ファイルストレージ, メール送信, OAuth, レート制限

---

## チケット全体像

チケットの詳細は `spec/tickets.md` で定義する。ここではカテゴリ → 種類 → 配分と、各チケットが狙う機能領域を示す。

### カテゴリ → 種類（MECE）

| カテゴリ | 種類 | 定義 |
|---------|------|------|
| バグ修正 | データの不正 | 出力結果が期待と異なる |
| | アクセス制御の不備 | 見えてはいけないものが見える・操作できる |
| | 機能の不全 | 動かない・エラーになる |
| 機能開発 | 既存機能の修正・拡張 | 既存モデル・コントローラの修正（新規モデルなし） |
| | 新規機能の構築 | 新規モデル・コントローラ・ルートをゼロから作成（テスト必須） |
| リファクタリング | コード構造 | 可読性・保守性の改善 |
| | パフォーマンス | 実行効率の改善（Advance） |

### チケット配分

| カテゴリ | 種類 | Basic | Advance | 計 | 狙う機能領域 |
|---------|------|-------|---------|---|------------|
| バグ修正 | データの不正 | 2 | - | 2 | search-browse（N+1）、admin（集計誤り） |
| | アクセス制御の不備 | 1-2 | - | 1-2 | course-management（認可漏れ）、learning（認証チェック漏れ） |
| | 機能の不全 | 1-2 | - | 1-2 | learning（バリデーション不備）、course-management（状態遷移不備） |
| 機能開発 | 既存機能の修正・拡張 | 2-3 | - | 2-3 | search-browse（フィルタ追加）、chat（機能拡張）、admin（表示項目追加） |
| | 新規機能の構築 | 2-3 | 2-3 | 4-6 | feedback, favorites, notes, announcements（Basic）/ api, chatbot（Advance） |
| リファクタリング | コード構造 | 2-3 | - | 2-3 | course-management（Fat Controller）、search-browse（Scope抽出） |
| | パフォーマンス | - | 1-2 | 1-2 | search-browse（インデックス）、learning（キャッシュ） |
| **計** | | **10-16** | **3-5** | **13-21** | |

### 推定時間

| 種類 | 1チケットあたり | 内訳 |
|------|---------------|------|
| バグ修正 | 1-3h | コードリーディング + 修正 |
| 既存機能の修正 | 2-4h | コードリーディング + 実装 |
| 新規機能の構築 | 5-10h | Blade読解 + 設計 + 実装 + テスト |
| リファクタ（コード構造） | 2-4h | 現状理解 + リファクタ |
| リファクタ（パフォーマンス） | 3-5h | 調査 + 実装（Advance） |

**Basic合計**: 概算 25-50h（BookShelfと同等かやや少ない程度）
**Advance合計**: 概算 10-20h

### 各チケットが要求する「深さ」の設計指針

チケット対象の機能には、以下のような「深さ」を意図的に組み込む。これにより CourseHub のような「標準CRUDの羅列」にならず、実務的なバグ・リファクタの題材が自然に生まれる。

| 深さの種類 | 具体例 | チケットとの関係 |
|-----------|--------|--------------|
| 業務ルール | 「公開Courseのみ受講登録可」「受講登録済みでないとSection閲覧不可」 | バグ（ルール実装漏れ） |
| 状態遷移 | Course: draft → published → archived。Chapter/Sectionが0なら公開不可 | バグ（遷移条件不備） |
| 集計ロジック | ダッシュボードの統計（平均進捗率、人気Top5） | リファクタ（foreachからクエリへ） |
| エッジケース | Chapter 0件のCourse、受講者0での平均計算（0除算） | バグ（データ起因） |
| 権限の複雑さ | コーチは自分のCourseのみ編集可。管理者は全Course操作可 | バグ（認可漏れ） |
| 検索の複雑さ | キーワード + カテゴリ + ソート（新着/人気）の組み合わせ | バグ（N+1）、リファクタ（Scope化） |

---

## Feature一覧

各featureの詳細は `spec/features/{feature-name}/` 配下の requirements.md + design.md + tasks.md で定義する。

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

---

## 参考LMS

機能設計の参考として以下のLMSを参照する。ただし、そのままコピーせずManaBase独自の組み合わせ・設計とする。

| LMS | 参考ポイント | ローカルパス |
|-----|------------|------------|
| ifield LMS | 機能のUXパターン、spec構造 | `/Users/yotaro/ifield-lms` |
| COACHTECH LMS | ドメイン知識（受講生・コーチの関係性、進捗管理の実態） | `/Users/yotaro/lms` |
