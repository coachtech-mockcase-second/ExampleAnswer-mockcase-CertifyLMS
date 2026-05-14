# dashboard タスクリスト

> 1タスク = 1コミット粒度。Step 単位で順次実装し、完了したものから `[x]` に更新する。
> 関連要件 ID は `requirements.md` の `REQ-dashboard-NNN` / `NFR-dashboard-NNN` を参照。
> 本 Feature は **読み取り専用 + 独自モデル / Service / Policy / Migration を作らない** ため、Step 1（Migration & Model）/ Step 2（Policy）は意図的に空。

## Step 1: Migration & Model

- 本 Feature では Migration / Eloquent Model / Enum を新規作成しない（NFR-dashboard-003、NFR-dashboard-008）。他 Feature が公開する `User` / `Enrollment` / `EnrollmentGoal` / `EnrollmentNote` / `Meeting` / `ChatRoom` / `QaThread` / `Certification` / `Certificate` / `DatabaseNotification` を読み取り消費する。

## Step 2: Policy

- 本 Feature では Policy を新規作成しない（NFR-dashboard-008、REQ-dashboard-004）。`auth` middleware + ロール別 Blade の構造的分離で cross-role アクセスを防ぐ。各セクションが遷移する先の Feature（[[enrollment]] / [[chat]] / [[mock-exam]] 等）は自身の Policy で当事者チェックを行う。

## Step 3: HTTP 層

- [ ] `App\Http\Controllers\DashboardController` 作成（index method 1 つ、3 つの Fetch Action を method DI、`match` でロール分岐 + `view('dashboard.' . $user->role->value, compact('viewModel'))` 返却）（REQ-dashboard-001, REQ-dashboard-002, REQ-dashboard-008, REQ-dashboard-010）
- [ ] `routes/web.php` に `Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('auth')->name('dashboard.index')` を追加（既存 `auth` middleware group 内）（REQ-dashboard-001）
- [ ] 既存の Wave 0b 仮実装ルート（`Route::get('/dashboard', fn() => view('dashboard.placeholder'))->name('dashboard.index')` 等）を削除 / 置換

## Step 4: Action / ViewModel / Exception

- [ ] `App\UseCases\Dashboard\AdminDashboardViewModel` 作成（readonly class、`AdminDashboardKpi` 子 DTO を含む）（REQ-dashboard-500..540）
- [ ] `App\UseCases\Dashboard\CoachDashboardViewModel` 作成（readonly class、`CoachEnrollmentRow` 子 DTO を含む）（REQ-dashboard-300..370）
- [ ] `App\UseCases\Dashboard\StudentDashboardViewModel` 作成（readonly class、`StudentEnrollmentCard` 子 DTO を含む）（REQ-dashboard-100..230）
- [ ] `App\UseCases\Dashboard\FetchAdminDashboardAction` 実装（`EnrollmentStatsService::adminKpi` / `Enrollment::pending()` / `StagnationDetectionService::detectStagnant` / `CoachActivityService::summarize` / notification 直近 5 件 を集約 + 個別 try/catch で例外境界）（REQ-dashboard-500..550, REQ-dashboard-007）
- [ ] `App\UseCases\Dashboard\FetchCoachDashboardAction` 実装（担当 Enrollment + `ProgressService::summarize` + `StagnationDetectionService::lastActivityAt` + 今日/明日 Meeting + `ChatUnreadCountService::roomCountForUser` + 担当資格 QaThread + 担当 Enrollment 集合の `WeaknessAnalysisService::getWeakCategories` 集約 + 自分担当の StagnationDetectionService + EnrollmentNote 直近 + notification 直近）（REQ-dashboard-300..380, REQ-dashboard-007）
- [ ] `App\UseCases\Dashboard\FetchStudentDashboardAction` 実装（受講中 Enrollment + 各 Enrollment への `ProgressService` / `LearningHourTargetService` / `WeaknessAnalysisService` / `CompletionEligibilityService` 呼出 + `StreakService::calculate` + EnrollmentGoal タイムライン + 今後の Meeting + notification 直近 + hasNoEnrollment 判定）（REQ-dashboard-100..240, REQ-dashboard-007）
- [ ] 本 Feature 固有のドメイン例外は **新規作成しない**（個別 Service の例外は Action 内 try/catch で吸収、画面全体 500 化を防ぐ）（REQ-dashboard-007）

## Step 5: Blade ビュー

- [ ] `resources/views/dashboard/admin.blade.php` 実装（`AdminDashboardViewModel` を受け、`_partials/admin/*` を組合せて全体構成）（REQ-dashboard-500..550）
- [ ] `resources/views/dashboard/coach.blade.php` 実装（`CoachDashboardViewModel` を受け、`_partials/coach/*` を組合せて全体構成）（REQ-dashboard-300..380）
- [ ] `resources/views/dashboard/student.blade.php` 実装（`StudentDashboardViewModel` を受け、`hasNoEnrollment` 分岐 + `_partials/student/*` を組合せて全体構成）（REQ-dashboard-100..240）
- [ ] `resources/views/dashboard/_partials/notification-list.blade.php` 実装（3 ロール共通、未読件数バッジ + 直近 5 件 + 「すべて見る」リンク）（REQ-dashboard-220, REQ-dashboard-221, REQ-dashboard-370, REQ-dashboard-540）
- [ ] `resources/views/dashboard/_partials/meeting-upcoming-list.blade.php` 実装（student / coach 共通）（REQ-dashboard-230, REQ-dashboard-231, REQ-dashboard-310）
- [ ] `resources/views/dashboard/_partials/empty-state.blade.php` 実装（icon + 文言 + CTA リンク汎用）（REQ-dashboard-006, REQ-dashboard-240, REQ-dashboard-380, REQ-dashboard-550）
- [ ] `resources/views/dashboard/_partials/kpi-tile.blade.php` 実装（admin / coach 共通の KPI 数値タイル）（REQ-dashboard-500, REQ-dashboard-320, REQ-dashboard-330）
- [ ] `resources/views/dashboard/_partials/student/enrollment-card.blade.php` 実装（試験日カウントダウン + 進捗ゲージ + 学習時間目標 + 合格可能性バンド + 弱点チップ + 修了申請ボタン状態分岐）（REQ-dashboard-100, REQ-dashboard-110, REQ-dashboard-120, REQ-dashboard-121, REQ-dashboard-130, REQ-dashboard-131, REQ-dashboard-140, REQ-dashboard-150, REQ-dashboard-151, REQ-dashboard-160..164）
- [ ] `resources/views/dashboard/_partials/student/streak-panel.blade.php` 実装（currentStreak / longestStreak / lastActiveDate + ゼロ件 empty state）（REQ-dashboard-200, REQ-dashboard-201）
- [ ] `resources/views/dashboard/_partials/student/goal-timeline.blade.php` 実装（EnrollmentGoal を Wantedly 風タイムライン、未達成優先 → 達成済降順）（REQ-dashboard-210, REQ-dashboard-211, REQ-dashboard-212）
- [ ] `resources/views/dashboard/_partials/coach/assigned-students-list.blade.php` 実装（担当受講生一覧、最終活動降順）（REQ-dashboard-300, REQ-dashboard-301, REQ-dashboard-302）
- [ ] `resources/views/dashboard/_partials/coach/chat-room-summary.blade.php` 実装（未対応件数 + 直近 5 件）（REQ-dashboard-320, REQ-dashboard-321）
- [ ] `resources/views/dashboard/_partials/coach/qa-thread-summary.blade.php` 実装（未回答件数 + 直近 5 件）（REQ-dashboard-330）
- [ ] `resources/views/dashboard/_partials/coach/weak-categories-aggregate.blade.php` 実装（担当受講生の頻出弱点カテゴリ上位 5 件）（REQ-dashboard-340）
- [ ] `resources/views/dashboard/_partials/coach/stagnation-list.blade.php` 実装（自分担当の滞留検知）（REQ-dashboard-350）
- [ ] `resources/views/dashboard/_partials/coach/enrollment-notes-recent.blade.php` 実装（最近更新のメモ + 詳細リンク）（REQ-dashboard-360）
- [ ] `resources/views/dashboard/_partials/admin/kpi-overview.blade.php` 実装（learning / paused / passed / failed / pending の 5 タイル）（REQ-dashboard-500）
- [ ] `resources/views/dashboard/_partials/admin/by-certification-breakdown.blade.php` 実装（資格別受講中人数 上位 10 件）（REQ-dashboard-501）
- [ ] `resources/views/dashboard/_partials/admin/pending-completion-list.blade.php` 実装（修了申請待ち上位 10 件 + 個別承認画面リンク）（REQ-dashboard-510, REQ-dashboard-511, REQ-dashboard-512）
- [ ] `resources/views/dashboard/_partials/admin/stagnation-list-admin.blade.php` 実装（全受講生の滞留検知）（REQ-dashboard-520）
- [ ] `resources/views/dashboard/_partials/admin/coach-activity-list.blade.php` 実装（直近 30 日 coach 稼働状況）（REQ-dashboard-530）

## Step 6: テスト

- [ ] `tests/Feature/Http/Dashboard/DashboardControllerTest.php` 作成
  - `test_guest_is_redirected_to_login`（REQ-dashboard-001）
  - `test_admin_sees_admin_blade`（REQ-dashboard-002）
  - `test_coach_sees_coach_blade`（REQ-dashboard-002）
  - `test_student_sees_student_blade`（REQ-dashboard-002）
  - `test_each_role_does_not_see_other_role_widgets`（REQ-dashboard-004、admin blade の HTML に coach / student 特有のセレクタが存在しないことを assert）
- [ ] `tests/Feature/UseCases/Dashboard/FetchAdminDashboardActionTest.php` 作成
  - `test_returns_kpi_with_correct_counts`（REQ-dashboard-500）
  - `test_pending_completion_requests_are_limited_to_10_and_eager_loaded`（REQ-dashboard-510）
  - `test_stagnant_list_uses_stagnation_detection_service`（REQ-dashboard-520）
  - `test_coach_activity_uses_coach_activity_service`（REQ-dashboard-530）
  - `test_graceful_degradation_when_service_throws`（REQ-dashboard-007）
- [ ] `tests/Feature/UseCases/Dashboard/FetchCoachDashboardActionTest.php` 作成
  - `test_only_assigned_enrollments_are_listed`（REQ-dashboard-300）
  - `test_unread_chat_count_uses_chat_unread_count_service`（REQ-dashboard-320）
  - `test_qa_unanswered_is_scoped_to_assigned_certifications`（REQ-dashboard-330）
  - `test_stagnation_list_filters_by_assigned_coach_id`（REQ-dashboard-350）
  - `test_recent_enrollment_notes_filters_by_assigned_coach`（REQ-dashboard-360）
- [ ] `tests/Feature/UseCases/Dashboard/FetchStudentDashboardActionTest.php` 作成
  - `test_only_learning_or_paused_enrollments_are_listed`（REQ-dashboard-100）
  - `test_days_until_exam_is_computed_correctly`（REQ-dashboard-110）
  - `test_completion_request_button_is_enabled_when_eligible`（REQ-dashboard-160）
  - `test_completion_request_button_is_disabled_with_reason_when_not_eligible`（REQ-dashboard-162）
  - `test_pending_request_state_shows_cancel_button`（REQ-dashboard-163）
  - `test_passed_state_shows_certificate_download_link`（REQ-dashboard-164）
  - `test_goal_timeline_orders_unachieved_first_then_recent_achieved`（REQ-dashboard-210）
  - `test_empty_enrollment_state_sets_has_no_enrollment_true`（REQ-dashboard-240）
- [ ] `tests/Feature/Http/Dashboard/DashboardSidebarConsistencyTest.php` 作成（REQ-dashboard-005, REQ-dashboard-512）
  - `test_admin_pending_count_matches_sidebar_badge`
  - `test_coach_unread_chat_count_matches_sidebar_badge`
  - `test_coach_unanswered_qa_count_matches_sidebar_badge`
- [ ] `tests/Feature/Http/Dashboard/DashboardQueryCountTest.php` 作成（NFR-dashboard-001, NFR-dashboard-002）
  - `test_admin_dashboard_uses_at_most_25_queries`
  - `test_coach_dashboard_uses_at_most_25_queries`
  - `test_student_dashboard_uses_at_most_20_queries`（受講生は受講中資格数に依存、シード固定で測定）
- [ ] `tests/Unit/UseCases/Dashboard/DashboardArchitectureTest.php` 作成（NFR-dashboard-003, NFR-dashboard-005, NFR-dashboard-008）
  - `test_no_dashboard_owned_service_exists`（`app/Services/` 配下に `Dashboard*Service.php` がゼロ）
  - `test_no_cache_facade_usage_in_dashboard_actions`（`app/UseCases/Dashboard/` 配下に `Cache::` 文字列が出現しない）
  - `test_no_dashboard_policy_or_middleware_exists`（`app/Policies/Dashboard*` / `app/Http/Middleware/Dashboard*` が存在しない）
- [ ] `tests/Unit/UseCases/Dashboard/DashboardBladeLintTest.php` 作成（NFR-dashboard-007）
  - `test_no_db_facade_usage_in_dashboard_views`（`resources/views/dashboard/**/*.blade.php` に `DB::` / `\App\Models\` の `::query()` が出現しない、ViewModel プロパティアクセスのみ）

## Step 7: 動作確認 & 整形

- [ ] `sail artisan test --filter=Dashboard` 全通過
- [ ] `sail bin pint --dirty` 整形
- [ ] ブラウザでの主要画面動作確認（通しシナリオ）:
  - admin ログイン → `/dashboard` で KPI / 修了申請待ち / 滞留 / コーチ稼働 / 通知 が表示される
  - coach ログイン → `/dashboard` で担当受講生 / 面談 / chat / QA / 弱点 / 滞留 / メモ / 通知 が表示される
  - student ログイン → `/dashboard` で受講中資格カード / ストリーク / 目標 / 通知 / 面談 が表示される
  - student が受講中ゼロの場合 → 「資格カタログから登録」CTA が表示される
  - student が公開模試すべて合格達成後に修了申請ボタンが活性、押下で `/dashboard` にリダイレクト + 「修了申請中」バッジ + 取消ボタン表示
  - student が修了承認後にアクセス → 「修了済み」バッジ + 修了証 PDF ダウンロードリンク表示
  - coach の担当外受講生は coach dashboard に表示されない
  - サイドバー `修了申請承認 (N)` バッジと admin dashboard 上の修了申請待ち件数が一致
  - サイドバー `chat 対応 (N)` バッジと coach dashboard 上の未対応 chat 件数が一致
- [ ] アクセシビリティ確認: focus-visible / aria-label / コントラスト（Chrome DevTools Lighthouse Accessibility スコア 90 以上）
- [ ] `lg:` 未満のモバイル幅で全 dashboard が縦 1 カラムに正常レイアウトされる
- [ ] N+1 確認: `sail artisan db:monitor` または `laravel-debugbar`（dev のみ）で各ロール dashboard のクエリ件数が上限内
- [ ] 個別 Service 例外境界の手動確認: `ProgressService` を意図的に例外 throw に書き換えて `/dashboard` を開き、該当ウィジェットだけ「データを取得できませんでした」表示、他は通常表示することを確認（NFR / REQ-dashboard-007）
