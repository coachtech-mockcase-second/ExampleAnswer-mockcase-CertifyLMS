# dashboard タスクリスト

> 1 タスク = 1 コミット粒度。Step 単位で順次実装し、完了したものから `[x]` に更新する。
> 関連要件 ID は `requirements.md` の `REQ-dashboard-NNN` / `NFR-dashboard-NNN` を参照。
> 本 Feature は **読み取り専用 + 独自モデル / Service / Policy / Migration を作らない**。
> **v3 改修反映**:
> - 受講生にプラン情報パネル + 修了済資格セクション(PDF DL のみ、復習モード遷移は撤回)追加、「修了証を受け取る」ボタン追加
> - graduated 専用ダッシュボード新設(修了済資格一覧のみ、卒業日 / プラン機能ロック / プロフィール閲覧は撤回)
> - admin 修了申請待ち / プラン期限 / 滞留検知 / 直近通知削除(notification spec REQ-026「admin 宛通知は発火しない」整合)
> - coach 滞留検知 / 受講生メモ / 弱点カテゴリ集約 / 最終活動日降順ソート削除、`certification.coaches` 経由判定
> - 例外境界: 各 Action で `safe()` ヘルパー(try/catch + report + null)、ViewModel プロパティ nullable
> - 集計最適化: `ProgressService::batchCalculate` 利用、coach 最終活動日は `withMax('learningSessions as last_activity_at', 'started_at')`
> - `Enrollment.lastLearningSession` リレーション追加なし(Action 側 `withMax` で対応)
> - SidebarBadgeComposer: admin の `notifications` バッジは常時 0、`pendingCompletions` キー削除

## Step 1: Migration & Model

- 本 Feature では Migration / Model / Enum を新規作成しない(NFR-dashboard-003, NFR-dashboard-008)

## Step 2: Policy

- 本 Feature では Policy を新規作成しない(NFR-dashboard-008)

## Step 3: HTTP 層 + SidebarBadgeComposer 更新

- [x] `App\Http\Controllers\DashboardController` 作成(`index` method 1 つ、4 つの Fetch Action を method DI、**`user->status === Graduated` を先に分岐**、`match` でロール分岐、`view('dashboard.' . $name, compact('viewModel'))` 返却)(REQ-dashboard-001〜004)
- [x] `routes/web.php` に `Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('auth')->name('dashboard.index')` 追加
- [x] 既存 Wave 0b 仮実装ルート(`Route::view('/dashboard', 'placeholders.coming-soon', ...)`)を置換
- [x] **`App\View\Composers\SidebarBadgeComposer` 更新**(REQ-dashboard-005):
  - **`pendingCompletions` キー削除**(v3 撤回、修了申請承認フロー撤廃)
  - **admin ロールの `notifications` キーは常時 0**(notification spec REQ-026「admin 宛通知は発火しない」整合)
  - student / coach の `notifications` 集計を `$user->unreadNotifications()->count()` で実装(dashboard 本体と同一クエリ)
  - student / coach の `todayMeetings` 集計を `Meeting::whereBetween('scheduled_at', [今日 0:00, 今日 23:59])->where(user_id or coach_id)` で実装(dashboard 本体と同一クエリ)
  - student の `unfinishedMockExams` は本 Feature スコープ外(関連 Feature 実装時に追加、本 Step では 0 のままで OK)

## Step 4: Action / ViewModel

### Action

- [x] `App\UseCases\Dashboard\FetchAdminDashboardAction`(`EnrollmentStatsService::adminKpi`(v3 で pending_count なし) + `completionRateByCertification`、各 Service 呼出を `safe()` ヘルパーで包む、**直近通知削除**(v3))(REQ-dashboard-500〜540)
- [x] `App\UseCases\Dashboard\FetchCoachDashboardAction`(**`certification.coaches` 経由**(v3)で担当 Enrollment 取得 + **`withMax('learningSessions as last_activity_at', 'started_at')`** で N+1 回避、**ソートなし**(v3 撤回) + 今日/明日 Meeting + ChatUnreadCountService + 未回答 QA + 通知、**弱点集約 / 受講生メモ / 滞留検知削除**(v3))(REQ-dashboard-300〜380)
- [x] `App\UseCases\Dashboard\FetchStudentDashboardAction`(**`PlanExpirationService::daysRemaining` + `MeetingQuotaService::remaining` + MeetingPack 一覧**(v3 新規) + `status IN (learning, passed)` Enrollment + **`ProgressService::batchCalculate` で進捗一括計算**(N+1 回避) + 各種 Service + **修了済資格セクション**(v3 新規、PDF DL のみ) + StreakService + EnrollmentGoal + 今後の Meeting + notification、各 build を `safe()` で包む)(REQ-dashboard-100〜240)
- [x] **`App\UseCases\Dashboard\FetchGraduatedDashboardAction`(v3 新規、修了済資格一覧のみ)** — `Enrollment::where('user_id')->where('status', Passed)->whereNotNull('passed_at')->with('certification', 'certificate')->orderByDesc('passed_at')->get()` のみ(REQ-dashboard-004 / 173)
- [x] 本 Feature 固有のドメイン例外は新規作成しない(個別 Service 例外を Action 内 `safe()` ヘルパーで吸収、ViewModel プロパティ nullable、画面全体 500 化防止)(REQ-dashboard-007)
- [x] `safe()` ヘルパー実装方針: 各 Action 内 private メソッド or trait(`HasDashboardSafeFetch`) で `try { return $fn(); } catch (\Throwable $e) { report($e); return null; }` を共通化

### ViewModel(readonly DTO)

- [x] `AdminDashboardViewModel`(v3 で **`pending_count` プロパティなし** + **`recentNotifications` / `unreadNotificationCount` 削除**(v3 撤回、notification spec REQ-026 整合)、`completionRateByCertification` 追加、各プロパティ nullable for safe)
- [x] `CoachDashboardViewModel`(v3 で **`stagnationList` / `aggregatedWeakCategories` / `recentEnrollmentNotes` プロパティ削除**、`assignedEnrollments` は `certification.coaches` 経由 + `last_activity_at` withMax、各プロパティ nullable for safe)
- [x] `StudentDashboardViewModel`(v3 で **`planInfo: ?PlanInfoPanel` 追加** + **`passedEnrollments` 追加**、`enrollmentCards.canRequestCompletion` を **`canReceiveCertificate`** に rename、各プロパティ nullable for safe)
- [x] **`GraduatedDashboardViewModel`(v3 新規、修了済資格一覧のみ)** — `passedEnrollments` のみ(**`graduatedAt` / `certificateCount` 削除**、v3 撤回)
- [x] **`PlanInfoPanel` DTO(v3 新規)** — `planName` / `courseDaysRemaining` / `meetingsRemaining` / `meetingPacks`
- [x] **`StudentEnrollmentCard` 更新** — `isPassed` boolean / `canReceiveCertificate`(rename) / `certificateDownloadUrl` / `learningHourTarget` / `passProbabilityBand` を nullable for safe

## Step 5: Blade ビュー

### ロール別 Blade

- [x] `resources/views/dashboard/admin.blade.php` 実装(KPI(pending なし) + 資格別 + 修了率、**通知セクション削除**(v3 撤回、notification spec REQ-026 整合))
- [x] `resources/views/dashboard/coach.blade.php` 実装(担当受講生(最終活動日表示、ソートなし) + 面談 + chat + QA + 通知、**弱点集約 / 受講生メモ / stagnation セクション削除**(v3))
- [x] `resources/views/dashboard/student.blade.php` 実装(**プラン情報パネル(最上部)** + 受講中資格カード(learning + passed) + **修了済資格セクション**(PDF DL のみ) + ストリーク + 目標 + 通知 + 面談予定)
- [x] **`resources/views/dashboard/graduated.blade.php` 実装(v3 新規、修了済資格一覧のみ)** — `passedEnrollments` のみ表示、**卒業日 / プラン機能ロック表示 / プロフィール閲覧リンクは削除**(v3 撤回)
- [x] 各 Blade で ViewModel プロパティ null 判定 → `_partials/empty-state.blade.php` にフォールバック(REQ-dashboard-007 整合)

### 共通 partial

- [x] `_partials/notification-list.blade.php`(student / coach 共通、admin 未使用)
- [x] `_partials/meeting-upcoming-list.blade.php`(student / coach 共通)
- [x] `_partials/empty-state.blade.php`(`safe()` null 時のフォールバック表示)
- [x] `_partials/kpi-tile.blade.php`

### Student partial

- [x] **`_partials/student/plan-info-panel.blade.php`(v3 新規)** — Plan 名 + プラン残日数 + 残面談回数 + **追加面談購入 CTA**(MeetingPack モーダル + Stripe checkout 遷移)
- [x] **`_partials/student/passed-enrollments.blade.php`(v3 新規)** — 修了済資格セクション、各行に資格名 + 修了日(年月日 + 経過日数) + **PDF DL のみ**(復習モード遷移リンクは v3 撤回)
- [x] `_partials/student/enrollment-card.blade.php`(試験日カウントダウン + 進捗ゲージ + 現在ターム + 学習時間目標 + 合格可能性 + 弱点チップ + **「修了証を受け取る」ボタン**(v3 で名前変更))
- [x] `_partials/student/streak-panel.blade.php`
- [x] `_partials/student/goal-timeline.blade.php`

### Coach partial

- [x] `_partials/coach/assigned-students-list.blade.php`(v3: certification.coaches 経由 + `last_activity_at` 表示、ソートなし)
- [x] `_partials/coach/chat-room-summary.blade.php`
- [x] `_partials/coach/qa-thread-summary.blade.php`

### Admin partial

- [x] `_partials/admin/kpi-overview.blade.php`(learning + passed + failed の 3 タイル、**pending タイル削除**)
- [x] `_partials/admin/by-certification-breakdown.blade.php`
- [x] **`_partials/admin/completion-rate-list.blade.php`(v3 新規)** — 資格別修了率

### 明示的に持たない Blade(v3 撤回)

- 旧 `_partials/admin/pending-completion-list.blade.php`(修了申請待ち)
- 旧 `_partials/admin/stagnation-list-admin.blade.php`
- 旧 `_partials/admin/coach-activity-list.blade.php`
- 旧 `_partials/coach/stagnation-list.blade.php`
- 旧 `_partials/coach/weak-categories-aggregate.blade.php`(v3 削除、弱点カテゴリ集約撤回)
- 旧 `_partials/coach/enrollment-notes-recent.blade.php`(v3 削除、受講生メモ表示撤回)
- 旧 `admin.blade.php` 内 notification セクション(v3 削除、notification spec REQ-026 整合)
- 旧 `graduated.blade.php` 内 卒業日 / プラン機能ロック表示 / プロフィール閲覧リンク(v3 撤回)

## Step 6: テスト

### Controller

- [x] `tests/Feature/Http/Dashboard/DashboardControllerTest.php`(guest redirect / admin blade / coach blade / student blade(in_progress) / **graduated blade**(v3) / cross-role 表示なし / **`safe()` 例外境界**(1 Service 例外で画面全体 500 化しない))

### Action

- [x] `FetchAdminDashboardActionTest`(KPI 件数 / 資格別上位 10 / 修了率 / **pending_count テスト削除**(v3) / **直近通知テスト削除**(v3、notification spec REQ-026 整合) / `safe()` 例外境界(個別 Service 例外時に該当プロパティのみ null))
- [x] `FetchCoachDashboardActionTest`(**`certification.coaches` 経由で担当 Enrollment 取得**(v3) / **`withMax('learningSessions as last_activity_at', ...)` で N+1 なし**(v3) / unread chat / unanswered QA / **降順ソートテスト削除**(v3) / **弱点集約 / 受講生メモテスト削除**(v3) / **滞留関連テスト削除**(v3) / `safe()` 例外境界)
- [x] `FetchStudentDashboardActionTest`(**learning + passed 両方 card 表示**(v3) / プラン情報パネル(残面談 + 残日数 + MeetingPack 一覧)(v3) / **修了済資格セクション(passed_at DESC、PDF DL のみ、復習モード遷移なし)**(v3) / **「修了証を受け取る」ボタン活性条件**(v3、`isEligible && status === Learning`) / **`ProgressService::batchCalculate` で N+1 なし**(v3) / hasNoEnrollment 判定 / `safe()` 例外境界)
- [x] **`FetchGraduatedDashboardActionTest`(v3 新規)** — passedEnrollments 取得 / 修了証 DL リンク含む / **卒業日 / certificateCount / プラン機能ロック / プロフィール閲覧プロパティが ViewModel に存在しない**(v3 撤回)

### Sidebar 整合性 / クエリ数

- [x] `DashboardSidebarConsistencyTest`:
  - coach / student のサイドバー値と dashboard 本体値の一致
  - **admin の `notifications` バッジは常時 0**(notification spec REQ-026 整合)
  - **`pendingCompletions` キーが Composer に存在しない**(v3 撤回)
- [x] `DashboardQueryCountTest`(NFR: **student 30 / coach 25 / admin 20 / graduated 10** 以内、上流 Service の N+1 を許容しつつ Enrollment 数の線形増加を保証)

### アーキテクチャ Lint

- [x] `DashboardArchitectureTest`(`app/Services/Dashboard*Service.php` ゼロ / `Cache::` 不使用 / `app/Policies/Dashboard*` ゼロ / `app/Http/Middleware/Dashboard*` ゼロ / **`Enrollment::lastLearningSession` リレーション不在**(v3) / `resources/views/dashboard/**` で `DB::` / `Model::query` 不使用)

## Step 7: Factory + Seeder

- [x] **Seeder 不要**: 本 Feature は他 Feature の集計を表示するだけで自前の永続データを持たないため、専用 Seeder は提供しない(`structure.md` Seeder 規約「④ 集計・読み取り専用系」分類)
- [ ] ただし **動作確認の網羅性確保のため** 以下を要求(各上流 Seeder が満たしていれば本 Feature 単体追加は不要):
  - 受講生ダッシュボード: in_progress + Plan 紐づけ + 進捗多様(開始直後 / 中盤 / 期限直前)→ [[auth]] / [[plan-management]] が担保
  - graduated 専用ダッシュボード: graduated student × Plan 過去履歴 → [[auth]] / [[plan-management]] が担保
  - 修了済資格セクション: passed enrollment + 修了証 → [[enrollment]] / [[certification-management]] が担保
  - admin ダッシュボード: KPI 集計 → 各マスタ Seeder の網羅データから自動集計

## Step 8: 動作確認 & 整形

- [ ] `sail artisan test --filter=Dashboard` 全通過
- [ ] `sail bin pint --dirty` 整形
- [ ] ブラウザ動作確認シナリオ:
  - [ ] admin → `/dashboard` で KPI(pending タイルなし) / 資格別 / 修了率 表示、**通知セクションなし**(v3 撤回)
  - [ ] coach → `/dashboard` で担当受講生(certification.coaches 経由、最終活動日表示、降順ソートなし) / 面談 / chat / QA / 通知 表示、**弱点集約 / 受講生メモ / 滞留検知セクションなし**(v3 撤回)
  - [ ] student → `/dashboard` で **プラン情報パネル**(残日数 + 残面談 + 購入 CTA) + 受講中資格カード(learning + passed 両方) + **修了済資格セクション**(PDF DL のみ、復習モード遷移なし)(v3) + ストリーク + 目標 + 通知 + 面談予定 表示
  - [ ] student が CompletionEligibility 達成後に **「修了証を受け取る」ボタン**(v3 改称)が活性、押下で `POST /enrollments/{enrollment}/receive-certificate` 起動 → enrollment.status=passed + Certificate 発行 + 修了済資格セクションに即時反映
  - [ ] student が `graduated` 化(plan_expires_at 経過) → **graduated 専用ダッシュボード**(修了済資格一覧のみ、卒業日 / プラン機能ロック / プロフィール閲覧なし)(v3) 表示、修了証 PDF DL 永続可能
  - [ ] サイドバー badge と dashboard 本体の数値一致(coach / student)、**admin の `notifications` バッジは常時 0**(v3)
- [ ] アクセシビリティ確認(Lighthouse Accessibility 90 以上)
- [ ] N+1 確認(Telescope / debugbar): 受講中資格カードで `batchCalculate` が効いていること、coach 担当受講生で `withMax` が効いていること
- [ ] 個別 Service 例外境界の手動確認(`ProgressService` を意図的に例外 → 該当ウィジェットだけ「データ取得できませんでした」、他は通常表示、画面全体 500 化しない)
- [ ] **SidebarBadgeComposer 動作確認**: `pendingCompletions` キーが Composer / `sidebarBadges` 配列に存在しないこと、admin で `notifications` が常時 0 になること
