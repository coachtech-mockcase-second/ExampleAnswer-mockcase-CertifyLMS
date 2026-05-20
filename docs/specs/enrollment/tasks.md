# enrollment 実装タスク

## Migration
- [x] `create_enrollments_table` (status 3 値、assigned_coach_id / completion_requested_at なし)
- [x] `create_enrollment_goals_table`
- [x] `create_enrollment_notes_table`
- [x] `create_enrollment_status_logs_table` (from_status / to_status で遷移を表現、event_type カラムは持たない、2026-05-17 設計修正)
- [x] INDEX: `(user_id, certification_id)` UNIQUE / `(certification_id, status)` / `(status, exam_date)` / `(enrollment_id, changed_at)` on logs

## Enum / Model
- [x] `App\Enums\EnrollmentStatus` (Learning / Passed / Failed、`failed` の label「学習中止」)
- [x] `App\Enums\TermType` (BasicLearning / MockPractice)
- [x] `App\Models\Enrollment` (リレーション: user / certification / goals / notes / statusLogs / mockExamSessions)
- [x] `App\Models\EnrollmentGoal`
- [x] `App\Models\EnrollmentNote`
- [x] `App\Models\EnrollmentStatusLog`

## Policy
- [x] `EnrollmentPolicy` (view / receiveCertificate)
- [x] `EnrollmentGoalPolicy`
- [x] `EnrollmentNotePolicy`

## Exception
- [x] `Exceptions/Enrollment/EnrollmentAlreadyEnrolledException` / `EnrollmentInvalidTransitionException` / `EnrollmentNotLearningException` / `EnrollmentAlreadyPassedException` / `CompletionNotEligibleException`

## FormRequest
- [x] `StoreEnrollmentRequest` (student 自己登録)
- [x] `UpdateEnrollmentExamDateRequest`
- [x] `StoreGoalRequest` / `UpdateGoalRequest`
- [x] `StoreNoteRequest` / `UpdateNoteRequest`

## Service
- [x] `CompletionEligibilityService::isEligible(Enrollment): bool`
- [x] `TermJudgementService::recalculate(Enrollment): TermType`
- [x] `EnrollmentStatsService::adminKpi(): array`
- [x] `EnrollmentStatusChangeService::recordStatusChange(...)` (INSERT only)

## UseCase / Action
- [x] `Enrollment\IndexAction` / `ShowAction` / `StoreAction` / `UpdateExamDateAction`
- [x] `Enrollment\FailAction` (admin manual)
- [x] `Enrollment\ResumeAction` (failed → learning 再挑戦)
- [x] **`Enrollment\ReceiveCertificateAction`** (新規、自己発火、Certificate 発行のみ。通知は送らない)
- [x] `EnrollmentGoal\StoreAction` / `UpdateAction` / `DestroyAction` / `MarkAchievedAction` / `UnmarkAchievedAction`
- [x] `EnrollmentNote\StoreAction` / `UpdateAction` / `DestroyAction`
- [ ] **`Enrollment\StoreAction` / `FailAction` / `ResumeAction` / `ReceiveCertificateAction` への [[default-enrollment]] 連携追加** — `DefaultEnrollmentService $resolver` を constructor injection、StoreAction 内で `$resolver->resolveAfterCreate($user, $newEnrollment)` 呼出、状態遷移 Action 内で `$resolver->resolveAfterStatusChange($user, $changedEnrollment)` 呼出(REQ-default-enrollment-018, REQ-default-enrollment-019)
- [ ] **`Enrollment\StoreAction` への [[chat]] E-3 連携追加** — `ChatMemberSyncService $chatMemberSync` を constructor injection、同一 `DB::transaction()` 内で `$enrollment` 作成直後に `ChatRoom::create(['enrollment_id' => $enrollment->id])` + `$chatMemberSync->syncForRoom($room)` を呼ぶ(REQ-enrollment-016, REQ-chat-003、設計: enrollment/design.md「[[chat]] 連携」セクション)
- [ ] **`tests/Feature/Http/EnrollmentControllerTest.php` に E-3 検証ケース追加** — `POST /enrollments` 成功で `chat_rooms` に `enrollment_id` 一致行 / `chat_members` に受講生 + 担当コーチ集合（コーチ 0 件なら受講生のみ）が INSERT されることを `assertDatabaseHas` で確認

## Schedule Command
- [x] `App\Console\Commands\FailExpiredEnrollmentsCommand` (`enrollments:fail-expired`、daily 00:00)
- [ ] **`FailExpiredEnrollmentsCommand` への [[default-enrollment]] 連携追加** — failed 遷移直後に `DefaultEnrollmentService::resolveAfterStatusChange` 呼出(REQ-default-enrollment-019)

## Controller / Route
- [x] `EnrollmentController` (index / show / store / destroy)
- [x] `EnrollmentManagementController` (admin index / show / updateExamDate / fail。手動割当 store は撤回)
- [x] `ReceiveCertificateController` (store)
- [x] `EnrollmentGoalController` / `EnrollmentNoteController`
- [x] routes/web.php に登録 (auth + role:student / role:admin / EnsureActiveLearning)

## Blade
- [x] `views/enrollments/index.blade.php` / `show.blade.php`
- [ ] **`views/enrollments/index.blade.php` への default UI 追加** — 各 Enrollment カードに `<x-enrollment-switcher.card :enrollment="$e" :is-default="..." />` を埋込み、「★デフォルト」バッジ + 「これをデフォルトにする」フォーム POST を表示(REQ-default-enrollment-051、Component 提供は [[default-enrollment]] が所有)
- [x] `views/admin/enrollments/index.blade.php` / `show.blade.php`
- [x] `views/enrollments/_receive_certificate_button.blade.php` (条件付き活性)
- [x] `views/enrollments/goals/*` / `views/enrollments/notes/*`

## Test
- [x] `tests/Feature/Http/EnrollmentControllerTest.php`
- [x] `tests/Feature/Http/Admin/EnrollmentControllerTest.php`
- [x] `tests/Feature/UseCases/Enrollment/ReceiveCertificateActionTest.php` (eligible 成功 / 不足で 409 / passed で 409 / graduated で 403)
- [x] `tests/Feature/Commands/FailExpiredEnrollmentsCommandTest.php`
- [x] `tests/Unit/Services/CompletionEligibilityServiceTest.php` (公開模試 0 件で false / 全合格で true)
- [x] `tests/Unit/Services/TermJudgementServiceTest.php`

## Factory / Seeder
- [x] `EnrollmentFactory`(`learning()` / `passed()` / `failed()` state)/ `EnrollmentGoalFactory` / `EnrollmentNoteFactory` / `EnrollmentStatusLogFactory`
- [x] **`database/seeders/EnrollmentSeeder.php`**(`student@certify-lms.test` を `CertificationSeeder` 投入の published 資格 1-2 件に `learning` で登録、`learning_started_at=now()-30days` 等の現実的な値)。`structure.md` Seeder 規約「派生・運用系」分類
- [x] `DatabaseSeeder::run()` に `EnrollmentSeeder::class` を `CertificationSeeder` の後に登録

## Coach 担当受講生管理 (REQ-enrollment-033)

- [x] **`Enrollment::scopeForUser(User)` をロール別 dispatcher に拡張** — admin = 全件 / coach = 担当資格 (`certification.coaches` whereHas) / student = 自分の (user_id = self.id) / その他 = 空集合。Laravel 業界標準パターン (Policy で viewAny 共通許可 + Eloquent scope で表示行絞込) に整合
- [x] **`EnrollmentRosterController::index/show`** 新規実装 (`app/Http/Controllers/EnrollmentRosterController.php`)。index は担当 Enrollment 一覧 (受講生名 / 資格 / status / 試験日 / 詳細リンク、フィルタは資格 / status / キーワード)。show は単一 Enrollment 詳細 (受講生情報 / 学習進捗 [ProgressService::summarize] / 個人目標 / コーチメモ一覧 + メモ追加フォーム)
- [x] **`routes/web.php`** に `coach.students.index` / `coach.students.show` を追加 (`Route::middleware(['auth', 'role:coach'])->prefix('coach')->name('coach.')` group 内)。サイドバー Blade は既存定義 (`sidebar-coach.blade.php` の `coach.students.index` 参照) で自動表示される
- [x] **`views/coach/students/index.blade.php` / `show.blade.php`** 新規作成。コーチメモ追加 POST は既存 `admin.enrollments.notes.store` ルート (admin/coach 共有グループ内) を流用
- [x] **`tests/Feature/Http/CoachStudent/IndexTest.php` / `ShowTest.php`** — 担当資格スコープ / admin・student 拒否 / 未担当 Enrollment への show 403 を検証
