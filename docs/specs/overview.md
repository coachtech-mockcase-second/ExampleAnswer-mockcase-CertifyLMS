# ManaBase - Overview

## テーマ

**ManaBase - オンライン学習プラットフォーム（LMS）**

プログラミングスクールCoachTechの受講生が実際に使ってきたLMSを題材に、自分でLMSの開発を行ってもらう模擬案件。
受講生にとって馴染みのあるドメインであるため、要件理解がスムーズになり、実装に集中できる。

---

## ロール

| ロール | 説明 |
|--------|------|
| 管理者（admin） | プラットフォーム全体の管理。ユーザー・カテゴリ・お知らせの管理、全チャット閲覧 |
| 講師（instructor） | コース作成・教材管理、受講生の進捗確認、チャット対応 |
| 受講生（student） | コース受講・学習、レビュー投稿、チャットでの質問 |

ロール管理は `users` テーブルの `role` カラム（enum: admin / instructor / student）で実装。

---

## コンテンツ階層

```
Course（コース）
  └── Chapter（章）
        └── Section（節）
```

- ZennのBooks機能に近い3階層構造
- Section が最小コンテンツ単位（Markdown本文 + 画像）
- 進捗管理は Section 単位（読了ボタン → Chapter/Course完了率%自動計算）

---

## 機能一覧

### 認証
- ユーザー登録 / ログイン / ログアウト（Fortify）
- ロール別アクセス制御（管理者/講師/受講生）

### 管理者機能
- **ダッシュボード**: 全体統計レポート
  - 総Course数、総ユーザー数（ロール別）、総受講登録数
  - 全体平均進捗率
  - 人気Course Top5
  - 新規登録推移
- カテゴリ管理（CRUD）
- ユーザー一覧（全ロール表示、ロール・キーワードでフィルタ）・ロール管理
- 教材管理（全Courseの閲覧・公開ステータス変更）
- 全チャット閲覧（生徒-講師間の全チャットを閲覧可能）
- お知らせ管理（CRUD、全体向けお知らせの作成・編集・削除）

### 講師機能
- **ダッシュボード**: 講師レポート
  - 自分のCourse数、総受講者数、平均評価
  - 各Courseの受講者数 / 平均進捗率 / レビュー数
- Course管理（CRUD、公開/非公開、サムネイル画像）
- Chapter管理（Course内のChapter CRUD、並び順管理）
- Section管理（Chapter内のSection CRUD、並び順管理、本文はMarkdown）
- 受講生の進捗確認（Course別の受講生一覧と進捗率）
- チャット（受講生とのメッセージやり取り）
- お知らせ投稿（自分のCourse受講者向けのお知らせ作成）

### 受講生機能
- **ダッシュボード**: 受講生レポート
  - 受講中Course数、全体進捗率
  - 各受講Courseの進捗率
  - 最近学習したSection
  - お気に入りCourse数
- Course一覧・検索（キーワード、カテゴリフィルタ、ソート）
- Course詳細（講師情報、Chapter/Section構成の閲覧、レビュー一覧）
- 講師一覧（講師プロフィール閲覧、講師に紐づくCourse一覧表示）
- Section内容の閲覧（Markdown本文、画像）
- Section読了ボタン（読了 → 進捗記録、Chapter/Course完了率%自動計算）
- Next/Prevナビゲーション（Section間移動）
- 左サイドバー目次（Chapter → Section、完了チェックマーク付き）
- 受講登録 / 解除
- Courseレビュー（5段階評価+コメント、1ユーザー1レビュー制約）
- お気に入りCourse（登録/解除、一覧表示）
- チャット（講師への質問）
- 学習ノート（Section単位で個人メモを作成・編集・削除）

### 共通機能
- 人気Courseランキング（受講者数ベース）
- 新着Course表示
- プロフィール編集（アバター画像、名前、自己紹介）
- 通知（右上ベルマーク、未読件数バッジ、ドロップダウン一覧、既読処理）
  - トリガー: お知らせ投稿、チャット受信、新レビュー（講師向け）、新受講登録（講師向け）
- お知らせ閲覧（一覧、未読/既読管理）

### Advance追加機能
- **ダッシュボード強化**: より高度なレポート（期間別推移グラフ、完了率分布、アクティブユーザー数推移等）
- **教材インポート機能**: JSON形式でCourse → Chapter → Section構造を一括インポート
- **AIチャットボット**: Gemini API連携、教材の内容をコンテキストとして読み込み、学習内容に関する質問に回答
- Sanctum認証API（Course一覧・詳細、受講進捗取得・更新）
- パフォーマンスチューニング（DBインデックス、Eager Loading最適化、キャッシュ）

---

## エンティティ設計（概要）

| エンティティ | 説明 | 主なリレーション |
|-------------|------|-----------------|
| User | 管理者/講師/受講生（roleカラム） | has many courses(講師), enrollments(受講生) |
| Course | コース（トップレベルコンテンツ） | belongs to instructor(User), has many chapters |
| Chapter | 章 | belongs to course, has many sections |
| Section | 節（最小コンテンツ単位） | belongs to chapter |
| Category | カテゴリ | many-to-many with course |
| Enrollment | 受講登録 | pivot: user - course |
| Review | レビュー（5段階評価+コメント） | belongs to user, belongs to course |
| Favorite | お気に入り | belongs to user, belongs to course |
| ChatRoom | チャットルーム（1対1） | student_id, instructor_id を直接保持 |
| ChatMessage | チャットメッセージ | belongs to chat_room, belongs to user |
| Announcement | お知らせ | belongs to author(User), many-to-many with user(既読管理) |
| Note | 学習ノート | belongs to user, belongs to section |

**ピボットテーブル:**
- `section_user`（completed_at）: 進捗管理
- `category_course`: カテゴリ紐付
- `announcement_user`（read_at）: お知らせ既読管理

**その他:**
- `notifications`（Laravel標準）: 通知機能（ベルマーク）

**テーブル数**: 16（エンティティ 12 + ピボット 3 + notifications 1）

---

## CoachTech LMS からの反映方針（ManaBaseへの採用判断）

### 採用（Basic既存機能）
- 3階層コンテンツ構造（Course → Chapter → Section）
- 進捗トラッキング（Section読了ボタン → Chapter/Course完了率%）
- ロール別ダッシュボード（レポート多数）
- Admin/講師による教材管理CRUD（並び順、公開ステータス）
- チャット機能（生徒-コーチ 1対1）
- 検索・フィルタ
- ユーザー管理

### 簡略化して採用
- ランキング: 学習時間ランキング → 人気Course（受講者数ベース）
- タイムライン/ターム管理 → Course別の進捗率中心

### 不採用

| 機能 | 理由 |
|------|------|
| 決済/課金 | Stripe等のセットアップが煩雑 |
| クイズ/テスト | スコープ超過。問題作成UIがフロントエンド寄り |
| 動画アップロード | ファイルストレージ設定が煩雑 |
| ディスカッションフォーラム | スレッド型UIの実装が重い |
| 修了証書発行 | PDF生成は特殊スキル |
| ゲーミフィケーション | スコープ超過 |
| メール通知 | 環境依存、テスト困難 |
| 面談スケジューリング | 外部カレンダー連携が必要 |
| 申請ワークフロー | CoachTech固有の業務機能 |
| 演習問題/テスト管理 | スコープ超過、問題作成UIが重い |
| CS一覧/プラン管理/受講期間延長 | CoachTech固有 |

---

## 新要素（CoachTech LMSにない機能）

| 機能 | 説明 |
|------|------|
| お知らせ機能 | 管理者→全体、講師→受講者向けのお知らせ配信・既読管理 |
| 学習ノート | Section単位で個人メモを作成・編集・削除 |
| 通知機能 | 右上ベルマーク、未読件数バッジ、ドロップダウン。Laravel標準のnotificationsテーブル使用 |
