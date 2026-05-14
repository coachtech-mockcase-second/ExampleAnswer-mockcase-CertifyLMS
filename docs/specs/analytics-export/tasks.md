# analytics-export タスクリスト

> 1 タスク = 1 コミット粒度。Step 単位で順次実装し、完了したものから `[x]` に更新する。
> 関連要件 ID は `requirements.md` の `REQ-analytics-export-NNN` / `NFR-analytics-export-NNN` を参照。
> コマンドはすべて `sail` プレフィックス（`tech.md`「コマンド慣習」参照）。
>
> **本 Feature は Migration / Model / Enum / Action / 独自 Service / Policy を新設しない**。Controller + Resource + IndexRequest + ApiKeyMiddleware + Config + Handler 修正 + GAS テンプレート + README が成果物。

## Step 0: 依存 Feature の Service `batch*` メソッド確認

> **前提条件**: 以下メソッドが [[learning]] / [[mock-exam]] 側で実装済み or spec に記述済みであることを確認する。未提供なら所有 Feature 側 spec への追記を先に行う（spec-generate 横断調整、自分の `/feature-implement` を進める前のブロッカー）。

- [ ] 確認: `App\Services\ProgressService::batchCalculate(Collection $enrollments): array` が `docs/specs/learning/design.md` に記述されているか。未記述なら [[learning]] design.md / requirements.md / tasks.md に追記提案
- [ ] 確認: `App\Services\StagnationDetectionService::batchLastActivityFor(Collection $enrollments): array` が `docs/specs/learning/design.md` に記述されているか。未記述なら同上
- [ ] 確認: `App\Services\WeaknessAnalysisService::batchHeatmap(Collection $sessions): array` が `docs/specs/mock-exam/design.md` に記述されているか。未記述なら同上

## Step 1: Config & .env

- [ ] config: `config/analytics-export.php` を新規作成（`'api_key' => env('ANALYTICS_API_KEY')`）（REQ-analytics-export-003）
- [ ] `.env.example` に `ANALYTICS_API_KEY=` プレースホルダ行を追加（REQ-analytics-export-004）
- [ ] `.env`（模範解答プロジェクト初期値）に 32 文字以上のランダム文字列を `ANALYTICS_API_KEY` としてセード値設定（例: `bin2hex(random_bytes(16))` 相当）（REQ-analytics-export-004）

## Step 2: ApiKeyMiddleware

- [ ] Middleware: `App\Http\Middleware\ApiKeyMiddleware` 新規作成（`handle()` で `config('analytics-export.api_key')` 取得 + `X-API-KEY` ヘッダ取得 + 空チェック → 503 / `hash_equals` 比較 → 401 / 通過時 `$next($request)`、すべて JSON `response()->json()` 返却）（REQ-analytics-export-001, REQ-analytics-export-005, REQ-analytics-export-006, REQ-analytics-export-007, NFR-analytics-export-003）
- [ ] `app/Http/Kernel.php` の `$middlewareAliases` に `'api.key' => ApiKeyMiddleware::class` を追加（REQ-analytics-export-002）

## Step 3: HTTP 層 — IndexRequest / Resource / Controller / Route

### IndexRequest（`app/Http/Requests/Api/Admin/`）

- [ ] `App\Http\Requests\Api\Admin\User\IndexRequest`（クラス名 `IndexRequest`、`authorize` true、`role: Rule::enum(UserRole)` / `status: Rule::in([Invited, Active])` / `per_page: 1..500` / `page: min:1`、messages 日本語化）（REQ-analytics-export-012, REQ-analytics-export-013, REQ-analytics-export-014, REQ-analytics-export-015, REQ-analytics-export-017, NFR-analytics-export-007）
- [ ] `App\Http\Requests\Api\Admin\Enrollment\IndexRequest`（クラス名 `IndexRequest`、`status: Rule::enum(EnrollmentStatus)` / `certification_id: ulid + exists:certifications,id` / `current_term: Rule::enum(TermType)` / `assigned_coach_id: ulid + exists:users,id` / `include: string` / `per_page` / `page`）（REQ-analytics-export-024, REQ-analytics-export-025, REQ-analytics-export-026, REQ-analytics-export-027, REQ-analytics-export-028）
- [ ] `App\Http\Requests\Api\Admin\MockExamSession\IndexRequest`（クラス名 `IndexRequest`、`mock_exam_id: ulid + exists:mock_exams,id` / `pass: boolean` / `status: Rule::enum(MockExamSessionStatus)` / `from: date_format:Y-m-d` / `to: date_format:Y-m-d + after_or_equal:from` / `include` / `per_page` / `page`）（REQ-analytics-export-033, REQ-analytics-export-034, REQ-analytics-export-035, REQ-analytics-export-036, REQ-analytics-export-037, REQ-analytics-export-038, REQ-analytics-export-039）

### Resource（`app/Http/Resources/Api/Admin/`）

- [ ] `UserResource`（`id` / `name` / `email` / `role` / `status` / `last_login_at` / `created_at` / `updated_at`、センシティブカラム除外）（REQ-analytics-export-011, NFR-analytics-export-004, NFR-analytics-export-005, NFR-analytics-export-011）
- [ ] `CertificationResource`（薄い、`?include=certification` 同梱用、`id` / `code` / `name` / `category_id` / `difficulty` / `passing_score` / `total_questions` / `exam_duration_minutes` / `status` / `published_at` / `archived_at`）（REQ-analytics-export-028）
- [ ] `EnrollmentResource`（`id` / `user_id` / `certification_id` / `assigned_coach_id` / `status` / `current_term` / `exam_date` / `passed_at` / `completion_requested_at` / `progress_rate`（`$this->additional['_batch']['progress_rate'][$this->id]` 参照）/ `last_activity_at`（同上）/ `created_at` / `updated_at` / `user` / `certification` / `assigned_coach`（whenLoaded）、Resource ファイル冒頭にコメントで `additional['_batch']` パターン明示）（REQ-analytics-export-021, REQ-analytics-export-022, REQ-analytics-export-023, NFR-analytics-export-004, NFR-analytics-export-005）
- [ ] `MockExamResource`（薄い、`?include=mock_exam` 同梱用、`id` / `certification_id` / `title` / `order` / `is_published` / `passing_score` / `question_count` / `time_limit_minutes`）（REQ-analytics-export-037）
- [ ] `MockExamSessionResource`（`id` / `user_id` / `mock_exam_id` / `enrollment_id` / `status` / `total_score` / `passing_score_threshold`（`$this->mockExam?->passing_score`）/ `pass` / `started_at` / `submitted_at` / `graded_at` / `category_breakdown`（`$this->additional['_batch']['category_breakdown'][$this->id]`）/ `created_at` / `user` / `mock_exam` / `enrollment`（whenLoaded））（REQ-analytics-export-031, REQ-analytics-export-032, NFR-analytics-export-004, NFR-analytics-export-005）

### Controller（`app/Http/Controllers/Api/Admin/`）

- [ ] `UserController::index(IndexRequest $request): AnonymousResourceCollection`（`use App\Http\Requests\Api\Admin\User\IndexRequest;` で import、`User::query()->where('status', '!=', UserStatus::Withdrawn)->whereNull('deleted_at')->when(role)->when(status)->orderBy('created_at')->paginate(per_page)`、`UserResource::collection($users)` 返却）（REQ-analytics-export-010, REQ-analytics-export-016, NFR-analytics-export-001）
- [ ] `EnrollmentController` コンストラクタ DI（`ProgressService` / `StagnationDetectionService`）（REQ-analytics-export-022, REQ-analytics-export-023, NFR-analytics-export-001）
- [ ] `EnrollmentController::index(IndexRequest $request): AnonymousResourceCollection`（`use App\Http\Requests\Api\Admin\Enrollment\IndexRequest;` で import、`Enrollment::query()->whereNull('deleted_at')->when(各フィルタ)->with($this->mapIncludes(...))->orderBy('created_at')->paginate(per_page)` → `$progressMap = $this->progressService->batchCalculate(...)` → `$activityMap = $this->stagnationService->batchLastActivityFor(...)` → `EnrollmentResource::collection($enrollments)->additional(['_batch' => [...]])`）（REQ-analytics-export-020, REQ-analytics-export-022, REQ-analytics-export-023, REQ-analytics-export-024, REQ-analytics-export-025, REQ-analytics-export-026, REQ-analytics-export-027, REQ-analytics-export-028, NFR-analytics-export-002）
- [ ] `EnrollmentController` の private ヘルパ `resolveIncludes(?string $raw, array $allowed): array` + `mapIncludes(array $includes): array`（`?include=user,foo` → `['user']` への寛容 sanitize + Eloquent リレーション名へのマッピング）（REQ-analytics-export-028）
- [ ] `MockExamSessionController` コンストラクタ DI（`WeaknessAnalysisService`）（REQ-analytics-export-032）
- [ ] `MockExamSessionController::index(IndexRequest $request): AnonymousResourceCollection`（`use App\Http\Requests\Api\Admin\MockExamSession\IndexRequest;` で import、`MockExamSession::query()->whereNull('deleted_at')->when(各フィルタ)->with($this->mapIncludes(...))->orderBy('created_at')->paginate(per_page)` → `$heatmapMap = $this->weaknessService->batchHeatmap(...)` → `MockExamSessionResource::collection($sessions)->additional(['_batch' => [...]])`）（REQ-analytics-export-030, REQ-analytics-export-032, REQ-analytics-export-033, REQ-analytics-export-034, REQ-analytics-export-035, REQ-analytics-export-036, REQ-analytics-export-037, NFR-analytics-export-002）
- [ ] `MockExamSessionController` の private ヘルパ `resolveIncludes` / `mapIncludes`（同上の寛容 sanitize + マッピング、`mock_exam => mockExam`）（REQ-analytics-export-037）

### Route

- [ ] `routes/api.php` に `Route::prefix('v1/admin')->middleware(['api.key', 'throttle:60,1'])->name('api.v1.admin.')->group(fn () => Route::get('users', ...)->name('users.index') + Route::get('enrollments', ...)->name('enrollments.index') + Route::get('mock-exam-sessions', ...)->name('mock-exam-sessions.index'))` を追加。`auth` / `auth:sanctum` Middleware は含めず `api.key + throttle:60,1` のみ。レスポンスは Laravel 標準 `AnonymousResourceCollection` の `data / meta / links` 構造（独自整形なし）。`Accept` ヘッダの有無に依存せず常に JSON 返却（API 専用 prefix）。Controller 内では Eloquent のみ使用し `DB::raw` / 生 SQL を使わない（REQ-analytics-export-040, REQ-analytics-export-041, REQ-analytics-export-042, REQ-analytics-export-043, REQ-analytics-export-044, REQ-analytics-export-070, NFR-analytics-export-010）

## Step 4: Exceptions/Handler 修正

- [ ] `app/Exceptions/Handler.php` の `register()` に `$this->renderable(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $e, $request) { ... })` を追加し、`$request->is('api/*')` 時に JSON `{ message: 'リクエスト過多', error_code: 'RATE_LIMIT_EXCEEDED', status: 429 }` を返却（REQ-analytics-export-043, REQ-analytics-export-045）
- [ ] `app/Exceptions/Handler.php` の `register()` に `NotFoundHttpException` / `MethodNotAllowedHttpException` の API JSON 化を追加（既存の Handler が共通実装している場合は確認のみ、[[quiz-answering]] / [[learning]] の Handler 修正方針と整合）（REQ-analytics-export-045）

## Step 5: テスト

### Feature テスト（`tests/Feature/Http/Api/Admin/`）

- [ ] `ApiKeyMiddlewareTest`（4 ケース: (a) キー一致で `$next` 通過 200、(b) ヘッダ欠落で 401 `INVALID_API_KEY`、(c) キー不一致で 401、(d) `config.api_key` 空文字 / null で 503 `API_KEY_NOT_CONFIGURED`。`config(['analytics-export.api_key' => ...])` で各ケースの設定を切替）（REQ-analytics-export-001, REQ-analytics-export-005, REQ-analytics-export-006, NFR-analytics-export-008）
- [ ] `UserIndexTest`（(a) 全件取得 200 + JSON 構造（`data` / `meta` / `links`）/ `withdrawn` 除外検証、(b) `?role=student` フィルタ動作、(c) `?status=invited` フィルタ動作、(d) `?status=withdrawn` で 422、(e) `?per_page=200` ページネーション、(f) `?per_page=501` で 422、(g) センシティブカラム（`password` 等）が出力に含まれない検証、(h) キー不一致 401）（REQ-analytics-export-010, REQ-analytics-export-011, REQ-analytics-export-012, REQ-analytics-export-013, REQ-analytics-export-014, REQ-analytics-export-016, REQ-analytics-export-017, NFR-analytics-export-008）
- [ ] `EnrollmentIndexTest`（(a) 全件取得 + `progress_rate` / `last_activity_at` が含まれる検証（`ProgressService` / `StagnationDetectionService` の動的 mock or 実 DB 計算）、(b) `?status=learning` フィルタ動作、(c) `?certification_id=不正ULID` で 422、(d) `?include=user,certification,assigned_coach` で Eager Loading + JSON に同梱、(e) `?include=foo,user` で `foo` は無視されつつ `user` は同梱（REQ-analytics-export-028 の寛容 sanitize 検証）、(f) N+1 検証（`DB::enableQueryLog()` でクエリ数 < 10 程度）、(g) キー不一致 401）（REQ-analytics-export-020, REQ-analytics-export-021, REQ-analytics-export-022, REQ-analytics-export-023, REQ-analytics-export-024, REQ-analytics-export-025, REQ-analytics-export-028, NFR-analytics-export-002, NFR-analytics-export-008）
- [ ] `MockExamSessionIndexTest`（(a) 全件取得 + `category_breakdown` が含まれる検証（`graded` セッションのみ非空配列）、(b) `?pass=true` フィルタ動作、(c) `?from=2026-04-01&to=2026-04-30` 期間フィルタ動作、(d) `?status=graded` フィルタ動作、(e) `?from > to` で 422、(f) `?include=user,mock_exam,enrollment` で Eager Loading + JSON 同梱、(g) `passing_score_threshold` が `mock_exam.passing_score` と一致（whenLoaded）、(h) キー不一致 401）（REQ-analytics-export-030, REQ-analytics-export-031, REQ-analytics-export-032, REQ-analytics-export-033, REQ-analytics-export-034, REQ-analytics-export-035, REQ-analytics-export-036, REQ-analytics-export-037, REQ-analytics-export-038, REQ-analytics-export-039, NFR-analytics-export-002, NFR-analytics-export-008）
- [ ] `ThrottleResponseTest`（`/api/v1/admin/users` を 61 回連投 → 61 回目 429 + JSON `RATE_LIMIT_EXCEEDED`、Laravel `Cache::shouldReceive` か `RateLimiter::clear()` で初期化制御）（REQ-analytics-export-043）
- [ ] `NotFoundJsonTest`（`/api/v1/admin/undefined-path` で 404 + JSON 返却検証）（REQ-analytics-export-045）

> Factory で `User::factory()->admin() / coach() / student() / withdrawn()` / `Enrollment::factory()->learning() / passed() / failed()` / `MockExamSession::factory()->graded() / submitted() / inProgress()` 等の state は **各 Feature 所有 Factory** に既に存在する前提（[[auth]] / [[enrollment]] / [[mock-exam]] の Step 1 で実装済み）。未提供なら所有 Feature spec を確認。

## Step 6: GAS スクリプト雛形 + README ドキュメント

- [ ] `関連ドキュメント/analytics-export/` ディレクトリ作成
- [ ] `関連ドキュメント/analytics-export/gas-template.gs` 作成（`getApiKey_()` / `getApiBaseUrl_()` / `fetchJson_(path, params)` / `fetchAllPages_(path, params)` の 4 関数 + 利用方法コメント、Sheet 書き込みロジックは含めない）（REQ-analytics-export-060, REQ-analytics-export-061）
- [ ] `関連ドキュメント/analytics-export/README.md` 作成（(a) Sheet 新規作成 / (b) Apps Script エディタ / (c) Script Properties 設定 / (d) gas-template.gs コピペ / (e) GAS 実行 → Sheet 反映確認 / (f) 採点者シェア手順（Restricted + 特定アカウント招待）/ (g) PR 動作確認 4 点（Sheet URL / 採点者シェア有無 / Sheet スクショ / GAS コード）/ (h) API キー秘匿の注意 / (i) Resource フィールドの破壊的変更時の `v2` 昇格運用ルール記述）（REQ-analytics-export-062, REQ-analytics-export-063, REQ-analytics-export-071, REQ-analytics-export-072, NFR-analytics-export-009）

## Step 7: 動作確認 & 整形

- [ ] `sail artisan test --filter=Api\\\\Admin` で本 Feature テストが全件 pass（バックスラッシュエスケープに注意、PHPUnit `--filter` 仕様）
- [ ] `sail artisan test` 全体実行で他 Feature への副作用なし（特に [[learning]] / [[mock-exam]] / [[enrollment]] / [[auth]] の既存テストが green、`ProgressService` / `StagnationDetectionService` / `WeaknessAnalysisService` の batch 系メソッド呼出が他テストを壊さない）
- [ ] `sail bin pint --dirty` 整形
- [ ] **API 動作確認 curl**:
  ```bash
  curl -H "X-API-KEY: $ANALYTICS_API_KEY" "http://localhost/api/v1/admin/users?per_page=10" | jq
  curl -H "X-API-KEY: $ANALYTICS_API_KEY" "http://localhost/api/v1/admin/enrollments?status=learning&include=user,certification" | jq
  curl -H "X-API-KEY: $ANALYTICS_API_KEY" "http://localhost/api/v1/admin/mock-exam-sessions?status=graded&include=user,mock_exam" | jq
  ```
- [ ] **不正リクエスト確認**:
  ```bash
  # キーなし → 401
  curl "http://localhost/api/v1/admin/users" | jq
  # キー不一致 → 401
  curl -H "X-API-KEY: invalid" "http://localhost/api/v1/admin/users" | jq
  # クエリ不正 → 422
  curl -H "X-API-KEY: $ANALYTICS_API_KEY" "http://localhost/api/v1/admin/users?role=invalid" | jq
  ```
- [ ] **GAS 動作確認** (Step 6 で作成した README に従い):
  - [ ] Google Sheets 新規作成（`Certify LMS 分析 v1` 等）
  - [ ] 拡張機能 → Apps Script → `gas-template.gs` の中身を貼り付け
  - [ ] Script Properties に `ANALYTICS_API_KEY` / `ANALYTICS_BASE_URL` を設定
  - [ ] 受講生実装範囲のサンプル関数（例: `function importUsersToSheet() { const users = fetchAllPages_('/api/v1/admin/users'); ... }`）を追記 → 実行
  - [ ] Sheet に users データが反映されることを確認
  - [ ] 採点者の Google アカウントに閲覧権限を付与
- [ ] **Sheet スクショ** を撮影し PR 動作確認に添付（API キーが映らないよう注意）
- [ ] N+1 動作確認: `sail artisan db:monitor` または `DB::enableQueryLog()` を `/api/v1/admin/enrollments` 呼出時に有効化し、ページ内 Enrollment 数 N に対してクエリ数が `O(N)` でなく `O(1)` 〜 `O(log N)` で抑えられていること（NFR-analytics-export-002）
- [ ] レート制限動作確認: `for i in $(seq 1 70); do curl ... ; done` で 61 回目以降に 429 が返ることを確認（429 のレスポンスが JSON）
- [ ] CORS 設定確認: `config/cors.php` がデフォルトのままで本 Feature 専用の上書きが入っていないこと（NFR-analytics-export-006）
- [ ] `Accept` ヘッダなしでの動作確認: `curl -H "X-API-KEY: $KEY" "http://localhost/api/v1/admin/users"`（Accept ヘッダ無指定）でも JSON が返ることを確認（REQ-analytics-export-042）
- [ ] **本 Feature の PR チェックリスト**（受講生・採点者向け、`関連ドキュメント/評価シート.md` への組込みは Step 5 / [[product.md]] フロー Step 5 で対応）:
  - [ ] PR 動作確認に Sheet URL が記載されているか
  - [ ] Sheet が採点者の Google アカウントに閲覧権限共有されているか
  - [ ] Sheet スクショが添付されているか（API キーが映っていないか）
  - [ ] GAS コードが PR コメント or リポジトリ内に貼られているか
