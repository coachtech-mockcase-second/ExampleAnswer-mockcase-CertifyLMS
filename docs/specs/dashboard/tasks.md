# dashboard タスクリスト

> 1 タスク = 1 コミット粒度。Step 単位で順次実装し、完了したものから `[x]` に更新する。
> 関連要件 ID は `requirements.md` の `REQ-dashboard-NNN` / `NFR-dashboard-NNN` を参照。
> 本 Feature は **読み取り専用 + 独自モデル / Service / Policy / Migration を作らない**。
> **v3 改修反映**: 受講生にプラン情報パネル + 修了済資格セクション追加、graduated 専用ダッシュボード新設、admin 修了申請待ち / プラン期限 / 滞留検知削除、coach 滞留検知削除、`certification.coaches` 経由判定。

## Step 1: Migration & Model

- 本 Feature では Migration / Model / Enum を新規作成しない(NFR-dashboard-003, NFR-dashboard-008)

## Step 2: Policy

- 本 Feature では Policy を新規作成しない(NFR-dashboard-008)

## Step 3: HTTP 層

- [ ] `App\Http\Controllers\DashboardController` 作成(`index` method 1 つ、4 つの Fetch Action を method DI、**`user->status === Graduated` を先に分岐**、`match` でロール分岐、`view('dashboard.' . $name, compact('viewModel'))` 返却)(REQ-dashboard-001〜004)
- [ ] `routes/web.php` に `Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('auth')->name('dashboard.index')` 追加
- [ ] 既存 Wave 0b 仮実装ルートを置換

## Step 4: Action / ViewModel

### Action

- [ ] `App\UseCases\Dashboard\FetchAdminDashboardAction`(`EnrollmentStatsService::adminKpi`(v3 で pending_count なし) + `completionRateByCertification` + notification、各 Service 呼出を個別 try/catch)(REQ-dashboard-500〜540)
- [ ] `App\UseCases\Dashboard\FetchCoachDashboardAction`(**`certification.coaches` 経由**(v3)で担当 Enrollment 取得 + 今日/明日 Meeting + ChatUnreadCountService + 未回答 QA + 担当受講生集合への WeaknessAnalysisService 集約 + EnrollmentNote 直近 + notification、**滞留検知削除**(v3))(REQ-dashboard-300〜380)
- [ ] `App\UseCases\Dashboard\FetchStudentDashboardAction`(**`PlanExpirationService::daysRemaining` + `MeetingQuotaService::remaining` + MeetingQuotaPlan 一覧**(v3 新規) + `status IN (learning, passed)` Enrollment + 各種 Service + **修了済資格セクション**(v3 新規) + StreakService + EnrollmentGoal + 今後の Meeting + notification)(REQ-dashboard-100〜240)
- [ ] **`App\UseCases\Dashboard\FetchGraduatedDashboardAction`(v3 新規)** — `Enrollment::where('user_id')->where('status', Passed)->with('certification', 'certificate')->orderByDesc('passed_at')->get()` + 卒業日(plan_expires_at)取得(REQ-dashboard-004)
- [ ] 本 Feature 固有のドメイン例外は新規作成しない(個別 Service 例外を Action 内 try/catch で吸収、画面全体 500 化防止)(REQ-dashboard-007)

### ViewModel(readonly DTO)

- [ ] `AdminDashboardViewModel`(v3 で **`pending_count` プロパティなし**、`completionRateByCertification` 追加)
- [ ] `CoachDashboardViewModel`(v3 で **stagnationList プロパティ削除**、`assignedEnrollments` は `certification.coaches` 経由)
- [ ] `StudentDashboardViewModel`(v3 で **`planInfo: PlanInfoPanel` 追加** + **`passedEnrollments` 追加**、`enrollmentCards.canRequestCompletion` を **`canReceiveCertificate`** に rename)
- [ ] **`GraduatedDashboardViewModel`(v3 新規)** — `graduatedAt` / `passedEnrollments` / `certificateCount`
- [ ] **`PlanInfoPanel` DTO(v3 新規)** — `planName` / `courseDaysRemaining` / `meetingsRemaining` / `meetingQuotaPlans`
- [ ] **`StudentEnrollmentCard` 更新** — `isPassed` boolean / `canReceiveCertificate`(rename) / `certificateDownloadUrl`
- [ ] `CoachEnrollmentRow` 更新(stagnation 関連削除)

## Step 5: Blade ビュー

### ロール別 Blade

- [ ] `resources/views/dashboard/admin.blade.php` 実装(KPI(pending なし) + 資格別 + 修了率 + 通知)
- [ ] `resources/views/dashboard/coach.blade.php` 実装(担当受講生 + 面談 + chat + QA + 弱点集約 + メモ + 通知、**stagnation セクション削除**)
- [ ] `resources/views/dashboard/student.blade.php` 実装(**プラン情報パネル(最上部)** + 受講中資格カード + **修了済資格セクション** + ストリーク + 目標 + 通知 + 面談予定)
- [ ] **`resources/views/dashboard/graduated.blade.php` 実装(v3 新規)** — 修了済資格セクション中核 + プロフィール閲覧リンク + プラン機能ロック表示

### 共通 partial

- [ ] `_partials/notification-list.blade.php`(3 ロール共通)
- [ ] `_partials/meeting-upcoming-list.blade.php`(student / coach 共通)
- [ ] `_partials/empty-state.blade.php`
- [ ] `_partials/kpi-tile.blade.php`

### Student partial

- [ ] **`_partials/student/plan-info-panel.blade.php`(v3 新規)** — Plan 名 + プラン残日数 + 残面談回数 + **追加面談購入 CTA**(MeetingQuotaPlan モーダル + Stripe checkout 遷移)
- [ ] **`_partials/student/passed-enrollments.blade.php`(v3 新規)** — 修了済資格セクション、各行に資格名 + 修了日 + PDF DL + 復習モード遷移
- [ ] `_partials/student/enrollment-card.blade.php`(試験日カウントダウン + 進捗ゲージ + 学習時間目標 + 合格可能性 + 弱点チップ + **「修了証を受け取る」ボタン**(v3 で名前変更))
- [ ] `_partials/student/streak-panel.blade.php`
- [ ] `_partials/student/goal-timeline.blade.php`

### Coach partial

- [ ] `_partials/coach/assigned-students-list.blade.php`(v3: certification.coaches 経由)
- [ ] `_partials/coach/chat-room-summary.blade.php`
- [ ] `_partials/coach/qa-thread-summary.blade.php`
- [ ] `_partials/coach/weak-categories-aggregate.blade.php`
- [ ] `_partials/coach/enrollment-notes-recent.blade.php`

### Admin partial

- [ ] `_partials/admin/kpi-overview.blade.php`(learning + passed + failed の 3 タイル、**pending タイル削除**)
- [ ] `_partials/admin/by-certification-breakdown.blade.php`
- [ ] **`_partials/admin/completion-rate-list.blade.php`(v3 新規)** — 資格別修了率

### 明示的に持たない Blade(v3 撤回)

- 旧 `_partials/admin/pending-completion-list.blade.php`
- 旧 `_partials/admin/stagnation-list-admin.blade.php`
- 旧 `_partials/admin/coach-activity-list.blade.php`
- 旧 `_partials/coach/stagnation-list.blade.php`

## Step 6: テスト

### Controller

- [ ] `tests/Feature/Http/Dashboard/DashboardControllerTest.php`(guest redirect / admin blade / coach blade / student blade(in_progress) / **graduated blade**(v3) / cross-role 表示なし)

### Action

- [ ] `FetchAdminDashboardActionTest`(KPI 件数 / 資格別上位 10 / 修了率 / **pending_count テスト削除**(v3) / 個別 Service 例外境界)
- [ ] `FetchCoachDashboardActionTest`(**`certification.coaches` 経由で担当 Enrollment 取得**(v3) / unread chat / unanswered QA / **滞留関連テスト削除**(v3) / EnrollmentNote 担当絞込)
- [ ] `FetchStudentDashboardActionTest`(**learning + passed 両方 card 表示**(v3) / プラン情報パネル(残面談 + 残日数 + MeetingQuotaPlan 一覧)(v3) / **修了済資格セクション(passed_at DESC)**(v3) / **「修了証を受け取る」ボタン活性条件**(v3、`isEligible && status === Learning`) / hasNoEnrollment 判定)
- [ ] **`FetchGraduatedDashboardActionTest`(v3 新規)** — passedEnrollments 取得 / 修了証 DL リンク含む

### Sidebar 整合性 / クエリ数

- [ ] `DashboardSidebarConsistencyTest`(admin / coach / student のサイドバー値と dashboard 本体値の一致)
- [ ] `DashboardQueryCountTest`(NFR: admin 25 / coach 25 / student 20 / **graduated 10**(v3) 以内)

### アーキテクチャ Lint

- [ ] `DashboardArchitectureTest`(`app/Services/Dashboard*Service.php` ゼロ / `Cache::` 不使用 / `app/Policies/Dashboard*` ゼロ / `app/Http/Middleware/Dashboard*` ゼロ)
- [ ] `DashboardBladeLintTest`(`resources/views/dashboard/**` で `DB::` / `\App\Models\` の `::query()` 不使用)

## Step 7: 動作確認 & 整形

- [ ] `sail artisan test --filter=Dashboard` 全通過
- [ ] `sail bin pint --dirty` 整形
- [ ] ブラウザ動作確認シナリオ:
  - [ ] admin → `/dashboard` で KPI(pending タイルなし) / 資格別 / 修了率 / 通知 表示
  - [ ] coach → `/dashboard` で担当受講生(certification.coaches 経由) / 面談 / chat / QA / 弱点 / メモ / 通知 表示、滞留検知セクションなし
  - [ ] student → `/dashboard` で **プラン情報パネル**(残日数 + 残面談 + 購入 CTA) + 受講中資格カード(learning + passed 両方) + **修了済資格セクション**(passed 過去履歴) + ストリーク + 目標 + 通知 + 面談予定 表示
  - [ ] student が CompletionEligibility 達成後に **「修了証を受け取る」ボタン**(v3 改称)が活性、押下で `POST /enrollments/{enrollment}/receive-certificate` 起動 → enrollment.status=passed + Certificate 発行 + 修了済資格セクションに即時反映
  - [ ] student が `graduated` 化(plan_expires_at 経過) → **graduated 専用ダッシュボード** 表示、プラン機能ロック表示 + 修了証 PDF DL 永続可能
  - [ ] サイドバー badge と dashboard 本体の数値一致
- [ ] アクセシビリティ確認(Lighthouse Accessibility 90 以上)
- [ ] N+1 確認(Telescope / debugbar)
- [ ] 個別 Service 例外境界の手動確認(`ProgressService` を意図的に例外 → 該当ウィジェットだけ「データ取得できませんでした」、他は通常表示)
