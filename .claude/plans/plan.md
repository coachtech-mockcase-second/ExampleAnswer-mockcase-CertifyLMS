# テーマ・アプリ設計 → specs/overview.md 作成

## Context

Phase 1（設計）の最初のステップ。テーマを確定し `docs/specs/overview.md` を新規作成する。
CoachTech LMS実機調査 + 一般LMS調査を経て機能セットを確定済み。

## 確定事項

- **テーマ**: SkillHub - オンライン学習プラットフォーム（LMS）
- **ロール**: 管理者 / 講師 / 受講生（3ロール）
- **コンテンツ階層**: Part（部）→ Chapter（章）→ Section（節）の3階層
- **チャット**: 生徒↔コーチの1対1のみ。管理者は全チャット閲覧可能
- **追加機能（CoachTech LMSにない新要素）**: お知らせ機能、学習ノート、通知機能（ベルマーク）

## 実装内容

### 1. `docs/specs/overview.md` を新規作成

以下の内容を記載:
- テーマ・コンセプト
- ロール定義（管理者/講師/受講生）
- コンテンツ階層（Part→Chapter→Section）
- 完成版SkillHubの機能一覧（ロール別）

#### 認証
- ユーザー登録 / ログイン / ログアウト（Fortify）
- ロール別アクセス制御（管理者/講師/受講生）

#### 管理者機能
- ダッシュボード: 全体統計レポート（総Part数、総ユーザー数(ロール別)、総受講登録数、全体平均進捗率、人気Part Top5、新規登録推移）
- カテゴリ管理（CRUD）
- ユーザー一覧（検索・フィルタ）・ロール管理
- 教材管理（全Partの閲覧・公開ステータス変更）
- 全チャット閲覧（生徒-講師間の全チャットを閲覧可能）
- お知らせ管理（CRUD、全体向けお知らせの作成・編集・削除）

#### 講師機能
- ダッシュボード: 講師レポート（自分のPart数、総受講者数、平均評価、各Partの受講者数/平均進捗率/レビュー数）
- Part管理（CRUD、公開/非公開、サムネイル画像）
- Chapter管理（Part内のChapter CRUD、並び順管理）
- Section管理（Chapter内のSection CRUD、並び順管理、本文はMarkdown）
- 受講生の進捗確認（Part別の受講生一覧と進捗率）
- チャット（受講生とのメッセージやり取り）
- お知らせ投稿（自分のPart受講者向けのお知らせ作成）

#### 受講生機能
- ダッシュボード: 受講生レポート（受講中Part数、全体進捗率、各受講Partの進捗率、最近学習したSection、お気に入りPart数）
- Part一覧・検索（キーワード、カテゴリフィルタ、ソート）
- Part詳細（講師情報、Chapter/Section構成の閲覧、レビュー一覧）
- Section内容の閲覧（Markdown本文、画像）
- Section読了ボタン（読了→進捗記録、Chapter/Part完了率%自動計算）
- Next/Prevナビゲーション（Section間移動）
- 左サイドバー目次（Chapter→Section、完了チェックマーク付き）
- 受講登録 / 解除
- Partレビュー（5段階評価+コメント、1ユーザー1レビュー制約）
- お気に入りPart（登録/解除、一覧表示）
- チャット（講師への質問）
- 学習ノート（Section単位で個人メモを作成・編集・削除）

#### 共通機能
- 人気Partランキング（受講者数ベース）
- 新着Part表示
- プロフィール編集（アバター画像、名前、自己紹介）
- 通知（右上ベルマーク、未読件数バッジ、ドロップダウン一覧、既読処理）
  - 通知トリガー: お知らせ投稿、チャット受信、新レビュー（講師向け）、新受講登録（講師向け）
- お知らせ閲覧（一覧、未読/既読管理）

#### Advance追加機能
- ダッシュボード強化（応用）: 期間別推移グラフ、完了率分布、アクティブユーザー数推移
- 教材インポート機能（応用）: JSON形式でPart→Chapter→Section構造を一括インポート
- AIチャットボット（応用）: Gemini API連携、教材コンテキストを読み込み学習内容に関する質問に回答
- Sanctum認証API: Part一覧・詳細、受講進捗取得・更新
- パフォーマンスチューニング: DBインデックス、Eager Loading最適化、キャッシュ
- エンティティ設計（概要）
- 不採用機能と理由

### エンティティ設計（完全版）

| エンティティ | 説明 | 主なリレーション |
|-------------|------|-----------------|
| User | 管理者/講師/受講生（roleカラム） | has many parts(講師), enrollments(受講生) |
| Part | 部（トップレベルコンテンツ） | belongs to instructor(User), has many chapters |
| Chapter | 章 | belongs to part, has many sections |
| Section | 節（最小コンテンツ単位） | belongs to chapter |
| Category | カテゴリ | many-to-many with part |
| Enrollment | 受講登録 | pivot: user - part |
| Review | レビュー（5段階評価+コメント） | belongs to user, belongs to part |
| Favorite | お気に入り | belongs to user, belongs to part |
| ChatRoom | チャットルーム（1対1） | student_id, instructor_id を直接保持 |
| ChatMessage | チャットメッセージ | belongs to chat_room, belongs to user |
| Announcement | お知らせ | belongs to author(User), many-to-many with user(既読管理) |
| Note | 学習ノート | belongs to user, belongs to section |

ピボットテーブル:
- section_user（completed_at）: 進捗管理
- category_part: カテゴリ紐付
- announcement_user（read_at）: お知らせ既読管理

その他:
- notifications（Laravel標準）: 通知機能（ベルマーク）

テーブル数: 16（エンティティ12 + ピボット3 + notifications 1）

**旧設計からの変更点:**
- ChatRoom: chat_room_user ピボット廃止 → student_id/instructor_id を直接保持
- 追加: Announcement, Note, announcement_user, notifications

### 2. `docs/design.md` を更新
- テーマ: TBD → 確定（SkillHub - オンライン学習プラットフォーム）

### 3. `docs/progress.md` を更新
- 「テーマ・アプリの題材を決定する」を完了に
- 次のアクション追加

## 対象ファイル

- 新規作成: `docs/specs/overview.md`
- 更新: `docs/design.md`（テーマをTBD→確定に）
- 更新: `docs/progress.md`（現在のアクション更新）
