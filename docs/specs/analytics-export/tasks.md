# analytics-export タスクリスト

> 1 タスク = 1 コミット粒度。Step 単位で順次実装し、完了したものから `[x]` に更新する。
> **v3 改修反映**: `assigned_coach_id` カラム削除 / `Question` 分離影響(`WeaknessAnalysisService::batchHeatmap` を `MockExamQuestion` ベースに) / `User.status` enum 拡張(`graduated`) / `User.plan_*` カラム追加 / `StagnationDetectionService::batchLastActivityFor` 撤回 → 新 `LastActivityService` に移管。
> 関連要件 ID は `requirements.md` の `REQ-analytics-export-NNN` / `NFR-analytics-export-NNN` を参照。
> コマンドはすべて `sail` プレフィックス。
>
> **本 Feature は Migration / Model / Enum / Action / 独自 Service / Policy を新設しない**。Controller + Resource + IndexRequest + ApiKeyMiddleware + Config + Handler 修正 + GAS テンプレート + README が成果物。

## Step 0: 依存 Feature の Service `batch*` メソッド確認

> **前提条件**: 以下メソッドが [[learning]] / [[mock-exam]] 側で実装済 or spec 記述済であることを確認する。

- [ ] 確認: `App\Services\ProgressService::batchCalculate(Collection<Enrollment>): array<string, float>` が `docs/specs/learning/design.md` に記述されているか
- [ ] **確認(v3 新規)**: `App\Services\LastActivityService::batchLastActivityFor(Collection<Enrollment>): array<string, Carbon>` が `docs/specs/learning/design.md` に記述されているか(`StagnationDetectionService` 撤回に伴い、この機能を新 Service に分離)。未記述なら [[learning]] design.md / tasks.md に追記提案
- [ ] **確認(v3 修正)**: `App\Services\WeaknessAnalysisService::batchHeatmap(Collection<MockExamSession>): array` が `docs/specs/mock-exam/design.md` に記述、かつ **`MockExamAnswer JOIN MockExamQuestion` ベース**で実装されているか(旧 `Question` JOIN は撤回)

## Step 1: Config & .env

- [ ] config: `config/analytics-export.php` を新規作成(`'api_key' => env('ANALYTICS_API_KEY')`)
- [ ] `.env.example` に `ANALYTICS_API_KEY=` プレースホルダ行を追加
- [ ] `.env`(模範解答プロジェクト初期値)に 32 文字以上のランダム文字列を `ANALYTICS_API_KEY` としてセード値設定

## Step 2: ApiKeyMiddleware

- [ ] Middleware: `App\Http\Middleware\ApiKeyMiddleware` 新規作成
  - `handle()` で `config('analytics-export.api_key')` 取得 + 空チェック → 503 `API_KEY_NOT_CONFIGURED`
  - `X-API-KEY` ヘッダ取得 + 空チェック → 401 `INVALID_API_KEY`
  - `hash_equals` 比較 → 不一致で 401
  - 通過時 `$next($request)`
  - すべて `response()->json()` で `Content-Type: application/json; charset=UTF-8` 返却(REQ-analytics-export-001, 005, 006, 007)
- [ ] `Kernel.php` の `$middlewareAliases` に `'api.key' => ApiKeyMiddleware::class` 追加

## Step 3: HTTP 層 — IndexRequest / Resource / Controller / Route

### IndexRequest(`app/Http/Requests/Api/Admin/`)

- [ ] **`User\IndexRequest`(v3 更新)** — `role: Rule::enum(UserRole)` / **`status: Rule::in([Invited, InProgress, Graduated])`**(v3、`graduated` 追加、`withdrawn` 不可) / `per_page: 1..500` / `page: min:1`、messages 日本語化
- [ ] **`Enrollment\IndexRequest`(v3 更新)** — **`status: Rule::enum(EnrollmentStatus)`**(v3、`paused` 撤回で 3 値) / `certification_id: ulid + exists` / `current_term: Rule::enum(TermType)` / `include: string` / `per_page` / `page`、**`assigned_coach_id` rule は撤回**(v3)
  - `resolveIncludes()` ヘルパで `?include=user,foo` → `['user']` への寛容 sanitize、**`'assigned_coach'` は allowed から除外**(v3)
- [ ] `MockExamSession\IndexRequest`(`mock_exam_id` / `pass: boolean` / `status: Rule::enum(MockExamSessionStatus)` / `from: date_format:Y-m-d` / `to: date_format:Y-m-d + after_or_equal:from` / `include` / `per_page` / `page`)

### Resource(`app/Http/Resources/Api/Admin/`)

- [ ] **`UserResource`(v3 更新)** — `id` / `name` / `email` / `role` / `status` / `last_login_at` / **`plan_id`** / **`plan_started_at`** / **`plan_expires_at`** / **`max_meetings`**(v3 新規 4 カラム) / `created_at` / `updated_at`
  - **絶対に含めない**: `password` / `remember_token` / `bio` / `avatar_url` / `profile_setup_completed` / `email_verified_at` / `meeting_url`
- [ ] **`CertificationResource`(v3 更新、薄い)** — `id` / `name` / `category_id` / `difficulty` / `description`、**`code` / `slug` / `passing_score` / `total_questions` / `exam_duration_minutes` は撤回**(v3)
- [ ] **`EnrollmentResource`(v3 更新)** — `id` / `user_id` / `certification_id` / `status` / `current_term` / `exam_date` / `passed_at` / `progress_rate`(`$this->additional['_batch']['progress_rate'][$this->id]` 参照) / `last_activity_at`(同上) / `created_at` / `updated_at` / `user`(whenLoaded) / `certification`(whenLoaded)
  - **`assigned_coach_id` / `completion_requested_at` / `assigned_coach`(whenLoaded) は撤回**(v3)
- [ ] `MockExamResource`(薄い、`id` / `certification_id` / `title` / `order` / `is_published` / `passing_score` / `time_limit_minutes`)
- [ ] **`MockExamSessionResource`(v3 更新)** — `id` / `user_id` / `mock_exam_id` / `enrollment_id` / `status` / `total_correct` / **`passing_score_snapshot`** / `pass` / `started_at` / `submitted_at` / `graded_at` / **`category_breakdown`**(`$this->additional['_batch']['category_breakdown'][$this->id]`、**MockExamQuestion ベース**)/ `created_at` / `user` / `mock_exam` / `enrollment`(whenLoaded)

### Controller(`app/Http/Controllers/Api/Admin/`)

- [ ] **`UserController::index(IndexRequest)`(v3 更新)** — `User::query()->where('status', '!=', Withdrawn)->whereNull('deleted_at')->when(role)->when(status)->orderBy('created_at')->paginate(per_page)`、`UserResource::collection`
- [ ] **`EnrollmentController` コンストラクタ DI(v3 更新)** — `ProgressService` + **`LastActivityService`(v3 新規、StagnationDetectionService から差し替え)**
- [ ] **`EnrollmentController::index(IndexRequest)`(v3 更新)** — `Enrollment::query()->whereNull('deleted_at')->when(filters)->with($this->mapIncludes(...))->orderBy('created_at')->paginate(per_page)` → `$progressMap = $this->progressService->batchCalculate(...)` → `$activityMap = $this->lastActivityService->batchLastActivityFor(...)` → `EnrollmentResource::collection->additional(['_batch' => [...]])`
  - **`assigned_coach_id` の when 条件は撤回**
  - `mapIncludes` の allowed から **`'assigned_coach'` 撤回**
- [ ] `MockExamSessionController` コンストラクタ DI(`WeaknessAnalysisService`)
- [ ] **`MockExamSessionController::index(IndexRequest)`(v3 更新)** — `MockExamSession::query()->whereNull('deleted_at')->when(filters)->with($this->mapIncludes(...))->orderBy('created_at')->paginate(per_page)` → **`$heatmapMap = $this->weaknessService->batchHeatmap(...)`**(MockExamQuestion JOIN ベース、v3) → `MockExamSessionResource::collection->additional(['_batch' => [...]])`

### Route

- [ ] `routes/api.php` に `Route::prefix('v1/admin')->middleware(['api.key', 'throttle:60,1'])->name('api.v1.admin.')->group(...)` を追加(`users.index` / `enrollments.index` / `mock-exam-sessions.index`)
  - Web セッション / Sanctum / Fortify Middleware は含めず、`api.key + throttle:60,1` のみ
  - レスポンスは `AnonymousResourceCollection` の `data / meta / links` 構造
  - 常に JSON 返却(Accept ヘッダ非依存)

## Step 4: Exceptions/Handler 修正

- [ ] `app/Exceptions/Handler.php::register()` に追加:
  - `ThrottleRequestsException` → `$request->is('api/*')` 時に JSON `{ message: 'リクエスト過多', error_code: 'RATE_LIMIT_EXCEEDED', status: 429 }`
  - `NotFoundHttpException` / `MethodNotAllowedHttpException` の API JSON 化(既存共通実装があれば確認のみ)

## Step 5: テスト

### Feature(`tests/Feature/Http/Api/Admin/`)

- [ ] `ApiKeyMiddlewareTest`(4 ケース: 一致 200 / 欠落 401 / 不一致 401 / config 空 503)
- [ ] **`UserIndexTest`(v3 更新)**:
  - 全件取得 200(`withdrawn` 除外)
  - **`?status=invited` / `?status=in_progress` / `?status=graduated`(v3 新規)フィルタ動作**
  - **`?status=withdrawn` で 422**
  - **JSON に `plan_id` / `plan_started_at` / `plan_expires_at` / `max_meetings` 含む**(v3)
  - センシティブカラム除外検証(`password` / `meeting_url` 等)
  - `?per_page=200` / `?per_page=501` で 422
  - キー不一致 401
- [ ] **`EnrollmentIndexTest`(v3 更新)**:
  - 全件取得 + `progress_rate` / `last_activity_at` 含有(`ProgressService` / `LastActivityService` の動的 mock or 実 DB 計算)
  - `?status=learning` / `?status=passed` / `?status=failed` フィルタ動作
  - **`?status=paused` で 422**(v3 撤回)
  - **`?assigned_coach_id` で 422**(v3 撤回)
  - **`?include=assigned_coach` 指定でも `assigned_coach` が JSON に含まれない**(v3 で allowed から削除)
  - `?include=user,certification` で Eager Loading + JSON 同梱
  - `?include=foo,user` で `foo` 無視 + `user` 同梱
  - N+1 検証(`DB::enableQueryLog()` でクエリ数 < 10)
  - **`EnrollmentResource` に `assigned_coach_id` / `completion_requested_at` が含まれない**(v3)
  - キー不一致 401
- [ ] **`MockExamSessionIndexTest`(v3 更新)**:
  - 全件取得 + `category_breakdown` 含有(graded のみ非空)
  - **`category_breakdown` の各要素が `category_id` / `category_name` / `correct` / `total` / `rate` を持つ**(MockExamQuestion ベース、v3)
  - `?pass=true` / `?from=Y-m-d&to=Y-m-d` 動作
  - `?from > to` で 422
  - `?include=user,mock_exam,enrollment` で Eager Loading + JSON 同梱
  - `passing_score_snapshot` が `mock_exam_session.passing_score_snapshot` と一致
  - キー不一致 401
- [ ] `ThrottleResponseTest`(`/api/v1/admin/users` を 61 回連投で 429 JSON `RATE_LIMIT_EXCEEDED`)
- [ ] `NotFoundJsonTest`(`/api/v1/admin/undefined-path` で 404 JSON)

> Factory は各 Feature 所有(`User::factory()->graduated()`(v3 新規) / `Enrollment::factory()->{learning/passed/failed}()`(v3 で paused 撤回) / `MockExamSession::factory()->graded()` 等)

## Step 6: GAS スクリプト雛形 + README

- [ ] `関連ドキュメント/analytics-export/` ディレクトリ作成
- [ ] `関連ドキュメント/analytics-export/gas-template.gs` 作成(`getApiKey_()` / `getApiBaseUrl_()` / `fetchJson_(path, params)` / `fetchAllPages_(path, params)` のプライベート関数 + 利用方法コメント、業務ロジック含めない)
- [ ] `関連ドキュメント/analytics-export/README.md` 作成
  - Sheet 新規作成 / Apps Script / Script Properties 設定 / gas-template.gs コピペ / GAS 実行 / 採点者シェア手順
  - **API キー秘匿の注意**(GAS Script Properties に閉じる、Sheet に出力しない)
  - PR 動作確認 4 点(Sheet URL / 採点者シェア / Sheet スクショ / GAS コード)
  - **v3 改修関連の注意**: `?assigned_coach_id` クエリは撤回されたため、コーチ別フィルタは GAS 側で `certification_coach_assignments` を別途取得して結合する必要がある

## Step 7: 動作確認 & 整形

- [ ] `sail artisan test --filter=Api\\\\Admin` 全件 pass
- [ ] `sail artisan test` 全体実行で他 Feature への副作用なし(特に [[learning]] / [[mock-exam]] の batch 系メソッドが他テストを壊さない)
- [ ] `sail bin pint --dirty` 整形
- [ ] **API 動作確認 curl**:
  ```bash
  curl -H "X-API-KEY: $ANALYTICS_API_KEY" "http://localhost/api/v1/admin/users?per_page=10" | jq
  curl -H "X-API-KEY: $ANALYTICS_API_KEY" "http://localhost/api/v1/admin/users?status=graduated" | jq  # v3 新規
  curl -H "X-API-KEY: $ANALYTICS_API_KEY" "http://localhost/api/v1/admin/enrollments?status=learning&include=user,certification" | jq
  curl -H "X-API-KEY: $ANALYTICS_API_KEY" "http://localhost/api/v1/admin/mock-exam-sessions?status=graded&include=user,mock_exam" | jq
  ```
- [ ] **v3 撤回されたクエリの 422 確認**:
  ```bash
  curl -H "X-API-KEY: $ANALYTICS_API_KEY" "http://localhost/api/v1/admin/enrollments?assigned_coach_id=01ABC..." | jq  # 422
  curl -H "X-API-KEY: $ANALYTICS_API_KEY" "http://localhost/api/v1/admin/enrollments?status=paused" | jq  # 422
  curl -H "X-API-KEY: $ANALYTICS_API_KEY" "http://localhost/api/v1/admin/users?status=withdrawn" | jq  # 422
  ```
- [ ] **JSON 構造確認**:
  - User Resource に `plan_id` / `plan_started_at` / `plan_expires_at` / `max_meetings` が含まれる
  - User Resource に `meeting_url` / `password` / `avatar_url` が含まれない
  - Enrollment Resource に `assigned_coach_id` / `assigned_coach` / `completion_requested_at` が含まれない
  - MockExamSession Resource の `category_breakdown` 要素が MockExamQuestion ベースで集計されている
- [ ] **GAS 動作確認**:
  - Google Sheets 新規作成 → Apps Script で `gas-template.gs` 貼り付け → Script Properties に API キー設定 → サンプル関数で users / enrollments / mock-exam-sessions 取得 → Sheet 反映
  - 採点者の Google アカウントに閲覧権限付与
- [ ] N+1 動作確認: `/api/v1/admin/enrollments` で `DB::enableQueryLog()` 有効化、ページ内 N に対し `O(1)` 〜 `O(log N)` 程度
- [ ] レート制限動作確認: 61 回目以降 429 JSON
- [ ] CORS 設定確認(`config/cors.php` がデフォルト)
- [ ] PR チェックリスト: Sheet URL / 採点者シェア / Sheet スクショ(API キー映らない) / GAS コード
