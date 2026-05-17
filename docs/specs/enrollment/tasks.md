# enrollment 実装タスク

## Migration
- [ ] `create_enrollments_table` (status 3 値、assigned_coach_id / completion_requested_at なし)
- [ ] `create_enrollment_goals_table`
- [ ] `create_enrollment_notes_table`
- [ ] `create_enrollment_status_logs_table` (event_type='status_change' のみ)
- [ ] INDEX: `(user_id, certification_id)` UNIQUE / `(certification_id, status)` / `(status, exam_date)` / `(enrollment_id, changed_at)` on logs

## Enum / Model
- [ ] `App\Enums\EnrollmentStatus` (Learning / Passed / Failed、`failed` の label「学習中止」)
- [ ] `App\Enums\TermType` (BasicLearning / MockPractice)
- [ ] `App\Models\Enrollment` (リレーション: user / certification / goals / notes / statusLogs / mockExamSessions)
- [ ] `App\Models\EnrollmentGoal`
- [ ] `App\Models\EnrollmentNote`
- [ ] `App\Models\EnrollmentStatusLog`

## Policy
- [ ] `EnrollmentPolicy` (view / receiveCertificate)
- [ ] `EnrollmentGoalPolicy`
- [ ] `EnrollmentNotePolicy`

## Exception
- [ ] `Exceptions/Enrollment/EnrollmentAlreadyEnrolledException` / `EnrollmentInvalidTransitionException` / `EnrollmentNotLearningException` / `EnrollmentAlreadyPassedException` / `CompletionNotEligibleException`

## FormRequest
- [ ] `StoreEnrollmentRequest` (student 自己登録)
- [ ] `AdminStoreEnrollmentRequest` (手動割当)
- [ ] `UpdateEnrollmentExamDateRequest`
- [ ] `StoreGoalRequest` / `UpdateGoalRequest`
- [ ] `StoreNoteRequest` / `UpdateNoteRequest`

## Service
- [ ] `CompletionEligibilityService::isEligible(Enrollment): bool`
- [ ] `TermJudgementService::recalculate(Enrollment): TermType`
- [ ] `EnrollmentStatsService::adminKpi(): array`
- [ ] `EnrollmentStatusChangeService::recordStatusChange(...)` (INSERT only)

## UseCase / Action
- [ ] `Enrollment\IndexAction` / `ShowAction` / `StoreAction` / `Admin\StoreAction` / `UpdateExamDateAction`
- [ ] `Enrollment\FailAction` (admin manual)
- [ ] `Enrollment\ResumeAction` (failed → learning 再挑戦)
- [ ] **`Enrollment\ReceiveCertificateAction`** (新規、自己発火、Certificate 発行と通知連携)
- [ ] `EnrollmentGoal\StoreAction` / `UpdateAction` / `DestroyAction` / `MarkAchievedAction` / `UnmarkAchievedAction`
- [ ] `EnrollmentNote\StoreAction` / `UpdateAction` / `DestroyAction`

## Schedule Command
- [ ] `App\Console\Commands\FailExpiredEnrollmentsCommand` (`enrollments:fail-expired`、daily 00:00)

## Controller / Route
- [ ] `EnrollmentController` (index / show / store / destroy)
- [ ] `Admin\EnrollmentController` (admin index / store / updateExamDate)
- [ ] `ReceiveCertificateController` (store)
- [ ] `EnrollmentGoalController` / `EnrollmentNoteController`
- [ ] routes/web.php に登録 (auth + role:student / role:admin / EnsureActiveLearning)

## Blade
- [ ] `views/enrollments/index.blade.php` / `show.blade.php`
- [ ] `views/admin/enrollments/index.blade.php` / `show.blade.php`
- [ ] `views/enrollments/_receive_certificate_button.blade.php` (条件付き活性)
- [ ] `views/enrollments/goals/*` / `views/enrollments/notes/*`

## Test
- [ ] `tests/Feature/Http/EnrollmentControllerTest.php`
- [ ] `tests/Feature/Http/Admin/EnrollmentControllerTest.php`
- [ ] `tests/Feature/UseCases/Enrollment/ReceiveCertificateActionTest.php` (eligible 成功 / 不足で 409 / passed で 409 / graduated で 403)
- [ ] `tests/Feature/Commands/FailExpiredEnrollmentsCommandTest.php`
- [ ] `tests/Unit/Services/CompletionEligibilityServiceTest.php` (公開模試 0 件で false / 全合格で true)
- [ ] `tests/Unit/Services/TermJudgementServiceTest.php`

## Factory / Seeder
- [ ] `EnrollmentFactory`(`learning()` / `passed()` / `failed()` state)/ `EnrollmentGoalFactory` / `EnrollmentNoteFactory` / `EnrollmentStatusLogFactory`
- [ ] **`database/seeders/EnrollmentSeeder.php`**(`student@certify-lms.test` を `CertificationSeeder` 投入の published 資格 1-2 件に `learning` で登録、`learning_started_at=now()-30days` 等の現実的な値)。`structure.md` Seeder 規約「派生・運用系」分類
- [ ] `DatabaseSeeder::run()` に `EnrollmentSeeder::class` を `CertificationSeeder` の後に登録
