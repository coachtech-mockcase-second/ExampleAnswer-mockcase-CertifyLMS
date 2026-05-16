# enrollment 設計

> **v3 改修反映**: `assigned_coach_id` 削除、修了申請承認削除、`ReceiveCertificateAction` 追加、status 3 値、`coach_change` event_type 削除。

## アーキテクチャ

```
[Web] EnrollmentController / ReceiveCertificateController / EnrollmentNoteController / EnrollmentGoalController
  ↓
[FormRequest] StoreEnrollmentRequest / ReceiveCertificateRequest 等
  ↓
[Policy] EnrollmentPolicy / EnrollmentGoalPolicy / EnrollmentNotePolicy
  ↓
[UseCase] StoreAction / FailAction / ReceiveCertificateAction / Goal/Note 各 CRUD Action
  ↓
[Service] CompletionEligibilityService / TermJudgementService / EnrollmentStatsService / EnrollmentStatusChangeService
  ↓
[Model] Enrollment / EnrollmentGoal / EnrollmentNote / EnrollmentStatusLog
  ↓
[Schedule] enrollments:fail-expired (日次 00:00)
```

## ERD

```
enrollments
├ id ULID PK
├ user_id ULID FK
├ certification_id ULID FK
├ exam_date DATE NULL
├ status enum('learning','passed','failed')
├ current_term enum('basic_learning','mock_practice')
├ passed_at datetime NULL
├ created_at / updated_at / deleted_at
UNIQUE (user_id, certification_id), INDEX (status, exam_date), (certification_id, status)
※ assigned_coach_id / completion_requested_at は持たない

enrollment_goals
├ id / enrollment_id FK / title / description / target_date / achieved_at / timestamps / soft_deletes

enrollment_notes
├ id / enrollment_id FK / coach_user_id FK / body / timestamps / soft_deletes

enrollment_status_logs (INSERT only)
├ id / enrollment_id FK
├ event_type enum('status_change')  ← coach_change は削除
├ from_status / to_status (EnrollmentStatus, nullable)
├ changed_by_user_id ULID FK NULL
├ changed_reason / changed_at / created_at / updated_at
INDEX (enrollment_id, changed_at)
```

## 主要 Action

### ReceiveCertificateAction (新規、自己発火)

> **設計判断**(2026-05-16): 認可は Controller の `$this->authorize('receiveCertificate', $enrollment)` で `EnrollmentPolicy::receiveCertificate` 経由に集約(本人検証 + status == Learning は Policy 側で判定)。Action 内では `auth()->id()` を参照せず、**データ整合性チェック**(`CompletionEligibilityService` の合格判定)のみ実施。依存 Service / Action はすべて **constructor injection** で受ける(`app()` ヘルパは Service Locator アンチパターンのため不採用、`backend-usecases.md` 規約準拠)。

```php
namespace App\UseCases\Enrollment;

use App\Enums\EnrollmentStatus;
use App\Exceptions\Enrollment\CompletionNotEligibleException;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Services\CompletionEligibilityService;
use App\Services\EnrollmentStatusChangeService;
use App\UseCases\Certificate\IssueCertificateAction;
use App\UseCases\Notification\NotifyCompletionApprovedAction;
use Illuminate\Support\Facades\DB;

final class ReceiveCertificateAction
{
    public function __construct(
        private readonly CompletionEligibilityService $eligibility,
        private readonly EnrollmentStatusChangeService $statusChanger,
        private readonly IssueCertificateAction $issueCertificate,
        private readonly NotifyCompletionApprovedAction $notifyCompletion,
    ) {}

    /**
     * 受講生本人による修了証受領処理。
     * 認可(本人 + status == Learning)は Controller の $this->authorize('receiveCertificate', $enrollment) で完結済の前提。
     *
     * @throws CompletionNotEligibleException 公開模試すべてに合格していない
     */
    public function __invoke(Enrollment $enrollment): Certificate
    {
        if (! $this->eligibility->isEligible($enrollment)) {
            throw new CompletionNotEligibleException;
        }

        return DB::transaction(function () use ($enrollment) {
            $enrollment->update([
                'status' => EnrollmentStatus::Passed,
                'passed_at' => now(),
            ]);

            $this->statusChanger->recordStatusChange(
                $enrollment,
                EnrollmentStatus::Learning,
                EnrollmentStatus::Passed,
                $enrollment->user,
                '受講生による修了証受領',
            );

            $certificate = ($this->issueCertificate)($enrollment);

            DB::afterCommit(fn () => ($this->notifyCompletion)($enrollment, $certificate));

            return $certificate;
        });
    }
}
```

> **Controller 側の対応**(`EnrollmentController::receiveCertificate`):
> ```php
> public function receiveCertificate(Enrollment $enrollment, ReceiveCertificateAction $action): RedirectResponse
> {
>     $this->authorize('receiveCertificate', $enrollment);  // 本人 + status == Learning を判定
>     $certificate = $action($enrollment);
>     return redirect()->route('certificates.show', $certificate);
> }
> ```

### CompletionEligibilityService

```php
public function isEligible(Enrollment $enrollment): bool
{
    $publishedCount = MockExam::where('certification_id', $enrollment->certification_id)
        ->where('is_published', true)->whereNull('deleted_at')->count();
    if ($publishedCount === 0) return false;

    $passedCount = MockExamSession::where('enrollment_id', $enrollment->id)
        ->where('pass', true)->distinct('mock_exam_id')->count('mock_exam_id');

    return $passedCount === $publishedCount;
}
```

### FailExpiredCommand (Schedule)

`status = learning AND exam_date IS NOT NULL AND exam_date < CURRENT_DATE` → `failed` 自動遷移 + ログ記録。

## Policy

- `EnrollmentPolicy::view`: 受講生本人 / coach は `$enrollment->certification->coaches->contains($user->id)` / admin true
- `EnrollmentPolicy::receiveCertificate`: 受講生本人 + `status === Learning` + `EnsureActiveLearning` の組合せ
- `EnrollmentGoalPolicy`: CRUD は受講生本人のみ、coach / admin は view のみ
- `EnrollmentNotePolicy`: coach（担当資格内のみ）+ admin、受講生は閲覧不可

## エラーハンドリング

`app/Exceptions/Enrollment/` 配下:
- `EnrollmentAlreadyEnrolledException` (409)
- `EnrollmentInvalidTransitionException` (409)
- `EnrollmentNotLearningException` (409)
- `EnrollmentAlreadyPassedException` (409)
- `CompletionNotEligibleException` (409)

## テスト戦略

- `tests/Feature/Http/EnrollmentControllerTest.php`: 一覧 / 詳細 / 自己登録 / admin 手動割当 / 認可
- `tests/Feature/UseCases/Enrollment/ReceiveCertificateActionTest.php`: eligible 検証 + status 遷移 + Certificate 発行 + 通知発火
- `tests/Feature/Commands/FailExpiredCommandTest.php`
- `tests/Unit/Services/CompletionEligibilityServiceTest.php` / `TermJudgementServiceTest.php`
