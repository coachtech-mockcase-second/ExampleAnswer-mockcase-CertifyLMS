# quiz-answering タスクリスト

> 1 タスク = 1 コミット粒度。Step 単位で順次実装し、完了したものから `[x]` に更新する。
> 関連要件 ID は `requirements.md` の `REQ-quiz-answering-NNN` / `NFR-quiz-answering-NNN` を参照。
> **v3 改修反映**: `Question` → `SectionQuestion`、`Answer` → `SectionQuestionAnswer`、`QuestionAttempt` → `SectionQuestionAttempt`、`QuestionOption` → `SectionQuestionOption` 参照に統一。`difficulty` 関連削除。`passed` でも演習可。`EnsureActiveLearning` Middleware 連動。弱点ドリル出題対象は SectionQuestion のみ。
> **FE 方針確定**（2026-05-16）: Blade + Form POST + Redirect の純 Laravel 標準パターンに統一。解答送信は `POST .../answer` → サーバで自動採点 + DB 反映 → 結果画面（独立 Blade ルート `.../result/{answer}`）へ 302 redirect → 「次の問題へ」リンクで連続演習。**JavaScript / Ajax fetch / sendBeacon / Sanctum SPA / 公開 JSON API / API Resource クラス / `routes/api.php` 登録 / `tests/Feature/Http/Api/...Test.php` はすべて本 Feature では作らない**。
> コマンドはすべて `sail` プレフィックス。

## Step 1: Migration & Model & Enum

### Migration

- [x] **migration: `create_section_question_answers_table`(v3 rename)** — ULID PK + SoftDeletes + `user_id` `restrictOnDelete` + **`section_question_id`** `restrictOnDelete` to `section_questions` + `selected_option_id` `nullable nullOnDelete` to `section_question_options` + `selected_option_body string 2000` + `is_correct boolean` + `source enum section_quiz weak_drill` + `answered_at datetime` + `(user_id, answered_at)` / `(user_id, section_question_id)` / `(section_question_id, is_correct)` 複合 INDEX + `source` / `deleted_at` 単体 INDEX(REQ-quiz-answering-001, REQ-quiz-answering-005, REQ-quiz-answering-007, NFR-quiz-answering-003)
- [x] **migration: `create_section_question_attempts_table`(v3 rename)** — ULID PK + SoftDeletes + `user_id` `restrictOnDelete` + **`section_question_id`** `restrictOnDelete` + `attempt_count unsigned int default 0` + `correct_count unsigned int default 0` + `last_is_correct boolean default false` + `last_answered_at datetime` + `(user_id, section_question_id)` UNIQUE + `(user_id, last_answered_at)` 複合 INDEX + `deleted_at` 単体 INDEX(REQ-quiz-answering-002, NFR-quiz-answering-003)

### 明示的に持たない migration(v3 撤回)

- 旧 `create_answers_table`(代わりに `section_question_answers`)
- 旧 `create_question_attempts_table`(代わりに `section_question_attempts`)

### Enum

- [x] Enum: `App\Enums\AnswerSource`(`SectionQuiz='section_quiz'` / `WeakDrill='weak_drill'`、`label()` 日本語)(REQ-quiz-answering-003)

### Model

- [x] **Model: `App\Models\SectionQuestionAnswer`(v3 rename)** — `HasUlids` + `HasFactory` + `SoftDeletes`、`fillable: user_id / section_question_id / selected_option_id / selected_option_body / is_correct / source / answered_at` / `$casts['is_correct'=>'boolean', 'answered_at'=>'datetime', 'source'=>AnswerSource::class]` / `belongsTo(User)` / **`belongsTo(SectionQuestion)`** / **`belongsTo(SectionQuestionOption, selected_option_id)`** / `scopeForUser` / `scopeForEnrollment` / `scopeForSection` / `scopeForCategory` / `scopeBySource` / `scopeCorrect` / `scopeIncorrect`(REQ-quiz-answering-001)
- [x] **Model: `App\Models\SectionQuestionAttempt`(v3 rename)** — `HasUlids` + `HasFactory` + `SoftDeletes`、`$casts['attempt_count'=>'integer', 'correct_count'=>'integer', 'last_is_correct'=>'boolean', 'last_answered_at'=>'datetime']` / `belongsTo(User)` / **`belongsTo(SectionQuestion)`** / `accuracy()` accessor / `scopeForUser` / `scopeForEnrollment` / `scopeForSection` / `scopeForCategory` / `scopeLastIs`(REQ-quiz-answering-002)

### 明示的に持たない Model(v3 撤回)

- 旧 `App\Models\Answer`(代わりに `SectionQuestionAnswer`)
- 旧 `App\Models\QuestionAttempt`(代わりに `SectionQuestionAttempt`)

### 関連 Feature の Model 追加

- [x] [[content-management]] への追加: `App\Models\SectionQuestion` に `hasMany(SectionQuestionAttempt::class)` / `hasMany(SectionQuestionAnswer::class)` リレーション追加
- [x] [[content-management]] への追加: `App\Models\SectionQuestion::scopeVisibleForStudent()`(`status=Published` + Section / Chapter / Part がすべて Published + SoftDelete でない)
- [x] [[auth]] への追加: `App\Models\User` に `hasMany(SectionQuestionAnswer::class)` / `hasMany(SectionQuestionAttempt::class)` リレーション追加

### Config

- [x] config: `config/quiz-answering.php` を新規作成(`weakness_analysis_service` キー、default null、`.env` の `QUIZ_ANSWERING_WEAKNESS_ANALYSIS_SERVICE` で上書き可)(NFR-quiz-answering-010)

### Factory

- [x] **`SectionQuestionAnswerFactory`(v3 rename)** — `forUser($user)` / `forQuestion($question)` / `correct()` / `incorrect()` / `source(AnswerSource $source)` / `answeredOn(Carbon $at)` state
- [x] **`SectionQuestionAttemptFactory`(v3 rename)** — `forUser($user)` / `forQuestion($question)` / `withAttempts(int $count, int $correct)` / `lastIs(bool $correct)` / `lastAnsweredAt(Carbon $at)` state

## Step 2: Policy

- [x] **Policy: `App\Policies\SectionQuestionAnswerPolicy`(v3 rename)** — `view(User, SectionQuestionAnswer)` / `create(User, SectionQuestion)`(本人 + Student + InProgress + Enrollment(learning/passed) + cascade visibility)(REQ-quiz-answering-081)
- [x] **Policy: `App\Policies\SectionQuestionAttemptPolicy`(v3 rename)** — `view(User, SectionQuestionAttempt)`(本人のみ)(REQ-quiz-answering-192)
- [x] Policy: `App\Policies\SectionQuizPolicy`(`view(User, Section)`、Enrollment(learning/passed) 存在 + cascade visibility)(REQ-quiz-answering-020)
- [x] Policy: `App\Policies\WeakDrillPolicy`(`view(User, Enrollment)`、本人 + **`status IN (learning, passed)`**(v3 で `paused` → `passed` 変更))(REQ-quiz-answering-050)
- [x] `AuthServiceProvider::$policies` 登録

### 明示的に持たない Policy(v3 撤回)

- 旧 `AnswerPolicy`(代わりに `SectionQuestionAnswerPolicy`)
- 旧 `QuestionAttemptPolicy`(代わりに `SectionQuestionAttemptPolicy`)

## Step 3: HTTP 層

### Controller(Web)

- [x] Controller: `App\Http\Controllers\SectionQuizController`(`show(Section)` / `showQuestion(Section, SectionQuestion)`、View 返却)(REQ-quiz-answering-020)
- [x] Controller: `App\Http\Controllers\WeakDrillController`(`index(Enrollment)` / `showCategory(Enrollment, QuestionCategory)` / `showQuestion(Enrollment, QuestionCategory, SectionQuestion)`、View 返却)(REQ-quiz-answering-050)
- [x] **Controller: `App\Http\Controllers\SectionQuestionAnswerController`(v3 rename)** — `store(SectionQuestion, SectionQuestionAnswer\StoreRequest): RedirectResponse`、`source` 値で `quiz.sections.result` / `quiz.drills.result` へ 302 redirect 分岐(REQ-quiz-answering-080)
- [x] **Controller: `App\Http\Controllers\SectionQuizResultController`(新規)** — `show(Section, SectionQuestion, SectionQuestionAnswer): View`、本人検証 + answer.section_question_id 整合 + cascade visibility + next_question 解決(REQ-quiz-answering-089)
- [x] **Controller: `App\Http\Controllers\WeakDrillResultController`(新規)** — `show(Enrollment, QuestionCategory, SectionQuestion, SectionQuestionAnswer): View`、本人検証 + 経路整合(enrollment.user / category.certification / answer 一致) + next_question 解決(REQ-quiz-answering-089)
- [x] Controller: `App\Http\Controllers\QuizHistoryController`(`index(Enrollment, IndexRequest)`、View 返却)(REQ-quiz-answering-120)
- [x] Controller: `App\Http\Controllers\QuizStatsController`(`index(Enrollment, IndexRequest)`、View 返却)(REQ-quiz-answering-123)

### FormRequest

- [x] **FormRequest: `App\Http\Requests\SectionQuestionAnswer\StoreRequest`(v3 rename、Controller method `store` と一致)** — `authorize` で `Policy::create($question)` 委譲、`rules: selected_option_id ulid exists where section_question_id` + `source new Enum(AnswerSource::class)` + **`source=section_quiz` なら `section_id ulid exists:sections,id`**、**`source=weak_drill` なら `enrollment_id ulid exists:enrollments,id + 本人` / `question_category_id ulid exists:question_categories,id`**(redirect 先決定に必要、conditional validation)(REQ-quiz-answering-080)
- [x] FormRequest: `App\Http\Requests\QuizHistory\IndexRequest`(`authorize` で `EnrollmentPolicy::view`、`section_id` / `category_id` / `is_correct` / `source` 任意フィルタ、**`difficulty` フィルタなし**)(REQ-quiz-answering-122)
- [x] FormRequest: `App\Http\Requests\QuizStats\IndexRequest`(同上 + `last_is_correct` / `sort` パラメータ)

### Route

- [x] `routes/web.php` に Web 系ルート定義(`auth + role:student + EnsureActiveLearning` Middleware group、prefix `/quiz`、name prefix `quiz.`、`sections.result` / `drills.result` ルート含む、design.md「Route」セクション参照)(REQ-quiz-answering-089, REQ-quiz-answering-190)

## Step 4: Action / Service / Exception / ServiceProvider

### SectionQuiz Action(`App\UseCases\SectionQuiz\`)

- [x] `ShowAction`(Section 配下 SectionQuestion 公開済 + 受講生 SectionQuestionAttempt 同梱、N+1 回避)(REQ-quiz-answering-020)
- [x] `ShowQuestionAction`(SectionQuestion 単体 + 次 SectionQuestion id + SectionQuestionAttempt 同梱、`section_id` 不一致は例外)

### WeakDrill Action(`App\UseCases\WeakDrill\`)

- [x] `IndexAction`(カテゴリ一覧 + `SectionQuestionAttemptStatsService::byCategory` + `WeaknessAnalysisServiceContract::getWeakCategories` + 公開済 SectionQuestion カウント、N+1 回避)(REQ-quiz-answering-051)
- [x] **`ShowCategoryAction`(v3 で SectionQuestion のみ出題)** — `SectionQuestion::scopeVisibleForStudent()` + `whereIn` で `SectionQuestionAttempt` 同梱、**MockExamQuestion 含まず**(REQ-quiz-answering-053)
- [x] `ShowQuestionAction`(SectionQuestion 単体 + attempt 同梱、カテゴリ × certification 整合チェック)

### SectionQuestionAnswer Action(`App\UseCases\SectionQuestionAnswer\`、v3 rename)

- [x] **`StoreAction`** — 3 段ガード `assertQuestionAvailable` / `assertEnrollmentActive`(learning + passed v3) / `option` 検証 → `DB::transaction()` で `SectionQuestionAnswer` INSERT + `SectionQuestionAttempt` UPSERT、`AnswerResult` 戻り値(REQ-quiz-answering-086, NFR-quiz-answering-001)
- [x] `AnswerResult` 値オブジェクト(readonly class、`app/UseCases/SectionQuestionAnswer/AnswerResult.php`)

### QuizHistory / QuizStats Action

- [x] `App\UseCases\QuizHistory\IndexAction`(when チェーンフィルタ + paginate 20 + Eager Loading)
- [x] `App\UseCases\QuizStats\IndexAction`(フィルタ + ソート + paginate 20 + Eager Loading)

### Service(`App\Services\`)

- [x] **`SectionQuestionAttemptStatsService`(v3 rename)** — `summarize(Enrollment): StatsSummary` + `byCategory(Enrollment): Collection<CategoryStats>` + `recentAnswers(Enrollment, int $limit = 5): Collection<SectionQuestionAnswer>`、ステートレス、他資格混入防止(REQ-quiz-answering-150)
- [x] `StatsSummary` DTO(readonly class)
- [x] `CategoryStats` DTO(readonly class)
- [x] `NullWeaknessAnalysisService`(`WeaknessAnalysisServiceContract` の Null Object 実装、`getWeakCategories` は `collect()` を返す)(NFR-quiz-answering-010)
- [x] `WeaknessAnalysisServiceContract` Interface(`app/Services/Contracts/WeaknessAnalysisServiceContract.php`、`getWeakCategories(Enrollment): Collection<QuestionCategory>` 契約)

### ドメイン例外(`app/Exceptions/QuizAnswering/`)

- [x] `EnrollmentInactiveForAnswerException`(HTTP 409)(REQ-quiz-answering-084)
- [x] **`SectionQuestionUnavailableForAnswerException`(HTTP 409、v3 rename)**(REQ-quiz-answering-085)
- [x] **`SectionQuestionOptionMismatchException`(HTTP 422、v3 rename)**(REQ-quiz-answering-082)
- [x] `WeakDrillCategoryMismatchException`(HTTP 404)(REQ-quiz-answering-052)

### 明示的に持たない Exception(v3 撤回)

- 旧 `QuestionUnavailableForAnswerException` / `QuestionOptionMismatchException`(代わりに `SectionQuestion*` 系)

### ServiceProvider

- [x] `App\Providers\QuizAnsweringServiceProvider`(`WeaknessAnalysisServiceContract::class` を `bindIf` で `config('quiz-answering.weakness_analysis_service')` の class、または `NullWeaknessAnalysisService` フォールバック bind)
- [x] `bootstrap/providers.php` に `QuizAnsweringServiceProvider::class` 登録

### Handler 追加

- [x] `app/Exceptions/Handler.php::register()` で本 Feature のドメイン例外マッピング(API JSON / Web flash + redirect back)

## Step 5: Blade ビュー + JavaScript

### Blade(`resources/views/quiz/`)

- [x] `sections/show.blade.php`(Section エントリ画面、SectionQuestion カード一覧 + 「最初から / 未解答から / 全部やり直す」ボタン + 全制覇判定)
- [x] `sections/question.blade.php`(1 問出題画面、SectionQuestion 本文 + `<x-form.radio>` 選択肢 + 解答フォーム + 結果ペイン)
- [x] `drills/index.blade.php`(カテゴリ一覧、おすすめバッジ + 正答率 + SectionQuestion 件数)
- [x] `drills/show.blade.php`(カテゴリ別 SectionQuestion リスト、カテゴリ全体正答率 + おすすめバッジ)
- [x] `drills/question.blade.php`(drills 経路の 1 問出題画面、Section 経路と共通 partial 利用)
- [x] **`sections/result.blade.php`(新規)** — Section 経路の結果画面、正誤バッジ + 自分の選択 + 正解選択肢 + 解説 + 累計 attempt(試行回数/正答率) + 「次の問題へ」リンク(next_question あり時)+ 「Section エントリへ戻る」リンク(REQ-quiz-answering-089)
- [x] **`drills/result.blade.php`(新規)** — ドリル経路の結果画面、Section 経路と同構成 + 「次の問題へ」「カテゴリリストへ戻る」リンク(REQ-quiz-answering-089)
- [x] `history/index.blade.php`(解答履歴一覧、フィルタ + ページネーション)
- [x] `stats/index.blade.php`(SectionQuestion サマリ一覧、フィルタ + ソート + ページネーション)
- [x] `partials/question-card.blade.php`(SectionQuestion カード共通部品、本文プレビュー + 試行数 + 最新正誤バッジ、**`difficulty` 表示なし**)
- [x] `partials/answer-form.blade.php`(解答フォーム共通部品、`<form method="POST" action="/quiz/questions/{q}/answer">` + `@csrf` + ラジオ選択肢 + hidden inputs(`source` / `section_id` or `enrollment_id`+`question_category_id`) + 「解答する」submit ボタン)
- [x] **`partials/result-pane.blade.php`(旧 `grading-result.blade.php` から rename + 役割変更)** — 結果画面共通部品、Section/ドリル 両 `result.blade.php` から `@include` する。正誤バッジ + 自分の選択 vs 正解の対比 + 解説 + 累計 attempt。**JS 不要、サーバ側で `$answer` / `$correctOption` / `$attempt` を Blade に渡してレンダリング**

### JavaScript

> **2026-05-16 撤回**: 旧 `resources/js/quiz-answering/answer-form.js`(Ajax fetch + DOM 操作)は採用しない。本 Feature は JavaScript ファイルを持たない。解答送信は HTML form POST + 302 redirect → 結果画面 Blade の Form POST + Redirect パターンで完結。

## Step 6: テスト

### Feature(HTTP)

- [x] `tests/Feature/Http/SectionQuiz/ShowTest.php`(`learning` で 200 / **`passed` でも 200**(v3) / 未受講 404 / cascade visibility / `graduated` 403)
- [x] `tests/Feature/Http/SectionQuiz/ShowQuestionTest.php`(出題 200 + 次 SectionQuestion id 返却 / Section 不一致 404)
- [x] `tests/Feature/Http/WeakDrill/IndexTest.php`(本人 200 / 他人 403 / `WeaknessAnalysisService` 未バインドでも 200 / `graduated` 403)
- [x] **`tests/Feature/Http/WeakDrill/ShowCategoryTest.php`(v3、SectionQuestion のみ出題確認)** — 資格不一致カテゴリ 404 / cascade visibility 違反 SectionQuestion 除外 / MockExamQuestion は出題されない
- [x] `tests/Feature/Http/WeakDrill/ShowQuestionTest.php`(カテゴリ × 資格 × 問題の三重整合)
- [x] **`tests/Feature/Http/SectionQuestionAnswer/StoreTest.php`(v3 rename)** — 正答 INSERT / 誤答 INSERT / 連投で attempt_count += 1 / **Enrollment passed でも 302**(v3) / `source=section_quiz` で `quiz.sections.result` へ 302 redirect / `source=weak_drill` で `quiz.drills.result` へ 302 redirect / `graduated` 403 / SectionQuestion Draft 409 / 選択肢不一致 422 / 他資格 403
- [x] **`tests/Feature/Http/SectionQuiz/ResultTest.php`(新規)** — 本人の answer で 200 + 正誤/自分の選択/正解/解説/attempt 表示 / 他者の answer 403 / answer.section_question_id 不一致 404 / 最終問題で「次の問題へ」リンク非表示 / cascade visibility 違反 404
- [x] **`tests/Feature/Http/WeakDrill/ResultTest.php`(新規)** — 本人の answer で 200 / 経路整合(enrollment.user / category.certification / answer 一致)検証 404 / 「次の問題へ」リンク存在/非存在
- [x] `tests/Feature/Http/QuizHistory/IndexTest.php`(本人のみ / 他者 403 / フィルタ / 他資格混入しない)
- [x] `tests/Feature/Http/QuizStats/IndexTest.php`(サマリ表示 / ソート / `accuracy_asc` で正答率 0 が先頭)
- [x] `tests/Feature/Http/EnsureActiveLearningTest.php`(`graduated` で全エンドポイント 403)

### Feature(UseCases)

- [x] **`tests/Feature/UseCases/SectionQuestionAnswer/StoreActionTest.php`(v3 rename)** — `AnswerResult` 値 / 新規 attempt / 既存 attempt UPDATE / SoftDeleted restore / トランザクション原子性
- [x] `tests/Feature/UseCases/WeakDrill/IndexActionTest.php`(カテゴリ統計 / `is_weak` フラグの mock-exam Service 連動 / Null fallback)

### Unit(Services)

- [x] **`tests/Unit/Services/SectionQuestionAttemptStatsServiceTest.php`(v3 rename)** — `summarize` 0 件で null accuracy / `byCategory` GROUP BY / `recentAnswers` limit / 他資格混入防止
- [x] `tests/Unit/Services/NullWeaknessAnalysisServiceTest.php`(常に空 Collection)

### Unit(Policies)

- [x] **`tests/Unit/Policies/SectionQuestionAnswerPolicyTest.php`(v3 rename)** — `view` 本人のみ / `create` ロール × Enrollment(learning + passed) × cascade visibility 各分岐
- [x] **`tests/Unit/Policies/SectionQuestionAttemptPolicyTest.php`(v3 rename)** — `view` 本人のみ
- [x] `tests/Unit/Policies/SectionQuizPolicyTest.php`(Enrollment(learning + passed) + cascade visibility 網羅)
- [x] **`tests/Unit/Policies/WeakDrillPolicyTest.php`(v3)** — 本人 + `status IN (learning, passed)` 網羅(旧 `paused` 削除)

## Step 7: 動作確認 & 整形

- [x] `sail artisan migrate:fresh --seed`(既存 [[content-management]] / [[enrollment]] / [[learning]] seeder 完了前提)
- [x] `sail artisan test --filter=QuizAnswering` 全件 pass
- [x] `sail artisan test --filter=SectionQuestion` 関連も pass
- [x] `sail artisan test` 全体実行で他 Feature への副作用なし(特に [[content-management]] が `SectionQuestion` 参照に切替済か)
- [x] `sail bin pint --dirty` 整形
- [x] ブラウザ動作確認シナリオ(Section 演習通しフロー):
  - [ ] [[learning]] Section 詳細から「Section の問題演習へ」リンク → エントリ画面(問題カード一覧)
  - [ ] 問題カードクリック or 「最初から」リンクで出題画面 → 選択肢を選んで「解答する」ボタン押下 → **302 redirect で結果画面へ遷移**
  - [ ] 結果画面で 正誤バッジ / 自分の選択 / 正解 / 解説 / 累計 attempt が表示される
  - [ ] 結果画面の「次の問題へ」リンク → 次の問題出題画面へ遷移 → 連続演習
  - [ ] 最終問題の結果画面では「次の問題へ」リンクが非表示、「Section エントリへ戻る」リンクのみ
  - [ ] `passed` 状態の受講生でも復習として演習可(v3)
  - [ ] `graduated` ユーザーで `/quiz/sections/{section}` → 403
- [x] ブラウザ動作確認シナリオ(苦手分野ドリル通しフロー):
  - [ ] 受講生ダッシュボード or mock-exam 結果画面 → ドリルエントリへ遷移
  - [ ] カテゴリクリック → カテゴリ別問題リスト → 問題クリックで出題画面 → 解答送信 → **302 redirect で結果画面へ遷移**(SectionQuestion のみ、MockExamQuestion 不混入)
  - [ ] 結果画面の「次の問題へ」リンク → 次の問題、最終問題なら「カテゴリリストへ戻る」のみ
  - [ ] おすすめバッジが mock-exam の弱点判定と連動
  - [ ] mock-exam 未実装環境では全カテゴリで `is_weak=false`(フォールバック)
- [x] ブラウザ動作確認シナリオ(履歴・サマリ):
  - [ ] 解答履歴画面 → セクション/カテゴリ/正誤フィルタ動作 + ページング
  - [ ] SectionQuestion サマリ画面 → ソート切替 + 正答率昇順で苦手問題が上位
- [x] ブラウザ動作確認シナリオ(認可・PRG 動作):
  - [ ] Enrollment を手動で `failed` に書き換え → 解答送信 409 + 日本語エラー
  - [ ] コーチ / admin で `/quiz/sections/{section}` 直接アクセス → 403
  - [ ] 他受講生の Enrollment URL で `/quiz/history/{enrollment}` → 403
  - [ ] 他者の answer ID で `.../result/{answer}` を直叩き → 403
  - [ ] CSRF トークンなしで `POST .../answer` → 419
  - [ ] 未ログインで `POST .../answer` → 302 リダイレクト(`/login`)
  - [ ] 結果画面でブラウザリロード → 同じ結果画面が再描画される(PRG パターンによりリロード安全)
  - [ ] 結果画面でブラウザバック → 出題画面に戻り、再度解答送信できる(answer は別レコードで INSERT、attempt_count += 1)
- [x] SectionQuestion を SoftDelete / Draft 化 → 解答送信 409、過去履歴は表示維持(`selected_option_body` snapshot で選択肢本文も残る)
- [x] mock-exam 未実装環境で「おすすめバッジ」が全カテゴリ false 表示になることを確認(NullObject フォールバック)

## v3.5 改修タスク — 教材画面の「演習問題」タブから到達 URL + スコアサマリ Service 公開

### スコアサマリ Service 新設 ([[learning]] 連携)

- [x] **`App\Services\SectionQuestionScoreService` 新設** — 公開メソッド:
  - `summarize(User $user, Section $section): SectionQuestionScoreSummary` — 単一 Section の集計(挑戦回数 / 最高スコア / 最新スコア)
  - `batchSummarize(User $user, Enrollment $enrollment): Collection<Section.id => SectionQuestionScoreSummary>` — Enrollment 配下の全 Section を 1 クエリで集計(1ショット SQL、N+1 回避)
- [x] **`App\Services\SectionQuestionScoreSummary` readonly class 新設** — `attemptCount: int` / `bestScore: int|null` / `latestScore: int|null` / `latestAnsweredAt: Carbon|null` / `accuracyRate: float|null`(%)。Service と同じ `App\Services` 名前空間に同居させる(`app/DTOs/` ディレクトリは作らない)
- [x] テスト: `tests/Unit/Services/SectionQuestionScoreServiceTest.php`(`summarize` / `batchSummarize` の各種パターン、N+1 確認)

### 教材画面「演習問題」タブからの到達 URL

- [x] **`routes/web.php` に「Section ひも付き問題演習」の到達 URL を追加**: `GET /learning/sections/{section}/quiz` (or 既存の `/quiz/sections/{section}` を活用)。教材画面の「演習問題」タブの各 Section リンクから遷移可能にする(REQ-learning-052, 053)
- [x] **教材画面 (`views/learning/enrollments/_partials/quizzes-tab.blade.php`) からの遷移時に SectionQuestionScoreService のスコアサマリを表示**: 挑戦回数 / 最高 / 最新を `<x-learning.section-score-row>` Component で展開(REQ-learning-052)
- [x] **Section 詳細画面 (`/learning/sections/{section}`) の読了ボタン付近に「Section 紐づき問題演習へ」リンク + 最新スコア表示**(REQ-learning-053、iField LMS `SectionQuizButton` 相当)

### 関連要件マッピング追加

- REQ-learning-052: `SectionQuestionScoreService::batchSummarize` を [[learning]] の `ShowEnrollmentAction` から呼出
- REQ-learning-053: Section 詳細の「演習問題へ」リンクは本 Feature の Section 演習 URL に遷移
