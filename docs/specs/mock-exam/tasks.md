# mock-exam タスクリスト

> 1 タスク = 1 コミット粒度。Step 単位で順次実装し、完了したものから `[x]` に更新する。
> 関連要件 ID は `requirements.md` の `REQ-mock-exam-NNN` / `NFR-mock-exam-NNN` を参照。
> **v3 改修反映 + E-3 time_limit_minutes 削除**: `MockExamQuestion` 独立リソース化 / `MockExamQuestionOption` 新設 / `mock_exam_answers.question_id` → `mock_exam_question_id` / `difficulty` 削除 / 修了申請承認フロー撤回 / `certification.coaches` 経由 / `passed` でも受験可 / `EnsureActiveLearning` 連動 / **`time_limit_minutes` / `time_limit_ends_at` / Schedule Command / タイマー JS / auto-submit 完全撤回**。
> コマンドはすべて `sail` プレフィックス。

## Step 1: Migration & Model & Enum

### Migration

- [x] **migration: `create_mock_exams_table`(E-3 簡素化)** — ULID PK + `certification_id` `restrictOnDelete` + `title string 100` + `description text nullable` + `order unsigned smallint` + `passing_score unsigned tinyint 1..100` + `is_published boolean default false` + `published_at datetime nullable` + `created_by_user_id` / `updated_by_user_id` `restrictOnDelete` + `(certification_id, is_published, order)` 複合 INDEX(REQ-mock-exam-001)
  - **`time_limit_minutes` カラムは持たない**(E-3 撤回)
- [x] migration: `create_mock_exam_questions_table`(ULID PK + `mock_exam_id` `cascadeOnDelete` NOT NULL + `category_id` `restrictOnDelete` + `body text NOT NULL` + `explanation text nullable` + `order unsigned smallint` + 複合 INDEX)(REQ-mock-exam-002)
  - **`difficulty` カラムは持たない**(v3 撤回)
- [x] migration: `create_mock_exam_question_options_table`(ULID PK + `mock_exam_question_id` `cascadeOnDelete` + `body text` + `is_correct boolean` + `order` + 複合 INDEX)(REQ-mock-exam-003)
- [x] **migration: `create_mock_exam_sessions_table`(E-3 簡素化)** — ULID PK + `mock_exam_id` `restrictOnDelete` + `enrollment_id` `restrictOnDelete` + `user_id` `restrictOnDelete` + `status enum 5 値` + `generated_question_ids json` + `total_questions` + `passing_score_snapshot` + `started_at datetime nullable` + `submitted_at datetime nullable` + `graded_at datetime nullable` + `canceled_at datetime nullable` + `total_correct nullable` + `score_percentage decimal 5,2 nullable` + `pass boolean nullable` + 各種 INDEX(REQ-mock-exam-004)
  - **`time_limit_minutes_snapshot` / `time_limit_ends_at` カラムは持たない**(E-3 撤回)
- [x] migration: `create_mock_exam_answers_table`(ULID PK + `mock_exam_session_id` `cascadeOnDelete` + **`mock_exam_question_id`** `restrictOnDelete` + `selected_option_id nullable nullOnDelete` to `mock_exam_question_options` + `selected_option_body string 2000` + `is_correct boolean default false` + `answered_at datetime` + `(mock_exam_session_id, mock_exam_question_id)` UNIQUE)(REQ-mock-exam-005)

### Enum

- [x] Enum: `App\Enums\MockExamSessionStatus`(`NotStarted` / `InProgress` / `Submitted` / `Graded` / `Canceled`、`label()`)(REQ-mock-exam-006)
- [x] Enum: `App\Enums\PassProbabilityBand`(`Safe` / `Warning` / `Danger` / `Unknown`、`label()` + `color()`)(REQ-mock-exam-007)

### Model

- [x] **Model: `App\Models\MockExam`(E-3 簡素化)** — `HasUlids` + `HasFactory`、`fillable` / `$casts['is_published'=>'boolean','published_at'=>'datetime']` / リレーション / scope。**`time_limit_minutes` プロパティなし**
- [x] Model: `App\Models\MockExamQuestion`(独立リソース、`difficulty` なし、`HasUlids` + `HasFactory`)
- [x] Model: `App\Models\MockExamQuestionOption`(新設、`HasUlids` + `HasFactory`、`is_correct` boolean cast)
- [x] **Model: `App\Models\MockExamSession`(E-3 簡素化)** — `HasUlids` + `HasFactory`、各 datetime cast(`started_at` / `submitted_at` / `graded_at` / `canceled_at`)、**`time_limit_ends_at` cast なし**、`generated_question_ids` array、`status` cast、`pass` boolean cast
- [x] Model: `App\Models\MockExamAnswer`(`HasUlids` + `HasFactory`、`mock_exam_question_id` 参照)
- [x] Factory 群(`MockExamFactory` / `MockExamQuestionFactory` / `MockExamQuestionOptionFactory` / `MockExamSessionFactory` / `MockExamAnswerFactory`)、**`MockExamFactory::withTimeLimit()` state なし**(E-3)

### 関連 Feature への追加

- [x] [[auth]] への追加: `User` に `hasMany(MockExamSession, user_id)` リレーション
- [x] [[certification-management]] への追加: `Certification` に `hasMany(MockExam)` リレーション
- [x] [[content-management]] への追加: `QuestionCategory` に `hasMany(MockExamQuestion)` リレーション

## Step 2: Policy

- [x] Policy: `App\Policies\MockExamPolicy`(`viewAny` / `view` / `create(User, Certification)` / `update` / `delete` / `publish` / `manageQuestions` / `take`、coach は `$mockExam->certification->coaches->contains($user->id)` 判定)
- [x] Policy: `App\Policies\MockExamQuestionPolicy`(`manage(User, MockExam): bool`)
- [x] Policy: `App\Policies\MockExamSessionPolicy`(`view` / `start` / `saveAnswer` / `submit` / `cancel`、`certification.coaches` 経由判定)
- [x] `AuthServiceProvider::$policies` 登録

## Step 3: HTTP 層

### Controller(admin / coach 用)

- [x] `App\Http\Controllers\MockExamController`(全 CRUD + publish / unpublish / reorder)
- [x] `App\Http\Controllers\MockExamQuestionController`(v3 で独立 CRUD、`index($mockExam)` / `create($mockExam)` / `store($mockExam, StoreRequest)` / `show($question)` / `edit($question)` / `update($question)` / `destroy($question)` / `reorder($mockExam)`)
- [x] `App\Http\Controllers\MockExamSessionMonitorController`(`index` / `show`、`Admin\` namespace は使わずフラット命名)

### Controller(student 用)

- [x] `App\Http\Controllers\MockExamCatalogController`(`index` / `show`)
- [x] `App\Http\Controllers\MockExamSessionController`(`index` / `store($enrollment, $mockExam)` / `show($session)` / `start($session)` / `submit($session)` / `destroy($session)`)
- [x] `App\Http\Controllers\MockExamAnswerController`(`update($session)`、PATCH 経由 JSON)

### FormRequest

- [x] **`MockExam\StoreRequest`(E-3 簡素化)** — `certification_id ulid exists` / `title required string max:100` / `description nullable text` / `order required integer min:0` / `passing_score required integer between:1,100`、**`time_limit_minutes` rule 削除**(E-3)
- [x] `MockExam\UpdateRequest`(同 rules、`certification_id` 不可変)
- [x] `MockExam\IndexRequest`(`certification_id` / `is_published` / `keyword` 任意フィルタ)
- [x] `MockExam\ReorderRequest`(`certification_id` / `items.*.id ulid` / `items.*.order integer min:0`)
- [x] `MockExamQuestion\StoreRequest`(`body required text` / `explanation nullable` / `category_id required ulid exists` / `options required array between:2,6` / `options.*.body required` / `options.*.is_correct required boolean` / `options.*.order required integer min:0`)
- [x] `MockExamQuestion\UpdateRequest`(同 rules、`mock_exam_id` 不可変)
- [x] `MockExamQuestion\ReorderRequest`(`items.*.id ulid` / `items.*.order integer min:0`)
- [x] `MockExamSession\IndexRequest`(`certification_id` / `mock_exam_id` / `pass` 任意フィルタ)
- [x] **`MockExamAnswer\UpdateRequest`(E-3 簡素化)** — `mock_exam_question_id required ulid exists` / `selected_option_id required ulid exists`、authorize で `Policy::saveAnswer` 委譲、ドメイン整合性は UpdateAction で検証(MockExamQuestionNotInSessionException / MockExamOptionMismatchException)
- [x] `MockExamSession\Monitor\IndexRequest`(`certification_id` / `user_id` / `status` / `pass`、coach 絞込は IndexAction 内で `certification.coaches` 経由)

### Resource(API)

- [x] **採用判断: Web Ajax のみ inline 配列 (`response()->json([...])`) で返却**(`backend-http.md` 規約: "Web Ajax の JSON 返却は inline 配列で十分、Resource は不要")。Resource クラスは作らず、`MockExamAnswerController::update` で必要フィールドのみ inline 出力。Blade では Model 直接渡しで OK。受験中の正答秘匿(NFR-mock-exam-008) は Blade 側で `is_correct` を出力しないことで担保。

### Route

- [x] `routes/web.php` に admin / coach 系ルート定義(`auth + role:admin,coach` group + prefix `/admin`):
  - `Route::resource('mock-exams', MockExamController::class)` + `publish` / `unpublish` / `reorder`
  - 模試問題 shallow CRUD + `reorder`
  - `MockExamSessionMonitorController` の `index` / `show`
- [x] `routes/web.php` に student 系ルート定義(`auth + role:student + active-learning` group):
  - 模試カタログ: `/learning/enrollments/{enrollment}/mock-exams` 配下 (v3.5)
  - 受験セッション CRUD + `start` / `submit` / `destroy`(セッション ID 直接参照)
  - `Route::patch('mock-exam-sessions/{session}/answers', ...)`
  - `/mock-exams` 直接アクセスは `resolve-default-enrollment:mock-exam.catalog.index` Middleware で default 資格へ redirect、フォールバックは empty-state view

## Step 4: Action / Service / Exception / ServiceProvider

### MockExam Action(admin / coach 用)

- [x] `IndexAction`(フィルタ + Eager Loading + paginate、coach は割当資格絞込)
- [x] `ShowAction`(Eager Loading + `loadCount('sessions')`)
- [x] `StoreAction`(`is_published=false` 固定 + `created_by` / `updated_by` セット、**`time_limit_minutes` フィールド受け取らない**(E-3))
- [x] `UpdateAction`(`certification_id` 不可変、E-3 で `time_limit_minutes` フィールドなし)
- [x] `DestroyAction`(`is_published=false` + 全 session canceled で物理削除、配下の `mock_exam_questions` / `mock_exam_question_options` は cascade 削除、違反で `MockExamInUseException`)
- [x] `PublishAction`(問題 1 件以上検証 + `MockExamPublishNotAllowedException`)
- [x] `UnpublishAction`(`is_published=true` ガード)
- [x] `ReorderAction`(同一資格内 `order` 一括 UPDATE)

### MockExamQuestion Action(v3 独立 CRUD)

- [x] `StoreAction`(category_id × certification 一致検証 + is_correct ちょうど 1 検証 + `lockForUpdate` で MAX(order) + INSERT、`DB::transaction`)
- [x] `UpdateAction`(`body` / `explanation` / `category_id` UPDATE + options を delete-and-insert 同期)
- [x] `DestroyAction`(物理削除、採点済 `mock_exam_answers.mock_exam_question_id` の `restrictOnDelete` 制約で削除阻止される設計。過去 MockExamSession は `generated_question_ids` snapshot で参照を保持)
- [x] `ReorderAction`(同 MockExam 内 `order` 一括 UPDATE)

### MockExamSession Action(E-3 で time_limit 関連削除)

- [x] `IndexAction`(受講生履歴、`whereIn('status', [Graded, Canceled])` + フィルタ + paginate)
- [x] `ShowAction`(status 別 Blade 描画用データ準備、NotStarted / InProgress / Graded / Canceled)
- [x] **`StoreAction`(`MockExamSessionController::store` と一致、E-3 簡素化)** — Enrollment 取得(learning + passed) + 重複進行中ガード + `generated_question_ids` snapshot + `passing_score_snapshot` 固定、**`time_limit_minutes_snapshot` フィールドなし**(E-3)
- [x] **`StartAction`(`MockExamSessionController::start` と一致、E-3 簡素化)** — `TermJudgementService` 注入、NotStarted ガード + 公開ガード → `status=InProgress` / `started_at=now()` UPDATE + `recalculate`、**`time_limit_ends_at` セットなし**(E-3)、`lockForUpdate`
- [x] `SubmitAction`(DI: `GradeAction` + `TermJudgementService`、InProgress ガード → Submitted UPDATE → `GradeAction` 呼出 → `recalculate`、通知 dispatch は本 Feature 外)
- [x] `GradeAction`(internal、`is_correct` 確定 + `total_correct` / `score_percentage` / `pass` 確定 → `Graded` UPDATE、`selected_option_id` が NULL の場合は `is_correct=false`)
- [x] `DestroyAction`(キャンセル、NotStarted ガード → Canceled UPDATE + `recalculate`)

### MockExamAnswer Action(student 用、E-3 で時間検査削除)

- [x] **`UpdateAction`(E-3 簡素化)** — 3 段ガード: `status=InProgress` / `mock_exam_question_id ∈ generated_question_ids` / `option ∈ question.options` → UPSERT、`lockForUpdate`、**「now() > time_limit_ends_at」検査削除**(E-3)

### MockExamCatalog Action

- [x] `IndexAction`(Enrollment 単位の公開模試一覧 + 進行中セッションマップ)
- [x] `ShowAction`(模試詳細 + 進行中セッション lookup)

### MockExamSession\Monitor Action

- [x] `IndexAction`(admin 全件、coach は `certification.coaches` 経由絞込)
- [x] `ShowAction`(認可後、`WeaknessAnalysisService::getHeatmap` + `getPassProbabilityBand` 同梱)

### Service

- [x] `App\Services\WeaknessAnalysisService`(`WeaknessAnalysisServiceContract` 実装、`getWeakCategories` / `getHeatmap` / `getPassProbabilityBand`)
- [x] `App\Services\CategoryHeatmapCell` DTO(readonly class)

### ドメイン例外(`app/Exceptions/MockExam/`)

- [x] `MockExamInUseException`(409)
- [x] `MockExamPublishNotAllowedException`(409)
- [x] `MockExamHasNoQuestionsException`(409)
- [x] `MockExamUnavailableException`(409)
- [x] `MockExamSessionAlreadyInProgressException`(409)
- [x] `MockExamSessionAlreadyStartedException`(409)
- [x] `MockExamSessionNotInProgressException`(409)
- [x] `MockExamSessionNotCancelableException`(409)
- [x] `MockExamQuestionNotInSessionException`(422)
- [x] `MockExamOptionMismatchException`(422)
- [x] `QuestionCategoryMismatchException`(422)
- [x] `QuestionInvalidOptionsException`(422)

### 明示的に持たない例外(E-3 撤回)

- **`MockExamSessionTimeExceededException`** — 時間制限なし

### ServiceProvider

- [x] `App\Providers\MockExamServiceProvider`(`WeaknessAnalysisServiceContract::class` → `WeaknessAnalysisService::class` を `bind`)
- [x] `config/app.php` の providers 配列に `MockExamServiceProvider::class` 登録(QuizAnsweringServiceProvider の後ろ)

### Handler 追加

- [x] `app/Exceptions/Handler.php` は既存の 409 / 422 redirect 変換ロジックでカバー済(本 Feature 例外はすべて `ConflictHttpException` / `UnprocessableEntityHttpException` 継承)

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

### Feature(HTTP) — 主要シナリオを `MockExam/CrudTest.php` 等に集約

- [x] `Http/MockExam/CrudTest.php`(認可 + 状態網羅 + `time_limit_minutes` silently drop 確認)
- [x] `Http/MockExam/PublishTest.php`(publish/unpublish の 3 段ガード網羅)
- [x] `Http/MockExamQuestion/CrudTest.php`(category 不一致 / is_correct 多重・ゼロ / 認可境界 / order 採番)
- [x] `Http/MockExamCatalog/IndexShowTest.php`(learning + passed 表示 / 他人 enrollment 403 / graduated 403 / 別資格 mock 404)
- [x] `Http/MockExamSession/LifecycleTest.php`(store / start / submit / destroy 全状態遷移 + TermJudgement / Notification::fake() で発火確認)
- [x] `Http/MockExamSession/AnswerTest.php`(UPSERT / 3 段ガード / 他者 403)
- [x] `Http/MockExamSession/AdminTest.php`(admin 全件 / coach 担当絞込 / student 拒否)

### Feature(UseCases)

- [x] `UseCases/MockExamSession/GradeActionTest.php`(混在正誤 / 不合格 / 未解答カウント)

### Unit(Services / Policies / Provider)

- [x] `Unit/Services/WeaknessAnalysisServiceTest.php`(`getWeakCategories` 閾値 / `getHeatmap` セル / `getPassProbabilityBand` 3 バンド + 直近 3 件絞込)
- [x] `Unit/Policies/MockExamPolicyTest.php`(admin / assigned coach / unassigned coach / student learning + passed / student unpublished 拒否 / admin take 拒否)
- [x] `Unit/Services/MockExamServiceProviderTest.php`(Contract resolve 確認)

### 補足

- MockExamQuestionPolicy / MockExamSessionPolicy の認可検証は Feature(HTTP) テストで網羅
- 並列 lockForUpdate テストは PHPUnit では正確に再現できないため省略(コード上の `SubmitAction::lockForUpdate()` で防御)

### 明示的に持たないテスト(E-3 撤回)

- **`Console/AutoSubmitExpiredMockExamSessionsCommandTest.php`** — Schedule Command なし

## Step 7: 動作確認 & 整形

- [x] `sail artisan migrate:fresh --seed` 通過(全 Seeder OK + MockExamSeeder 追加)
- [x] `sail artisan test --filter=MockExam` 56 件全 pass(113 assertions)
- [x] `sail artisan test` 全体 661 件 pass(リグレッションなし、QuestionCategory DestroyActionTest を新スキーマに合わせて修正済)
- [x] `sail bin pint --dirty` 整形完了
- [x] `MockExamSeeder` 投入で状態網羅 demo: 公開模試 6 / 下書き 2 / 質問 36 / 選択肢 144 / セッション 11 / 解答 56 / 固定 student セッション 7
- ブラウザ動作確認は Phase 5 (E2E 動作検証) で Claude が Playwright で実施
- **E-3 撤回確認**(コード grep):
  - [x] `mock-exam:auto-submit-expired` Artisan コマンドなし
  - [x] `MockExamSessionTimeExceededException` クラスなし
  - [x] `timer.js` / `auto-submit.js` ファイルなし

## v3.5 改修タスク — URL 再設計 + [[default-enrollment]] 統合 + Switcher 埋込

### URL 再設計 (学習体験の包含構造)

- [x] **student 系ルートの prefix を `/mock-exams` から `/learning/enrollments/{enrollment}/mock-exams` に再設計**(v3.5、[[default-enrollment]] 連携、`/learning` 配下に集約):
  - `GET /learning/enrollments/{enrollment}/mock-exams` (一覧、`MockExamCatalogController::index`)
  - `GET /learning/enrollments/{enrollment}/mock-exams/{mockExam}` (詳細、`MockExamCatalogController::show`)
  - `POST /learning/enrollments/{enrollment}/mock-exams/{mockExam}/sessions` (セッション作成)
  - 既存の `MockExamSession` 系ルート (`/mock-exam-sessions/{session}/*`) は維持(セッション ID で参照、enrollment コンテキストは不要)
- [x] **`routes/web.php` の `/mock-exams` ルートに `'resolve-default-enrollment:mock-exam.catalog.index'` Middleware 適用**(`mock-exam.fallback.index` route)
- [x] **`/mock-exams` 直接アクセス時の挙動**: `ResolveDefaultEnrollment` Middleware で default 資格の URL (`/learning/enrollments/{default}/mock-exams`) に自動 redirect、default NULL + 残存 0 件 / 2+ 件のフォールバックは `mock-exams/empty-state.blade.php` で資格選択 UI を表示

### Switcher 埋込

- [x] **`views/mock-exams/index.blade.php` の上部に `<x-enrollment-switcher variant="inline" :current="$enrollment" />` 埋込**
- [x] `MockExamCatalogController::index` / `show` で Route Model Binding で受けた `$enrollment` を Blade に渡す
- [x] サイドバーの「模試」リンクを `mock-exam.fallback.index` ルートに更新(middleware で default 資格へ自動 redirect)

### 関連要件マッピング追加

- REQ-default-enrollment-031 / 081 / 082: mock-exam URL の `resolve-default-enrollment` Middleware 適用 + empty-state UI
- REQ-default-enrollment-051: inline Switcher 埋込
