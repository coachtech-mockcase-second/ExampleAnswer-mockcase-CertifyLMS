# analytics-export 要件定義

> **v3 改修反映**（2026-05-16）: `Enrollment.assigned_coach_id` カラム削除に伴う API 出力影響、`questions` テーブル廃止 → `section_questions` / `mock_exam_questions` 分離影響、`mock-exam-sessions` の `category_breakdown` 集計クエリを `MockExamQuestion` ベースに修正、`User.status` enum 拡張（`graduated` 値追加）に伴う filter 拡張、`User.plan_*` カラム追加に伴う Resource 拡張。

## 概要

admin / coach 向け管理運用データエクスポート API。`X-API-KEY` ヘッダ + `.env` の共通キー（`ANALYTICS_API_KEY`）で保護された **読み取り専用 JSON API** を 3 本提供する（`GET /api/v1/admin/users` / `enrollments` / `mock-exam-sessions`）。BE は素データのみ整形し、加工・集計は Google Apps Script + Google Sheets 側の責務とする。

## ロールごとのストーリー

- **管理者（admin）**: LMS 運用者として `.env` の `ANALYTICS_API_KEY` を発行し、GAS の Script Properties に貼り付ける。Google Sheets から GAS スクリプトを実行して受講生 / 受講登録 / 模試結果の素データを Sheet に流し込み、Sheet 関数・ピボット・条件付き書式で「全体 KPI」「資格別合格率分布」「コーチ稼働状況」等の運用分析を Sheet 内で組み立てる。
- **コーチ（coach）**: admin と共通の API キーで API を叩き、admin 視点で全件返却された素データから **GAS 側で「自分の担当資格に登録した受講生のみ」のフィルタ** を実装（`Enrollment.certification` 経由で coach assignment を判定）。
- **受講生（student）**: 直接 API を叩く動線はない（運用向け）。受講生は本 Feature の実装責務を担う。
- **API クライアント（GAS）**: `X-API-KEY` ヘッダ付きで API を叩き、Laravel 標準ページネーション JSON を受け取って `UrlFetchApp` 経由で Sheet に書き込む。

## 受け入れ基準（EARS形式）

### 機能要件 — A. API キー認証 / Middleware

- **REQ-analytics-export-001**: The system shall `app/Http/Middleware/ApiKeyMiddleware.php` を提供し、HTTP リクエストの `X-API-KEY` ヘッダ値を `config('analytics-export.api_key')` と比較する。一致しない / 欠落 / 空文字の場合は HTTP 401 + JSON ボディ `{"message": "API キーが無効です。", "error_code": "INVALID_API_KEY", "status": 401}` を返却する。
- **REQ-analytics-export-002**: The system shall `Kernel.php` の `$middlewareAliases` に `'api.key' => ApiKeyMiddleware::class` を登録する。
- **REQ-analytics-export-003**: The system shall `config/analytics-export.php` を新規作成し、`api_key` キーに `env('ANALYTICS_API_KEY')` を割り当てる。
- **REQ-analytics-export-004**: The system shall `.env.example` に `ANALYTICS_API_KEY=` プレースホルダ行を追加する。
- **REQ-analytics-export-005**: If `config('analytics-export.api_key')` が空文字 / null, then the system shall HTTP 503 + JSON `{"message": "API キー未設定", "error_code": "API_KEY_NOT_CONFIGURED", "status": 503}` で拒否する。
- **REQ-analytics-export-006**: The system shall API キー比較を `hash_equals` で実装する（タイミング攻撃耐性）。
- **REQ-analytics-export-007**: When `ApiKeyMiddleware` が認可エラー / 設定エラーを返す際, the system shall `Content-Type: application/json; charset=UTF-8` を常時付与する。

### 機能要件 — B. /api/v1/admin/users エンドポイント

- **REQ-analytics-export-010**: When API クライアントが `GET /api/v1/admin/users` を叩いた際, the system shall `users` テーブルから `deleted_at IS NULL` かつ `status != 'withdrawn'` の `User` を `created_at ASC` で取得し、`UserResource::collection($paginator)` 形式の JSON で返却する。
- **REQ-analytics-export-011**: The system shall `UserResource` で以下を返す: `id` / `name` / `email` / `role` / `status`（`invited` / `in_progress` / `graduated` の 3 値、`withdrawn` 除外）/ `last_login_at` / **`plan_id`** / **`plan_started_at`** / **`plan_expires_at`** / **`max_meetings`** / `created_at` / `updated_at`。`password` / `remember_token` / `bio` / `avatar_url` / `profile_setup_completed` / `email_verified_at` / `meeting_url` は **絶対に含めない**。
- **REQ-analytics-export-012**: When `?role=admin|coach|student` クエリ指定時, the system shall `users.role` で絞り込む。
- **REQ-analytics-export-013**: When `?status=invited|in_progress|graduated` クエリ指定時, the system shall `users.status` で絞り込む（**`withdrawn` は除外済のため指定不可、指定時 422、v3 で `graduated` 追加**）。
- **REQ-analytics-export-014**: When `?per_page=N`（1〜500）指定時, the system shall N 件単位でページングする。デフォルト 100、`per_page > 500` は HTTP 422。
- **REQ-analytics-export-015**: When `?page=N` 指定時, the system shall そのページの結果を返す。範囲外は空 `data: []` + 正常 meta を返す。

### 機能要件 — C. /api/v1/admin/enrollments エンドポイント

- **REQ-analytics-export-020**: When API クライアントが `GET /api/v1/admin/enrollments` を叩いた際, the system shall `enrollments` を `created_at ASC` で取得し、`EnrollmentResource::collection($paginator)` 形式で返却する。
- **REQ-analytics-export-021**: The system shall `EnrollmentResource` で以下を返す: `id` / `user_id` / `certification_id` / `status`（`learning` / `passed` / `failed` の 3 値、`paused` 撤回）/ `current_term` / `exam_date` / `passed_at` / **`progress_rate`** / **`last_activity_at`** / `created_at` / `updated_at` / `user`（whenLoaded） / `certification`（whenLoaded）。**`assigned_coach_id` / `assigned_coach`（whenLoaded）は撤回**（v3 で削除されたカラム）。**`completion_requested_at` も撤回**。
- **REQ-analytics-export-022**: The system shall `progress_rate` を [[learning]] の `ProgressService::batchCalculate(Collection $enrollments): array<string, float>` の戻り値で **バッチ算出** する（N+1 回避）。
- **REQ-analytics-export-023**: The system shall `last_activity_at` を **[[learning]] が所有する `LastActivityService::batchLastActivityFor(Collection<Enrollment>): array<string, Carbon>`**(D5 確定、Phase D 導入) で算出する。本 Service は `LearningSession.ended_at` の MAX + `SectionQuestionAnswer.answered_at` の MAX を統合したバッチ集計を 1 クエリで提供する(`StagnationDetectionService` の旧機能を移管、所有 Feature は learning に確定)。
- **REQ-analytics-export-024**: When `?status=learning|passed|failed` クエリ指定時, the system shall 絞り込む。`paused` は指定不可（撤回）。
- **REQ-analytics-export-025**: When `?certification_id={ulid}` クエリ指定時, the system shall 絞り込む（`exists:certifications,id`）。
- **REQ-analytics-export-026**: When `?current_term=basic_learning|mock_practice` クエリ指定時, the system shall 絞り込む。
- **REQ-analytics-export-027**: **削除（v3 撤回）**: `?assigned_coach_id={ulid}` クエリは提供しない（`Enrollment` から `assigned_coach_id` カラム削除）。コーチ別フィルタは GAS 側で `Enrollment.certification` 経由 `certification_coach_assignments` を別途取得して結合する。または、本 API に新規エンドポイント `GET /api/v1/admin/certification-coach-assignments` を追加する（次期スコープ）。
- **REQ-analytics-export-028**: When `?include=user,certification` クエリ指定時, the system shall Eager Loading し、`whenLoaded()` で出力する。許容値は `user` / `certification` のみ（`assigned_coach` 撤回）。

### 機能要件 — D. /api/v1/admin/mock-exam-sessions エンドポイント

- **REQ-analytics-export-030**: When API クライアントが `GET /api/v1/admin/mock-exam-sessions` を叩いた際, the system shall `mock_exam_sessions` を `created_at ASC` で取得し、`MockExamSessionResource::collection($paginator)` 形式で返却する。
- **REQ-analytics-export-031**: The system shall `MockExamSessionResource` で以下を返す: `id` / `user_id` / `mock_exam_id` / `enrollment_id` / `status` / `total_correct` / **`passing_score_snapshot`** / `pass` / `started_at` / `submitted_at` / `graded_at` / **`category_breakdown`**（JSON 配列、各要素 `{ "category_id": ulid, "category_name": string, "correct": int, "total": int, "rate": float }`） / `created_at` / `user`（whenLoaded） / `mock_exam`（whenLoaded） / `enrollment`（whenLoaded）。
- **REQ-analytics-export-032**: The system shall `category_breakdown` を [[mock-exam]] の `WeaknessAnalysisService::batchHeatmap(Collection $sessions): array` で **バッチ算出** する。**集計クエリは `MockExamAnswer JOIN MockExamQuestion` ベースに修正**（v3 改修で `MockExamQuestion` が独自リソース化されたため、旧 `Question` JOIN は撤回）。`graded` 以外のセッションは空配列 `[]`。
- **REQ-analytics-export-033**: When `?mock_exam_id={ulid}` クエリ指定時, the system shall 絞り込む。
- **REQ-analytics-export-034**: When `?pass=true|false` クエリ指定時, the system shall 絞り込む。
- **REQ-analytics-export-035**: When `?from=YYYY-MM-DD` / `?to=YYYY-MM-DD` クエリ指定時, the system shall `submitted_at` で範囲絞り込みする。
- **REQ-analytics-export-036**: When `?status=...` クエリ指定時, the system shall 絞り込む。
- **REQ-analytics-export-037**: When `?include=user,mock_exam,enrollment` クエリ指定時, the system shall Eager Loading する。
- **REQ-analytics-export-038**: If `?pass` が boolean 不可な値の場合, then HTTP 422。
- **REQ-analytics-export-039**: If `?from` / `?to` が `Y-m-d` 形式でない / `?from > ?to`, then HTTP 422。

### 機能要件 — E. ルート / グローバル仕様

- **REQ-analytics-export-040**: The system shall `routes/api.php` に `Route::prefix('v1/admin')->middleware(['api.key', 'throttle:60,1'])->name('api.v1.admin.')->group(...)` を定義する。
- **REQ-analytics-export-041**: The system shall API レスポンスを Laravel 標準 `data` / `meta` / `links` 形式で返す。
- **REQ-analytics-export-042**: The system shall `Accept` ヘッダの有無に関わらず常に JSON で返却する。
- **REQ-analytics-export-043**: When `throttle:60,1` 上限超過, the system shall HTTP 429 + JSON を返却する。
- **REQ-analytics-export-044**: The system shall `/api/v1/admin/...` 配下では Web セッション / Sanctum / Fortify を一切使用しない（`ApiKeyMiddleware` のみ）。
- **REQ-analytics-export-045**: When 未定義パスにアクセスした際, the system shall 404 JSON を返却する。

### 機能要件 — F. GAS スクリプト雛形 + 採点フロー

- **REQ-analytics-export-060**: The system shall `関連ドキュメント/analytics-export/gas-template.gs` を提供し、`getApiKey_()` / `getApiBaseUrl_()` / `fetchJson_(path, params)` / `fetchAllPages_(path, params)` のプライベート関数を含む。
- **REQ-analytics-export-061**: The system shall GAS 雛形には Fetch 関数だけを提供し、業務ロジックは含めない（受講生実装範囲）。
- **REQ-analytics-export-062**: The system shall `関連ドキュメント/analytics-export/README.md` を提供し、Sheet 作成 / Apps Script / Script Properties / 採点者シェア手順 / API キー漏洩防止注意を記述する。
- **REQ-analytics-export-063**: The system shall PR 動作確認の **4 必須項目**（Sheet URL / 採点者シェア / Sheet スクショ / GAS コード）を `関連ドキュメント/評価シート.md` に明記。

### 機能要件 — G. アクセス制御 / 認可

- **REQ-analytics-export-070**: The system shall `/api/v1/admin/...` を **共通 API キー方式のみ** で保護し、ユーザー単位のロール認可は BE では実装しない。
- **REQ-analytics-export-071**: The system shall API キーが LMS 運用者に共有される運用前提とする。
- **REQ-analytics-export-072**: The system shall PR の動作確認スクショ / Sheet 共有時に API キーを写さない / 貼らないことを README.md に明記する。

## 非機能要件

- **NFR-analytics-export-001**: The system shall `/api/v1/admin/...` 配下の Controller を薄く保つ（`Model::with()->paginate()` + `Resource::collection()` + バッチ集計サービス呼出）。
- **NFR-analytics-export-002**: The system shall N+1 を回避する: (a) `?include` で Eager Loading、(b) `progress_rate` / `last_activity_at` / `category_breakdown` をバッチサービスで一括計算。
- **NFR-analytics-export-003**: The system shall `hash_equals` で API キー比較する。
- **NFR-analytics-export-004**: The system shall レスポンスのフィールドキーを snake_case で統一する。
- **NFR-analytics-export-005**: The system shall 日時を ISO 8601（`toIso8601String()`）、日付を `Y-m-d` で統一する。
- **NFR-analytics-export-006**: The system shall CORS を Laravel デフォルトのままとする。
- **NFR-analytics-export-007**: The system shall 日本語エラーメッセージを集約する。
- **NFR-analytics-export-008**: The system shall Feature テストを `tests/Feature/Http/Api/Admin/` 配下に配置する。各エンドポイントで キー一致 200 / キー不一致 401 / キー欠落 401 / キー未設定 503 の 4 ケースを保証。
- **NFR-analytics-export-009**: The system shall Resource フィールドの破壊的変更を運用ルールで管理する。
- **NFR-analytics-export-010**: The system shall すべての SQL を Eloquent ベースで実装し、生 SQL を使わない。
- **NFR-analytics-export-011**: The system shall API レスポンスを必ず Resource 経由で返す（カラム漏洩防止）。

## スコープ外

- 書き込み系 API（POST / PUT / DELETE）— 各 Feature 自前
- ユーザー単位ロール認可 / 個人トークン管理 UI / Sanctum PAT / OAuth — LMS 全体で不採用
- 集計ロジックの BE 計算 — GAS / Sheet 側責務
- LMS 画面での分析ダッシュボード — [[dashboard]] Feature
- 採点用 Sheet テンプレート提供 — 受講生がゼロから作る
- GAS の認証（OAuth / Web app デプロイ）— Script Properties のみ
- API ドキュメント自動生成（OpenAPI）— 手書き仕様で代替
- API レスポンス圧縮 / ETag / 条件付き GET — Laravel デフォルト
- API キーローテーション / 期限 / 失効履歴
- 複数 API キー（環境別 / 用途別）
- abilities / scope の API キー版
- `mock-exam-sessions` の問題単位解答ログ取得 — 集計値のみ
- `learning_sessions` / `section_progresses` / `section_question_answers` の API 公開 — 集計済値で代替
- XML / CSV 形式対応 — JSON のみ
- WebSocket / SSE push — [[notification]] の Broadcasting が責務、本 Feature では扱わない（読み取り専用エクスポート API のみ）

## 関連 Feature

- **依存元**: なし
- **依存先**:
  - [[auth]] — `User` モデル / `UserRole` / `UserStatus` Enum（`graduated` 値追加に伴う API 出力対応）
  - [[user-management]] — User の `withdrawn` 除外
  - [[certification-management]] — `Certification` モデル
  - [[enrollment]] — `Enrollment` モデル / `EnrollmentStatus`（`paused` 撤回）/ `TermType`（読み取り再利用）
  - [[learning]] — `ProgressService::batchCalculate` / `last_activity_at` バッチサービスの **読み取り契約に依存**。`StagnationDetectionService` 撤回に伴い、本 Feature 内に専用バッチ集計を持つか [[learning]] に追加する
  - [[mock-exam]] — `MockExam` / `MockExamSession` / `MockExamSessionStatus` / `WeaknessAnalysisService::batchHeatmap`（`MockExamAnswer JOIN MockExamQuestion` 経由、`MockExamQuestion` 独自リソース化対応）
  - [[plan-management]] — `User.plan_*` カラムの読み取り（`UserResource` で出力）
