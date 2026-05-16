# mock-exam タスクリスト

> 1 タスク = 1 コミット粒度。Step 単位で順次実装し、完了したものから `[x]` に更新する。
> 関連要件 ID は `requirements.md` の `REQ-mock-exam-NNN` / `NFR-mock-exam-NNN` を参照。
> **v3 改修反映 + E-3 time_limit_minutes 削除**: `MockExamQuestion` 独立リソース化 / `MockExamQuestionOption` 新設 / `mock_exam_answers.question_id` → `mock_exam_question_id` / `difficulty` 削除 / 修了申請承認フロー撤回 / `certification.coaches` 経由 / `passed` でも受験可 / `EnsureActiveLearning` 連動 / **`time_limit_minutes` / `time_limit_ends_at` / Schedule Command / タイマー JS / auto-submit 完全撤回**。
> コマンドはすべて `sail` プレフィックス。

## Step 1: Migration & Model & Enum

### Migration

- [ ] **migration: `create_mock_exams_table`(E-3 簡素化)** — ULID PK + SoftDeletes + `certification_id` `restrictOnDelete` + `title string 100` + `description text nullable` + `order unsigned smallint` + `passing_score unsigned tinyint 1..100` + `is_published boolean default false` + `published_at datetime nullable` + `created_by_user_id` / `updated_by_user_id` `restrictOnDelete` + `(certification_id, is_published, order)` 複合 INDEX + `(certification_id, deleted_at)` 複合 INDEX(REQ-mock-exam-001)
  - **`time_limit_minutes` カラムは持たない**(E-3 撤回)
- [ ] migration: `create_mock_exam_questions_table`(ULID PK + SoftDeletes + `mock_exam_id` `cascadeOnDelete` NOT NULL + `category_id` `restrictOnDelete` + `body text NOT NULL` + `explanation text nullable` + `order unsigned smallint` + 複合 INDEX)(REQ-mock-exam-002)
  - **`difficulty` カラムは持たない**(v3 撤回)
- [ ] migration: `create_mock_exam_question_options_table`(ULID PK + SoftDeletes + `mock_exam_question_id` `cascadeOnDelete` + `body text` + `is_correct boolean` + `order` + 複合 INDEX)(REQ-mock-exam-003)
- [ ] **migration: `create_mock_exam_sessions_table`(E-3 簡素化)** — ULID PK + SoftDeletes + `mock_exam_id` `restrictOnDelete` + `enrollment_id` `restrictOnDelete` + `user_id` `restrictOnDelete` + `status enum 5 値` + `generated_question_ids json` + `total_questions` + `passing_score_snapshot` + `started_at datetime nullable` + `submitted_at datetime nullable` + `graded_at datetime nullable` + `canceled_at datetime nullable` + `total_correct nullable` + `score_percentage decimal 5,2 nullable` + `pass boolean nullable` + 各種 INDEX(REQ-mock-exam-004)
  - **`time_limit_minutes_snapshot` / `time_limit_ends_at` カラムは持たない**(E-3 撤回)
- [ ] migration: `create_mock_exam_answers_table`(ULID PK + SoftDelete 非採用 + `mock_exam_session_id` `cascadeOnDelete` + **`mock_exam_question_id`** `restrictOnDelete` + `selected_option_id nullable nullOnDelete` to `mock_exam_question_options` + `selected_option_body string 2000` + `is_correct boolean default false` + `answered_at datetime` + `(mock_exam_session_id, mock_exam_question_id)` UNIQUE)(REQ-mock-exam-005)

### Enum

- [ ] Enum: `App\Enums\MockExamSessionStatus`(`NotStarted` / `InProgress` / `Submitted` / `Graded` / `Canceled`、`label()`)(REQ-mock-exam-006)
- [ ] Enum: `App\Enums\PassProbabilityBand`(`Safe` / `Warning` / `Danger` / `Unknown`、`label()` + `color()`)(REQ-mock-exam-007)

### Model

- [ ] **Model: `App\Models\MockExam`(E-3 簡素化)** — `HasUlids` + `HasFactory` + `SoftDeletes`、`fillable` / `$casts['is_published'=>'boolean','published_at'=>'datetime']` / リレーション / scope。**`time_limit_minutes` プロパティなし**
- [ ] Model: `App\Models\MockExamQuestion`(独立リソース、`difficulty` なし)
- [ ] Model: `App\Models\MockExamQuestionOption`(新設、`is_correct` boolean cast)
- [ ] **Model: `App\Models\MockExamSession`(E-3 簡素化)** — 各 datetime cast(`started_at` / `submitted_at` / `graded_at` / `canceled_at`)、**`time_limit_ends_at` cast なし**、`generated_question_ids` array、`status` cast、`pass` boolean cast
- [ ] Model: `App\Models\MockExamAnswer`(`mock_exam_question_id` 参照)
- [ ] Factory 群(`MockExamFactory` / `MockExamQuestionFactory` / `MockExamQuestionOptionFactory` / `MockExamSessionFactory` / `MockExamAnswerFactory`)、**`MockExamFactory::withTimeLimit()` state なし**(E-3)

### 関連 Feature への追加

- [ ] [[auth]] への追加: `User` に `hasMany(MockExamSession, user_id)` リレーション
- [ ] [[certification-management]] への追加: `Certification` に `hasMany(MockExam)` リレーション
- [ ] [[content-management]] への追加: `QuestionCategory` に `hasMany(MockExamQuestion)` リレーション

## Step 2: Policy

- [ ] Policy: `App\Policies\MockExamPolicy`(`viewAny` / `view` / `create(User, Certification)` / `update` / `delete` / `publish` / `manageQuestions` / `take`、coach は `$mockExam->certification->coaches->contains($user->id)` 判定)
- [ ] Policy: `App\Policies\MockExamQuestionPolicy`(`manage(User, MockExam): bool`)
- [ ] Policy: `App\Policies\MockExamSessionPolicy`(`view` / `start` / `saveAnswer` / `submit` / `cancel`、`certification.coaches` 経由判定)
- [ ] `AuthServiceProvider::$policies` 登録

## Step 3: HTTP 層

### Controller(admin / coach 用)

- [ ] `App\Http\Controllers\MockExamController`(全 CRUD + publish / unpublish / reorder)
- [ ] `App\Http\Controllers\MockExamQuestionController`(v3 で独立 CRUD、`index($mockExam)` / `create($mockExam)` / `store($mockExam, StoreRequest)` / `show($question)` / `edit($question)` / `update($question)` / `destroy($question)` / `reorder($mockExam)`)
- [ ] `App\Http\Controllers\Admin\MockExamSessionController`(`index` / `show`)

### Controller(student 用)

- [ ] `App\Http\Controllers\MockExamCatalogController`(`index` / `show`)
- [ ] `App\Http\Controllers\MockExamSessionController`(`index` / `store($mockExam)` / `show($session)` / `start($session)` / `submit($session)` / `destroy($session)`)
- [ ] `App\Http\Controllers\MockExamAnswerController`(`update($session)`、PATCH 経由 JSON)

### FormRequest

- [ ] **`MockExam\StoreRequest`(E-3 簡素化)** — `certification_id ulid exists` / `title required string max:100` / `description nullable text` / `order required integer min:0` / `passing_score required integer between:1,100`、**`time_limit_minutes` rule 削除**(E-3)
- [ ] `MockExam\UpdateRequest`(同 rules、`certification_id` 不可変)
- [ ] `MockExam\IndexRequest`(`certification_id` / `is_published` / `keyword` 任意フィルタ)
- [ ] `MockExamQuestion\StoreRequest`(`body required text` / `explanation nullable` / `category_id required ulid exists` / `options required array between:2,6` / `options.*.body required` / `options.*.is_correct required boolean` / `options.*.order required integer min:0`)
- [ ] `MockExamQuestion\UpdateRequest`(同 rules、`mock_exam_id` 不可変)
- [ ] `MockExamQuestion\ReorderRequest`(`items.*.id ulid` / `items.*.order integer min:0`)
- [ ] `MockExamSession\IndexRequest`(`certification_id` / `mock_exam_id` / `pass` 任意フィルタ)
- [ ] **`MockExamAnswer\UpdateRequest`(E-3 簡素化)** — `mock_exam_question_id required ulid in:generated_question_ids` / `selected_option_id required ulid exists where mock_exam_question_id`、authorize で `Policy::saveAnswer` 委譲
- [ ] `Admin\MockExamSession\IndexRequest`(`certification_id` / `user_id` / `status` / `pass`、coach は `user_id` を担当受講生に絞込検証)

### Resource(API)

- [ ] `MockExamResource`(`is_published` / `published_at` / `questions_count`、**`time_limit_minutes` フィールドなし**)
- [ ] `MockExamQuestionResource`(v3、`options: MockExamQuestionOptionResource::collection`)
- [ ] `MockExamQuestionOptionResource`(admin/coach 用、正答含む)
- [ ] `QuestionForMockExamSessionResource`(受験中用、**`is_correct` 除外** / 受験中は `explanation` も除外、NFR-mock-exam-008)
- [ ] `MockExamSessionResource`(`status` / `total_correct` / `score_percentage` / `pass` / 各タイムスタンプ、**`time_limit_ends_at` フィールドなし**)
- [ ] `MockExamAnswerResource`(`mock_exam_question_id` / `selected_option_id` / `selected_option_body` / `is_correct` / `answered_at`)

### Route

- [ ] `routes/web.php` に admin / coach 系ルート定義(`auth + verified + role:admin,coach` group + prefix `/admin`):
  - `Route::resource('mock-exams', MockExamController::class)`
  - `Route::post('mock-exams/{mockExam}/publish'|'unpublish')` / `Route::put('mock-exams/reorder')`
  - `Route::resource('mock-exams.questions', MockExamQuestionController::class)->shallow()`(v3)
  - `Route::put('mock-exams/{mockExam}/questions/reorder')`
  - Admin\MockExamSessionController の `index` / `show`
- [ ] `routes/web.php` に student 系ルート定義(`auth + verified + role:student + EnsureActiveLearning` group):
  - Catalog + Session CRUD + Start / Submit / Destroy
  - `Route::patch('mock-exam-sessions/{session}/answers', ...)`

## Step 4: Action / Service / Exception / ServiceProvider

### MockExam Action(admin / coach 用)

- [ ] `IndexAction`(フィルタ + Eager Loading + paginate、coach は割当資格絞込)
- [ ] `ShowAction`(Eager Loading + `loadCount('sessions')`)
- [ ] `StoreAction`(`is_published=false` 固定 + `created_by` / `updated_by` セット、**`time_limit_minutes` フィールド受け取らない**(E-3))
- [ ] `UpdateAction`(`certification_id` 不可変、E-3 で `time_limit_minutes` フィールドなし)
- [ ] `DestroyAction`(`is_published=false` + 全 session canceled で SoftDelete、違反で `MockExamInUseException`)
- [ ] `PublishAction`(問題 1 件以上検証 + `MockExamPublishNotAllowedException`)
- [ ] `UnpublishAction`(`is_published=true` ガード)
- [ ] `ReorderAction`(同一資格内 `order` 一括 UPDATE)

### MockExamQuestion Action(v3 独立 CRUD)

- [ ] `StoreAction`(category_id × certification 一致検証 + is_correct ちょうど 1 検証 + `lockForUpdate` で MAX(order) + INSERT、`DB::transaction`)
- [ ] `UpdateAction`(`body` / `explanation` / `category_id` UPDATE + options を delete-and-insert 同期)
- [ ] `DestroyAction`(SoftDelete、過去 MockExamSession は `generated_question_ids` snapshot + `withTrashed`)
- [ ] `ReorderAction`(同 MockExam 内 `order` 一括 UPDATE)

### MockExamSession Action(E-3 で time_limit 関連削除)

- [ ] `IndexAction`(受講生履歴、`whereIn('status', [Graded, Canceled])` + フィルタ + paginate)
- [ ] `ShowAction`(status 別 Blade 描画用データ準備、NotStarted / InProgress / Graded / Canceled)
- [ ] **`StoreAction`(`MockExamSessionController::store` と一致、E-3 簡素化)** — Enrollment 取得(learning + passed) + 重複進行中ガード + `generated_question_ids` snapshot + `passing_score_snapshot` 固定、**`time_limit_minutes_snapshot` フィールドなし**(E-3)
- [ ] **`StartAction`(`MockExamSessionController::start` と一致、E-3 簡素化)** — `TermJudgementService` 注入、NotStarted ガード + 公開ガード → `status=InProgress` / `started_at=now()` UPDATE + `recalculate`、**`time_limit_ends_at` セットなし**(E-3)、`lockForUpdate`
- [ ] `SubmitAction`(DI: `GradeAction` + `TermJudgementService` + `NotifyMockExamGradedAction`、InProgress ガード → Submitted UPDATE → `GradeAction` 呼出 → `recalculate` → `DB::afterCommit` で通知)
- [ ] `GradeAction`(internal、`is_correct` 確定 + `total_correct` / `score_percentage` / `pass` 確定 → `Graded` UPDATE、option SoftDelete は `withTrashed`)
- [ ] `DestroyAction`(キャンセル、NotStarted ガード → Canceled UPDATE + `recalculate`)

### MockExamAnswer Action(student 用、E-3 で時間検査削除)

- [ ] **`UpdateAction`(E-3 簡素化)** — 3 段ガード: `status=InProgress` / `mock_exam_question_id ∈ generated_question_ids` / `option ∈ question.options` → UPSERT、`lockForUpdate`、**「now() > time_limit_ends_at」検査削除**(E-3)

### Admin\MockExamSession Action

- [ ] `IndexAction`(admin 全件、coach は `certification.coaches` 経由絞込)
- [ ] `ShowAction`(認可後、`WeaknessAnalysisService::getHeatmap` + `getPassProbabilityBand` 同梱)

### Service

- [ ] `App\Services\WeaknessAnalysisService`(`WeaknessAnalysisServiceContract` 実装、`getWeakCategories` / `getHeatmap` / `getPassProbabilityBand` / `batchHeatmap`)
- [ ] `App\Services\CategoryHeatmapCell` DTO(readonly class)

### ドメイン例外(`app/Exceptions/MockExam/`)

- [ ] `MockExamInUseException`(409)
- [ ] `MockExamPublishNotAllowedException`(409)
- [ ] `MockExamHasNoQuestionsException`(409)
- [ ] `MockExamUnavailableException`(409)
- [ ] `MockExamSessionAlreadyInProgressException`(409)
- [ ] `MockExamSessionAlreadyStartedException`(409)
- [ ] `MockExamSessionNotInProgressException`(409)
- [ ] `MockExamSessionNotCancelableException`(409)
- [ ] `MockExamQuestionNotInSessionException`(422)
- [ ] `MockExamOptionMismatchException`(422)
- [ ] `QuestionCategoryMismatchException`(422)
- [ ] `QuestionInvalidOptionsException`(422)

### 明示的に持たない例外(E-3 撤回)

- **`MockExamSessionTimeExceededException`** — 時間制限なし

### ServiceProvider

- [ ] `App\Providers\MockExamServiceProvider`(`WeaknessAnalysisServiceContract::class` → `WeaknessAnalysisService::class` を `bind`)
- [ ] `bootstrap/providers.php` に `MockExamServiceProvider::class` 登録

### Handler 追加

- [ ] `app/Exceptions/Handler.php::register()` で本 Feature ドメイン例外マッピング

## Step 5: Blade ビュー + JavaScript

### Blade(admin / coach 用)

- [ ] `admin/mock-exams/index.blade.php`(一覧 + フィルタ + ページネーション + 並び順 drag-and-drop)
- [ ] `admin/mock-exams/create.blade.php` / `edit.blade.php`(**`time_limit_minutes` 入力欄なし**(E-3))
- [ ] `admin/mock-exams/show.blade.php`(詳細 + 問題リスト + 公開/非公開ボタン + 削除)
- [ ] `admin/mock-exams/questions/index.blade.php`(MockExamQuestion 一覧 + reorder)
- [ ] `admin/mock-exams/questions/create.blade.php` / `edit.blade.php`(`body` / `explanation` / `category_id` / `options[]`)
- [ ] `admin/mock-exam-sessions/index.blade.php` / `show.blade.php`(coach 担当絞込)

### Blade(student 用)

- [ ] `mock-exams/index.blade.php`(受講中資格別グルーピング + バッジ)
- [ ] `mock-exams/show.blade.php`(進行中再開 or 新規セッション作成)
- [ ] `mock-exam-sessions/index.blade.php`(履歴一覧 + フィルタ)
- [ ] `mock-exam-sessions/lobby.blade.php`(NotStarted、開始 + キャンセル、**「制限時間」表示なし**(E-3))
- [ ] **`mock-exam-sessions/take.blade.php`(E-3 簡素化)** — InProgress、問題一覧 + ラジオ選択肢 + sticky 提出ボタン、選択肢へ `data-quiz-autosave` 付与、**タイマー UI / `data-time-limit-ends-at` 埋込なし**(E-3)
- [ ] `mock-exam-sessions/result.blade.php`(Graded、得点 + 合否 + ヒートマップ + 合格可能性 + 苦手分野ドリル + 「もう一度受験」)
- [ ] `mock-exam-sessions/canceled.blade.php`(Canceled、戻り導線のみ)

### JavaScript(`resources/js/mock-exam/`、E-3 で簡素化)

- [ ] `answer-autosave.js`(`[data-quiz-autosave]` の `change` で `PATCH /mock-exam-sessions/{id}/answers` 呼出、`[data-save-status]` 反映)
- [ ] `admin/mock-exam-questions-reorder.js`(問題並び順 drag-and-drop)
- [ ] `admin/mock-exams-reorder.js`(模試並び順 drag-and-drop)

### 明示的に持たない JS(E-3 撤回)

- **`timer.js`** — タイマーカウントダウン機能なし
- **`auto-submit.js`** — 自動提出機能なし

## Step 6: テスト

### Feature(HTTP)

- [ ] `MockExam/IndexTest.php` / `StoreTest.php`(**`time_limit_minutes` フィールド送信時 silently drop**、E-3) / `UpdateTest.php` / `PublishTest.php` / `UnpublishTest.php` / `DestroyTest.php`
- [ ] `MockExamQuestion/StoreTest.php` / `UpdateTest.php` / `DestroyTest.php` / `ReorderTest.php`(v3、category 不一致 / is_correct 多重 / options 件数違反など)
- [ ] `MockExamCatalog/IndexTest.php` / `ShowTest.php`(受講中(learning + passed) のみ表示 / 非公開除外 / graduated 403)
- [ ] `MockExamSession/StoreTest.php`(重複進行中 / Question ゼロ / snapshot 値確認)
- [ ] **`MockExamSession/StartTest.php`(E-3 簡素化)** — NotStarted → InProgress、**`time_limit_ends_at` セットなし確認**(E-3)、TermJudgement で `current_term=mock_practice`
- [ ] `MockExamSession/DestroyTest.php`(NotStarted のキャンセル、TermJudgement で basic_learning 戻り)
- [ ] `MockExamSession/ShowTest.php`(各 status 別 Blade 分岐 / 他者セッション 403)
- [ ] `MockExamSession/SubmitTest.php`(InProgress → graded、二重押下で 409)
- [ ] **`MockExamAnswer/UpdateTest.php`(E-3 簡素化)** — PATCH 解答保存 / NotStarted 409 / `mock_exam_question_id` 不所属 422 / option 不一致 422 / 同一 question UPSERT、**「時間外で 409」テスト削除**(E-3)
- [ ] `Admin/MockExamSession/IndexTest.php` / `ShowTest.php`(v3 certification.coaches 経由判定)
- [ ] `EnsureActiveLearningTest.php`(graduated 403)

### Feature(UseCases)

- [ ] `MockExamQuestion/StoreActionTest.php`(category 不一致 / is_correct 多重 / lockForUpdate MAX(order))
- [ ] `MockExamSession/StoreActionTest.php`(`passing_score_snapshot` 固定確認、**`time_limit_minutes_snapshot` フィールドなし**(E-3))
- [ ] `MockExamSession/StartActionTest.php`(`time_limit_ends_at` assert なし、E-3)
- [ ] `MockExamSession/SubmitActionTest.php`(`lockForUpdate` で並列提出競合排除 / `DB::afterCommit` で notification)
- [ ] `MockExamSession/GradeActionTest.php`(正答/誤答/未解答混在 / Option SoftDelete / Question SoftDelete)
- [ ] `MockExamSession/DestroyActionTest.php`(NotStarted ガード / Canceled 遷移 / TermJudgement)
- [ ] `MockExamAnswer/UpdateActionTest.php`(3 段ガード網羅 / UPSERT、**時間検査テストなし**(E-3))
- [ ] `MockExam/PublishActionTest.php`(3 段ガード網羅)

### Unit(Services / Policies / Provider)

- [ ] `WeaknessAnalysisServiceTest.php`(`getWeakCategories` / `getHeatmap` / `getPassProbabilityBand`)
- [ ] `MockExamPolicyTest.php`(admin / coach 担当 / coach 担当外 / student 受講中(learning + passed))
- [ ] `MockExamQuestionPolicyTest.php`(`manage` の真偽値網羅)
- [ ] `MockExamSessionPolicyTest.php`(v3 certification.coaches 経由)
- [ ] `MockExamServiceProviderTest.php`(`WeaknessAnalysisServiceContract` resolve で本 Feature 実装が返る)

### 明示的に持たないテスト(E-3 撤回)

- **`Console/AutoSubmitExpiredMockExamSessionsCommandTest.php`** — Schedule Command なし

## Step 7: 動作確認 & 整形

- [ ] `sail artisan migrate:fresh --seed`
- [ ] `sail artisan test --filter=MockExam` 全件 pass
- [ ] `sail artisan test` 全体実行で他 Feature 影響なし(特に [[enrollment]] `CompletionEligibilityService` / [[quiz-answering]] 弱点ドリル)
- [ ] `sail bin pint --dirty` 整形
- [ ] ブラウザ動作確認(admin / coach):
  - [ ] admin で MockExam 作成 → **time_limit_minutes 入力欄なし**(E-3 確認) → 問題追加 → 公開 → 受講生で表示
  - [ ] options 並び順 drag-and-drop
  - [ ] MockExamQuestion reorder
  - [ ] 担当外 coach で他資格編集試行 → 403
- [ ] ブラウザ動作確認(student):
  - [ ] 受講中 / 修了済(passed)資格の公開模試を受験 → **タイマー UI が表示されない**(E-3 確認)
  - [ ] 問題解答 → 自動保存
  - [ ] 明示提出 → 採点 → 結果画面
  - [ ] **長時間経過(数時間)後にアクセスしても進行中セッションとして再開可能**(時間制限なし、E-3)
  - [ ] `graduated` ユーザーで 403(EnsureActiveLearning)
- [ ] ブラウザ動作確認(コーチ閲覧):
  - [ ] coach で `/admin/mock-exam-sessions/{session}` → 担当受講生のセッション閲覧(v3 certification.coaches)
- [ ] 修了判定:
  - [ ] 公開模試すべて合格達成 → [[enrollment]] の「修了証を受け取る」ボタン活性化 → Certificate 発行
- [ ] **E-3 撤回確認**:
  - [ ] `mock-exam:auto-submit-expired` Artisan コマンド存在しない
  - [ ] `MockExamSessionTimeExceededException` クラス存在しない
  - [ ] `timer.js` / `auto-submit.js` ファイル存在しない
