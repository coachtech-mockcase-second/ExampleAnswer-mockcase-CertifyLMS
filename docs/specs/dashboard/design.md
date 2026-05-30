# dashboard 設計

> **v3 改修反映**(2026-05-16):
> - 受講生: **プラン情報パネル**(残面談回数 + プラン残日数 + 追加面談購入 CTA)追加、**修了済資格セクション** 追加(PDF DL のみ、復習モード遷移リンクは撤回)、**「修了証を受け取る」ボタン**(`ReceiveCertificateAction` 起動、自己発火)
> - 受講生: `Enrollment.status IN (learning, passed)` で表示(v3 で `passed` は受講中カードに残し、修了済セクションでも一覧化)
> - **graduated 専用ダッシュボード** 新設(**修了済資格一覧のみ**、卒業日 / プラン機能ロック表示 / プロフィール閲覧リンクは撤回)
> - admin: **修了申請待ち一覧 / プラン期限切れ間近一覧 / 滞留検知 / 直近通知** 削除(運用モニタリング MVP 最小限 + notification spec REQ-026「admin 宛通知は発火しない」整合)
> - coach: **滞留検知リスト / 受講生メモ / 弱点カテゴリ集約 / 最終活動日降順ソート** 削除、担当範囲を `assigned_coach_id` → **`certification.coaches` 経由**
> - 例外境界: 各 Action で `safe()` ヘルパー(try/catch + `report()` + null 返却)を使い、ViewModel プロパティを nullable にして Blade で null 判定
> - 集計最適化: 受講中資格カード進捗集計は `ProgressService::batchCalculate(Collection)` を利用、coach 担当受講生の最終活動日は `withMax('learningSessions as last_activity_at', 'started_at')` で取得
> - `Enrollment.lastLearningSession` リレーション: 追加しない(Action 側 `withMax` で対応)
> - SidebarBadgeComposer: admin の `notifications` バッジは常時 0、`pendingCompletions` キーは削除
> - **`StagnationDetectionService` は利用しない**(v3 で撤回)

## 概要

ログイン直後の `/dashboard` を **読み取り専用の集約画面** として提供する。独自モデル / Migration / Service / Policy を作らず、各 Feature が公開している Service と Eloquent モデルを DI で消費する。ロール別 Blade を 4 ファイルに分離(`admin.blade.php` / `coach.blade.php` / `student.blade.php` / `graduated.blade.php`)、`DashboardController::index` で `auth()->user()->role` および `status === Graduated` を判定して該当 Action(`FetchAdminDashboardAction` / `FetchCoachDashboardAction` / `FetchStudentDashboardAction` / `FetchGraduatedDashboardAction`)を呼び、readonly DTO ViewModel に詰めて Blade に渡す。サイドバーバッジ(`SidebarBadgeComposer`)と dashboard 本体の集計値は同一 Service を再利用し、数字の二重計算 / 乖離を構造的に防ぐ。

## アーキテクチャ概要

```mermaid
flowchart TB
    Request[GET /dashboard] --> Controller[DashboardController::index]
    Controller --> RoleCheck{role + status<br/>判定}
    RoleCheck -->|admin| FetchAdmin[FetchAdminDashboardAction]
    RoleCheck -->|coach| FetchCoach[FetchCoachDashboardAction]
    RoleCheck -->|student + in_progress| FetchStudent[FetchStudentDashboardAction]
    RoleCheck -->|student + graduated| FetchGraduated[FetchGraduatedDashboardAction]

    FetchStudent --> ProgressSvc[ProgressService]
    FetchStudent --> StreakSvc[StreakService]
    FetchStudent --> LearningCalendarSvc[LearningCalendarService]
    FetchStudent --> LearningHourSvc[LearningHourTargetService]
    FetchStudent --> WeaknessSvc[WeaknessAnalysisService]
    FetchStudent --> CompletionSvc[CompletionEligibilityService]
    FetchStudent --> MeetingQuotaSvc[MeetingQuotaService]
    FetchStudent --> PlanExpirationSvc[PlanExpirationService]

    FetchCoach --> ChatUnreadSvc[ChatUnreadCountService]

    FetchAdmin --> EnrollmentStatsSvc[EnrollmentStatsService]
```

> coach から `WeaknessAnalysisService` への連結は v3 で削除(弱点カテゴリ集約撤回)。`EnrollmentNote` 直近メモ取得も v3 で削除。

### 1. ロール判定 → ViewModel 構築

```mermaid
sequenceDiagram
    participant User
    participant DC as DashboardController
    participant FA as FetchStudentDashboardAction (例)
    participant Services as 各 Service 群

    User->>DC: GET /dashboard
    Note over DC: auth middleware で未ログイン弾き
    DC->>DC: role + status 判定<br/>(student + in_progress → student blade)
    DC->>FA: __invoke($user)
    par
        FA->>Services: PlanExpirationService::daysRemaining($user)
        FA->>Services: MeetingQuotaService::remaining($user)
        FA->>Services: ProgressService::summarize($enrollment) per enrollment
        FA->>Services: WeaknessAnalysisService::getPassProbabilityBand($enrollment)
        FA->>Services: CompletionEligibilityService::isEligible($enrollment)
        FA->>Services: StreakService::calculate($user)
    end
    Note over FA: 各 Service 呼出を個別 try/catch で例外境界<br/>(REQ-dashboard-007)
    FA-->>DC: StudentDashboardViewModel (readonly DTO)
    DC-->>User: views/dashboard/student.blade.php
```

### 2. graduated 専用ダッシュボード分岐

```mermaid
sequenceDiagram
    participant User as graduated user
    participant DC as DashboardController
    participant FG as FetchGraduatedDashboardAction

    User->>DC: GET /dashboard
    DC->>DC: user.status === Graduated 判定
    DC->>FG: __invoke($user)
    FG->>Enrollment: where('user_id')->where('status', Passed)->with('certification', 'certificate')
    FG-->>DC: GraduatedDashboardViewModel(passedEnrollments, profileLink)
    DC-->>User: views/dashboard/graduated.blade.php<br/>(修了証 PDF 一覧 + プロフィール + プラン機能ロック表示)
```

## コンポーネント

### Controller

```php
namespace App\Http\Controllers;

class DashboardController
{
    public function index(
        FetchAdminDashboardAction $fetchAdmin,
        FetchCoachDashboardAction $fetchCoach,
        FetchStudentDashboardAction $fetchStudent,
        FetchGraduatedDashboardAction $fetchGraduated,
    ): View {
        $user = auth()->user();

        // v3: graduated 専用ダッシュボード
        if ($user->status === UserStatus::Graduated) {
            $viewModel = ($fetchGraduated)($user);
            return view('dashboard.graduated', compact('viewModel'));
        }

        $viewModel = match ($user->role) {
            UserRole::Admin => ($fetchAdmin)($user),
            UserRole::Coach => ($fetchCoach)($user),
            UserRole::Student => ($fetchStudent)($user),
        };

        return view('dashboard.' . $user->role->value, compact('viewModel'));
    }
}
```

### Action(`App\UseCases\Dashboard\`)

#### `FetchStudentDashboardAction`(v3 でプラン情報パネル + 修了済資格セクション追加、`safe()` + `batchCalculate` 利用)

```php
class FetchStudentDashboardAction
{
    public function __construct(
        private ProgressService $progress,
        private StreakService $streak,
        private LearningCalendarService $learningCalendar,
        private LearningHourTargetService $hourTarget,
        private WeaknessAnalysisService $weakness,
        private CompletionEligibilityService $completion,
        private MeetingQuotaService $meetingQuota,
        private PlanExpirationService $planExpiration,
    ) {}

    public function __invoke(User $student): StudentDashboardViewModel
    {
        $activeEnrollments = Enrollment::where('user_id', $student->id)
            ->whereIn('status', [EnrollmentStatus::Learning, EnrollmentStatus::Passed])
            ->with('certification')
            ->get();

        return new StudentDashboardViewModel(
            planInfo: $this->safe(fn () => $this->buildPlanInfo($student)),
            enrollmentCards: $this->buildEnrollmentCards($activeEnrollments),
            passedEnrollments: $this->buildPassedEnrollments($student),
            streak: $this->safe(fn () => $this->streak->calculate($student)),
            learningCalendar: $this->safe(fn () => $this->learningCalendar->build($student)),
            goalTimeline: $this->safe(fn () => $this->buildGoalTimeline($student)),
            upcomingMeetings: $this->safe(fn () => $this->buildUpcomingMeetings($student)),
            recentNotifications: $student->notifications()->latest()->limit(5)->get(),
            unreadNotificationCount: $student->unreadNotifications()->count(),
            hasNoEnrollment: $activeEnrollments->isEmpty(),
        );
    }

    private function buildPlanInfo(User $student): PlanInfoPanel
    {
        return new PlanInfoPanel(
            planName: $student->plan?->name,
            courseDaysRemaining: $this->planExpiration->daysRemaining($student),
            meetingsRemaining: $this->meetingQuota->remaining($student),
            meetingPacks: MeetingPack::published()->ordered()->get(),
        );
    }

    private function buildEnrollmentCards(Collection $activeEnrollments): Collection
    {
        // N+1 回避: ProgressService::batchCalculate で進捗を一括計算
        $progressMap = $this->progress->batchCalculate($activeEnrollments);

        return $activeEnrollments->map(
            fn (Enrollment $e) => $this->buildCard($e, $progressMap[$e->id] ?? null)
        );
    }

    private function buildPassedEnrollments(User $student): Collection
    {
        return Enrollment::where('user_id', $student->id)
            ->where('status', EnrollmentStatus::Passed)
            ->whereNotNull('passed_at')
            ->with(['certification', 'certificate'])
            ->orderByDesc('passed_at')
            ->get();
    }

    private function buildCard(Enrollment $e, ?ProgressSummary $progressSummary): StudentEnrollmentCard
    {
        return new StudentEnrollmentCard(
            enrollmentId: $e->id,
            certificationName: $e->certification->name,
            status: $e->status,
            isPassed: $e->status === EnrollmentStatus::Passed,
            examDate: $e->exam_date,
            daysUntilExam: $e->exam_date?->diffInDays(now(), false),
            progressRatio: $progressSummary?->overallCompletionRatio,
            currentTerm: $e->current_term,
            learningHourTarget: $this->safe(fn () => $this->hourTarget->compute($e)),
            passProbabilityBand: $this->safe(fn () => $this->weakness->getPassProbabilityBand($e)),
            weakCategories: $this->safe(fn () => $this->weakness->getWeakCategories($e)->take(3)) ?? collect(),
            canReceiveCertificate: $this->completion->isEligible($e) && $e->status === EnrollmentStatus::Learning,
            certificateDownloadUrl: $e->certificate?->downloadUrl(),
        );
    }

    /**
     * 例外境界ヘルパー(REQ-dashboard-007)。
     * 各セクション build を包み、Service 例外を report() してから null 返却。
     */
    private function safe(\Closure $fn): mixed
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }
}
```

#### `FetchCoachDashboardAction`(v3 で certification.coaches 経由 + 滞留検知 / 受講生メモ / 弱点集約 / 降順ソート削除)

```php
class FetchCoachDashboardAction
{
    public function __construct(
        private ChatUnreadCountService $chatUnread,
    ) {}

    public function __invoke(User $coach): CoachDashboardViewModel
    {
        // v3: assigned_coach_id 撤回 → certification.coaches 経由
        // v3: 最終活動日表示は withMax で集約取得(N+1 回避)、sortByDesc は撤回
        $assignedEnrollments = Enrollment::query()
            ->whereHas('certification.coaches', fn ($q) => $q->where('users.id', $coach->id))
            ->whereIn('status', [EnrollmentStatus::Learning, EnrollmentStatus::Passed])
            ->with(['user', 'certification'])
            ->withMax('learningSessions as last_activity_at', 'started_at')
            ->get();

        // 今日 / 明日の面談(coach_id 指定で取得)
        $todayMeetings = Meeting::where('coach_id', $coach->id)
            ->where('status', MeetingStatus::Reserved)
            ->whereBetween('scheduled_at', [now()->startOfDay(), now()->endOfDay()->addDay()])
            ->with(['student', 'enrollment.certification'])
            ->orderBy('scheduled_at')->get();

        return new CoachDashboardViewModel(
            assignedEnrollments: $assignedEnrollments,
            todayAndTomorrowMeetings: $todayMeetings,
            unreadChatCount: $this->safe(fn () => $this->chatUnread->roomCountForUser($coach)),
            recentUnreadChatRooms: $this->safe(fn () => $this->fetchRecentChatRooms($coach, 5)),
            unansweredQaCount: $this->safe(fn () => $this->fetchUnansweredQaCount($coach)),
            recentQaThreads: $this->safe(fn () => $this->fetchRecentQa($coach, 5)),
            // v3 削除: aggregatedWeakCategories, recentEnrollmentNotes, stagnationList
            recentNotifications: $coach->notifications()->latest()->limit(5)->get(),
            unreadNotificationCount: $coach->unreadNotifications()->count(),
        );
    }

    private function safe(\Closure $fn): mixed
    {
        try { return $fn(); } catch (\Throwable $e) { report($e); return null; }
    }
}
```

#### `FetchAdminDashboardAction`(v3 で pending / プラン期限切れ / 滞留検知 / 直近通知 削除)

```php
class FetchAdminDashboardAction
{
    public function __construct(private EnrollmentStatsService $stats) {}

    public function __invoke(User $admin): AdminDashboardViewModel
    {
        $kpi = $this->safe(fn () => $this->stats->adminKpi());  // v3: pending_count なし

        return new AdminDashboardViewModel(
            kpi: $kpi,  // { learning_count, passed_count, failed_count, by_certification } or null
            byCertificationTop10: $kpi ? collect($kpi['by_certification'])->take(10) : collect(),
            completionRateByCertification: $this->safe(fn () => $this->stats->completionRateByCertification()),
            // v3 削除: recentNotifications, unreadNotificationCount(notification spec REQ-026 整合)
            isEmptyState: $kpi
                ? collect($kpi)->only(['learning_count', 'passed_count'])->sum() === 0
                : true,
        );
    }

    private function safe(\Closure $fn): mixed
    {
        try { return $fn(); } catch (\Throwable $e) { report($e); return null; }
    }
}
```

#### `FetchGraduatedDashboardAction`(v3 新規、修了済資格一覧のみ)

```php
class FetchGraduatedDashboardAction
{
    public function __invoke(User $graduated): GraduatedDashboardViewModel
    {
        // v3: 卒業日 / certificateCount / プラン機能ロック / プロフィール閲覧は撤回、
        // 修了済資格一覧のみを中核として返す
        $passedEnrollments = Enrollment::where('user_id', $graduated->id)
            ->where('status', EnrollmentStatus::Passed)
            ->whereNotNull('passed_at')
            ->with(['certification', 'certificate'])
            ->orderByDesc('passed_at')->get();

        return new GraduatedDashboardViewModel(
            passedEnrollments: $passedEnrollments,
        );
    }
}
```

### ViewModel(readonly DTO)

> 例外境界(REQ-dashboard-007 / NFR-dashboard-007): `safe()` で包んだセクションのプロパティは **nullable** とし、Blade で null 判定して empty-state に切り替える。

```php
// v3 新規 / 更新
readonly class StudentDashboardViewModel
{
    public function __construct(
        public ?PlanInfoPanel $planInfo,                         // v3 新規 / nullable(safe)
        public Collection $enrollmentCards,
        public Collection $passedEnrollments,                    // v3 新規(修了済資格セクション)
        public ?StreakSummary $streak,                           // nullable(safe)
        public ?LearningCalendar $learningCalendar,              // 学習カレンダー / nullable(safe)
        public ?Collection $goalTimeline,                        // nullable(safe)
        public ?Collection $upcomingMeetings,                    // nullable(safe)
        public Collection $recentNotifications,
        public int $unreadNotificationCount,
        public bool $hasNoEnrollment,
    ) {}
}

readonly class PlanInfoPanel
{
    public function __construct(
        public ?string $planName,
        public ?int $courseDaysRemaining,
        public int $meetingsRemaining,
        public Collection $meetingPacks,  // 追加面談購入 CTA 用
    ) {}
}

readonly class StudentEnrollmentCard
{
    public function __construct(
        public string $enrollmentId,
        public string $certificationName,
        public EnrollmentStatus $status,
        public bool $isPassed,                                   // v3 追加
        public ?Carbon $examDate,
        public ?int $daysUntilExam,
        public ?float $progressRatio,                            // batchCalculate 失敗時 null
        public TermType $currentTerm,
        public ?LearningHourTargetSummary $learningHourTarget,   // nullable(safe)
        public ?PassProbabilityBand $passProbabilityBand,        // nullable(safe)
        public Collection $weakCategories,                       // safe 失敗時 collect()
        public bool $canReceiveCertificate,                      // v3 で名前変更(canRequestCompletion → canReceiveCertificate)
        public ?string $certificateDownloadUrl,
    ) {}
}

readonly class CoachDashboardViewModel
{
    public function __construct(
        public Collection $assignedEnrollments,                  // withMax で last_activity_at 取得済、ソートなし(v3 撤回)
        public Collection $todayAndTomorrowMeetings,
        public ?int $unreadChatCount,                            // nullable(safe)
        public ?Collection $recentUnreadChatRooms,
        public ?int $unansweredQaCount,
        public ?Collection $recentQaThreads,
        // v3 削除: aggregatedWeakCategories, recentEnrollmentNotes
        public Collection $recentNotifications,
        public int $unreadNotificationCount,
    ) {}
}

readonly class AdminDashboardViewModel
{
    public function __construct(
        public ?array $kpi,                                      // nullable(Service 例外時) / v3: pending_count なし
        public Collection $byCertificationTop10,
        public ?Collection $completionRateByCertification,       // v3 新規 / nullable(safe)
        // v3 削除: recentNotifications, unreadNotificationCount(notification spec REQ-026 整合)
        public bool $isEmptyState,
    ) {}
}

readonly class GraduatedDashboardViewModel  // v3 新規(修了済資格一覧のみ)
{
    public function __construct(
        public Collection $passedEnrollments,
        // v3 削除: graduatedAt, certificateCount(passedEnrollments->count() で代用可、撤回)
    ) {}
}
```

## Blade ビュー

`resources/views/dashboard/`:

| ファイル | 役割 |
|---|---|
| `student.blade.php` | プラン情報パネル(最上部) → 受講中資格カード → 修了済資格セクション → ストリーク → 学習カレンダー → 目標タイムライン → 通知 → 面談予定 |
| `coach.blade.php` | 担当受講生(certification.coaches 経由、ソートなし) → 今日/明日の面談 → 未読 chat → 未回答 QA → 通知 |
| `admin.blade.php` | 全体 KPI(learning + passed + failed) → 資格別受講中人数 → 資格別修了率 |
| **`graduated.blade.php`(v3 新規)** | 修了済資格一覧のみ(中核) |
| `_partials/student/plan-info-panel.blade.php` | **v3 新規**: Plan 名 + プラン残日数 + 残面談回数 + 追加面談購入 CTA(MeetingPack モーダル) |
| `_partials/student/enrollment-card.blade.php` | 試験日カウントダウン + 進捗ゲージ + 現在ターム + 学習時間目標 + 合格可能性 + 弱点チップ + **「修了証を受け取る」ボタン** |
| `_partials/student/passed-enrollments.blade.php` | **v3 新規**: 修了済資格 + 修了日(年月日 + 経過日数) + PDF DL(復習モード遷移は撤回) |
| `_partials/student/streak-panel.blade.php` | |
| `_partials/student/learning-calendar.blade.php` | 直近 4 ヶ月の日別学習時間ヒートマップ(GitHub 風草グリッド、`resources/js/dashboard/learning-calendar.js` が日別マップを読んで描画 + 凡例 + 当月サマリ) |
| `_partials/student/goal-timeline.blade.php` | |
| `_partials/coach/assigned-students-list.blade.php` | v3: certification.coaches 経由、`last_activity_at` 表示、ソートなし |
| `_partials/coach/chat-room-summary.blade.php` | |
| `_partials/coach/qa-thread-summary.blade.php` | |
| `_partials/admin/kpi-overview.blade.php` | v3: pending_count タイルなし、learning + passed + failed の 3 タイル + 資格別 |
| `_partials/admin/completion-rate-list.blade.php` | v3 新規: 資格別修了率 |
| `_partials/admin/by-certification-breakdown.blade.php` | |
| `_partials/notification-list.blade.php`(student / coach 共通、admin 未使用) | |
| `_partials/meeting-upcoming-list.blade.php`(共通) | |
| `_partials/empty-state.blade.php`(共通、`safe()` null 時のフォールバック表示) | |
| `_partials/kpi-tile.blade.php`(共通) | |

### 明示的に持たない Blade(v3 撤回)

- 旧 `_partials/admin/pending-completion-list.blade.php`(修了申請待ち)
- 旧 `_partials/admin/stagnation-list-admin.blade.php`
- 旧 `_partials/admin/coach-activity-list.blade.php`
- 旧 `_partials/coach/stagnation-list.blade.php`
- 旧 `_partials/coach/weak-categories-aggregate.blade.php`(v3 で削除、担当受講生弱点集約撤回)
- 旧 `_partials/coach/enrollment-notes-recent.blade.php`(v3 で削除、受講生メモ表示撤回)
- 旧 `admin.blade.php` 内 notification セクション(v3 で削除、notification spec REQ-026 整合)
- 旧 `graduated.blade.php` 内 卒業日 / プラン機能ロック表示 / プロフィール閲覧リンク(v3 撤回)

## 関連要件マッピング

| 要件 ID | 実装ポイント |
|---|---|
| REQ-dashboard-001〜007 | `App\Http\Controllers\DashboardController` + `safe()` ヘルパー(各 Action) |
| REQ-dashboard-004 | `FetchGraduatedDashboardAction` + `graduated.blade.php`(v3 新規、修了済資格一覧のみ) |
| REQ-dashboard-005 | `App\View\Composers\SidebarBadgeComposer` と同一 Service を共有、admin の `notifications` は常時 0、`pendingCompletions` キー削除 |
| REQ-dashboard-100〜101 | `_partials/student/plan-info-panel.blade.php`(v3 新規) + `MeetingQuotaService::remaining` + `PlanExpirationService::daysRemaining` |
| REQ-dashboard-110〜151 | `_partials/student/enrollment-card.blade.php` + 各 Service 呼出(進捗は `ProgressService::batchCalculate` で前計算) |
| REQ-dashboard-160〜164 | `StudentEnrollmentCard::canReceiveCertificate` + `CompletionEligibilityService::isEligible` + `EnrollmentStatus === Learning` 論理積 |
| REQ-dashboard-170〜173 | `_partials/student/passed-enrollments.blade.php`(v3 新規、PDF DL のみ) + `FetchStudentDashboardAction::buildPassedEnrollments` |
| REQ-dashboard-171 | 復習モード遷移リンク撤回(v3) |
| REQ-dashboard-173 | graduated 専用ダッシュボードは修了済資格一覧のみ(卒業日 / プラン機能ロック / プロフィール閲覧は撤回) |
| REQ-dashboard-200〜240 | `StreakService` / `EnrollmentGoal` / `Notification` / `Meeting` を Action 内で集約、`safe()` で包む |
| REQ-dashboard-201 | `LearningCalendarService::build`(日別学習時間マップ + 今月合計) + `_partials/student/learning-calendar.blade.php`(data 属性) + `resources/js/dashboard/learning-calendar.js`(草グリッド描画)、`safe()` で包む |
| REQ-dashboard-300〜380 | `FetchCoachDashboardAction`(v3 で `certification.coaches` 経由 + `withMax`)、最終活動日表示のみ(ソートなし)、弱点集約 / 受講生メモ / 滞留検知削除 |
| REQ-dashboard-302 | **削除**(v3 撤回、最終活動日降順ソート撤廃) |
| REQ-dashboard-340 | **削除**(v3 撤回、弱点カテゴリ集約撤廃) |
| REQ-dashboard-360 | **削除**(v3 撤回、受講生メモ表示撤廃) |
| REQ-dashboard-500〜540 | `FetchAdminDashboardAction`(v3 で pending_count なし、滞留検知なし、coach 稼働なし、直近通知なし) |
| REQ-dashboard-520 | **削除**(v3 撤回、admin 宛通知は notification spec REQ-026 で発火しないため) |
| REQ-dashboard-530 | **削除**(v3 撤回) |
| NFR-dashboard-001 | 各 Action でクエリ計画(student 20 / coach 20 / admin 15 / graduated 10) |
| NFR-dashboard-002 | `with([...])` Eager Loading + `withMax('learningSessions as last_activity_at', 'started_at')` |
| NFR-dashboard-003 | 独自 Service を新設しない、他 Feature の Service のみ消費、集約は Action |
| NFR-dashboard-006 | `dashboard/{admin,coach,student,graduated}.blade.php` 4 ファイル(v3 で graduated 追加) |
| NFR-dashboard-007 | 各 Action 内 `safe()` ヘルパーで個別 Service 例外を吸収、ViewModel プロパティ nullable、Blade で null 判定 |
| NFR-dashboard-008 | `Enrollment` Model への `lastLearningSession` リレーション追加なし、Action 側 `withMax` で対応 |

## テスト戦略

`tests/Feature/Http/Dashboard/` および `tests/Feature/UseCases/Dashboard/`:

### Controller

- `DashboardControllerTest`: guest redirect / 各ロール blade 表示 / **graduated は graduated.blade を表示**(v3) / `safe()` 例外境界(1 Service 例外で画面全体は 500 化しない)

### Action

- `FetchStudentDashboardActionTest`:
  - learning + passed Enrollment 両方を card に表示(v3)
  - プラン情報パネルの残面談回数表示(v3)
  - 修了済資格セクションが `passed_at DESC` 順(v3)
  - **修了済資格セクションは PDF DL のみ、復習モード遷移リンクなし**(v3)
  - 「修了証を受け取る」ボタン活性条件(v3、`isEligible && status === Learning` の論理積)
  - **`ProgressService::batchCalculate` 利用で N+1 発生しない**(v3)
  - `safe()` 例外境界: 個別 Service 例外時に該当プロパティのみ null、他は正常
  - 学習カレンダー(`LearningCalendarService::build`)が ViewModel に格納される / Service 例外時に null
- `FetchCoachDashboardActionTest`:
  - certification.coaches 経由で担当 Enrollment 取得(v3)
  - **`withMax('learningSessions as last_activity_at', ...)` で N+1 なし**(v3)
  - **最終活動日降順ソートテスト削除**(v3)
  - **弱点集約テスト削除 / 受講生メモテスト削除**(v3)
  - **stagnation 関連テスト削除**(v3)
- `FetchAdminDashboardActionTest`:
  - **pending_count テスト削除**(v3)
  - 修了率テスト(v3)
  - **直近通知テスト削除**(v3、notification spec REQ-026 整合)
- **`FetchGraduatedDashboardActionTest`(v3 新規)**:
  - graduated user で passedEnrollments を返す
  - 修了証 DL リンク含む
  - **卒業日 / certificateCount / プラン機能ロック / プロフィール閲覧プロパティが ViewModel に存在しない**(v3 撤回)

### Service

- **`LearningCalendarServiceTest`(`tests/Unit/Services/`)**: 日別学習時間マップ集約 / 秒→分変換 / 今月合計 / 範囲外(4 ヶ月より前)セッション除外 / 基準日。濃淡レベル離散化・グリッド構築は JS 側責務のため Playwright で動作確認

### Sidebar 整合性

- `DashboardSidebarConsistencyTest`(REQ-dashboard-005):
  - coach / student のサイドバーバッジと dashboard 本体の数値一致
  - **admin の `notifications` バッジは常時 0**(notification spec REQ-026 整合)
  - **`pendingCompletions` キーが Composer に存在しない**(v3 撤回)

### クエリ数

- `DashboardQueryCountTest`(NFR-dashboard-001): student 20 / coach 20 / admin 15 / graduated 10 以内
