# enrollment 設計

> **v3 改修反映**: `assigned_coach_id` 削除、修了申請承認削除、`ReceiveCertificateAction` 追加、status 3 値、`coach_change` event_type 削除。
> **2026-05-17 設計修正**: `EnrollmentStatusLog.event_type` カラムごと撤回（`from_status` / `to_status` で遷移を表現できるため冗長）。
> **2026-05-18 [[chat]] E-3 連携**: `Enrollment\StoreAction` が `DB::transaction()` 内で `ChatRoom::create` + `ChatMemberSyncService::syncForRoom` を呼び、受講登録と同一トランザクションで `ChatRoom` + 全 `ChatMember` を eager 生成する。依存方向は enrollment → chat（constructor injection で `ChatMemberSyncService` を受ける）。REQ-enrollment-016 / REQ-chat-003 対応。

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
  ↓ + [[default-enrollment]] DefaultEnrollmentService 呼出(StoreAction / FailAction / ResumeAction / ReceiveCertificateAction / FailExpiredCommand から、resolveAfterCreate / resolveAfterStatusChange、constructor injection)
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
├ from_status (EnrollmentStatus, nullable)  ← 初回登録時のみ NULL
├ to_status (EnrollmentStatus)
├ changed_by_user_id ULID FK NULL
├ changed_reason / changed_at / created_at / updated_at
INDEX (enrollment_id, changed_at)
※ event_type カラムは持たない: from/to で遷移を表現するため冗長 (2026-05-17 設計修正)
```

## 主要 Action

### ReceiveCertificateAction (新規、自己発火)

> **設計判断**(2026-05-16): 認可は Controller の `$this->authorize('receiveCertificate', $enrollment)` で `EnrollmentPolicy::receiveCertificate` 経由に集約(本人検証 + status == Learning は Policy 側で判定)。Action 内では `auth()->id()` を参照せず、**データ整合性チェック**(`CompletionEligibilityService` の合格判定)のみ実施。依存 Service / Action はすべて **constructor injection** で受ける(`app()` ヘルパは Service Locator アンチパターンのため不採用、`backend-usecases.md` 規約準拠)。
>
> **設計判断**(2026-05-18): 修了通知 (Database / Mail) は送らない。受講生がボタンを押した直後のリダイレクト先画面に PDF DL リンクが表示されるため、通知は冗長。`NotifyCompletionApprovedAction` への dispatch は削除する。

```php
namespace App\UseCases\Enrollment;

use App\Enums\EnrollmentStatus;
use App\Exceptions\Enrollment\CompletionNotEligibleException;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Services\CompletionEligibilityService;
use App\Services\EnrollmentStatusChangeService;
use App\UseCases\Certificate\IssueAction as IssueCertificateAction;
use Illuminate\Support\Facades\DB;

final class ReceiveCertificateAction
{
    public function __construct(
        private readonly CompletionEligibilityService $eligibility,
        private readonly EnrollmentStatusChangeService $statusChanger,
        private readonly IssueCertificateAction $issueCertificate,
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

            return ($this->issueCertificate)($enrollment);
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

## [[chat]] 連携 (E-3 ChatRoom + ChatMember eager 生成)

`Enrollment\StoreAction` は **`DB::transaction()` 内で** [[chat]] の `ChatRoom` + `ChatMember` 集合を eager 生成する。同一トランザクションで実行することで、chat 側の lazy 生成ロジック / `chat.storeFirstMessage` ルート / `StoreFirstMessageAction` ラッパー / `Policy::sendMessageForEnrollment` をすべて撤回でき、UX 動線（受講登録直後から `/chat-rooms` 一覧にルーム表示）も自然に成立する。

### 連携箇所

| Action | 呼出 | タイミング |
|---|---|---|
| `Enrollment\StoreAction` | `ChatRoom::create(['enrollment_id' => $enrollment->id])` → `ChatMemberSyncService::syncForRoom($room)` | 受講登録 INSERT 直後（同一 `DB::transaction()` 内、`EnrollmentStatusLog` INSERT より後、コミット前）|

### Action 連携サンプル

```php
namespace App\UseCases\Enrollment;

use App\Models\Enrollment;
use App\Models\ChatRoom;
use App\Models\User;
use App\Services\ChatMemberSyncService;
use App\Services\DefaultEnrollmentService;
use Illuminate\Support\Facades\DB;

final class StoreAction
{
    public function __construct(
        private readonly ChatMemberSyncService $chatMemberSync,
        private readonly DefaultEnrollmentService $defaultEnrollmentResolver,
    ) {}

    public function __invoke(User $user, array $validated): Enrollment
    {
        return DB::transaction(function () use ($user, $validated) {
            $enrollment = Enrollment::create([
                'user_id' => $user->id,
                'certification_id' => $validated['certification_id'],
                'exam_date' => $validated['exam_date'] ?? null,
                'status' => EnrollmentStatus::Learning,
                'current_term' => TermType::BasicLearning,
            ]);

            EnrollmentStatusLog::create([
                'enrollment_id' => $enrollment->id,
                'from_status' => null,
                'to_status' => EnrollmentStatus::Learning,
                'changed_by_user_id' => $user->id,
                'changed_reason' => '新規登録',
            ]);

            // E-3: ChatRoom + ChatMember を同一トランザクションで eager 生成
            $room = ChatRoom::create(['enrollment_id' => $enrollment->id]);
            $this->chatMemberSync->syncForRoom($room);

            // [[default-enrollment]] 連携
            $this->defaultEnrollmentResolver->resolveAfterCreate($user, $enrollment);

            return $enrollment;
        });
    }
}
```

### 依存方向

enrollment → chat（本 Feature が依存元）。`ChatMemberSyncService` は constructor injection で受ける（`backend-usecases.md` 規約準拠、`app()` ヘルパは不採用）。chat 側の `StoreMessageAction` は逆に enrollment に依存せず、`ChatRoom` 確定のシグネチャに統一されている（[[chat]] design.md 参照）。

### コーチ未割当の Enrollment 作成

受講登録時に対象資格の `certification_coach_assignments` が 0 件の場合、`ChatMemberSyncService::syncForRoom` は受講生のみを `ChatMember` に INSERT する。後で `CoachAssignment\AttachAction` が `CertificationCoachAttached` イベントを発火すると、chat 側の `SyncChatMembersOnCoachAssignmentChanged` Listener が `ChatMemberSyncService::syncForCertification` を呼び出し、該当資格の全 `ChatRoom` にコーチを差分追加する。

---

## [[default-enrollment]] 連携 (v3 cross-cutting infrastructure)

本 Feature は受講登録 / 状態遷移の各 Action 内で [[default-enrollment]] の `DefaultEnrollmentService` を呼び、`users.default_enrollment_id` の自動設定 / 自動振替 / NULL リセットを連動させる。

### 連携箇所

| Action / Command | 呼出メソッド | タイミング |
|---|---|---|
| `Enrollment\StoreAction` | `resolveAfterCreate($user, $newEnrollment)` | 受講登録 INSERT 直後(同一 DB::transaction 内) |
| `Enrollment\FailAction` | `resolveAfterStatusChange($user, $changedEnrollment)` | admin 手動失敗マークで `failed` 遷移直後 |
| `Enrollment\ResumeAction` | `resolveAfterStatusChange($user, $changedEnrollment)` | `failed → learning` 再挑戦遷移直後 |
| `Enrollment\ReceiveCertificateAction` | `resolveAfterStatusChange($user, $changedEnrollment)` | `learning → passed` 修了遷移直後 |
| `FailExpiredEnrollmentsCommand` | `resolveAfterStatusChange($user, $enrollment)` | 試験日超過自動失敗で `failed` 遷移直後 |

### 依存方向

enrollment → default-enrollment(本 Feature が依存元)。各 Action の constructor injection で `DefaultEnrollmentService $defaultEnrollmentService` を受ける(`backend-usecases.md` 規約準拠)。

### 受講中資格画面の default UI

`/enrollments` index 画面 (`views/enrollments/index.blade.php`) の各 Enrollment カードに `<x-enrollment-switcher.card :enrollment="$e" :is-default="$e->id === auth()->user()->default_enrollment_id" />` を埋込み、「★デフォルト」バッジ + 「これをデフォルトにする」フォーム POST を表示する。本 UI は [[default-enrollment]] Feature が Blade Component を提供し、enrollment Feature の Blade で include する形を取る。
