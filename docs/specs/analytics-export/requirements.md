# analytics-export 要件定義

## 概要

admin / coach 向け管理運用データエクスポート API。`X-API-KEY` ヘッダ + `.env` の共通キー（`ANALYTICS_API_KEY`）で保護された **読み取り専用 JSON API** を 3 本提供する（`GET /api/v1/admin/users` / `enrollments` / `mock-exam-sessions`）。BE は素データのみ整形し、加工・集計は Google Apps Script + Google Sheets 側の責務とする（BookShelf 公開 API の延長 + 認証 Middleware + GAS 連携の複合題材）。シンプル構成: `Controller` + JSON `Resource` + `IndexRequest` + `ApiKeyMiddleware` のみで、Action / 独自 Service / Policy / Sanctum は不採用（`enrollments` の `progress_rate` / `last_activity_at` 算出 と `mock-exam-sessions` の `category_breakdown` 算出のために [[learning]] / [[mock-exam]] 所有の既存 Service を Controller 内で **読み取り再利用** する例外あり）。受講生は採点者向け Google Sheets を発行し、PR 動作確認に **Sheet URL + 採点者シェア + Sheet スクショ + GAS コード** の 4 点を貼る運用とする。

## ロールごとのストーリー

- **管理者（admin）**: LMS 運用者として `.env` の `ANALYTICS_API_KEY` を発行し、GAS の Script Properties に貼り付ける。Google Sheets から GAS スクリプトを実行して受講生 / 受講登録 / 模試結果の素データを Sheet に流し込み、Sheet 関数・ピボット・条件付き書式で「全体 KPI」「資格別合格率分布」「滞留検知リスト」「コーチ稼働状況」等の運用分析を Sheet 内で組み立てる。
- **コーチ（coach）**: admin と共通の API キーで API を叩き、admin 視点で全件返却された素データから **GAS 側で「自分の担当受講生のみ」のフィルタ** を実装して担当範囲の進捗ランキング / 滞留検知を行う。
- **受講生（student）**: 直接 API を叩く動線はない（API は admin / coach の運用向け、共通 API キーは LMS 運用者が管理）。受講生は本 Feature の **実装責務**（`ApiKeyMiddleware` / 3 Controller / 3 Resource / 3 IndexRequest / `routes/api.php` 登録 + GAS スクリプト + Sheet 設計 + 採点者シェア手順）を担当する。
- **API クライアント（GAS）**: `X-API-KEY` ヘッダ付きで `GET /api/v1/admin/users` 等を叩き、Laravel 標準の `data` / `meta` / `links` 形式のページネーション JSON を受け取って `UrlFetchApp` 経由で Sheet に書き込む。

## 受け入れ基準（EARS形式）

### 機能要件 — A. API キー認証 / Middleware

- **REQ-analytics-export-001**: The system shall `app/Http/Middleware/ApiKeyMiddleware.php` を提供し、HTTP リクエストの `X-API-KEY` ヘッダ値を `config('analytics-export.api_key')` と比較する。値が一致しない / ヘッダが欠落している / 空文字の場合は HTTP 401 Unauthorized + JSON ボディ `{"message": "API キーが無効です。", "error_code": "INVALID_API_KEY", "status": 401}` を返却する。
- **REQ-analytics-export-002**: The system shall `app/Http/Kernel.php` の `$middlewareAliases` に `'api.key' => ApiKeyMiddleware::class` を登録し、`routes/api.php` のグループ Middleware から `'api.key'` で参照可能にする。
- **REQ-analytics-export-003**: The system shall `config/analytics-export.php` を新規作成し、`api_key` キーに `env('ANALYTICS_API_KEY')` を割り当てる。Middleware / Controller / Test からは `config('analytics-export.api_key')` 経由で取得し、`env()` 直接呼出やコード内ハードコーディングを禁止する。
- **REQ-analytics-export-004**: The system shall `.env.example` に `ANALYTICS_API_KEY=` プレースホルダ行を追加し、実値は各環境の `.env` で設定する。CI / 模範解答プロジェクトの初期 `.env` には 32 文字以上のランダム文字列をシード値として設定する。
- **REQ-analytics-export-005**: If `config('analytics-export.api_key')` が空文字 / null の場合, then the system shall すべての `/api/v1/admin/...` リクエストを HTTP 503 Service Unavailable + JSON ボディ `{"message": "API キー未設定", "error_code": "API_KEY_NOT_CONFIGURED", "status": 503}` で拒否する（環境設定漏れの早期検知、本番 503 のほうがプラットフォーム停止メッセージとして自然）。
- **REQ-analytics-export-006**: The system shall `ApiKeyMiddleware` の API キー比較を **`hash_equals(string $known, string $user)` で実装** し、タイミング攻撃耐性を担保する（PHP 標準関数、Laravel Sanctum 内部でも同パターン）。
- **REQ-analytics-export-007**: When `ApiKeyMiddleware` が認可エラー / 設定エラーを返す際, the system shall `Content-Type: application/json; charset=UTF-8` を常時付与し、Blade テンプレート / HTML 形式エラーへフォールバックしない。

### 機能要件 — B. /api/v1/admin/users エンドポイント

- **REQ-analytics-export-010**: When API クライアントが `GET /api/v1/admin/users` を `X-API-KEY` 付きで叩いた際, the system shall `users` テーブルから `deleted_at IS NULL` かつ `status != 'withdrawn'` の `User` を `created_at ASC` で取得し、`UserResource::collection($paginator)` 形式の JSON で返却する。
- **REQ-analytics-export-011**: The system shall `UserResource` で以下フィールドを返す: `id`（ULID）/ `name`（nullable、`invited` 状態では null）/ `email` / `role`（`UserRole` enum 値）/ `status`（`UserStatus` enum 値）/ `last_login_at`（ISO 8601 / nullable）/ `created_at`（ISO 8601）/ `updated_at`（ISO 8601）。`password` / `remember_token` / `bio` / `avatar_url` / `profile_setup_completed` / `email_verified_at` は **絶対に含めない**（センシティブカラム + 分析不要カラムの両観点）。
- **REQ-analytics-export-012**: When `?role=admin|coach|student` クエリが指定された際, the system shall `users.role` で絞り込む。
- **REQ-analytics-export-013**: When `?status=invited|active` クエリが指定された際, the system shall `users.status` で絞り込む（`withdrawn` は REQ-016 で除外済のため指定不可、指定時は 422）。
- **REQ-analytics-export-014**: When `?per_page=N`（1〜500）が指定された際, the system shall N 件単位でページングする。デフォルトは 100、`per_page > 500` の場合は HTTP 422 を返す（`IndexRequest` バリデーション）。
- **REQ-analytics-export-015**: When `?page=N` が指定された際, the system shall そのページに対応する結果を返す。`page` 範囲外は空の `data: []` + 正常 meta を返す（Laravel デフォルト挙動）。
- **REQ-analytics-export-016**: The system shall `withdrawn` ステータスのユーザー（SoftDelete されている）を **`UserResource` 出力に含めない**（運用分析でアクティブ + 招待中のみが意味を持つ）。
- **REQ-analytics-export-017**: If `?role` / `?status` のクエリが Enum の値以外の場合, then the system shall HTTP 422 を `IndexRequest` バリデーションで返却する。

### 機能要件 — C. /api/v1/admin/enrollments エンドポイント

- **REQ-analytics-export-020**: When API クライアントが `GET /api/v1/admin/enrollments` を叩いた際, the system shall `enrollments` テーブルから `deleted_at IS NULL` の `Enrollment` を `created_at ASC` で取得し、`EnrollmentResource::collection($paginator)` 形式で返却する。
- **REQ-analytics-export-021**: The system shall `EnrollmentResource` で以下フィールドを返す: `id` / `user_id` / `certification_id` / `assigned_coach_id`（nullable）/ `status`（`EnrollmentStatus` enum 値）/ `current_term`（`TermType` enum 値）/ `exam_date`（`Y-m-d` / nullable）/ `passed_at`（ISO 8601 / nullable）/ `completion_requested_at`（ISO 8601 / nullable）/ `progress_rate`（float 0..1）/ `last_activity_at`（ISO 8601 / nullable）/ `created_at` / `updated_at` / `user`（whenLoaded、`UserResource`）/ `certification`（whenLoaded、`CertificationResource`）/ `assigned_coach`（whenLoaded、`UserResource`）。
- **REQ-analytics-export-022**: The system shall `progress_rate` を [[learning]] 所有 `ProgressService::batchCalculate(Collection $enrollments): array<string, float>`（key = enrollment_id、value = 0.0〜1.0 の進捗率）の戻り値で **バッチ算出** し、Resource 内で対応する値を引く。Eloquent ループ内で `ProgressService::calculateForEnrollment` を呼ばない（N+1 回避、NFR-002）。
- **REQ-analytics-export-023**: The system shall `last_activity_at` を [[learning]] 所有 `StagnationDetectionService::batchLastActivityFor(Collection $enrollments): array<string, ?Carbon>`（key = enrollment_id、value = 学習活動 / 解答活動の MAX 時刻 or null）の戻り値で **バッチ算出** し、Resource 内で対応する値を引く。Eloquent ループ内で `StagnationDetectionService::lastActivityFor` を呼ばない（N+1 回避）。`last_activity_at` の算出ロジック自体（`LearningSession.ended_at` の MAX と `Answer.answered_at` の MAX の MAX）は [[learning]] / `StagnationDetectionService` 側責務。
- **REQ-analytics-export-024**: When `?status=learning|paused|passed|failed` クエリが指定された際, the system shall `enrollments.status` で絞り込む。
- **REQ-analytics-export-025**: When `?certification_id={ulid}` クエリが指定された際, the system shall `enrollments.certification_id` で絞り込む。`Certification` 存在チェックはバリデーションで `exists:certifications,id` する。
- **REQ-analytics-export-026**: When `?current_term=basic_learning|mock_practice` クエリが指定された際, the system shall `enrollments.current_term` で絞り込む。
- **REQ-analytics-export-027**: When `?assigned_coach_id={ulid}` クエリが指定された際, the system shall `enrollments.assigned_coach_id` で絞り込む（coach 担当者別の分析用、本フィルタも `exists:users,id` する）。
- **REQ-analytics-export-028**: When `?include=user,certification,assigned_coach` クエリが指定された際, the system shall 該当リレーションを `with(...)` で Eager Loading し、`EnrollmentResource` の `whenLoaded()` で出力に含める。`?include` の許容値は `user` / `certification` / `assigned_coach` のみで、不正値（例: `user,foo`）は **`foo` 部分のみ無視** し、残り（`user`）は通常通り Eager Loading する（バリデーションエラーにしない、寛容運用）。

### 機能要件 — D. /api/v1/admin/mock-exam-sessions エンドポイント

- **REQ-analytics-export-030**: When API クライアントが `GET /api/v1/admin/mock-exam-sessions` を叩いた際, the system shall `mock_exam_sessions` テーブルから `deleted_at IS NULL` の `MockExamSession` を `created_at ASC` で取得し、`MockExamSessionResource::collection($paginator)` 形式で返却する。
- **REQ-analytics-export-031**: The system shall `MockExamSessionResource` で以下フィールドを返す: `id` / `user_id` / `mock_exam_id` / `enrollment_id`（Enrollment 経由集計用）/ `status`（`MockExamSessionStatus` enum 値）/ `total_score`（int / nullable、未採点時 null）/ `passing_score_threshold`（int、MockExam.passing_score を都度参照、非正規化複写は持たない）/ `pass`（bool / nullable）/ `started_at` / `submitted_at` / `graded_at`（ISO 8601 / nullable）/ `category_breakdown`（JSON 配列、各要素 `{ "category_id": ulid, "category_name": string, "correct": int, "total": int, "rate": float }`）/ `created_at` / `user`（whenLoaded）/ `mock_exam`（whenLoaded）/ `enrollment`（whenLoaded）。
- **REQ-analytics-export-032**: The system shall `category_breakdown` を [[mock-exam]] 所有 `WeaknessAnalysisService::batchHeatmap(Collection $sessions): array<string, array>`（key = session_id、value = カテゴリ別正答率配列）で **バッチ算出** し、Resource 内で対応する値を引く（N+1 回避）。`graded` 以外の状態のセッションは空配列 `[]` を返す（未採点）。
- **REQ-analytics-export-033**: When `?mock_exam_id={ulid}` クエリが指定された際, the system shall `mock_exam_sessions.mock_exam_id` で絞り込む（`exists:mock_exams,id` バリデーション）。
- **REQ-analytics-export-034**: When `?pass=true|false` クエリが指定された際, the system shall `mock_exam_sessions.pass` で絞り込む。`pass = NULL`（未採点）のセッションは `?pass` 指定時には除外される。
- **REQ-analytics-export-035**: When `?from=YYYY-MM-DD` / `?to=YYYY-MM-DD` クエリが指定された際, the system shall `mock_exam_sessions.submitted_at >= from 00:00:00` および `submitted_at <= to 23:59:59` で絞り込む（`from` / `to` ともに inclusive）。
- **REQ-analytics-export-036**: When `?status=not_started|in_progress|submitted|graded|canceled` クエリが指定された際, the system shall `mock_exam_sessions.status` で絞り込む。
- **REQ-analytics-export-037**: When `?include=user,mock_exam,enrollment` クエリが指定された際, the system shall 該当リレーションを Eager Loading する。許容値は `user` / `mock_exam` / `enrollment` のみで、不正値は無視（REQ-028 と同流儀）。
- **REQ-analytics-export-038**: If `?pass` クエリが boolean 解釈不可能な値（`yes` / `1` 以外の数値 等）の場合, then the system shall HTTP 422 を返却する（Laravel `boolean` rule は `1|0|true|false` のみを受け付ける）。
- **REQ-analytics-export-039**: If `?from` / `?to` クエリが `Y-m-d` 形式でない場合, または `?from > ?to` の場合, then the system shall HTTP 422 を `IndexRequest` バリデーションで返却する。

### 機能要件 — E. ルート / グローバル仕様

- **REQ-analytics-export-040**: The system shall `routes/api.php` に `Route::prefix('v1/admin')->middleware(['api.key', 'throttle:60,1'])->name('api.v1.admin.')->group(...)` グループを定義し、上記 3 リソースの `index` ルートをそのグループ内に登録する（`users` / `enrollments` / `mock-exam-sessions` の各 `Route::get(..., [Controller::class, 'index'])`）。
- **REQ-analytics-export-041**: The system shall API レスポンスのトップレベル構造を Laravel 標準 `JsonResource::collection()->paginate()` の `data` / `meta`（`current_page` / `last_page` / `per_page` / `total` / `from` / `to` / `path`）/ `links`（`first` / `last` / `prev` / `next`）形式で返す。
- **REQ-analytics-export-042**: The system shall `Accept: application/json` ヘッダの有無に関わらず常に JSON 形式で返却する（API 専用 prefix、Web セッションでアクセスする想定なし）。
- **REQ-analytics-export-043**: When `throttle:60,1`（IP ベース、60 リクエスト/分）の上限を超過した際, the system shall HTTP 429 Too Many Requests + JSON `{"message": "リクエスト過多", "error_code": "RATE_LIMIT_EXCEEDED", "status": 429}` を返却する（`app/Exceptions/Handler.php` で `ThrottleRequestsException` の JSON 形式分岐を確認する）。
- **REQ-analytics-export-044**: The system shall `/api/v1/admin/...` 配下では Web セッション（Cookie）/ Sanctum / Fortify を **一切使用しない**（`ApiKeyMiddleware` のみで認可、`auth` / `auth:sanctum` Middleware は適用しない）。Sanctum SPA 認証は [[quiz-answering]] の `/api/v1/quiz/...` 専用で、本 Feature とは別系統。
- **REQ-analytics-export-045**: When API リクエストの URI に `/api/v1/admin` 配下の未定義パスにアクセスした際, the system shall Laravel 標準の 404 JSON 返却（`Exceptions/Handler.php` の JSON 形式分岐）で `{"message": "Not Found", "status": 404}` を返却する。

### 機能要件 — F. GAS スクリプト雛形 + 採点フロー

- **REQ-analytics-export-060**: The system shall `関連ドキュメント/analytics-export/gas-template.gs` を提供し、以下を含む: (a) `getApiKey_()` プライベート関数（Script Properties から `ANALYTICS_API_KEY` を取得）、(b) `getApiBaseUrl_()` プライベート関数（Script Properties から `ANALYTICS_BASE_URL` を取得）、(c) `fetchJson_(path, params = {})` プライベート関数（`UrlFetchApp.fetch` でヘッダ `X-API-KEY` 付き GET、`muteHttpExceptions: true` で 4xx/5xx 時にも例外化せず JSON を読み取り）、(d) `fetchAllPages_(path, params = {})` プライベート関数（ページネーション全頁取得ループ、`?page=N` を 1 から `meta.last_page` まで増やす）、(e) コメントによる利用方法説明 + 採点者シェア手順への参照。
- **REQ-analytics-export-061**: The system shall GAS 雛形（`gas-template.gs`）に **「どの API を叩いて、どの Sheet に、どう出力するか」のロジックを含めない**（受講生実装範囲、Fetch 関数だけ提供）。
- **REQ-analytics-export-062**: The system shall `関連ドキュメント/analytics-export/README.md` を提供し、以下を記述する: (a) Sheet 新規作成手順（Google Drive）、(b) Apps Script エディタの開き方（`拡張機能 → Apps Script`）、(c) Script Properties の設定方法（`プロジェクトの設定 → スクリプト プロパティ`）、(d) `gas-template.gs` のコピペ手順、(e) GAS 実行 → Sheet データ反映確認、(f) **採点者シェア手順**（採点者 Google アカウントに閲覧権限を付与、`Anyone with the link` は NG、`Restricted` で特定アカウント招待）、(g) PR 動作確認に貼るべき 4 点（Sheet URL / 採点者シェア有無 / Sheet スクショ / GAS コード）、(h) API キーを Sheet / スクショに写さない注意。
- **REQ-analytics-export-063**: The system shall PR 動作確認の **4 必須項目** をすべて満たさない PR を採点で減点する旨を `関連ドキュメント/評価シート.md` に明記する想定で、評価シート側に「analytics-export」項目を追加することを `関連ドキュメント/完全手順書_*.md` 作成時（[[product.md]] の Step 5）に組み込む（spec レベルでは記述のみ）。

### 機能要件 — G. アクセス制御 / 認可

- **REQ-analytics-export-070**: The system shall `/api/v1/admin/...` を **共通 API キー方式のみ**で保護し、ユーザー単位のロール認可（admin / coach / student 区別）を BE では実装しない（admin 視点で全件返却し、coach 別フィルタは GAS 側 + `?assigned_coach_id` クエリの組合せで実装）。
- **REQ-analytics-export-071**: The system shall API キーが LMS 運用者（admin / coach）に共有される運用前提とし、受講生に API キーを共有しない旨を `関連ドキュメント/analytics-export/README.md` に明記する（受講生は本 Feature 実装担当だが、運用時はキーを使う立場ではない）。
- **REQ-analytics-export-072**: The system shall PR の動作確認スクショ / Sheet 共有時に **API キーを写さない / Sheet に貼らない**ことを README.md の注意事項として明記する（GAS Script Properties に閉じ込め、Sheet 上には絶対に出力しない）。

## 非機能要件

- **NFR-analytics-export-001**: The system shall `/api/v1/admin/...` 配下のすべての Controller を **薄く保ち、ビジネスロジックを Controller 内に持たない**（`Model::with()->paginate()` + `Resource::collection()` の薄い構成、ただし `progress_rate` / `last_activity_at` / `category_breakdown` 算出のための [[learning]] / [[mock-exam]] 所有 Service 呼出は例外）。**Action / 独自 Service 層 / Policy は作らない**（既存 Service の読み取り再利用に閉じる）。
- **NFR-analytics-export-002**: The system shall N+1 を回避するため以下を実施: (a) `?include=user,certification,assigned_coach` で指定された関連リソースを `with(...)` で Eager Loading、(b) `/enrollments` の `progress_rate` / `last_activity_at` を `ProgressService::batchCalculate` / `StagnationDetectionService::batchLastActivityFor` でページ内 ID 群一括計算、(c) `/mock-exam-sessions` の `category_breakdown` を `WeaknessAnalysisService::batchHeatmap` でページ内 ID 群一括計算。
- **NFR-analytics-export-003**: The system shall `ApiKeyMiddleware` での API キー比較に **`hash_equals(string $known, string $user)` を利用**しタイミング攻撃を防ぐ（PHP 標準関数、Laravel Sanctum でも内部利用される慣行）。
- **NFR-analytics-export-004**: The system shall API レスポンスのフィールドキーを **snake_case で統一**（`last_login_at` / `progress_rate` / `assigned_coach_id` 等、Laravel デフォルト）。camelCase / PascalCase に変換しない（GAS 側のキー参照を簡素化）。
- **NFR-analytics-export-005**: The system shall API レスポンスの日時フィールドを **ISO 8601 形式 + UTC 表記**（`toIso8601String()`）で統一（`last_login_at` / `passed_at` / `submitted_at` 等）。日付のみのフィールド（`exam_date`）は `Y-m-d` 形式（`toDateString()`）。
- **NFR-analytics-export-006**: The system shall CORS 設定を Laravel `config/cors.php` のデフォルト（同一オリジン許可）のままとし、`/api/v1/admin/...` に対する追加 CORS 設定を行わない。GAS は `UrlFetchApp` で server-side fetch のため CORS 制約を受けず、ブラウザからの fetch は想定しない。
- **NFR-analytics-export-007**: The system shall すべての日本語エラーメッセージを `lang/ja/validation.php` / Middleware 内 / IndexRequest 内で定義し、コード内マジック文字列は避ける（`backend-exceptions.md` の精神を Middleware にも適用）。
- **NFR-analytics-export-008**: The system shall 本 Feature のすべての Feature テストを `tests/Feature/Http/Api/Admin/` 配下に配置する（`UserIndexTest.php` / `EnrollmentIndexTest.php` / `MockExamSessionIndexTest.php` / `ApiKeyMiddlewareTest.php`）。各エンドポイントに対し最低 4 ケースを保証: (a) キー一致 200 + JSON 構造検証、(b) キー不一致 401、(c) キー欠落 401、(d) キー未設定 503。
- **NFR-analytics-export-009**: The system shall API レスポンスが破壊的変更を起こさないよう、Resource フィールドの **削除 / 名称変更 / 型変更** を行う際は `v2/...` プレフィックス更新または明示的なドキュメント更新を経る運用ルールとする（spec / README 記述、コード強制力はなし、PR レビューで担保）。
- **NFR-analytics-export-010**: The system shall すべての SQL を `Eloquent` ベースで実装し、生 SQL / `DB::raw` を使わない（`tech.md`「コード品質ルール」準拠）。
- **NFR-analytics-export-011**: The system shall API レスポンスにシリアライズ前のモデルをそのまま JSON 化せず、必ず `Resource` クラスを経由する（カラム漏洩防止、`tech.md` `backend-http.md` の Resource 規約準拠）。

## スコープ外

- **書き込み系 API**（POST / PUT / DELETE）— 各 Feature が自前で生やす（[[quiz-answering]] の `/api/v1/quiz/...` 等）
- **ユーザー単位のロール認可 / 個人トークン管理 UI / Sanctum Personal Access Token / OAuth プロバイダ機能** — 共通 API キー方式のみ、本 Feature・LMS 全体で不採用（[[product.md]] スコープ外明示）
- **集計ロジックの BE 計算**（資格別合格率分布 / ロール別人数集計 / 滞留検知リスト整形 等）— GAS / Sheet 側責務、本 Feature は **素データ提供のみ**
- **LMS 画面での分析ダッシュボード** — [[dashboard]] Feature の責務
- **採点用 Sheet テンプレート提供** — 受講生がゼロから作る（Sheet 設計も評価対象）
- **GAS の認証（OAuth / Web app デプロイ）** — Script Properties に API キー保存のみ
- **API ドキュメント自動生成（OpenAPI / Swagger）** — 教育PJスコープ外、`関連ドキュメント/analytics-export/README.md` での手書き仕様で代替
- **API レスポンスの圧縮（gzip）/ ETag / 条件付き GET** — Laravel デフォルト挙動のみ、独自最適化なし
- **API キーのローテーション / 期限 / 失効履歴** — `.env` での手動更新のみ（教育PJスコープ）
- **複数 API キー（環境別 / 用途別）** — 1 つの共通キーで運用、複数キー管理は要件外
- **abilities / scope（Sanctum 用語）の API キー版** — 採用しない、共通キー = フル権限固定
- **`mock-exam-sessions` の問題単位の解答ログ取得**（`MockExamAnswer` の API 公開）— 本 Feature では `category_breakdown` の集計値のみ、個別解答行データは公開しない（粒度過剰 + データ量過大）
- **`learning_sessions` / `section_progress` / `answers` テーブルの API 公開** — 素データ粒度が粗すぎ Sheet 上で扱いづらいため未公開。`progress_rate` / `last_activity_at` のような集計済値で代替
- **API レスポンスの XML / CSV 形式対応** — JSON のみ（GAS が JSON 前提）
- **WebSocket / SSE による push 配信** — Laravel Broadcasting は [[notification]] の Advance 範囲、本 Feature とは別領域

## 関連 Feature

- **依存元**（本 Feature を利用する）: なし（本 Feature は LMS の最外周にあり、他 Feature から呼び出されない）
- **依存先**（本 Feature が前提とする）:
  - [[auth]] — `User` モデル / `UserRole` Enum / `UserStatus` Enum（読み取り再利用、`UserResource` で出力）。`auth` / `auth:sanctum` Middleware は **利用しない**
  - [[user-management]] — `User` の `withdrawn` ステータス除外ロジック（`status != withdrawn` フィルタ条件）
  - [[certification-management]] — `Certification` モデル（`?include=certification` で `EnrollmentResource` に同梱出力、専用 Resource は本 Feature では新設せず既存 `CertificationResource` を再利用する想定。[[certification-management]] 側で `CertificationResource` が `app/Http/Resources/` に置かれていない場合は本 Feature 内に **薄い `Api\Admin\CertificationResource`** を新設する）
  - [[enrollment]] — `Enrollment` モデル / `EnrollmentStatus` Enum / `TermType` Enum（読み取り再利用）
  - [[learning]] — `ProgressService::batchCalculate(Collection): array` / `StagnationDetectionService::batchLastActivityFor(Collection): array` の **読み取り契約 2 つに依存**。本 Feature 実装前に [[learning]] 側で `batch*` メソッドが提供されていない場合、[[learning]] の design.md / requirements.md に追記を依頼（spec-generate 横断調整）
  - [[mock-exam]] — `MockExam` モデル / `MockExamSession` モデル / `MockExamSessionStatus` Enum / `WeaknessAnalysisService::batchHeatmap(Collection): array` の **読み取り契約 1 つに依存**。[[mock-exam]] 側で `batchHeatmap` が提供されていない場合は同様に追記を依頼
