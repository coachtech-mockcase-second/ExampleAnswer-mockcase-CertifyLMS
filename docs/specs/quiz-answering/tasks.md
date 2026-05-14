# quiz-answering タスクリスト

> 1 タスク = 1 コミット粒度。Step 単位で順次実装し、完了したものから `[x]` に更新する。
> 関連要件 ID は `requirements.md` の `REQ-quiz-answering-NNN` / `NFR-quiz-answering-NNN` を参照。
> コマンドはすべて `sail` プレフィックス（`tech.md` の「コマンド慣習」参照）。
>
> **Basic / Advance スコープの分離**: 以下のタスクは **Advance ブランチ専用**（Basic ブランチでは実装しない、`advance` ブランチで純粋追加）— Step 3「Controller（API、`app/Http/Controllers/Api/`）」群 / Step 3「Resource（API）」群 / Step 3「`routes/api.php` 登録」 / Step 6「Feature（HTTP）」内 `tests/Feature/Http/Api/...Test.php` 群 / Step 7「Sanctum API 動作確認」。これら以外（Web Controller / FormRequest / Action / Service / Policy / Blade / 動作確認 Web 系 / 各 Unit テスト）は Basic ブランチに含む。

## Step 1: Migration & Model & Enum

- [ ] migration: `create_answers_table`（ULID PK + SoftDeletes + `user_id` `restrictOnDelete` + `question_id` `restrictOnDelete` + `selected_option_id` `nullable + nullOnDelete` + `selected_option_body string 2000` + `is_correct boolean` + `source enum section_quiz weak_drill` + `answered_at datetime` + `(user_id, answered_at)` / `(user_id, question_id)` / `(question_id, is_correct)` 複合 INDEX + `source` 単体 INDEX + `deleted_at` 単体 INDEX）（REQ-quiz-answering-001, REQ-quiz-answering-005, REQ-quiz-answering-006, REQ-quiz-answering-007, REQ-quiz-answering-008, NFR-quiz-answering-003）
- [ ] migration: `create_question_attempts_table`（ULID PK + SoftDeletes + `user_id` `restrictOnDelete` + `question_id` `restrictOnDelete` + `attempt_count` `unsigned default 0` + `correct_count` `unsigned default 0` + `last_is_correct boolean default false` + `last_answered_at datetime` + `(user_id, question_id)` UNIQUE + `(user_id, last_answered_at)` 複合 INDEX + `deleted_at` 単体 INDEX）（REQ-quiz-answering-002, REQ-quiz-answering-005, REQ-quiz-answering-006, NFR-quiz-answering-003）
- [ ] Enum: `App\Enums\AnswerSource`（`SectionQuiz = 'section_quiz'` / `WeakDrill = 'weak_drill'`、`label()` 日本語）（REQ-quiz-answering-003）
- [ ] Model: `App\Models\Answer`（`fillable` / `$casts['is_correct' => 'boolean', 'answered_at' => 'datetime', 'source' => AnswerSource::class]` / `belongsTo(User::class)` / `belongsTo(Question::class)` / `belongsTo(QuestionOption::class, 'selected_option_id')` / `scopeForUser` / `scopeForEnrollment` / `scopeForSection` / `scopeForCategory` / `scopeBySource` / `scopeCorrect` / `scopeIncorrect`）（REQ-quiz-answering-001, REQ-quiz-answering-004）
- [ ] Model: `App\Models\QuestionAttempt`（`fillable` / `$casts['attempt_count' => 'integer', 'correct_count' => 'integer', 'last_is_correct' => 'boolean', 'last_answered_at' => 'datetime']` / `belongsTo(User::class)` / `belongsTo(Question::class)` / `scopeForUser` / `scopeForEnrollment` / `scopeForSection` / `scopeForCategory` / `scopeLastIs`）（REQ-quiz-answering-002, REQ-quiz-answering-004）
- [ ] [[content-management]] への追加: `App\Models\Question` に `hasMany(QuestionAttempt::class)` リレーション追加（既存 `hasMany(Answer::class)` 予告は本タスクで実装、`content-management/design.md` L214 の予告との整合確認）
- [ ] [[content-management]] への追加: `App\Models\Question` に `scopeVisibleForStudent()` を追加（`section_id IS NULL OR (Section / Chapter / Part すべて Published かつ deleted_at IS NULL)`）。本 Feature の `WeakDrill\ShowCategoryAction` / `Question::withCount('questions')` で利用
- [ ] [[auth]] への追加: `App\Models\User` に `hasMany(Answer::class)` / `hasMany(QuestionAttempt::class)` リレーション追加
- [ ] config: `config/quiz-answering.php` を新規作成（`weakness_analysis_service` キー、default null、`.env` の `QUIZ_ANSWERING_WEAKNESS_ANALYSIS_SERVICE` で上書き可）（REQ-quiz-answering-057, NFR-quiz-answering-010）
- [ ] Factory: `AnswerFactory`（`forUser($user)` / `forQuestion($question)` / `correct()` / `incorrect()` / `source(AnswerSource $source)` / `answeredOn(Carbon $at)` state）
- [ ] Factory: `QuestionAttemptFactory`（`forUser($user)` / `forQuestion($question)` / `withAttempts(int $count, int $correct)` / `lastIs(bool $correct)` / `lastAnsweredAt(Carbon $at)` state）

## Step 2: Policy

- [ ] Policy: `App\Policies\AnswerPolicy`（`view(User, Answer)` / `create(User, Question)`）（REQ-quiz-answering-081, REQ-quiz-answering-191）
- [ ] Policy: `App\Policies\QuestionAttemptPolicy`（`view(User, QuestionAttempt)`）（REQ-quiz-answering-192）
- [ ] Policy: `App\Policies\SectionQuizPolicy`（`view(User, Section)`、Enrollment 存在 + cascade visibility）（REQ-quiz-answering-020, REQ-quiz-answering-193）
- [ ] Policy: `App\Policies\WeakDrillPolicy`（`view(User, Enrollment)`、本人 + `status IN (learning, paused)`）（REQ-quiz-answering-050, REQ-quiz-answering-194）
- [ ] `AuthServiceProvider` に各 Policy を登録（`SectionQuizPolicy` / `WeakDrillPolicy` は引数型ベースの `Gate::define` で登録、既存 [[content-management]] の `SectionPolicy` と競合しないよう Gate 名 `quiz.section.view` / `quiz.weak-drill.view` で登録）

## Step 3: HTTP 層

### Controller（Web）

- [ ] Controller: `App\Http\Controllers\SectionQuizController`（`show(Section, ShowAction)` / `showQuestion(Section, Question, ShowQuestionAction)`、Controller method = Action 名一致）（REQ-quiz-answering-020, REQ-quiz-answering-023）
- [ ] Controller: `App\Http\Controllers\WeakDrillController`（`index(Enrollment, IndexAction)` / `showCategory(Enrollment, QuestionCategory, ShowCategoryAction)` / `showQuestion(Enrollment, QuestionCategory, Question, ShowQuestionAction)`）（REQ-quiz-answering-050, REQ-quiz-answering-052, REQ-quiz-answering-055）
- [ ] Controller: `App\Http\Controllers\AnswerController`（`store(Question, StoreAnswerRequest, StoreAction)`、JSON 返却）（REQ-quiz-answering-080）
- [ ] Controller: `App\Http\Controllers\QuizHistoryController`（`index(Enrollment, IndexRequest, IndexAction)`）（REQ-quiz-answering-120, REQ-quiz-answering-122）
- [ ] Controller: `App\Http\Controllers\QuizStatsController`（`index(Enrollment, IndexRequest, IndexAction)`）（REQ-quiz-answering-123, REQ-quiz-answering-124）

### Controller（API、`app/Http/Controllers/Api/`）

- [ ] Controller: `App\Http\Controllers\Api\AnswerController`（`store`、Web Controller と同一 Action 共有、`AnswerGradingResource` で整形）（REQ-quiz-answering-170, REQ-quiz-answering-171）
- [ ] Controller: `App\Http\Controllers\Api\WeakDrillController`（`index`、Web Controller と同一 `IndexAction` 共有、`CategoryDrillResource::collection` で整形）（REQ-quiz-answering-170, REQ-quiz-answering-171）
- [ ] Controller: `App\Http\Controllers\Api\QuizHistoryController`（`index`、`AnswerResource::collection` で整形）（REQ-quiz-answering-170, REQ-quiz-answering-171）
- [ ] Controller: `App\Http\Controllers\Api\QuizStatsController`（`index`、`QuestionAttemptResource::collection` で整形）（REQ-quiz-answering-170, REQ-quiz-answering-171）

### FormRequest

- [ ] FormRequest: `App\Http\Requests\Answer\StoreAnswerRequest`（`authorize` で `Policy::create` 経由、`rules selected_option_id ulid exists where question_id` + `source new Enum(AnswerSource::class)`）（REQ-quiz-answering-080, REQ-quiz-answering-082, REQ-quiz-answering-083）
- [ ] FormRequest: `App\Http\Requests\QuizHistory\IndexRequest`（`authorize` で `EnrollmentPolicy::view`、`rules section_id ulid exists` / `category_id ulid exists` / `is_correct boolean` / `source Enum(AnswerSource)`）（REQ-quiz-answering-120, REQ-quiz-answering-122, REQ-quiz-answering-125）
- [ ] FormRequest: `App\Http\Requests\QuizStats\IndexRequest`（同上 + `last_is_correct boolean` / `sort in:last_answered_at_desc,attempt_count_desc,accuracy_asc`）（REQ-quiz-answering-123, REQ-quiz-answering-124）

### Resource（API）

- [ ] Resource: `App\Http\Resources\AnswerResource`（`id` / `question_id` / `selected_option_id` / `selected_option_body` / `is_correct` / `source` / `answered_at`、optional `question`）（REQ-quiz-answering-171）
- [ ] Resource: `App\Http\Resources\QuestionAttemptResource`（`id` / `question_id` / `attempt_count` / `correct_count` / `accuracy` / `last_is_correct` / `last_answered_at`、optional `question`）（REQ-quiz-answering-171）
- [ ] Resource: `App\Http\Resources\QuestionResource`（出題用、正答非表示版。`id` / `body` / `explanation` / `difficulty` / `category` / `section` / `options` は `QuestionOptionResource::collection`）（REQ-quiz-answering-171）
- [ ] Resource: `App\Http\Resources\QuestionOptionResource`（`id` / `body` / `order`、`is_correct` を含めない）（REQ-quiz-answering-171）
- [ ] Resource: `App\Http\Resources\AnswerGradingResource`（`answer` / `attempt` / `correct_option_id` / `correct_option_body` / `explanation`）（REQ-quiz-answering-088, REQ-quiz-answering-171, NFR-quiz-answering-006）
- [ ] Resource: `App\Http\Resources\CategoryDrillResource`（`id` / `name` / `slug` / `question_count` / `is_weak` / `stats`）（REQ-quiz-answering-171）

### Route

- [ ] `routes/web.php` に Web 系ルート定義（`auth + role:student` Middleware group、prefix `/quiz`、name prefix `quiz.`、`SectionQuizController` / `WeakDrillController` / `AnswerController` / `QuizHistoryController` / `QuizStatsController` 全エンドポイント）（REQ-quiz-answering-190, REQ-quiz-answering-195）
- [ ] `routes/api.php` に API 系ルート定義（`auth:sanctum + role:student + throttle:60,1` Middleware group、prefix `api/v1/quiz`、name prefix `api.v1.quiz.`、`Api\AnswerController` / `Api\WeakDrillController` / `Api\QuizHistoryController` / `Api\QuizStatsController`）（REQ-quiz-answering-170, REQ-quiz-answering-173）

## Step 4: Action / Service / Exception / ServiceProvider

### SectionQuiz Action（`App\UseCases\SectionQuiz\`）

- [ ] `ShowAction`（Section 配下 Question 公開済 + 受講生 `QuestionAttempt` 同梱、N+1 回避）（REQ-quiz-answering-020, REQ-quiz-answering-021, REQ-quiz-answering-026, NFR-quiz-answering-002）
- [ ] `ShowQuestionAction`（Question 単体 + 次 Question の id + 受講生 `QuestionAttempt` 同梱、`section_id` 不一致は `QuestionUnavailableForAnswerException`）（REQ-quiz-answering-023, REQ-quiz-answering-024, REQ-quiz-answering-025, NFR-quiz-answering-002）

### WeakDrill Action（`App\UseCases\WeakDrill\`）

- [ ] `IndexAction`（カテゴリ一覧 + `QuestionAttemptStatsService::byCategory` + `WeaknessAnalysisServiceContract::getWeakCategories` + `withCount('questions')` Eager）（REQ-quiz-answering-050, REQ-quiz-answering-051, REQ-quiz-answering-057, NFR-quiz-answering-002, NFR-quiz-answering-010）
- [ ] `ShowCategoryAction`（カテゴリ × Question リスト、`Question::visibleForStudent()` + `whereIn` で `QuestionAttempt` 同梱）（REQ-quiz-answering-052, REQ-quiz-answering-053, REQ-quiz-answering-054, REQ-quiz-answering-056, NFR-quiz-answering-002）
- [ ] `ShowQuestionAction`（Question 単体 + `attempt` 同梱、カテゴリと certification の整合チェック）（REQ-quiz-answering-055, NFR-quiz-answering-002）

### Answer Action（`App\UseCases\Answer\`）

- [ ] `StoreAction`（3 段ガード `assertQuestionAvailable` / `assertEnrollmentActive` / `option` 検証 → `DB::transaction()` で `Answer` INSERT + `QuestionAttempt` UPSERT、`AnswerResult` 戻り値）（REQ-quiz-answering-080, REQ-quiz-answering-082, REQ-quiz-answering-084, REQ-quiz-answering-085, REQ-quiz-answering-086, REQ-quiz-answering-087, REQ-quiz-answering-088, REQ-quiz-answering-089, NFR-quiz-answering-001, NFR-quiz-answering-006）
- [ ] `AnswerResult` 値オブジェクト（readonly class、`app/UseCases/Answer/AnswerResult.php`）（REQ-quiz-answering-088, NFR-quiz-answering-006）

### QuizHistory / QuizStats Action

- [ ] `App\UseCases\QuizHistory\IndexAction`（`when` チェーンフィルタ + paginate 20 + eager load）（REQ-quiz-answering-120, REQ-quiz-answering-122, NFR-quiz-answering-002）
- [ ] `App\UseCases\QuizStats\IndexAction`（フィルタ + `resolveSort` + paginate 20 + eager load）（REQ-quiz-answering-123, REQ-quiz-answering-124, NFR-quiz-answering-002）

### Service（`App\Services\`）

- [ ] `QuestionAttemptStatsService`（`summarize(Enrollment): QuestionAttemptStatsSummary` + `byCategory(Enrollment): Collection<CategoryStats>` + `recentAnswers(Enrollment, int $limit = 5): Collection<Answer>`、ステートレス、トランザクション非保有）（REQ-quiz-answering-150, REQ-quiz-answering-151, REQ-quiz-answering-152, REQ-quiz-answering-153, REQ-quiz-answering-154, REQ-quiz-answering-155, REQ-quiz-answering-156, NFR-quiz-answering-005）
- [ ] `QuestionAttemptStatsSummary` DTO（readonly class、`app/Services/QuestionAttemptStatsSummary.php`）（REQ-quiz-answering-151）
- [ ] `CategoryStats` DTO（readonly class、`app/Services/CategoryStats.php`）（REQ-quiz-answering-152）
- [ ] `NullWeaknessAnalysisService`（`WeaknessAnalysisServiceContract` の Null Object 実装、`getWeakCategories` は `collect()` を返す）（REQ-quiz-answering-057, NFR-quiz-answering-010）
- [ ] `WeaknessAnalysisServiceContract` Interface（`app/Services/Contracts/WeaknessAnalysisServiceContract.php`、`getWeakCategories(Enrollment): Collection<QuestionCategory>` を契約として宣言）（REQ-quiz-answering-051, NFR-quiz-answering-010）

### ドメイン例外（`app/Exceptions/QuizAnswering/`）

- [ ] `EnrollmentInactiveForAnswerException`（HTTP 409、`ConflictHttpException` 継承）（REQ-quiz-answering-084, NFR-quiz-answering-004）
- [ ] `QuestionUnavailableForAnswerException`（HTTP 409、`ConflictHttpException` 継承）（REQ-quiz-answering-085, NFR-quiz-answering-004）
- [ ] `QuestionOptionMismatchException`（HTTP 422、`UnprocessableEntityHttpException` 継承）（REQ-quiz-answering-082, NFR-quiz-answering-004）
- [ ] `WeakDrillCategoryMismatchException`（HTTP 404、`NotFoundHttpException` 継承）（REQ-quiz-answering-052, NFR-quiz-answering-004）

### ServiceProvider

- [ ] `App\Providers\QuizAnsweringServiceProvider`（`WeaknessAnalysisServiceContract::class` を `bindIf` で `config('quiz-answering.weakness_analysis_service')` の class、または `NullWeaknessAnalysisService` にフォールバック bind）（REQ-quiz-answering-057, NFR-quiz-answering-010）
- [ ] `bootstrap/providers.php` に `QuizAnsweringServiceProvider::class` 登録確認（Package Auto-Discovery 利用、Wave 0b で確定済 `providers.php` を編集）

### Handler 追加

- [ ] `app/Exceptions/Handler.php` の `register()` に本 Feature のドメイン例外マッピングを追加（API リクエスト時に JSON 返却、`{ message, error_code, status }`）（REQ-quiz-answering-174）

## Step 5: Blade ビュー + JavaScript

### Blade（`resources/views/quiz/`）

- [ ] `sections/show.blade.php`（Section エントリ画面、Question カード一覧 + 「最初から / 未解答から / 全部やり直す」ボタン + 全制覇判定）（REQ-quiz-answering-021, REQ-quiz-answering-022, REQ-quiz-answering-026）
- [ ] `sections/question.blade.php`（1 問の出題画面、Question 本文 + `<x-form.radio>` 選択肢 + 解答フォーム埋込 + 結果ペイン）（REQ-quiz-answering-024, REQ-quiz-answering-025）
- [ ] `drills/index.blade.php`（カテゴリ一覧、おすすめバッジ + 正答率 + Question 件数）（REQ-quiz-answering-051, REQ-quiz-answering-056）
- [ ] `drills/show.blade.php`（カテゴリ別 Question リスト、ヘッダにカテゴリ全体正答率 + おすすめバッジ）（REQ-quiz-answering-053, REQ-quiz-answering-054）
- [ ] `drills/question.blade.php`（drills 経路の 1 問出題画面、Section 経路 question.blade と共通 partial 利用）（REQ-quiz-answering-055）
- [ ] `history/index.blade.php`（解答履歴一覧、フィルタ + ページネーション）（REQ-quiz-answering-121, REQ-quiz-answering-122）
- [ ] `stats/index.blade.php`（Question サマリ一覧、フィルタ + ソート + ページネーション）（REQ-quiz-answering-123, REQ-quiz-answering-124）
- [ ] `partials/question-card.blade.php`（Question カード共通部品、本文プレビュー + 試行数 + 最新正誤バッジ）
- [ ] `partials/answer-form.blade.php`（解答フォーム共通部品、`data-quiz-answer-form` + `data-source` で JS と契約）（REQ-quiz-answering-024, NFR-quiz-answering-007, NFR-quiz-answering-008）
- [ ] `partials/grading-result.blade.php`（解答結果ペイン、初期 `hidden`、JS が fill）（REQ-quiz-answering-024, NFR-quiz-answering-006）

### JavaScript（`resources/js/quiz-answering/`）

- [ ] `answer-form.js`（`data-quiz-answer-form` を捕捉 → `postJson(form.action, payload)` → 結果セクションを描画、submit ボタン disable、選択肢を `disabled` + 正解 highlight、`next` ボタン表示）（REQ-quiz-answering-080, NFR-quiz-answering-006, NFR-quiz-answering-007）
- [ ] `app.js` への import 追加（`resources/js/app.js` に `import './quiz-answering/answer-form.js'`）

## Step 6: テスト

### Feature（HTTP）

- [ ] `tests/Feature/Http/SectionQuiz/ShowTest.php`（受講生本人 200 / 未受講資格 404 / cascade visibility 違反 404 / Question 0 件時の empty-state）（REQ-quiz-answering-020, REQ-quiz-answering-021, REQ-quiz-answering-026）
- [ ] `tests/Feature/Http/SectionQuiz/ShowQuestionTest.php`（出題 200 + 次 Question id 返却 / Section 不一致 404 / Question Draft 404）（REQ-quiz-answering-023, REQ-quiz-answering-024, REQ-quiz-answering-025）
- [ ] `tests/Feature/Http/WeakDrill/IndexTest.php`（受講生本人 200 / 他人 Enrollment 403 / `WeaknessAnalysisService` 未バインドでも 200）（REQ-quiz-answering-050, REQ-quiz-answering-051, REQ-quiz-answering-057）
- [ ] `tests/Feature/Http/WeakDrill/ShowCategoryTest.php`（資格不一致カテゴリ 404 / cascade visibility 違反 Question 除外 / `section_id IS NULL` 問題包含）（REQ-quiz-answering-052, REQ-quiz-answering-053）
- [ ] `tests/Feature/Http/WeakDrill/ShowQuestionTest.php`（カテゴリ × 資格 × 問題の三重整合検証）（REQ-quiz-answering-055）
- [ ] `tests/Feature/Http/Answer/StoreTest.php`（正答 INSERT / 誤答 INSERT / 連投で attempt_count += 1 / Enrollment passed 409 / Question Draft 409 / 選択肢不一致 422 / 他資格の Question 403 / 未受講資格 403）（REQ-quiz-answering-080, REQ-quiz-answering-081, REQ-quiz-answering-082, REQ-quiz-answering-084, REQ-quiz-answering-085, REQ-quiz-answering-086, REQ-quiz-answering-087, REQ-quiz-answering-088, REQ-quiz-answering-089）
- [ ] `tests/Feature/Http/QuizHistory/IndexTest.php`（自分の履歴のみ表示 / 他者 403 / フィルタ動作 / 他資格 Answer は混入しない）（REQ-quiz-answering-120, REQ-quiz-answering-121, REQ-quiz-answering-122, REQ-quiz-answering-125）
- [ ] `tests/Feature/Http/QuizStats/IndexTest.php`（サマリ表示 / ソート動作 / `accuracy_asc` で正答率 0 が先頭）（REQ-quiz-answering-123, REQ-quiz-answering-124）
- [ ] `tests/Feature/Http/Api/AnswerStoreTest.php`（Sanctum SPA Cookie 認証で POST / 整形済 JSON 返却 / Cookie なし 401、`Sanctum::actingAs($student)` ヘルパで認証）（REQ-quiz-answering-170, REQ-quiz-answering-171, REQ-quiz-answering-172）
- [ ] `tests/Feature/Http/Api/QuizHistoryIndexTest.php`（API 経由履歴取得 / `AnswerResource` 整形確認）（REQ-quiz-answering-170, REQ-quiz-answering-171）

### Feature（UseCases）

- [ ] `tests/Feature/UseCases/Answer/StoreActionTest.php`（`AnswerResult` 値 / 新規 attempt INSERT / 既存 attempt UPDATE / SoftDeleted attempt restore / 同一トランザクション内で `Answer` INSERT + `QuestionAttempt` UPSERT を確認）（REQ-quiz-answering-086, REQ-quiz-answering-088, NFR-quiz-answering-001）
- [ ] `tests/Feature/UseCases/WeakDrill/IndexActionTest.php`（カテゴリ統計の正確性 / `is_weak` フラグの mock-exam Service 連動 / Null fallback 時の `is_weak=false`）（REQ-quiz-answering-051, REQ-quiz-answering-057）

### Unit（Services）

- [ ] `tests/Unit/Services/QuestionAttemptStatsServiceTest.php`（`summarize` で 0 件時 `null` accuracy / `byCategory` の GROUP BY 集計 / `recentAnswers` の eager load + limit / 他資格混入防止）（REQ-quiz-answering-150, REQ-quiz-answering-151, REQ-quiz-answering-152, REQ-quiz-answering-153, REQ-quiz-answering-154, REQ-quiz-answering-156）
- [ ] `tests/Unit/Services/NullWeaknessAnalysisServiceTest.php`（常に空 Collection を返すことを確認）（REQ-quiz-answering-057, NFR-quiz-answering-010）

### Unit（Policies）

- [ ] `tests/Unit/Policies/AnswerPolicyTest.php`（`view`: 本人のみ true / `create`: ロール × Enrollment × cascade visibility 各分岐）（REQ-quiz-answering-081, REQ-quiz-answering-191）
- [ ] `tests/Unit/Policies/QuestionAttemptPolicyTest.php`（`view`: 本人のみ true）（REQ-quiz-answering-192）
- [ ] `tests/Unit/Policies/SectionQuizPolicyTest.php`（受講登録 + cascade visibility の各分岐網羅）（REQ-quiz-answering-020, REQ-quiz-answering-193）
- [ ] `tests/Unit/Policies/WeakDrillPolicyTest.php`（本人 Enrollment + `status IN (learning, paused)` の各分岐）（REQ-quiz-answering-050, REQ-quiz-answering-194）

## Step 7: 動作確認 & 整形

- [ ] `sail artisan migrate:fresh --seed` 実行（既存 [[content-management]] / [[enrollment]] / [[learning]] seeder が事前完了している前提）
- [ ] `sail artisan test --filter=QuizAnswering` で本 Feature テストが全件 pass
- [ ] `sail artisan test` 全体実行で他 Feature への副作用なし（特に [[learning]] / [[content-management]] / [[enrollment]] の既存テストが green）
- [ ] `sail bin pint --dirty` 整形
- [ ] ブラウザ動作確認シナリオ（受講生でログイン → [[learning]] の Section 詳細から「Section の問題演習へ」リンクをクリック → 演習エントリ画面 → 1 問解いて結果表示 → 「次の問題」遷移 → Section 全制覇）
- [ ] ブラウザ動作確認シナリオ（受講生ダッシュボード or mock-exam 結果画面 → 苦手分野ドリルへ遷移 → カテゴリ select → 出題 → 1 問解いて結果表示 → リスト画面に戻る）
- [ ] ブラウザ動作確認シナリオ（解答履歴画面 → セクションフィルタ / カテゴリフィルタ / 正誤フィルタ動作 + ページング）
- [ ] ブラウザ動作確認シナリオ（Question サマリ画面 → ソート切替 + 正答率昇順で苦手問題が上位に来ることを確認）
- [ ] ブラウザ動作確認シナリオ（Enrollment を `passed` に手動で書き換えてから解答送信 → 409 + 日本語エラーメッセージ表示）
- [ ] ブラウザ動作確認シナリオ（コーチ / admin で `/quiz/sections/{section}` 直接アクセス → 403）
- [ ] Sanctum SPA 認証 API 動作確認（**Advance ブランチのみ**）: ブラウザで受講生 User として Fortify ログイン → 同一オリジンで自前 SPA をホスト → SPA から `GET /sanctum/csrf-cookie` → `POST /api/v1/quiz/questions/{id}/answer` を Cookie 付きで叩き、整形済 JSON 結果が返ることを確認（Personal Access Token は不要、Web セッション Cookie で認証）
- [ ] [[content-management]] の Question を SoftDelete / Draft 化 → 解答送信が 409、過去履歴は表示維持を確認
- [ ] [[mock-exam]] 未実装環境で「おすすめバッジ」が全カテゴリ false 表示になることを確認（フォールバック）
