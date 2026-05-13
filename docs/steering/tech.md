# Certify LMS — 技術スタックとコーディング規約

> 本プロジェクトの技術選定・アーキテクチャ方針・コード品質ルール・テスト方針・Git運用を集約する。
> **このドキュメントは構築側のみ参照**（受講生には渡らない）。受講生は提供PJコード + 要件シートで作業する。
> プロダクト定義は `product.md`、ディレクトリ構成・命名規則は `structure.md` を参照。
> Claude 用の実装ルールは `/.claude/rules/` を参照（同じく構築側のみ）。

---

## 技術スタック

| 領域 | 技術 |
|---|---|
| 言語 | PHP 8.2 |
| フレームワーク | Laravel 10 |
| DB | MySQL 8.0 |
| ORM | Eloquent |
| 認証 | Laravel Fortify（Web セッション）+ Laravel Sanctum（API トークン認証）|
| **FE** | **Blade + Tailwind CSS + 素のJavaScript**（Vite ビルド）。**Alpine.js / Livewire は不採用** |
| ビルド | Vite（Laravel 10 標準）。`resources/js/` `resources/css/` を `sail npm run dev` / `sail npm run build` でバンドル |
| **PDF生成** | `barryvdh/laravel-dompdf`（修了証 PDF 出力、Blade テンプレート + dompdf による同期生成、Basic 範囲）|
| **Markdown** | `league/commonmark`（教材 `Section.body` の Markdown → HTML 変換、XSS 対策で `safe_links_policy` + `unallowed_attributes` を設定、Basic 範囲）|
| **ファイル保存** | Laravel Storage（**private driver**: chat 添付・修了証 PDF / **public driver**: 教材内画像・プロフィールアイコン、private 配信は `AttachmentController` 経由で Policy 当事者チェック + signed URL）|
| **スケジューラ** | Laravel Schedule（`app/Console/Commands/` 配下、学習途絶検知 / 招待期限切れ判定 / 試験日超過の `failed` 自動遷移 等の日次 Command、Basic 範囲）|
| 環境 | Docker / Laravel Sail |
| テスト | PHPUnit（Feature / Unit）|
| フォーマット | Laravel Pint |
| 静的解析 | （任意）Larastan |
| 外部API | Google Calendar OAuth, Gemini API（Advance範囲）|
| リアルタイム | Laravel Broadcasting + Pusher（Advance範囲）|
| 非同期処理 | Laravel Queue / Job（Advance範囲）|

### Frontend 方針の補足

| 機能 | 実装手段 |
|---|---|
| 通常CRUD・教材閲覧・面談予約・チャット非同期 | **Blade テンプレート + フォーム送信** |
| mock-exam の時間制限タイマー / マークシート式選択 / 提出 | **素のJS（`resources/js/mock-exam/`）+ fetch** |
| 動的フィルタ（一覧の絞込等） | 素のJS + fetch（必要時）|
| **Advance**: 公開API SPA / リアルタイムチャット / Google Calendar OAuth | 素のJS + Sanctum + Pusher / Echo.js |

採用しないもの:
- **Alpine.js** — 受講生に教材経験なし、Advance で純粋JSを学ぶので役割が重複
- **Livewire** — 新フレームワーク学習負荷大、教育目標と外れる
- **Inertia / Vue / React** — Basic スコープに対し過剰

## コマンド慣習（Sail プレフィックス必須）

開発環境は Laravel Sail（Docker Compose）で完結する。**`composer` `php` `npm` `vendor/bin/*` を素のホスト側で実行しない**。これは確認テスト（ContactForm）/ 模擬案件①（BookShelf）と同じ慣習で、受講生が混乱しないよう統一する。

| 操作 | コマンド |
|---|---|
| コンテナ起動 / 停止 | `sail up -d` / `sail down` |
| Artisan | `sail artisan {command}`（例: `sail artisan migrate:fresh --seed` / `sail artisan test` / `sail artisan invitations:expire`）|
| Composer | `sail composer {command}`（初回 vendor 未配置時のみ Docker 直起動の `laravelsail/php82-composer` で composer install） |
| npm | `sail npm {command}`（例: `sail npm install` / `sail npm run dev` / `sail npm run build`） |
| Pint 整形 | `sail bin pint` または `sail bin pint --dirty` |
| Tinker | `sail artisan tinker` |
| DB 確認 | phpMyAdmin（http://localhost:8080） |
| Mail 確認 | Mailpit（http://localhost:8025） |
| アプリ | http://localhost |

**alias 推奨**（`tech.md` / 受講生資料で共通記載）:
```bash
alias sail='[ -f sail ] && bash sail || bash vendor/bin/sail'
```

> Why: spec / 完全手順書 / 復習教材 / PR 動作確認手順に書く **すべてのコマンド** を `sail` プレフィックスで統一すると、受講生は「ホスト側で動かす / Docker 内で動かす」の混乱なく作業できる。確認テスト・模擬案件①と同じ慣習なので、3 プロジェクト連続で同じコマンド感覚が身につく。

## アーキテクチャ方針

**Clean Architecture（軽量版）** を採用。Controller / UseCase / Service / Repository / Eloquent Model の責務分離。Laravel コミュニティ標準よりやや厚めだが、教育目的で **層分離の体験** を提供する。

| 層 | 役割 | 例 | 必須/任意 |
|---|---|---|---|
| Controller | リクエストの受付、入力検証の指示、レスポンス整形 | `QuestionController` | 必須 |
| **FormRequest** | バリデーション（独立クラス）| `StoreQuestionRequest` | 必須 |
| **Policy** | 認可（リソース固有ルール）| `QuestionPolicy` | 必須 |
| **UseCase（Action）** | 1業務操作 = 1クラス。トランザクション境界。クラス名は `{Action}Action.php`（COACHTECH 流） | `SubmitAction`, `ApproveCompletionAction` | 推奨（複雑度に応じ） |
| **Service** | 横断的ビジネスロジック / 計算ロジック | `ProgressService` `ScoreService` | 推奨 |
| **Repository** | **外部API依存の切り離し** に限定採用 | `GeminiRepository`, `GoogleCalendarRepository` | 限定（DB専用には作らない）|
| **Eloquent Model** | DB アクセス、リレーション、スコープ | `Question` | 必須 |
| Resource | API レスポンス整形 | `QuestionResource` | API のみ |
| **Middleware** | ロール存在確認等の**横串処理**（リソース固有認可は Policy へ）| `EnsureUserRole` | 必須 |

### 各層の判断指針

- **Controller は薄く**: バリデは FormRequest、認可は Policy、ビジネスロジックは Service / UseCase へ
- **UseCase（Action） vs Service**:
  - UseCase = 「1ユースケース = 1 Action クラス」（例: `SubmitAction` / `ApproveCompletionAction`）。トランザクション境界。`__invoke()` メソッドが主。配置は `app/UseCases/{Entity}/{Action}Action.php`
  - 命名規則: `IndexAction` / `ShowAction` / `StoreAction` / `UpdateAction` / `DestroyAction`（CRUD）、`Fetch{Name}Action`（その他取得）、動詞 + Action（業務操作）
  - Service = 「複数 Action から共有される計算 / ロジック」（例: `ProgressService.recalculate()`）
  - 単純な操作は Action を省略して Controller → Service で OK
  - **Policy は Action 内で呼ばない**。認可は Controller / FormRequest で実施。Action 内ではデータ整合性チェックのみ
- **Repository は限定採用**:
  - DB 専用には作らない（Eloquent Model のスコープで十分）
  - 外部API（Gemini / GoogleCalendar / Pusher）の依存切り離し / モック化用に限定
- **Policy 主役**: 「コーチは担当資格のみ」「受講生は自分のリソースのみ」等のリソース固有認可は **Policy で表現**。Middleware は「ロール存在確認」止め

### モデルの推奨パターン（COACHTECH LMS 流）

- 主キーは **ULID**（`use HasUlids`）を推奨。URL 安全 + 時系列ソート可
- **`SoftDeletes`** を採用（論理削除）。学習履歴の保持に有用
- カラムは **`fillable`** で明示
- 状態は **PHP Enum**（`UserRole`, `EnrollmentStatus` 等）
- **`scope*`** メソッドで再利用可能なクエリ条件を定義

## コード品質ルール

| ルール | 詳細 |
|---|---|
| フォーマット | Laravel Pint（コミット前 `sail bin pint`）|
| ORM | Eloquent 中心。クエリビルダ・生SQLは原則使用しない（パフォーマンス上必要な箇所のみ例外）|
| N+1対策 | `with()` Eager Loading を適切に使う。Advance で `sail artisan db:monitor` 等で検知 |
| バリデーション | FormRequest クラスに分離。Controller では `$request->validated()` を使う |
| 認可 | Policy + `$this->authorize()` または FormRequest の `authorize()` メソッド |
| 状態管理 | PHP Enum（`EnrollmentStatus`, `UserRole`, `MockExamSessionStatus` 等）|
| ロール制御 | Middleware `EnsureUserRole`（ロール存在確認のみ）+ Policy（リソース固有認可）|
| 設定値 | `.env` で管理。コード内ハードコーディング禁止 |
| Basic API | フロント連携対象は Blade版 + JSON API版の二本立て（認証は Fortify セッション + CSRF）|
| Sanctum API | Basic（公開API実装）+ Advance（SPAから連携）の二段構成 |
| 例外 | ドメイン例外は `app/Exceptions/{Domain}/` に配置（例: `app/Exceptions/Enrollment/EnrollmentNotFoundException.php`）|

## テスト方針

- **Feature テスト**: HTTP リクエスト/レスポンス、認証認可、バリデーション、DBレコード反映
- **Unit テスト**: Service / UseCase / Repository などの単体ロジック
- **`RefreshDatabase` + `actingAs`** を基本パターンとする
- 既存テストパターンに倣って実装（提供プロジェクトには参考になるテストが含まれる）
- テスト配置の規約は `structure.md` 参照

### 必須テストパターン

| カテゴリ | 必須シナリオ |
|---|---|
| 取得系（index/show）| 正常系（フィルタ含む）+ 認可漏れ（他者リソースアクセス）|
| 登録系（store）| 正常系 + バリデーション失敗 |
| 更新/削除系 | 正常系 + 認可漏れ + 他リソース非更新確認 |
| ロール固有機能 | 各ロール（admin/coach/student）での挙動分岐 |

## Git運用

### ブランチ命名

- `feature/{ticket-name}` — 機能開発（例: `feature/mock-exam-grading`）
- `fix/{ticket-name}` — バグ修正（例: `fix/enrollment-status-bug`）
- `refactor/{area}` — リファクタリング

### コミットメッセージ

- 日本語可。Conventional Commits は強制しない
- 1コミット = 1論理変更を心がける
- 例: `mock-exam: 採点ロジックを実装`

### PR記述（7セクション必須）

```markdown
## 関連チケット
（要件シートのチケット名 or 番号、例: #B-03 enrollment 受講状態管理拡張）

## 調査内容
（何を読んだか、どこを確認したか — 既存コード / 既存テスト / 関連 Feature の挙動 等。**AIには書けない、本人が読まないと書けない要素**）

## 原因分析 / 設計判断
（バグ: 根本原因 / 新機能: なぜこの設計か・代替案を退けた理由 / リファクタ: なぜこの形か。**Why を中心に**、What の再掲は不要）

## 実装内容
（主要な変更点を **振る舞い単位** で箇条書き。ファイルリストではなく「何ができるようになったか」「何が直ったか」を書く）

## 自動テスト
（追加・修正したテストと、それが何を保証するか。`sail artisan test` 結果サマリ。テスト追加なしの場合は理由を明記）

## 動作確認

**手順**（再現できる粒度、操作順）:
1. ログイン: ...
2. ...

**スクショ or 動画（必須）**:
- 静的UI（一覧 / 詳細 / フォーム）: **スクリーンショット**
- 動的機能（タイマー / 状態遷移 / リアルタイム / モーダル / 非同期更新）: **動画必須**（Loom / 画面録画 / GIF）
- バグ修正: **修正前 / 修正後 の比較**
- 改修・拡張: 改修前 / 改修後 の比較
- リファクタリング: **テスト pass のスクショ** で代替可（画面変化なしのため）

## レビュー観点 / 自己評価
（不安な箇所、判断に迷った箇所、コーチに重点的に見てほしい点。**「特になし」は避ける** — 自己内省の機会として活用）
```

**PR 7セクションは AI 丸投げ排除設計の中核**（CLAUDE.md 参照）。受講生は **すべての PR で記述必須**。

各セクションの AI 耐性的役割:

| セクション | AI 耐性 | 役割 |
|---|---|---|
| 関連チケット | — | レビュー起点、要件との紐づけ |
| 調査内容 | ◎ | **本人が読まないと書けない**（docs/コード参照） |
| 原因分析 / 設計判断 | ◎ | **Why の言語化**（AI は What を書きがち）|
| 実装内容 | ○ | 振る舞い単位の整理（コピペ排除）|
| 自動テスト | ○ | テスト存在の証明 |
| 動作確認（スクショ・動画） | ◎ | **AI 生成不可**（実機操作が必要）|
| レビュー観点 / 自己評価 | ◎ | **自分の理解の限界を言語化**（AI は不安を持たない）|
