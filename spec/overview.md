# 資格対策LMS — プロダクト定義

> 名称（ManaBase）は仮。新しい名前は後で確定する。

## プロダクト背景

### サービス概要

資格試験対策に特化したオンライン学習プラットフォーム。講師が資格試験向けの教材と問題を作成・管理し、受験者が教材で学習・問題演習を行い、管理者がプラットフォーム全体を運営する。

### 開発チームと経緯

| 項目 | 設定 |
|------|------|
| チーム規模 | エンジニア2-3名の小規模チーム |
| プロジェクト年齢 | ローンチから約1.5年 |
| 初期スコープ | 教材閲覧 + 認証 + 基本的な管理画面 |
| 成長経緯 | ローンチ後に問題演習・進捗管理・チャット・ダッシュボード等を段階的に追加 |

### アーキテクチャの成熟度

プロジェクトの成長に伴い、コードの成熟度にムラがある。これは実務プロジェクトとして自然な状態であり、リファクタリングチケットの素材にもなる。

プロジェクト全体の設計思想として **Clean Architecture（軽量版）** を採用。UseCase / Service / Repository の各層に責務を分離する。受講生は既存パターンを読んで理解し、新規実装時は同じパターンに倣う。

| 時期 | 対象機能 | コードの特徴 |
|------|---------|------------|
| 初期（~6ヶ月） | 認証、教材CRUD、カテゴリ管理 | Plain MVC。一部Fat Controller。Clean Architecture未導入 |
| 中期（6-12ヶ月） | ダッシュボード、検索、進捗管理 | Service層を部分的に導入 |
| 後期（12ヶ月~） | 問題演習、管理者機能の拡充 | UseCase / Repository導入。Clean Architecture準拠 |

**混在するパターン:**
- 初期コードの一部はFat Controller（Service未分離、Clean Architecture未導入）
- 中期以降はビジネスロジックをService層に分離
- 後期はUseCase / Repository導入
- Enumは後から導入（初期コードは文字列定数）
- カスタムMiddlewareでロール制御（教材範囲外だが既存コードとして存在）

### データのリアリティ

| 項目 | 設定 |
|------|------|
| ロール分布 | admin 2名、講師 5名、受験者 50名 |
| 教材（Part）数 | 10-15件（公開/非公開/アーカイブ混在） |
| Chapter/Section | Partあたり3-5 Chapter、Chapterあたり3-8 Section |
| 問題 | Sectionあたり3-10問 |
| エッジケースデータ | Chapter 0件のPart、問題0件のSection、非公開Part、大量受講者のPart |
| チャット | 活発なルーム、空のルーム、長期間やりとりのないルーム |

### ドキュメントの状態

| ドキュメント | 状態 |
|------------|------|
| README | セットアップ手順は正確。アーキテクチャ説明は簡素。コーディング規約あり |
| コード内コメント | 複雑な業務ルール（進捗計算、正答率算出等）にはコメントあり。自明な箇所にはなし |
| TODO/FIXME | 1-2箇所残っている（「後でリファクタする」「パフォーマンス改善の余地あり」等） |

---

## ロール

| ロール | DB値 | 説明 |
|--------|------|------|
| 管理者 | `admin` | プラットフォーム全体の管理 |
| 講師 | `instructor` | 教材作成・問題作成・受験者サポート |
| 受験者 | `student` | 教材学習・問題演習 |

ロール管理は `users` テーブルの `role` カラム（enum: admin / instructor / student）で実装。

---

## コンテンツ階層

```
Part（教材）
  └── Chapter（章）
        └── Section（節）+ 問題（Section紐づき）
```

- Section が最小学習単位（Markdown本文）
- 各 Section に問題が紐づく（単一選択 / 複数選択など、形式は要件定義時に確定）
- 進捗管理は Section 単位（読了ボタン → Chapter/Part完了率%自動計算）

---

## Feature一覧

> 資格対策LMSとして再設計中。
> 各featureの詳細は `spec/features/{feature-name}/` 配下の requirements.md + design.md + tasks.md で定義。

**（Feature一覧は再設計中。次セッションで A-1 として確定する）**

### 分類の定義

| 分類 | 仮PJでの状態 | provided/ での状態 |
|------|-------------|-------------------|
| 提供版 | backend あり（正常動作） | バグ・リファクタ対象を含む |
| Basic新規 | backend あり（正常動作） | backend 削除（Blade のみ残す） |
| Advance（BE側） | backend あり（正常動作） | backend 削除 |
| Advance（FE側） | フロント実装あり | Blade含めフロント実装ファイル削除（受講生がゼロから作成） |

### Basic API（Advance FE連携用）

Advance フロント機能の連携対象となる Basic 機能は、Blade ビュー版に加えて **JSON API 版も Basic で実装**する。フロント連携対象でない Basic 機能は Blade のみ。

例:
- Quiz機能（Advance「模擬試験UI」の連携先）→ Blade版 + API版
- スコア集計機能（Advance「スコアダッシュボード」の連携先）→ Blade版 + API版
- 認証認可、教材管理、お知らせ等 → Blade版のみ

### Bladeロックの例外

`spec/CLAUDE.md` のBladeロック方針に対し、**Advance フロント機能のBladeはロック対象外**（受講生がゼロから作成するため）。

---

## コーディング規約

提供プロジェクトの README に転記し、受講生がこれに従って実装する。既存コードもこの規約に従って書かれている（ただしプロダクト背景の「アーキテクチャの成熟度」の通り、初期コードの一部は規約に完全には従っていない）。

### 命名規則

| 対象 | ルール | 例 |
|------|--------|---|
| 変数/メソッド | camelCase | `$questionCount`, `getCorrectAnswerRate()` |
| クラス | PascalCase | `QuestionController`, `StoreAnswerRequest` |
| DBテーブル | snake_case（複数形） | `chat_messages`, `question_options` |
| DBカラム | snake_case（単数形） | `user_id`, `completed_at` |
| モデル | PascalCase（単数形） | `Part`, `Question` |
| コントローラ | PascalCase + Controller | `QuestionController` |
| FormRequest | PascalCase | `StoreQuestionRequest`, `UpdateQuestionRequest` |
| Policy | PascalCase + Policy | `PartPolicy` |
| Service | PascalCase + Service | `ProgressService`, `ScoreService` |
| UseCase | PascalCase + UseCase | `SubmitAnswerUseCase` |
| Repository | PascalCase + Repository | `QuestionRepository` |
| Enum | PascalCase | `PartStatus`, `UserRole` |
| Middleware | PascalCase | `EnsureUserRole` |
| マイグレーション | snake_case | `create_parts_table` |
| シーダー | PascalCase + Seeder | `PartSeeder` |

### コード品質

| ルール | 詳細 |
|--------|------|
| コードフォーマット | Laravel Pint を使用。コミット前に `sail bin pint` を実行 |
| Eloquent ORM | DB操作にはEloquentを使用。クエリビルダや生SQLは原則使用しない |
| N+1対策 | `with()` によるEager Loadingを適切に使用する |
| コントローラの責務 | リクエストの受付とレスポンスの返却に専念。複雑なロジックはService/UseCaseクラスに分離 |
| バリデーション | FormRequestクラスに分離する |
| 認可 | Policyクラスで実装。コントローラで `$this->authorize()` を使用 |
| 設定値 | `.env` で管理。コード内にハードコーディングしない |
| 状態管理 | PHP Enumを使用する（PartStatus, UserRole等） |
| Git運用 | チケットごとにブランチを作成し、mainにPRを出す |
| PR記述 | 調査内容・原因分析（または設計判断）・実装内容・テスト確認の4項目を必須記載 |

### 設計方針

| 方針 | 詳細 |
|------|------|
| ディレクトリ構成 | `app/Services/`, `app/UseCases/`, `app/Repositories/`, `app/Enums/`, `app/Http/Middleware/` 等 |
| Clean Architecture（軽量版） | UseCase / Service / Repository の責務分離。新規実装は既存パターンに倣う |
| Service層 | 複雑なビジネスロジック（進捗計算、スコア集計等）はServiceクラスに分離 |
| UseCase層 | 1つの業務操作を1つのUseCaseクラスに（例: `SubmitAnswerUseCase`） |
| Repository層 | データアクセスをRepository経由で抽象化。外部API依存を切り離す目的でも使用（Geminiチャットボット等） |
| ロール制御 | カスタムMiddleware `EnsureUserRole` でルートグループ単位で制御 |
| 状態遷移 | Part の公開状態（draft/published/archived）はEnumで定義し、遷移条件をServiceで管理 |
| Basic API | フロント連携対象のBasic機能はWeb（Blade）版とJSON API版を両方実装。認証はFortifyセッション + CSRFトークン |
| Sanctum API | Advance枠で「外部公開API」を別途実装（トークン認証） |
