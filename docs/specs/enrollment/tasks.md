# enrollment タスクリスト

> 1 タスク = 1 コミット粒度。Step 単位で順次実装し、完了したものから `[x]` に更新する。
> 関連要件 ID は `requirements.md` の `REQ-enrollment-NNN` / `NFR-enrollment-NNN` を参照。
> コマンドはすべて `sail` プレフィックス（`tech.md` の「コマンド慣習」参照）。

## Step 1: Migration & Model

- [ ] migration: `create_enrollments_table`（ULID PK + SoftDeletes + `(user_id, certification_id)` UNIQUE + INDEX 群）（REQ-enrollment-001, REQ-enrollment-002, NFR-enrollment-003）
- [ ] migration: `create_enrollment_goals_table`（ULID PK + SoftDeletes + `(enrollment_id, achieved_at)` INDEX）（REQ-enrollment-070）
- [ ] migration: `create_enrollment_notes_table`（ULID PK + SoftDeletes + `(enrollment_id, created_at)` INDEX）（REQ-enrollment-080）
- [ ] migration: `create_enrollment_status_logs_table`（ULID PK、SoftDeletes 非採用 + `(enrollment_id, changed_at)` INDEX）（REQ-enrollment-110）
- [ ] Enum: `App\Enums\EnrollmentStatus`（`Learning` / `Paused` / `Passed` / `Failed`、`label()` 含む）（REQ-enrollment-003）
- [ ] Enum: `App\Enums\TermType`（`BasicLearning` / `MockPractice`、`label()` 含む）（REQ-enrollment-004）
- [ ] Enum: `App\Enums\EnrollmentLogEventType`（`StatusChange` / `CoachChange`、`label()` 含む）（REQ-enrollment-110）
- [ ] Model: `App\Models\Enrollment`（fillable / casts / scopes / リレーション）（REQ-enrollment-002）
- [ ] Model: `App\Models\EnrollmentGoal`（fillable / casts / scopes / `belongsTo(Enrollment)`）（REQ-enrollment-070）
- [ ] Model: `App\Models\EnrollmentNote`（fillable / casts / `belongsTo(Enrollment)` / `belongsTo(User as coach)`）（REQ-enrollment-080）
- [ ] Model: `App\Models\EnrollmentStatusLog`（INSERT only 想定、`belongsTo` × 4、`changedBy` / `fromCoach` / `toCoach` に `withTrashed()` 適用）（REQ-enrollment-110, REQ-enrollment-111, REQ-enrollment-112）
- [ ] Factory: `EnrollmentFactory`（`learning()` / `paused()` / `passed()` / `failed()` / `pending()` state 提供）
- [ ] Factory: `EnrollmentGoalFactory`（`active()` / `achieved()` state）
- [ ] Factory: `EnrollmentNoteFactory`
- [ ] Factory: `EnrollmentStatusLogFactory`（`statusChange()` / `coachChange()` state）

## Step 2: Policy

- [ ] `App\Policies\EnrollmentPolicy`（viewAny / view / create / update / delete / pause / resume / requestCompletion / cancelCompletionRequest / approveCompletion / fail / assignCoach）（NFR-enrollment-007）
- [ ] `App\Policies\EnrollmentGoalPolicy`（viewAny / view / create / update / delete / achieve）（REQ-enrollment-077, NFR-enrollment-007）
- [ ] `App\Policies\EnrollmentNotePolicy`（viewAny / create / update / delete）（REQ-enrollment-083, REQ-enrollment-084, REQ-enrollment-085, REQ-enrollment-086, NFR-enrollment-006, NFR-enrollment-007）
- [ ] `AuthServiceProvider` の `$policies` 登録 or 自動検出確認

## Step 3: HTTP 層

- [ ] `App\Http\Controllers\EnrollmentController`（受講生用、`index` / `show` / `store` / `pause` / `resume` / `requestCompletion` / `cancelCompletionRequest`）（REQ-enrollment-010, 030, 031, 040, 041, 090, 095）
- [ ] `App\Http\Controllers\Admin\EnrollmentController`（admin 用、`index` / `pending` / `show` / `store` / `update` / `destroy` / `pause` / `resume` / `fail` / `assignCoach` / `approveCompletion`）（REQ-enrollment-020, 032, 043, 050, 097）
- [ ] `App\Http\Controllers\EnrollmentGoalController`（student 用、`store` / `update` / `destroy` / `achieve` / `unachieve`）（REQ-enrollment-071, 072, 073, 074, 075）
- [ ] `App\Http\Controllers\EnrollmentNoteController`（coach + admin 用、`index` / `store` / `update` / `destroy`）（REQ-enrollment-081, 087）
- [ ] `App\Http\Requests\Enrollment\StoreRequest`（rules + authorize）（REQ-enrollment-010, REQ-enrollment-013）
- [ ] `App\Http\Requests\Enrollment\PauseRequest` / `ResumeRequest`（reason nullable）（REQ-enrollment-040, 041）
- [ ] `App\Http\Requests\Admin\Enrollment\IndexRequest`（filter + with_trashed）（REQ-enrollment-032）
- [ ] `App\Http\Requests\Admin\Enrollment\StoreRequest`（user_id / certification_id / assigned_coach_id 等）（REQ-enrollment-020, 021）
- [ ] `App\Http\Requests\Admin\Enrollment\UpdateRequest`（exam_date のみ）（REQ-enrollment-024）
- [ ] `App\Http\Requests\Admin\Enrollment\PauseRequest` / `ResumeRequest` / `FailRequest`（reason 各々）（REQ-enrollment-043）
- [ ] `App\Http\Requests\Admin\Enrollment\AssignCoachRequest`（coach_user_id nullable）（REQ-enrollment-050, 053）
- [ ] `App\Http\Requests\EnrollmentGoal\StoreRequest` / `UpdateRequest`（title / description / target_date）（REQ-enrollment-071, 072）
- [ ] `App\Http\Requests\EnrollmentNote\StoreRequest` / `UpdateRequest`（body）（REQ-enrollment-081）
- [ ] `routes/web.php` への enrollment 系ルート定義（student / coach / admin の 3 グループ）（REQ-enrollment-030, 032, 087, 097）

## Step 4: Action / Service / Exception

### 受講生用 Action（`App\UseCases\Enrollment\`）
- [ ] `IndexAction`（受講中資格一覧、Eager Loading）（REQ-enrollment-030, NFR-enrollment-002）
- [ ] `ShowAction`（詳細、StatusLog timeline 含む eager load）（REQ-enrollment-031, NFR-enrollment-002）
- [ ] `StoreAction`（自己登録、`certification_coach_assignments` 先頭自動割当 + 重複検査 + StatusLog 記録）（REQ-enrollment-005, 010, 011, 012, 014, 015）
- [ ] `PauseAction`（学習中ガード + status 更新 + StatusLog 記録）（REQ-enrollment-040, 042, 044）
- [ ] `ResumeAction`（paused / failed ガード + status 更新 + StatusLog 記録）（REQ-enrollment-041, 042, 044）
- [ ] `RequestCompletionAction`（学習中ガード + 申請中重複ガード + Eligibility 検証 + `completion_requested_at` 更新）（REQ-enrollment-090, 092, 093, 094）
- [ ] `CancelCompletionRequestAction`（申請中ガード + `completion_requested_at = null`）（REQ-enrollment-095, 096）

### admin 用 Action（`App\UseCases\Admin\Enrollment\`）
- [ ] `IndexAction`（フィルタ + ページネーション + 並び順）（REQ-enrollment-032）
- [ ] `PendingAction`（修了申請待ち一覧、`pending()` scope）（REQ-enrollment-097）
- [ ] `ShowAction`（admin 詳細、`withTrashed` で SoftDelete 対応）（REQ-enrollment-034）
- [ ] `StoreAction`（手動割当、role / status / coach 担当検査 + StatusLog 記録）（REQ-enrollment-020, 021, 022, 023）
- [ ] `UpdateAction`（exam_date 更新、passed ガード）（REQ-enrollment-024, 044）
- [ ] `DestroyAction`（SoftDelete）
- [ ] `PauseAction` / `ResumeAction`（受講生用 Action のラッパー、admin actor で呼出）（REQ-enrollment-043）
- [ ] `FailAction`（learning / paused → failed、StatusLog 記録）（REQ-enrollment-043, 044, 045）
- [ ] `AssignCoachAction`（`certification_coach_assignments` 検証 + coach role/status 検証 + CoachChange log）（REQ-enrollment-050, 051, 052, 053）
- [ ] `ApproveCompletionAction`（申請中ガード + Eligibility 再判定 + passed 遷移 + Certificate IssueAction DI 呼出 + StatusLog 記録 + `DB::afterCommit` で通知 dispatch）（REQ-enrollment-097, 098, 099）
- [ ] `FailExpiredAction`（Schedule Command 本体、`status=learning AND exam_date < CURRENT_DATE` 抽出 + 一括 failed 遷移）（REQ-enrollment-100, 101）

### EnrollmentGoal Action（`App\UseCases\EnrollmentGoal\`）
- [ ] `StoreAction`（INSERT、`achieved_at = null`）（REQ-enrollment-071）
- [ ] `UpdateAction`（title / description / target_date 更新）（REQ-enrollment-072）
- [ ] `DestroyAction`（SoftDelete）（REQ-enrollment-073）
- [ ] `AchieveAction`（`achieved_at = now()`）（REQ-enrollment-074）
- [ ] `UnachieveAction`（`achieved_at = null`）（REQ-enrollment-075）

### EnrollmentNote Action（`App\UseCases\EnrollmentNote\`）
- [ ] `IndexAction`（時系列降順）（REQ-enrollment-087）
- [ ] `StoreAction`（`coach_user_id = actor.id`）（REQ-enrollment-081, 082）
- [ ] `UpdateAction`（body 更新）（REQ-enrollment-083, 084）
- [ ] `DestroyAction`（SoftDelete）（REQ-enrollment-083, 084）

### Service（`App\Services\`）
- [ ] `EnrollmentStatusChangeService`（`recordStatusChange` / `recordCoachChange`、INSERT only）（REQ-enrollment-113, NFR-enrollment-005）
- [ ] `TermJudgementService`（`recalculate(Enrollment): TermType`、変化時のみ UPDATE）（REQ-enrollment-060, 061, 062, 063, 064, REQ-enrollment-121）
- [ ] `CompletionEligibilityService`（`isEligible(Enrollment): bool`、`mock_exams.is_published=true` と DISTINCT `mock_exam_id` 比較）（REQ-enrollment-091, REQ-enrollment-120）
- [ ] `EnrollmentStatsService`（`adminKpi` / `studentDashboard`）（REQ-enrollment-122）

### ドメイン例外（`app/Exceptions/Enrollment/`）
- [ ] `EnrollmentAlreadyEnrolledException`（HTTP 409）（NFR-enrollment-004）
- [ ] `EnrollmentInvalidTransitionException`（HTTP 409）（NFR-enrollment-004）
- [ ] `EnrollmentNotLearningException`（HTTP 409）（NFR-enrollment-004）
- [ ] `EnrollmentAlreadyPassedException`（HTTP 409）（NFR-enrollment-004）
- [ ] `CompletionAlreadyRequestedException`（HTTP 409）（NFR-enrollment-004）
- [ ] `CompletionNotRequestedException`（HTTP 409）（NFR-enrollment-004）
- [ ] `CompletionNotEligibleException`（HTTP 409）（NFR-enrollment-004）
- [ ] `CoachNotAssignedToCertificationException`（HTTP 409）（NFR-enrollment-004）

### Schedule Command
- [ ] `App\Console\Commands\Enrollment\FailExpiredEnrollmentsCommand`（signature: `enrollments:fail-expired`、`FailExpiredAction` を呼ぶ薄いラッパー）（REQ-enrollment-100）
- [ ] `App\Console\Kernel::schedule()` に `->command('enrollments:fail-expired')->dailyAt('00:00')` を追加

## Step 5: Blade ビュー

### 受講生用
- [ ] `resources/views/enrollments/index.blade.php`（受講中資格カードグリッド + 現在ターム / 進捗 / カウントダウン）
- [ ] `resources/views/enrollments/show.blade.php`（詳細 + 状態カード + 目標タイムライン + 状態ログタイムライン）
- [ ] `resources/views/enrollments/_partials/status-card.blade.php`
- [ ] `resources/views/enrollments/_partials/goal-timeline.blade.php`
- [ ] `resources/views/enrollments/_partials/status-log-timeline.blade.php`
- [ ] `resources/views/enrollments/_modals/pause-confirm.blade.php`
- [ ] `resources/views/enrollments/_modals/resume-confirm.blade.php`
- [ ] `resources/views/enrollments/_modals/request-completion-confirm.blade.php`
- [ ] `resources/views/enrollments/_modals/cancel-completion-confirm.blade.php`
- [ ] `resources/views/enrollments/_modals/add-goal-form.blade.php`
- [ ] `resources/views/enrollments/_modals/edit-goal-form.blade.php`

### admin 用
- [ ] `resources/views/admin/enrollments/index.blade.php`（全件一覧 + フィルタ + 「+割当」ボタン）
- [ ] `resources/views/admin/enrollments/pending.blade.php`（修了申請待ち一覧）
- [ ] `resources/views/admin/enrollments/show.blade.php`（詳細 + 状態操作 + コーチ変更 + StatusLog timeline）
- [ ] `resources/views/admin/enrollments/_partials/assign-coach-section.blade.php`
- [ ] `resources/views/admin/enrollments/_partials/status-actions.blade.php`
- [ ] `resources/views/admin/enrollments/_modals/assign-form.blade.php`
- [ ] `resources/views/admin/enrollments/_modals/edit-form.blade.php`
- [ ] `resources/views/admin/enrollments/_modals/delete-confirm.blade.php`
- [ ] `resources/views/admin/enrollments/_modals/fail-confirm.blade.php`
- [ ] `resources/views/admin/enrollments/_modals/approve-completion-confirm.blade.php`
- [ ] `resources/views/admin/enrollments/_modals/change-coach-form.blade.php`

### coach / admin 共用（Note）
- [ ] `resources/views/enrollments/notes/index.blade.php`
- [ ] `resources/views/enrollments/notes/_partials/note-card.blade.php`

## Step 6: テスト

### Feature テスト（Controller 単位）
- [ ] `tests/Feature/Http/Enrollment/IndexTest.php`（受講生の一覧取得 / 他者一覧不可）
- [ ] `tests/Feature/Http/Enrollment/ShowTest.php`（自分の詳細 / 他者 403）
- [ ] `tests/Feature/Http/Enrollment/StoreTest.php`（正常系 + 重複 409 + 非公開資格 404 + 過去日付 422）
- [ ] `tests/Feature/Http/Enrollment/PauseTest.php`（正常系 + learning 以外 409 + 他者 403）
- [ ] `tests/Feature/Http/Enrollment/ResumeTest.php`（paused / failed 両方 + 正常系 + 不正状態 409）
- [ ] `tests/Feature/Http/Enrollment/RequestCompletionTest.php`（合格達成時 OK + 未達成 409 + 学習中以外 409 + 重複申請 409）
- [ ] `tests/Feature/Http/Enrollment/CancelCompletionRequestTest.php`（申請中 OK + 申請なし 409 + passed 409）
- [ ] `tests/Feature/Http/Admin/Enrollment/IndexTest.php`（admin 一覧 + フィルタ + withTrashed）
- [ ] `tests/Feature/Http/Admin/Enrollment/PendingTest.php`（修了申請待ち一覧）
- [ ] `tests/Feature/Http/Admin/Enrollment/ShowTest.php`（SoftDelete 含む詳細）
- [ ] `tests/Feature/Http/Admin/Enrollment/StoreTest.php`（強制割当 + コーチ未割当 409 + 重複 409）
- [ ] `tests/Feature/Http/Admin/Enrollment/UpdateTest.php`（exam_date 更新 + passed 409）
- [ ] `tests/Feature/Http/Admin/Enrollment/DestroyTest.php`（SoftDelete 確認）
- [ ] `tests/Feature/Http/Admin/Enrollment/PauseResumeFailTest.php`（admin 強制遷移 + 各遷移ガード）
- [ ] `tests/Feature/Http/Admin/Enrollment/AssignCoachTest.php`（変更 + 未担当 409 + null 解除 + CoachChange log INSERT）
- [ ] `tests/Feature/Http/Admin/Enrollment/ApproveCompletionTest.php`（承認 → passed + Certificate INSERT + 通知 dispatch + StatusLog INSERT）
- [ ] `tests/Feature/Http/EnrollmentGoal/StoreUpdateDestroyTest.php`（CRUD + 他者 403）
- [ ] `tests/Feature/Http/EnrollmentGoal/AchieveUnachieveTest.php`（達成マーク + 取消）
- [ ] `tests/Feature/Http/EnrollmentNote/IndexTest.php`（coach: 担当のみ閲覧 / admin: 全件 / student: 403）
- [ ] `tests/Feature/Http/EnrollmentNote/StoreUpdateDestroyTest.php`（coach 自作 / 他コーチ 403 / admin 越境 OK）

### UseCase テスト（複雑な Action のみ）
- [ ] `tests/Feature/UseCases/Enrollment/StoreActionTest.php`（自動コーチ割当 + StatusLog 記録）
- [ ] `tests/Feature/UseCases/Enrollment/RequestCompletionActionTest.php`（Eligibility 判定の境界値）
- [ ] `tests/Feature/UseCases/Admin/Enrollment/ApproveCompletionActionTest.php`（passed 遷移 + Certificate 発行 + 通知 + StatusLog の原子性 + 例外時のロールバック）
- [ ] `tests/Feature/UseCases/Admin/Enrollment/FailExpiredActionTest.php`（試験日超過 → failed + paused は対象外 + 件数返却）
- [ ] `tests/Feature/UseCases/Admin/Enrollment/AssignCoachActionTest.php`（担当外コーチ → 409 + null 解除）

### Unit テスト
- [ ] `tests/Unit/Services/EnrollmentStatusChangeServiceTest.php`（statusChange / coachChange の INSERT 形式 / actor null 許容）
- [ ] `tests/Unit/Services/TermJudgementServiceTest.php`（active セッション有無による current_term 切替 + 変化なし時の UPDATE スキップ）
- [ ] `tests/Unit/Services/CompletionEligibilityServiceTest.php`（公開模試 0 件 / 一部合格 / 全合格 / pass=false 混在の境界）
- [ ] `tests/Unit/Services/EnrollmentStatsServiceTest.php`（admin KPI 集計 + 資格別件数）
- [ ] `tests/Unit/Policies/EnrollmentPolicyTest.php`（ロール × 操作 真偽値網羅）
- [ ] `tests/Unit/Policies/EnrollmentGoalPolicyTest.php`（coach / admin が CRUD 不可、student のみ可）
- [ ] `tests/Unit/Policies/EnrollmentNotePolicyTest.php`（student 完全拒否 / coach 自己作成のみ更新 / admin 越境）

## Step 7: 動作確認 & 整形

- [ ] `sail artisan test --filter=Enrollment` 通過（全 Feature / UseCase / Unit テスト）
- [ ] `sail bin pint --dirty` 整形
- [ ] 受講生フロー動作確認:
  1. 公開済資格カタログから受講登録 → `/enrollments` に表示される
  2. 詳細ページで個人目標を追加 → 達成マーク → 取消
  3. 休止 → 受講中一覧に「休止中」バッジ → 再開
  4. （前提として mock-exam 公開模試すべて合格達成後）修了申請ボタン押下 → 申請中状態 → 取消
- [ ] admin フロー動作確認:
  1. `/admin/enrollments` で全件 + フィルタ
  2. 「+割当」モーダルで受講生 × 資格 強制割当
  3. 詳細から exam_date 更新 / 担当コーチ変更 / 強制 paused / 強制 failed
  4. `/admin/enrollments/pending` で修了申請待ち閲覧 → 承認 → 受講生に修了通知 + Certificate ダウンロード可能になる
- [ ] coach フロー動作確認:
  1. 担当受講生詳細から Note 一覧 → 追加 → 編集 → 削除
  2. 他コーチが書いた Note は閲覧可、編集 / 削除ボタンが非表示
  3. 担当受講生の個人目標が閲覧専用で表示される
- [ ] Schedule Command 動作確認: `sail artisan enrollments:fail-expired` を手動実行し、試験日超過 learning Enrollment が failed に遷移 + StatusLog が INSERT されることを確認（`paused` は対象外であることも確認）
- [ ] [[mock-exam]] 連携確認: mock-exam セッション開始時に `current_term` が `basic_learning → mock_practice` に切り替わり、すべてキャンセル時に `basic_learning` に戻ることを確認
