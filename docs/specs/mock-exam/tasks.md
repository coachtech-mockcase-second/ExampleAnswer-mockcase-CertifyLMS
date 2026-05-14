# mock-exam タスクリスト

> 1 タスク = 1 コミット粒度。Step 単位で順次実装し、完了したものから `[x]` に更新する。
> 関連要件 ID は `requirements.md` の `REQ-mock-exam-NNN` / `NFR-mock-exam-NNN` を参照。
> コマンドはすべて `sail` プレフィックス（`tech.md` の「コマンド慣習」参照）。

## Step 1: Migration & Model & Enum

- [ ] migration: `create_mock_exams_table`（ULID PK + SoftDeletes + `certification_id` `restrictOnDelete` + `title string 100` + `description text nullable` + `order unsigned` + `time_limit_minutes unsigned` + `passing_score unsigned 1..100` + `is_published boolean default false` + `published_at datetime nullable` + `created_by_user_id` `restrictOnDelete` + `updated_by_user_id` `restrictOnDelete` + `(certification_id, is_published, order)` 複合 INDEX + `(certification_id, deleted_at)` 複合 INDEX + `deleted_at` 単体 INDEX）（REQ-mock-exam-001, NFR-mock-exam-003）
- [ ] migration: `create_mock_exam_questions_table`（ULID PK + SoftDelete 非採用 + `mock_exam_id` `cascadeOnDelete` + `question_id` `restrictOnDelete` + `order unsigned` + `(mock_exam_id, question_id)` UNIQUE + `(mock_exam_id, order)` 複合 INDEX）（REQ-mock-exam-002, NFR-mock-exam-003）
- [ ] migration: `create_mock_exam_sessions_table`（ULID PK + SoftDeletes + `mock_exam_id` `restrictOnDelete` + `enrollment_id` `restrictOnDelete` + `user_id` `restrictOnDelete` + `status enum 5 値` + `generated_question_ids json` + `total_questions unsigned` + `passing_score_snapshot unsigned` + `time_limit_minutes_snapshot unsigned` + `started_at datetime nullable` + `time_limit_ends_at datetime nullable` + `submitted_at datetime nullable` + `graded_at datetime nullable` + `canceled_at datetime nullable` + `total_correct unsigned nullable` + `score_percentage decimal 5,2 nullable` + `pass boolean nullable` + `(enrollment_id, status)` / `(mock_exam_id, pass)` / `(user_id, graded_at)` / `(user_id, status)` 複合 INDEX + `deleted_at` 単体 INDEX）（REQ-mock-exam-003, REQ-mock-exam-008, NFR-mock-exam-003）
- [ ] migration: `create_mock_exam_answers_table`（ULID PK + SoftDelete 非採用 + `mock_exam_session_id` `cascadeOnDelete` + `question_id` `restrictOnDelete` + `selected_option_id nullable + nullOnDelete`（QuestionOption delete-and-insert に対応）+ `selected_option_body string 2000`（不変スナップショット、本文保持で履歴可読性を保証）+ `is_correct boolean default false`（採点時に確定、以後不変）+ `answered_at datetime` + `(mock_exam_session_id, question_id)` UNIQUE + `(mock_exam_session_id, is_correct)` / `(question_id, is_correct)` 複合 INDEX）（REQ-mock-exam-004, NFR-mock-exam-003, NFR-mock-exam-012）
- [ ] Enum: `App\Enums\MockExamSessionStatus`（`NotStarted='not_started'` / `InProgress='in_progress'` / `Submitted='submitted'` / `Graded='graded'` / `Canceled='canceled'`、`label()` 日本語）（REQ-mock-exam-005）
- [ ] Enum: `App\Enums\PassProbabilityBand`（`Safe='safe'` / `Warning='warning'` / `Danger='danger'` / `Unknown='unknown'`、`label()` / `color()` メソッド）（REQ-mock-exam-006）
- [ ] Model: `App\Models\MockExam`（`fillable` / `$casts['is_published'=>'boolean','published_at'=>'datetime']` / `belongsTo(Certification)` / `belongsTo(User, created_by_user_id, createdBy)` / `belongsTo(User, updated_by_user_id, updatedBy)` / `belongsToMany(Question, mock_exam_questions)->withPivot('order')->orderByPivot('order')` / `hasMany(MockExamSession)` / `scopePublished` / `scopeOfCertification` / `scopeKeyword` / `scopeOrdered`）（REQ-mock-exam-001, REQ-mock-exam-007）
- [ ] Model: `App\Models\MockExamSession`（`fillable` / `$casts['status'=>MockExamSessionStatus::class,'generated_question_ids'=>'array','started_at'=>'datetime','time_limit_ends_at'=>'datetime','submitted_at'=>'datetime','graded_at'=>'datetime','canceled_at'=>'datetime','total_correct'=>'integer','total_questions'=>'integer','passing_score_snapshot'=>'integer','time_limit_minutes_snapshot'=>'integer','score_percentage'=>'decimal:2','pass'=>'boolean']` / `belongsTo(MockExam)` / `belongsTo(Enrollment)` / `belongsTo(User, user_id)` / `hasMany(MockExamAnswer)` / `scopeOfUser` / `scopeOfEnrollment` / `scopeStatus` / `scopeActive`（in_progress, submitted, graded）/ `scopePassed`（graded AND pass=true）/ `scopeGraded`）（REQ-mock-exam-003, REQ-mock-exam-007）
- [ ] Model: `App\Models\MockExamAnswer`（`fillable` / `$casts['is_correct'=>'boolean','answered_at'=>'datetime']` / `belongsTo(MockExamSession)` / `belongsTo(Question)` / `belongsTo(QuestionOption, selected_option_id)` / `scopeOfSession` / `scopeCorrect` / `scopeIncorrect` / `scopeForQuestion`）（REQ-mock-exam-004, REQ-mock-exam-007）
- [ ] [[content-management]] への追加: `App\Models\Question` に `belongsToMany(MockExam, mock_exam_questions)->withPivot('order')` と `hasMany(MockExamAnswer)` リレーション追加（[[content-management]] design L214 予告との整合確認）（REQ-mock-exam-009）
- [ ] [[auth]] への追加: `App\Models\User` に `hasMany(MockExamSession, user_id)` リレーション追加（REQ-mock-exam-009）
- [ ] [[certification-management]] への追加: `App\Models\Certification` に `hasMany(MockExam)` リレーション追加（既存 design L184 予告との整合確認）（REQ-mock-exam-010）
- [ ] Factory: `MockExamFactory`（`forCertification($certification)` / `published()` / `unpublished()` / `withTimeLimit(int $minutes)` / `withPassingScore(int $score)` state）
- [ ] Factory: `MockExamSessionFactory`（`forEnrollment($enrollment)` / `forMockExam($mockExam)` / `notStarted()` / `inProgress()` / `submitted()` / `graded()` / `canceled()` / `pass()` / `fail()` / `withGeneratedQuestions(array $ids)` state）
- [ ] Factory: `MockExamAnswerFactory`（`forSession($session)` / `forQuestion($question)` / `correct()` / `incorrect()` state）

## Step 2: Policy

- [ ] Policy: `App\Policies\MockExamPolicy`（`viewAny` / `view` / `create(User, Certification)` / `update` / `delete` / `publish` / `manageQuestions` / `take`）（REQ-mock-exam-401, REQ-mock-exam-073）
- [ ] Policy: `App\Policies\MockExamSessionPolicy`（`viewAny` / `view` / `start` / `saveAnswer` / `submit` / `cancel`）（REQ-mock-exam-402）
- [ ] `AuthServiceProvider::$policies` に `MockExam::class => MockExamPolicy::class` / `MockExamSession::class => MockExamSessionPolicy::class` 登録

## Step 3: HTTP 層

### Controller（admin / coach 用）

- [ ] Controller: `App\Http\Controllers\MockExamController`（`index(IndexRequest, IndexAction)` / `create(Certification $certification)` / `store(StoreRequest, StoreAction)` / `show(MockExam, ShowAction)` / `edit(MockExam)` / `update(MockExam, UpdateRequest, UpdateAction)` / `destroy(MockExam, DestroyAction)` / `publish(MockExam, PublishAction)` / `unpublish(MockExam, UnpublishAction)` / `reorder(ReorderRequest, ReorderAction)`、Controller method = Action 名一致）（REQ-mock-exam-040, REQ-mock-exam-042, REQ-mock-exam-043, REQ-mock-exam-044, REQ-mock-exam-045, REQ-mock-exam-046, REQ-mock-exam-047, REQ-mock-exam-048, REQ-mock-exam-049）
- [ ] Controller: `App\Http\Controllers\MockExamQuestionController`（`store(MockExam, StoreRequest, StoreAction)` / `destroy(MockExam, Question, DestroyAction)` / `reorderQuestion(MockExam, ReorderQuestionRequest, ReorderQuestionAction)`）（REQ-mock-exam-070, REQ-mock-exam-071, REQ-mock-exam-072）
- [ ] Controller: `App\Http\Controllers\AdminMockExamSessionController`（`index(IndexRequest, IndexAction)` / `show(MockExamSession, ShowAction)`、admin / coach のみ）（REQ-mock-exam-300, REQ-mock-exam-302）

### Controller（student 用）

- [ ] Controller: `App\Http\Controllers\MockExamCatalogController`（`index(IndexAction)` / `show(MockExam, ShowAction)`、受講生用模試カタログ）（REQ-mock-exam-100, REQ-mock-exam-102）
- [ ] Controller: `App\Http\Controllers\MockExamSessionController`（`index(IndexRequest, IndexAction)` / `store(MockExam, StoreAction)`（セッション作成）/ `show(MockExamSession, ShowAction)`（状態に応じて lobby / take / result / canceled 描画）/ `start(MockExamSession, StartAction)` / `submit(MockExamSession, SubmitAction)` / `destroy(MockExamSession, DestroyAction)`（キャンセル））（REQ-mock-exam-103, REQ-mock-exam-105, REQ-mock-exam-150, REQ-mock-exam-151, REQ-mock-exam-200, REQ-mock-exam-251）
- [ ] Controller: `App\Http\Controllers\MockExamAnswerController`（`update(MockExamSession, UpdateRequest, UpdateAction)`、PATCH 経由の逐次解答保存。Web JSON 応答。[[quiz-answering]] の `AnswerController` と名前空間衝突しないよう `MockExam` プレフィックス必須）（REQ-mock-exam-154）

### FormRequest

- [ ] FormRequest: `App\Http\Requests\MockExam\IndexRequest`（admin/coach 一覧、`certification_id` / `is_published` / `keyword` 任意フィルタ + `authorize` で `viewAny`）（REQ-mock-exam-040, REQ-mock-exam-041）
- [ ] FormRequest: `App\Http\Requests\MockExam\StoreRequest`（`certification_id` / `title` / `description` / `order` / `time_limit_minutes` / `passing_score` + `authorize` で `create($certification)`）（REQ-mock-exam-043）
- [ ] FormRequest: `App\Http\Requests\MockExam\UpdateRequest`（Store と同セット、`certification_id` 不可変）（REQ-mock-exam-045）
- [ ] FormRequest: `App\Http\Requests\MockExam\ReorderRequest`（`items.*.id` / `items.*.order` 配列）（REQ-mock-exam-049）
- [ ] FormRequest: `App\Http\Requests\MockExamQuestion\StoreRequest`（`question_id ulid exists` + `authorize` で `manageQuestions($mockExam)`）（REQ-mock-exam-070）
- [ ] FormRequest: `App\Http\Requests\MockExamQuestion\ReorderQuestionRequest`（`items.*.question_id` / `items.*.order` 配列）（REQ-mock-exam-072）
- [ ] FormRequest: `App\Http\Requests\MockExamSession\IndexRequest`（受講生履歴、`certification_id` / `mock_exam_id` / `pass` 任意フィルタ）（REQ-mock-exam-251, REQ-mock-exam-252）
- [ ] FormRequest: `App\Http\Requests\AdminMockExamSession\IndexRequest`（コーチ・admin 結果一覧、`certification_id` / `user_id` / `status` / `pass` 任意フィルタ + coach は `user_id` を担当受講生に絞り込む `withValidator` 検証）（REQ-mock-exam-300, REQ-mock-exam-301）
- [ ] FormRequest: `App\Http\Requests\MockExamAnswer\UpdateRequest`（`question_id ulid in:generated_question_ids` + `selected_option_id ulid exists where question_id` + `authorize` で `saveAnswer($session)`）（REQ-mock-exam-154）

### Resource（API、Advance 予備）

- [ ] Resource: `App\Http\Resources\MockExamResource`（`id` / `certification_id` / `title` / `description` / `order` / `time_limit_minutes` / `passing_score` / `is_published` / `published_at` / optional `questions_count`）（NFR-mock-exam-008 整合）
- [ ] Resource: `App\Http\Resources\MockExamSessionResource`（`id` / `mock_exam_id` / `status` / `total_questions` / `total_correct` / `score_percentage` / `pass` / 各タイムスタンプ / optional `mock_exam`）
- [ ] Resource: `App\Http\Resources\MockExamSessionResultResource`（結果画面用、`session` + `heatmap` + `pass_probability_band`）
- [ ] Resource: `App\Http\Resources\MockExamAnswerResource`（`question_id` / `selected_option_id` / `selected_option_body` / `is_correct` / `answered_at`）
- [ ] Resource: `App\Http\Resources\QuestionForMockExamResource`（出題用、正答非表示版、`id` / `body` / `category` / `difficulty` / `options` は `QuestionOptionResource::collection`、`is_correct` 除外、`explanation` も受験中は除外）（NFR-mock-exam-008）

### Route

- [ ] `routes/web.php` に admin / coach 系ルート定義（`auth + role:admin|coach` Middleware group、prefix `/admin`、name prefix `admin.`、`MockExamController` 全エンドポイント + `MockExamQuestionController` + `AdminMockExamSessionController`）（REQ-mock-exam-400）
- [ ] `routes/web.php` に student 系ルート定義（`auth + role:student` Middleware group、prefix なし、name prefix `mock-exams.` / `mock-exam-sessions.`、`MockExamCatalogController` + `MockExamSessionController` + `MockExamAnswerController`）（REQ-mock-exam-400）

## Step 4: Action / Service / Exception / ServiceProvider

### MockExam Action（`App\UseCases\MockExam\`、admin / coach 用）

- [ ] `IndexAction`（一覧 + フィルタ + `with('certification','createdBy','updatedBy')->withCount('questions')` + `paginate(20)`、coach は割当資格に絞込）（REQ-mock-exam-040, REQ-mock-exam-041, NFR-mock-exam-002）
- [ ] `ShowAction`（`with('certification', 'questions' => withPivot order orderByPivot, 'createdBy', 'updatedBy')->loadCount('sessions')`）（REQ-mock-exam-044, NFR-mock-exam-002）
- [ ] `StoreAction`（`is_published=false` 固定 + `created_by` / `updated_by` セット + `DB::transaction`）（REQ-mock-exam-043, NFR-mock-exam-001）
- [ ] `UpdateAction`（`certification_id` 不可変 + `updated_by` 更新 + `DB::transaction`）（REQ-mock-exam-045, NFR-mock-exam-001）
- [ ] `DestroyAction`（2 段ガード: `is_published=false` + 全セッションが `canceled` のみ → `MockExamInUseException` または SoftDelete）（REQ-mock-exam-046, NFR-mock-exam-001）
- [ ] `PublishAction`（3 段ガード: `is_published=false` + 問題 1 件以上 + 全 Question 公開済 + SoftDelete 済でない → `MockExamPublishNotAllowedException` または UPDATE）（REQ-mock-exam-047, NFR-mock-exam-001）
- [ ] `UnpublishAction`（`is_published=true` ガード + UPDATE）（REQ-mock-exam-048, NFR-mock-exam-001）
- [ ] `ReorderAction`（同一資格内 MockExam の `order` 一括更新、`DB::transaction`）（REQ-mock-exam-049, NFR-mock-exam-001）

### MockExamCatalog Action（`App\UseCases\MockExamCatalog\`、student 用）

- [ ] `IndexAction`（受講生の `enrollments WHERE status IN(learning,paused)` を起点に、各 `certification_id` 配下の `MockExam WHERE is_published=true` を eager load、各 MockExam の最新セッション・進行中セッションを併せて返却）（REQ-mock-exam-100, REQ-mock-exam-101, NFR-mock-exam-002）
- [ ] `ShowAction`（受講中 + 公開チェック後、`MockExam` 詳細 + 最新セッション / 進行中セッション併記）（REQ-mock-exam-102）

### MockExamQuestion Action（`App\UseCases\MockExamQuestion\`）

- [ ] `StoreAction`（資格一致 / 公開済 / 重複なし検証 → `mock_exam_questions` 末尾 attach、`lockForUpdate` で `MAX(order)` 取得）（REQ-mock-exam-070, NFR-mock-exam-001, NFR-mock-exam-009）
- [ ] `DestroyAction`（`mock_exam_questions` から DELETE、既存セッションの `generated_question_ids` snapshot は不変）（REQ-mock-exam-071, NFR-mock-exam-001）
- [ ] `ReorderQuestionAction`（ペイロード網羅性検証 + `order` 一括 UPDATE、`DB::transaction`）（REQ-mock-exam-072, NFR-mock-exam-001）

### MockExamSession Action（`App\UseCases\MockExamSession\`、student 用 + internal）

- [ ] `IndexAction`（受講生の履歴一覧、`whereIn('status', [Graded, Canceled])->with('mockExam.certification')->orderByDesc('graded_at')->orderByDesc('canceled_at')->paginate(20)` + フィルタ）（REQ-mock-exam-251, REQ-mock-exam-252, NFR-mock-exam-002）
- [ ] `ShowAction`（状態に応じて各種データ準備: `NotStarted` → mockExam の概要 / `InProgress` → questions + answersByQid を eager load / `Graded` → heatmap + pass_probability_band / `Canceled` → 戻り導線のみ）（REQ-mock-exam-151, REQ-mock-exam-152, REQ-mock-exam-250, NFR-mock-exam-002）
- [ ] `StoreAction`（Enrollment 取得 + 重複進行中ガード + 問題 ID snapshot + スナップショット値固定で INSERT、`DB::transaction` + `lockForUpdate`）（REQ-mock-exam-103, REQ-mock-exam-104, NFR-mock-exam-001, NFR-mock-exam-009）
- [ ] `StartAction`（`TermJudgementService` を constructor 注入、NotStarted ガード + 公開ガード → `status=InProgress` / `started_at=now` / `time_limit_ends_at` 計算で UPDATE + `recalculate($enrollment)` 同一 transaction）（REQ-mock-exam-150, NFR-mock-exam-001, NFR-mock-exam-009）
- [ ] `SubmitAction`（`GradeAction` / `TermJudgementService` を constructor 注入、InProgress ガード → `status=Submitted` + `submitted_at` UPDATE → `GradeAction` 呼出 → `recalculate` → `DB::afterCommit` で notification 起点 dispatch）（REQ-mock-exam-200, REQ-mock-exam-204, REQ-mock-exam-205, NFR-mock-exam-001, NFR-mock-exam-009）
- [ ] `GradeAction`（internal、SubmitAction 内呼出専用、Controller から直接呼ばない）: `mock_exam_answers` の `is_correct` を raw UPDATE で確定 + `total_correct` 集計 + `score_percentage` ROUND + `pass` 判定 → `status=Graded` + 関連カラム UPDATE。Question / Option SoftDelete を `withTrashed` で扱う（REQ-mock-exam-220, REQ-mock-exam-221, REQ-mock-exam-222, REQ-mock-exam-223, REQ-mock-exam-224）
- [ ] `DestroyAction`（キャンセル、`TermJudgementService` 注入、NotStarted ガード → `status=Canceled` + `canceled_at` UPDATE + `recalculate`）（REQ-mock-exam-105, NFR-mock-exam-001）

### MockExamAnswer Action（`App\UseCases\MockExamAnswer\`、student 用）

- [ ] `UpdateAction`（4 段ガード: status=InProgress / 時間内 / question_id ∈ generated_question_ids / option 一致 → UPSERT、`lockForUpdate`）（REQ-mock-exam-154, REQ-mock-exam-155, NFR-mock-exam-001, NFR-mock-exam-007, NFR-mock-exam-009）

### AdminMockExamSession Action（`App\UseCases\AdminMockExamSession\`、admin / coach 用）

- [ ] `IndexAction`（admin は全件、coach は `whereHas('enrollment', fn ($q) => $q->where('assigned_coach_id', $auth->id))` 絞込、`with('user', 'mockExam.certification', 'enrollment')` + フィルタ + paginate）（REQ-mock-exam-300, REQ-mock-exam-301, NFR-mock-exam-002）
- [ ] `ShowAction`（admin / coach 認可後、受講生視点と同じ `MockExamSession` データ + `WeaknessAnalysisService::getHeatmap` + `getPassProbabilityBand` を呼出側に返す、Blade は閲覧専用）（REQ-mock-exam-302, REQ-mock-exam-303, NFR-mock-exam-002）

### Service（`App\Services\`）

- [ ] `WeaknessAnalysisService`（`WeaknessAnalysisServiceContract` を implements、`getWeakCategories(Enrollment): Collection<QuestionCategory>` + `getHeatmap(MockExamSession): Collection<CategoryHeatmapCell>` + `getPassProbabilityBand(Enrollment): PassProbabilityBand`、ステートレス、トランザクション非保有）（REQ-mock-exam-350, REQ-mock-exam-351, REQ-mock-exam-352, REQ-mock-exam-353, REQ-mock-exam-354, REQ-mock-exam-356, NFR-mock-exam-005）
- [ ] `CategoryHeatmapCell` DTO（readonly class、`app/Services/CategoryHeatmapCell.php`）（REQ-mock-exam-352）

### ドメイン例外（`app/Exceptions/MockExam/`）

- [ ] `MockExamInUseException`（HTTP 409、`ConflictHttpException` 継承）（REQ-mock-exam-046, NFR-mock-exam-004）
- [ ] `MockExamPublishNotAllowedException`（HTTP 409）（REQ-mock-exam-047, NFR-mock-exam-004）
- [ ] `MockExamHasNoQuestionsException`（HTTP 409）（REQ-mock-exam-104, NFR-mock-exam-004）
- [ ] `MockExamUnavailableException`（HTTP 409）（REQ-mock-exam-150, NFR-mock-exam-004）
- [ ] `MockExamSessionAlreadyInProgressException`（HTTP 409）（REQ-mock-exam-103, NFR-mock-exam-004）
- [ ] `MockExamSessionAlreadyStartedException`（HTTP 409）（REQ-mock-exam-150, NFR-mock-exam-004）
- [ ] `MockExamSessionNotInProgressException`（HTTP 409）（REQ-mock-exam-154, REQ-mock-exam-200, NFR-mock-exam-004）
- [ ] `MockExamSessionNotCancelableException`（HTTP 409）（REQ-mock-exam-105, NFR-mock-exam-004）
- [ ] `MockExamSessionTimeExceededException`（HTTP 409）（REQ-mock-exam-154, NFR-mock-exam-004）
- [ ] `MockExamQuestionIneligibleException`（HTTP 409）（REQ-mock-exam-070, NFR-mock-exam-004）
- [ ] `MockExamQuestionNotInSessionException`（HTTP 422、`UnprocessableEntityHttpException` 継承）（REQ-mock-exam-154, NFR-mock-exam-004）
- [ ] `MockExamOptionMismatchException`（HTTP 422）（REQ-mock-exam-154, NFR-mock-exam-004）

### ServiceProvider

- [ ] `App\Providers\MockExamServiceProvider`（`register()` で `WeaknessAnalysisServiceContract::class` を `WeaknessAnalysisService::class` に `bind`（`bindIf` ではなく明示 `bind` で [[quiz-answering]] のフォールバックを上書き））（REQ-mock-exam-355）
- [ ] `bootstrap/providers.php` に `MockExamServiceProvider::class` 登録確認（Wave 0b で確定済 `providers.php` を編集）

### Handler 追加

- [ ] `app/Exceptions/Handler.php` の `register()` に本 Feature のドメイン例外マッピングを追加（API リクエスト時に JSON 返却、`{ message, error_code, status }`、Web リクエスト時は flash + redirect back）（NFR-mock-exam-004）

## Step 5: Blade ビュー + JavaScript

### Blade（admin / coach 用、`resources/views/admin/`）

- [ ] `admin/mock-exams/index.blade.php`（一覧 + フィルタ + ページネーション + 並び順 drag-and-drop UI、`<x-table>` + `<x-paginator>`）（REQ-mock-exam-040, REQ-mock-exam-041, REQ-mock-exam-049, NFR-mock-exam-010）
- [ ] `admin/mock-exams/create.blade.php`（新規作成フォーム、`<x-form.input>` / `<x-form.select>` / `<x-form.textarea>`）（REQ-mock-exam-042, REQ-mock-exam-043, NFR-mock-exam-010）
- [ ] `admin/mock-exams/edit.blade.php`（編集フォーム、`<x-form.*>` + 公開・非公開切替ボタン + 削除ボタン）（REQ-mock-exam-045, REQ-mock-exam-046, REQ-mock-exam-047, REQ-mock-exam-048, NFR-mock-exam-010）
- [ ] `admin/mock-exams/show.blade.php`（詳細 + 問題セット組成 UI: 現在の問題リスト + Question 選択ドロップダウン（資格内の公開 Question から選ぶ）+ 並び順 drag-and-drop）（REQ-mock-exam-044, REQ-mock-exam-070, REQ-mock-exam-071, REQ-mock-exam-072, NFR-mock-exam-010）
- [ ] `admin/mock-exam-sessions/index.blade.php`（admin / coach 用結果一覧 + フィルタ + ページネーション、`<x-table>`）（REQ-mock-exam-300, REQ-mock-exam-301, NFR-mock-exam-010）
- [ ] `admin/mock-exam-sessions/show.blade.php`（受講生視点の result / take を閲覧専用化、選択肢 disable、heatmap + pass_probability_band 表示）（REQ-mock-exam-302, REQ-mock-exam-303, NFR-mock-exam-010）

### Blade（student 用、`resources/views/`）

- [ ] `mock-exams/index.blade.php`（受講生用模試一覧、受講中資格別グルーピング + 各 MockExam のバッジ表示: 進行中 / 合格 / 不合格 / 未受験）（REQ-mock-exam-100, REQ-mock-exam-101, NFR-mock-exam-010）
- [ ] `mock-exams/show.blade.php`（受講生用模試詳細、進行中セッションあり時の「再開」リンク or 新規セッション作成ボタン）（REQ-mock-exam-102, NFR-mock-exam-010）
- [ ] `mock-exam-sessions/index.blade.php`（受講生履歴一覧 + フィルタ + ページネーション、`<x-table>`）（REQ-mock-exam-251, REQ-mock-exam-252, NFR-mock-exam-010）
- [ ] `mock-exam-sessions/lobby.blade.php`（NotStarted セッション画面、開始ボタン + キャンセルボタン + 概要表示）（REQ-mock-exam-151, NFR-mock-exam-010）
- [ ] `mock-exam-sessions/take.blade.php`（InProgress 受験画面、タイマー + 問題一覧 + ラジオ選択肢 + sticky 提出ボタン、`data-time-limit-ends-at` + `data-server-now` 埋込、選択肢へ `data-quiz-autosave` 付与）（REQ-mock-exam-152, REQ-mock-exam-153, NFR-mock-exam-006, NFR-mock-exam-007, NFR-mock-exam-008, NFR-mock-exam-010, NFR-mock-exam-011）
- [ ] `mock-exam-sessions/result.blade.php`（Graded 結果画面、得点 + 合否バッジ + 分野別ヒートマップ + 合格可能性スコア + カテゴリ別「苦手分野ドリルへ」リンク + 「もう一度受験」+ 「履歴へ」）（REQ-mock-exam-250, NFR-mock-exam-010）
- [ ] `mock-exam-sessions/canceled.blade.php`（Canceled セッション、メッセージ + 戻り導線のみ）（REQ-mock-exam-151, NFR-mock-exam-010）

### JavaScript（`resources/js/mock-exam/`）

- [ ] `timer.js`（`#mock-exam-timer` 要素を捕捉、`data-time-limit-ends-at` + `data-server-now` でサーバ時刻補正 → 1秒ごとにカウントダウン → 0 秒で `mock-exam:time-up` カスタムイベント dispatch）（REQ-mock-exam-152, REQ-mock-exam-155, NFR-mock-exam-006, NFR-mock-exam-007）
- [ ] `answer-autosave.js`（`[data-quiz-autosave]` 要素を捕捉、`change` イベントで `patchJson(answer_url, {question_id, selected_option_id})` → 保存状態を `[data-save-status]` に反映）（REQ-mock-exam-154, NFR-mock-exam-006）
- [ ] `auto-submit.js`（`mock-exam:time-up` イベントで `#mock-exam-submit-form` を `submit()` 実行）（REQ-mock-exam-156, NFR-mock-exam-006）
- [ ] `admin/mock-exam-questions-reorder.js`（admin / coach 用、Question 並び順 drag-and-drop、`PUT /admin/mock-exams/{id}/questions/order` 呼出）（REQ-mock-exam-072）
- [ ] `admin/mock-exams-reorder.js`（admin / coach 用、MockExam 並び順 drag-and-drop、`PUT /admin/mock-exams/order` 呼出）（REQ-mock-exam-049）
- [ ] `resources/js/utils/fetch-json.js` に `patchJson` 追加（Wave 0b で `postJson` のみだった場合）
- [ ] `app.js` への import 追加（`resources/js/app.js` に `import './mock-exam/timer.js'` 等、受験中画面でのみロードされるよう dynamic import 推奨）

## Step 6: テスト

### Feature（HTTP）

- [ ] `tests/Feature/Http/MockExam/IndexTest.php`（admin / coach の一覧表示 / 担当外資格除外 / フィルタ動作）（REQ-mock-exam-040, REQ-mock-exam-041）
- [ ] `tests/Feature/Http/MockExam/StoreTest.php`（admin / coach 作成 / 担当外資格に対する作成 403 / バリデーション失敗）（REQ-mock-exam-042, REQ-mock-exam-043）
- [ ] `tests/Feature/Http/MockExam/PublishTest.php`（3 段ガード網羅: is_published=false でないとき 409 / 問題ゼロ時 409 / Question 未公開時 409 / 全条件クリア時 200）（REQ-mock-exam-047）
- [ ] `tests/Feature/Http/MockExam/UnpublishTest.php`（公開済から非公開化 / 既存 in_progress セッション影響なし確認）（REQ-mock-exam-048, REQ-mock-exam-203）
- [ ] `tests/Feature/Http/MockExam/DestroyTest.php`（is_published=true で 409 / 採点済セッション残存で 409 / 全 canceled なら SoftDelete 成功）（REQ-mock-exam-046）
- [ ] `tests/Feature/Http/MockExamQuestion/StoreTest.php`（資格不一致 question_id で 409 / 重複 attach で 409 / 正常時 order 採番）（REQ-mock-exam-070）
- [ ] `tests/Feature/Http/MockExamQuestion/DestroyTest.php`（DetachQuestion 後も既存 session の generated_question_ids 不変確認）（REQ-mock-exam-071）
- [ ] `tests/Feature/Http/MockExamCatalog/IndexTest.php`（受講中資格の公開模試のみ表示 / 未受講資格除外 / 非公開模試除外 / 進行中バッジ表示）（REQ-mock-exam-100, REQ-mock-exam-101）
- [ ] `tests/Feature/Http/MockExamCatalog/ShowTest.php`（未受講資格で 404 / 非公開で 404 / 受講中 + 公開で 200）（REQ-mock-exam-102）
- [ ] `tests/Feature/Http/MockExamSession/StoreTest.php`（重複進行中で 409 / Question ゼロで 409 / 正常時 not_started で INSERT + snapshot 値確認）（REQ-mock-exam-103, REQ-mock-exam-104）
- [ ] `tests/Feature/Http/MockExamSession/StartTest.php`（NotStarted から InProgress / 既に in_progress で 409 / 非公開模試で 409 / TermJudgement が呼ばれて current_term が mock_practice に切替確認）（REQ-mock-exam-150, REQ-mock-exam-153）
- [ ] `tests/Feature/Http/MockExamSession/DestroyTest.php`（NotStarted のキャンセル / in_progress で 409 / TermJudgement が呼ばれて basic_learning に戻ること確認）（REQ-mock-exam-105）
- [ ] `tests/Feature/Http/MockExamSession/ShowTest.php`（各 status での Blade 分岐確認 / 他者セッション 403 / 未受講資格セッション 403）（REQ-mock-exam-151, REQ-mock-exam-253）
- [ ] `tests/Feature/Http/MockExamSession/SubmitTest.php`（InProgress から graded への遷移 / 採点結果 total_correct/score_percentage/pass 確認 / 時間切れ後の submit でも採点完遂 / 二重押下で 409）（REQ-mock-exam-200, REQ-mock-exam-201, REQ-mock-exam-202, REQ-mock-exam-204, REQ-mock-exam-205）
- [ ] `tests/Feature/Http/MockExamAnswer/UpdateTest.php`（PATCH 解答保存 / 時間外で 409 / status=NotStarted で 409 / question_id 不所属で 422 / option 不一致で 422 / 同一 question への再 PATCH で UPDATE 動作）（REQ-mock-exam-154, REQ-mock-exam-155）
- [ ] `tests/Feature/Http/AdminMockExamSession/IndexTest.php`（admin 全件 / coach 担当受講生のみ / 担当外受講生で 403 / フィルタ動作）（REQ-mock-exam-300, REQ-mock-exam-301）
- [ ] `tests/Feature/Http/AdminMockExamSession/ShowTest.php`（admin / coach 閲覧 / 他コーチ担当受講生で 403 / 閲覧専用 UI 確認）（REQ-mock-exam-302, REQ-mock-exam-405）

### Feature（UseCases）

- [ ] `tests/Feature/UseCases/MockExamSession/StoreActionTest.php`（重複進行中ガード / Question ゼロガード / generated_question_ids snapshot / passing_score_snapshot 固定確認）（REQ-mock-exam-103, REQ-mock-exam-104）
- [ ] `tests/Feature/UseCases/MockExamSession/StartActionTest.php`（NotStarted ガード / 公開ガード / time_limit_ends_at がサーバ時刻基準計算 / TermJudgementService 呼出確認）（REQ-mock-exam-150）
- [ ] `tests/Feature/UseCases/MockExamSession/SubmitActionTest.php`（InProgress ガード / Submitted 経由 Graded 到達 / GradeAction 呼出 / lockForUpdate で並列提出競合排除 / DB::afterCommit で notification 起点が走ること）（REQ-mock-exam-200, REQ-mock-exam-205）
- [ ] `tests/Feature/UseCases/MockExamSession/GradeActionTest.php`（正答/誤答/未解答が混在するセッションで is_correct 確定 / total_correct = SUM / score_percentage / pass 確定 / Question SoftDelete 状態でも採点完遂 / Option 物理削除（nullOnDelete）でも is_correct=false 扱い）（REQ-mock-exam-220, REQ-mock-exam-221, REQ-mock-exam-222, REQ-mock-exam-223, REQ-mock-exam-224）
- [ ] `tests/Feature/UseCases/MockExamSession/DestroyActionTest.php`（NotStarted ガード / Canceled に遷移 / TermJudgement 呼出 / 他に active なし → basic_learning に戻る）（REQ-mock-exam-105）
- [ ] `tests/Feature/UseCases/MockExamAnswer/UpdateActionTest.php`（4 段ガード網羅 / UPSERT 動作（新規 INSERT + 既存 UPDATE）/ time_limit_ends_at 超過時 throw）（REQ-mock-exam-154, REQ-mock-exam-155）
- [ ] `tests/Feature/UseCases/MockExam/PublishActionTest.php`（3 段ガード網羅 / 公開後 published_at = now / updated_by 更新）（REQ-mock-exam-047）

### Unit（Services）

- [ ] `tests/Unit/Services/WeaknessAnalysisServiceTest.php`（`getWeakCategories`: 0 セッション時に空 Collection / 直近 3 セッションから 70% 未満カテゴリ抽出 / `passing_score_snapshot` 相対閾値の検証 / 他資格の混入なし）（REQ-mock-exam-350, REQ-mock-exam-351, REQ-mock-exam-356）
- [ ] `tests/Unit/Services/WeaknessAnalysisServiceTest.php`（続き、`getHeatmap`: セッション 1 件分のカテゴリ別正答率算出 / 0 件カテゴリの除外 / is_weak フラグ確認）（REQ-mock-exam-352）
- [ ] `tests/Unit/Services/WeaknessAnalysisServiceTest.php`（続き、`getPassProbabilityBand`: 0 件で Unknown / 90% 以上で Safe / 70-90% で Warning / 70% 未満で Danger / 直近 3 セッション数 1 or 2 でも算出）（REQ-mock-exam-353）

### Unit（Policies）

- [ ] `tests/Unit/Policies/MockExamPolicyTest.php`（admin: 全 true / coach: 担当資格のみ true（他コーチ担当資格は false → Controller 層で 403 期待）/ student: 受講中資格のみ `take` で true / 未受講資格は false → Controller 層で 404 期待）（REQ-mock-exam-254, REQ-mock-exam-401, REQ-mock-exam-403, REQ-mock-exam-404）
- [ ] `tests/Unit/Policies/MockExamSessionPolicyTest.php`（admin: 全 true / coach: 担当受講生のみ true / student: 本人 + Student role のみ true）（REQ-mock-exam-402）

### Integration（Service Provider）

- [ ] `tests/Feature/Providers/MockExamServiceProviderTest.php`（`WeaknessAnalysisServiceContract` を resolve すると `WeaknessAnalysisService`（mock-exam 実装）が返ることを確認、`NullWeaknessAnalysisService` ではないこと）（REQ-mock-exam-355）

## Step 7: 動作確認 & 整形

- [ ] `sail artisan migrate:fresh --seed` 実行（既存 [[content-management]] / [[enrollment]] / [[certification-management]] / [[learning]] seeder が事前完了している前提）
- [ ] `sail artisan test --filter=MockExam` で本 Feature テストが全件 pass
- [ ] `sail artisan test` 全体実行で他 Feature への副作用なし（特に [[enrollment]] の `CompletionEligibilityService` テスト / [[quiz-answering]] の `WeakDrillIndexActionTest`（`WeaknessAnalysisServiceContract` が本 Feature 実装に置き換わってもテストが通るか）を green 確認）
- [ ] `sail bin pint --dirty` 整形
- [ ] ブラウザ動作確認シナリオ（admin で MockExam マスタ作成 → 問題セット組成 → 公開 → 受講生ログイン → 受講中資格の模試一覧に表示）
- [ ] ブラウザ動作確認シナリオ（受講生で受験開始 → サイドバーに「進行中模試あり (1)」表示 → タブ閉じ・別画面・再アクセスで「進行中セッションあり」バナーから残り時間継続表示）
- [ ] ブラウザ動作確認シナリオ（受験中、各選択肢クリックで「✓ 保存済」表示 → DevTools で `mock_exam_answers` を確認 → DB に UPSERT 反映）
- [ ] ブラウザ動作確認シナリオ（タイマー 0 秒到達で自動 submit → 採点完了 → 結果画面に分野別ヒートマップ + 合格可能性スコア表示）
- [ ] ブラウザ動作確認シナリオ（結果画面の「苦手分野ドリル」リンクから [[quiz-answering]] の `WeakDrill ShowCategoryAction` へ遷移し、おすすめバッジが点灯することを確認）
- [ ] ブラウザ動作確認シナリオ（受講生が `not_started` セッションをキャンセル → ターム判定再計算 → 他に active なければ basic_learning に戻る）
- [ ] ブラウザ動作確認シナリオ（受講生 A が受験中、コーチが `/admin/mock-exam-sessions/{session}` で閲覧 → 選択肢 disable で答案表示 + heatmap 表示）
- [ ] ブラウザ動作確認シナリオ（公開模試すべて合格達成 → [[enrollment]] のダッシュボードで修了申請ボタン活性化 → 修了申請 → admin 承認）
- [ ] [[mock-exam]] 未公開化テスト: `is_published=false` 切替後、受講中の in_progress セッションが完遂可能 + 新規セッション作成が 409 で阻止される
- [ ] クライアントタイマー改ざんテスト: ブラウザ DevTools で `time-up` イベントを抑制 → サーバ側 `SaveAnswerAction` が `time_limit_ends_at` 超過時に 409 を返すことを確認（手動提出も 409、自動採点フォールバックが必要 → 次のタスク参照）

## Step 8: 時間超過後の遅延処理（Schedule Command）

> 本 Step は **時間切れ後にブラウザを閉じたまま提出されないケース** のフォロー。本来は `auto-submit.js` で submit されるが、クライアント JS の改ざんやネットワーク断で submit が走らない場合の保険。

- [ ] Console Command: `app/Console/Commands/AutoSubmitExpiredMockExamSessions.php`（`mock_exam_sessions WHERE status = in_progress AND time_limit_ends_at < now()` を取得し、各 session に対して `SubmitAction` を直接呼ぶ）（REQ-mock-exam-201）
- [ ] `app/Console/Kernel.php` に `schedule->command('mock-exam:auto-submit-expired')->everyFiveMinutes()` 登録（or hourly）
- [ ] テスト: `tests/Feature/Console/AutoSubmitExpiredMockExamSessionsTest.php`（time_limit_ends_at 超過セッションが自動で graded になること確認 + 期限内 in_progress には影響なし）

> Why everyFiveMinutes: 受験開始から最大 5 分の遅延で自動採点が走る。本試験リハーサル文脈で「終了直後の即時採点」が UX 要件ではないため、低頻度で十分。`product.md` のスコープ外（Queue 化）に踏み込まない、Basic 範囲の Schedule Command で完結。
