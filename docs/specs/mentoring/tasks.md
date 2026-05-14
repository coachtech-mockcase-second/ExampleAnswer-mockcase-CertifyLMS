# mentoring タスクリスト

> 1タスク = 1コミット粒度。Step 単位で順次実装し、完了したものから `[x]` に更新する。
> 関連要件 ID は `requirements.md` の `REQ-mentoring-NNN` / `NFR-mentoring-NNN` を参照。
>
> **前提**:
> - [[auth]] / [[user-management]] / [[enrollment]] の Step 1（migration + Model）完了済み（`User` / `Enrollment` モデルが存在）
> - [[notification]] の `NotifyMeetingRequestedAction` / `NotifyMeetingApprovedAction` / `NotifyMeetingRejectedAction` / `NotifyMeetingCanceledAction` / `NotifyMeetingReminderAction` の **インターフェース** が先行か並行で実装されること。本 Feature の Action は constructor injection でこれらを使う
> - [[settings-profile]] が `/settings/availability` の編集 UI（CoachAvailability CRUD）+ `/settings/profile` の `meeting_url` 編集 UI を **並行で実装** する想定。本 Feature は **Model / Policy / migration**（含む `add_meeting_url_to_users_table`）のみ所有
> - `product.md` の Meeting state diagram に `requested → canceled: 受講生が取り下げ` 追記が Phase 0 合意済 → 本 Feature 実装と同じコミット系列で `docs/steering/product.md` も更新する

## Step 1: Migration & Model & Enum & Factory

- [ ] `database/migrations/{date}_add_meeting_url_to_users_table.php` — `users.meeting_url` string nullable カラム追加（REQ-mentoring-014）
- [ ] `app/Models/User.php` に `meeting_url` を `$fillable` 追加 / `meetingsAsCoach()` / `meetingsAsStudent()` / `coachAvailabilities()` リレーション追加（[[auth]] 既存モデル拡張、REQ-mentoring-090, 091）
- [ ] `app/Enums/MeetingStatus.php` — backed enum `Requested` / `Approved` / `Rejected` / `Canceled` / `InProgress` / `Completed` + `label()`（REQ-mentoring-002）
- [ ] `database/migrations/{date}_create_meetings_table.php` — ULID 主キー / `enrollment_id` / `coach_id` / `student_id` / `scheduled_at` / `status` / `topic` / `rejected_reason` / `canceled_by_user_id` / `canceled_at` / `meeting_url_snapshot` / `started_at` / `ended_at` / timestamps / softDeletes + 4 つの複合 INDEX（REQ-mentoring-001, 004）
- [ ] `app/Models/Meeting.php` — `HasUlids` + `SoftDeletes`、`$fillable` / `$casts`（status を Enum cast、scheduled_at/canceled_at/started_at/ended_at を datetime cast）、5 リレーション（enrollment / coach / student / canceledBy / meetingMemo）、`scopeUpcoming()` / `scopePast()` / `scopeForCoach()` / `scopeForStudent()`（REQ-mentoring-003, 064）
- [ ] `database/factories/MeetingFactory.php` — `requested()` / `approved()` / `rejected()` / `canceled()` / `inProgress()` / `completed()` state 提供。`forCoach(User)` / `forStudent(User)` / `forEnrollment(Enrollment)` ヘルパ
- [ ] `database/migrations/{date}_create_meeting_memos_table.php` — ULID 主キー / `meeting_id` UNIQUE FK cascade / `body` text / timestamps / softDeletes（REQ-mentoring-015）
- [ ] `app/Models/MeetingMemo.php` — `HasUlids` + `SoftDeletes`、`$fillable`、`belongsTo(Meeting)`（REQ-mentoring-016）
- [ ] `database/factories/MeetingMemoFactory.php` — `forMeeting(Meeting)` ヘルパ
- [ ] `database/migrations/{date}_create_coach_availabilities_table.php` — ULID 主キー / `coach_id` FK / `day_of_week` tinyint / `start_time` / `end_time` / `is_active` / timestamps / softDeletes + 2 つの複合 INDEX（REQ-mentoring-010, 011）
- [ ] `app/Models/CoachAvailability.php` — `HasUlids` + `SoftDeletes`、`$fillable` / `$casts`（is_active を boolean、start_time/end_time を string で扱う or `datetime:H:i:s`）、`belongsTo(User, 'coach_id', 'coach')`、`scopeActive()` / `scopeForDay(int $dow)`
- [ ] `database/factories/CoachAvailabilityFactory.php` — `forCoach(User)` / `active()` / `inactive()` / `monday()` / `tuesday()` ... state 提供
- [ ] `docs/steering/product.md` の Meeting state diagram に `requested --> canceled: 受講生が取り下げ` 行を追記（Phase 0 合意、REQ-mentoring-040）

## Step 2: Policy

- [ ] `app/Policies/MeetingPolicy.php` — `viewAny` / `view` / `create` / `cancel`（status 分岐）/ `approve` / `reject` / `start` / `complete` / `updateMemo`（9 メソッド、REQ-mentoring-080）
- [ ] `app/Policies/CoachAvailabilityPolicy.php` — `viewAny` / `view` / `create` / `update` / `delete`（5 メソッド、REQ-mentoring-081）
- [ ] `app/Providers/AuthServiceProvider::$policies` に `Meeting::class => MeetingPolicy::class` / `CoachAvailability::class => CoachAvailabilityPolicy::class` を登録（または自動検出確認）

## Step 3: HTTP 層

- [ ] `app/Http/Controllers/MeetingController.php` — 12 メソッド（`index` / `indexAsCoach` / `show` / `create` / `store` / `cancel` / `approve` / `reject` / `start` / `complete` / `updateMemo` / `fetchAvailability`）、各メソッドは同名 Action を呼ぶ薄いラッパー（`backend-usecases.md`「Controller method 名 = Action クラス名」準拠、`create` のみ Blade view を返す薄いハンドラ）
- [ ] `app/Http/Requests/Meeting/IndexRequest.php` — `filter` nullable（REQ-mentoring-060）
- [ ] `app/Http/Requests/Meeting/IndexAsCoachRequest.php` — `filter` / `student` / `enrollment` nullable（REQ-mentoring-061）
- [ ] `app/Http/Requests/Meeting/StoreRequest.php` — `enrollment_id` / `scheduled_at`（`after:now` + regex `:00\|:30`）/ `topic`（REQ-mentoring-020, 023, 024）
- [ ] `app/Http/Requests/Meeting/RejectRequest.php` — `rejected_reason` required max:500（REQ-mentoring-031）
- [ ] `app/Http/Requests/Meeting/CompleteRequest.php` — `body` required max:5000（REQ-mentoring-052）
- [ ] `app/Http/Requests/Meeting/UpdateMemoRequest.php` — `body` required max:5000（REQ-mentoring-053）
- [ ] `app/Http/Requests/Meeting/AvailabilityRequest.php` — `enrollment` ulid / `date` date_format:Y-m-d（REQ-mentoring-026）
- [ ] `routes/web.php` — student グループ（`role:student`）/ coach グループ（`role:coach`、`/coach` プレフィックス）/ 共通グループ（show / cancel）の 3 ブロック追加（REQ-mentoring-020, 030, 031, 040, 042, 050, 052, 053, 060, 061, 062）

## Step 4: Action / Service / Exception

### 例外（`app/Exceptions/Mentoring/`）

- [ ] `MeetingOutOfAvailabilityException.php` — HttpException(422)（REQ-mentoring-022）
- [ ] `MeetingTimeSlotTakenException.php` — ConflictHttpException(409)（REQ-mentoring-027, 033）
- [ ] `MeetingStatusTransitionException.php` — ConflictHttpException(409)（REQ-mentoring-032, 041）
- [ ] `MeetingNotInStartWindowException.php` — ConflictHttpException(409)（REQ-mentoring-051）
- [ ] `MeetingAlreadyStartedException.php` — ConflictHttpException(409)（REQ-mentoring-043）
- [ ] `EnrollmentCoachNotAssignedException.php` — ConflictHttpException(409)（REQ-mentoring-025）
- [ ] `MeetingMemoNotFoundException.php` — NotFoundHttpException(404)（REQ-mentoring-053）

### Service

- [ ] `app/Services/MeetingAvailabilityService.php` — `slotsForDate(User $coach, Carbon $date): Collection` / `validateSlot(User $coach, Carbon $scheduled_at): void`。CoachAvailability + 既存 Meeting の差集合で空き枠を算出（REQ-mentoring-026, 021, NFR-mentoring-007）
- [ ] `app/Services/CoachActivityService.php` — `summarize(?Carbon $from, ?Carbon $to): Collection`。30 日デフォルト、`withCount` で completed/canceled/rejected 集計（REQ-mentoring-090）

### Action

- [ ] `app/UseCases/Meeting/IndexAction.php` — `__invoke(User $student, ?string $filter, int $perPage = 20): LengthAwarePaginator`（REQ-mentoring-060）
- [ ] `app/UseCases/Meeting/IndexAsCoachAction.php` — `__invoke(User $coach, ?string $filter, ?string $studentId, ?string $enrollmentId, int $perPage = 20): LengthAwarePaginator`（REQ-mentoring-061）
- [ ] `app/UseCases/Meeting/ShowAction.php` — `__invoke(Meeting $meeting): Meeting`、eager load `with(['coach', 'student', 'enrollment.certification', 'meetingMemo'])`（REQ-mentoring-062, 063）
- [ ] `app/UseCases/Meeting/StoreAction.php` — `__invoke(User $student, array $validated): Meeting`。`MeetingAvailabilityService::validateSlot` + 衝突 FOR UPDATE + `NotifyMeetingRequestedAction` DI（REQ-mentoring-021, 027, NFR-mentoring-001, 003）
- [ ] `app/UseCases/Meeting/CancelAction.php` — `__invoke(Meeting $meeting, User $actor): Meeting`。requested / approved 分岐 + `NotifyMeetingCanceledAction` DI（REQ-mentoring-040, 042, 043）
- [ ] `app/UseCases/Meeting/ApproveAction.php` — `__invoke(Meeting $meeting, User $coach): Meeting`。`meeting_url_snapshot = $coach->meeting_url` 焼き込み + race 再検査 + `NotifyMeetingApprovedAction` DI（REQ-mentoring-030, 033）
- [ ] `app/UseCases/Meeting/RejectAction.php` — `__invoke(Meeting $meeting, string $rejectedReason): Meeting`。`NotifyMeetingRejectedAction` DI（REQ-mentoring-031）
- [ ] `app/UseCases/Meeting/StartAction.php` — `__invoke(Meeting $meeting): Meeting`。入室窓検証（REQ-mentoring-050, 051）
- [ ] `app/UseCases/Meeting/CompleteAction.php` — `__invoke(Meeting $meeting, string $memoBody): Meeting`。MeetingMemo INSERT + status=Completed UPDATE を単一トランザクションで（REQ-mentoring-052）
- [ ] `app/UseCases/Meeting/UpdateMemoAction.php` — `__invoke(Meeting $meeting, string $memoBody): MeetingMemo`（REQ-mentoring-053）
- [ ] `app/UseCases/Meeting/FetchAvailabilityAction.php` — `__invoke(Enrollment $enrollment, Carbon $date): Collection`。`MeetingAvailabilityService::slotsForDate` を呼び `EnrollmentCoachNotAssignedException` を投げる（REQ-mentoring-026）

## Step 5: Schedule Command

> 本 Feature では Reminder 系の Schedule Command を **所有しない**。`SendMeetingRemindersCommand` / `NotifyMeetingReminderAction(Meeting, MeetingReminderWindow)` / `MeetingReminderWindow` Enum は [[notification]] が所有する（[[notification]] tasks.md Step 9 で実装）。本 Feature は Meeting 抽出条件と window 引数の契約共有のみを担う（REQ-mentoring-071, 072）。

## Step 6: Blade ビュー & JS & Email テンプレ

- [ ] `resources/views/meetings/index.blade.php` — student 一覧（tabs + table + paginator + empty-state、REQ-mentoring-060）
- [ ] `resources/views/meetings/create.blade.php` — 予約申請フォーム（Enrollment select + date input + 空き枠 select + topic textarea）
- [ ] `resources/views/meetings/show.blade.php` — 当事者共通詳細（status バッジ + 詳細カード + 状態別操作ボタン群 + MeetingMemo 表示部）（REQ-mentoring-062, 063, 054）
- [ ] `resources/views/meetings/_partials/status-badge.blade.php` — `<x-badge>` ラッパ
- [ ] `resources/views/meetings/_modals/reject-form.blade.php` — 拒否理由入力モーダル（REQ-mentoring-031）
- [ ] `resources/views/meetings/_modals/complete-form.blade.php` — 完了+メモ入力モーダル（REQ-mentoring-052）
- [ ] `resources/views/meetings/_modals/cancel-confirm.blade.php` — キャンセル確認モーダル
- [ ] `resources/views/coach/meetings/index.blade.php` — coach 一覧（REQ-mentoring-061）
- [ ] `resources/js/mentoring/availability-picker.js` — `/meetings/availability` を fetch → 60 分スロット一覧を `<select>` または `<button>` 群で動的描画。日付 input 変更時に再 fetch（REQ-mentoring-026）
- [ ] `resources/views/emails/meeting-requested.blade.php` — Markdown Mailable（コーチ宛、REQ-mentoring-070）
- [ ] `resources/views/emails/meeting-approved.blade.php` — Markdown Mailable（受講生宛、`meeting_url_snapshot` 埋め込み、REQ-mentoring-073）
- [ ] `resources/views/emails/meeting-rejected.blade.php` — Markdown Mailable（受講生宛、`rejected_reason` 表示、REQ-mentoring-070）
- [ ] `resources/views/emails/meeting-canceled.blade.php` — Markdown Mailable（相手方宛、canceler ロール表示、REQ-mentoring-070）
- [ ] `resources/views/emails/meeting-reminder.blade.php` — Markdown Mailable（双方宛、window: 1-hour-before / eve で文面分岐、REQ-mentoring-071, 072）
- [ ] `lang/ja/mentoring.php` — 例外メッセージ / 通知件名 / Blade ラベル / 曜日ラベル（NFR-mentoring-006）

## Step 7: テスト

### Feature テスト（`tests/Feature/Http/Meeting/`）

- [ ] `IndexTest.php`
  - `test_student_sees_only_own_meetings`（REQ-mentoring-060, 080）
  - `test_filter_upcoming_excludes_completed_canceled_rejected`
  - `test_filter_past_includes_completed_canceled_rejected`
- [ ] `IndexAsCoachTest.php`
  - `test_coach_sees_only_own_meetings`（REQ-mentoring-061, 080）
  - `test_filter_by_student_narrows_results`
  - `test_filter_by_enrollment_narrows_results`
- [ ] `ShowTest.php`
  - `test_student_can_view_own_meeting`（REQ-mentoring-062, 080）
  - `test_coach_can_view_assigned_meeting`
  - `test_other_student_cannot_view_meeting_returns_403`
  - `test_other_coach_cannot_view_meeting_returns_403`
  - `test_admin_can_view_any_meeting`
  - `test_completed_meeting_shows_memo_to_student`（REQ-mentoring-063）
- [ ] `StoreTest.php`
  - `test_student_can_request_meeting_in_availability`（REQ-mentoring-021）
  - `test_request_outside_availability_returns_422_with_out_of_availability_message`（REQ-mentoring-022）
  - `test_request_with_non_30min_aligned_scheduled_at_returns_422`（REQ-mentoring-023）
  - `test_request_with_past_scheduled_at_returns_422`（REQ-mentoring-024）
  - `test_request_for_enrollment_without_assigned_coach_returns_409`（REQ-mentoring-025）
  - `test_request_for_already_taken_slot_returns_409`（REQ-mentoring-027）
  - `test_coach_cannot_create_meeting_returns_403`（REQ-mentoring-080 create）
  - `test_notification_dispatched_to_coach`（REQ-mentoring-070）
- [ ] `CancelTest.php`
  - `test_student_can_cancel_own_requested_meeting`（REQ-mentoring-040）
  - `test_student_can_cancel_own_approved_meeting_before_scheduled_at`（REQ-mentoring-042）
  - `test_coach_can_cancel_assigned_approved_meeting`
  - `test_cancel_approved_meeting_after_scheduled_at_returns_409`（REQ-mentoring-043）
  - `test_cancel_rejected_meeting_returns_409`（REQ-mentoring-041, 032）
  - `test_other_user_cannot_cancel_meeting_returns_403`
- [ ] `ApproveTest.php`
  - `test_coach_can_approve_requested_meeting_and_url_is_snapshotted`（REQ-mentoring-030）
  - `test_approve_already_approved_meeting_returns_409`（REQ-mentoring-032）
  - `test_approve_when_other_meeting_at_same_time_exists_returns_409`（REQ-mentoring-033）
  - `test_other_coach_cannot_approve_meeting_returns_403`
  - `test_notification_dispatched_to_student_with_url`（REQ-mentoring-070, 073）
- [ ] `RejectTest.php`
  - `test_coach_can_reject_requested_meeting_with_reason`（REQ-mentoring-031）
  - `test_reject_without_reason_returns_422`
  - `test_reject_approved_meeting_returns_409`（REQ-mentoring-032）
  - `test_notification_dispatched_to_student_with_reason`
- [ ] `StartTest.php`
  - `test_coach_can_start_meeting_in_window`（REQ-mentoring-050）
  - `test_start_outside_window_returns_409`（REQ-mentoring-051）
  - `test_start_requested_meeting_returns_409`
- [ ] `CompleteTest.php`
  - `test_coach_can_complete_in_progress_with_memo`（REQ-mentoring-052）
  - `test_complete_without_body_returns_422`
  - `test_complete_approved_returns_409`
  - `test_meeting_memo_is_created`
- [ ] `UpdateMemoTest.php`
  - `test_coach_can_update_memo_of_completed_meeting`（REQ-mentoring-053）
  - `test_update_memo_of_in_progress_returns_403`（REQ-mentoring-080 updateMemo）
  - `test_other_coach_cannot_update_memo`
- [ ] `AvailabilityTest.php`
  - `test_returns_60min_slots_within_active_availabilities`（REQ-mentoring-026）
  - `test_excludes_already_booked_slots`
  - `test_inactive_availability_is_excluded`
  - `test_enrollment_without_assigned_coach_returns_409`

### Unit テスト

- [ ] `tests/Unit/Services/MeetingAvailabilityServiceTest.php`
  - `test_slots_for_date_expands_availability_into_60min_slots`（REQ-mentoring-026）
  - `test_slots_excludes_requested_approved_in_progress_meetings`
  - `test_validate_slot_throws_out_of_availability_when_outside_range`
  - `test_validate_slot_throws_time_slot_taken_when_conflict_exists`
- [ ] `tests/Unit/Services/CoachActivityServiceTest.php`
  - `test_summarize_returns_per_coach_completed_canceled_rejected_counts`（REQ-mentoring-090）
  - `test_summarize_respects_from_to_date_range`
- [ ] `tests/Unit/Policies/MeetingPolicyTest.php`
  - 9 メソッド × ロール網羅（admin / coach 当事者 / coach 他 / student 当事者 / student 他）（REQ-mentoring-080）
- [ ] `tests/Unit/Policies/CoachAvailabilityPolicyTest.php`
  - 5 メソッド × ロール網羅（REQ-mentoring-081）
- [ ] `tests/Feature/UseCases/Meeting/StoreActionTest.php`
  - `test_dispatches_notify_meeting_requested_action`（REQ-mentoring-070 mock）
- [ ] `tests/Feature/UseCases/Meeting/ApproveActionTest.php`
  - `test_snapshots_coach_meeting_url_at_approve_time`（REQ-mentoring-030）
  - `test_snapshot_is_null_when_coach_meeting_url_is_null`

### Schedule Command テスト

> Schedule Command は [[notification]] が所有するため、本 Feature では Reminder Command 単体テストを書かない。`SendMeetingRemindersCommandTest`（前日範囲 / 1h 前範囲の Meeting 抽出 + window 別の通知発火 + 重複排除）は [[notification]] tasks.md Step 12 で実装される。

## Step 8: 動作確認 & 整形

- [ ] `sail artisan test --filter=Meeting` 通過
- [ ] `sail artisan test --filter=CoachAvailability` 通過
- [ ] `sail artisan test --filter=Mentoring` 通過
- [ ] `sail bin pint --dirty` で整形
- [ ] ブラウザで通しシナリオ確認（Mailpit http://localhost:8025 で通知メール、phpMyAdmin http://localhost:8080 で DB 状態を併せて確認）:
  1. `sail artisan tinker` で coach の `users.meeting_url = 'https://meet.example.com/coach-a'` を仮設定（編集 UI は [[settings-profile]] が並行実装）
  2. 同じく `CoachAvailability::create([...])` で月曜 09:00-12:00 の枠を仮投入
  3. student で `/meetings/create` → Enrollment 選択 → 翌週月曜 10:00 を選択 → 申請送信 → `/meetings` 一覧に requested で表示
  4. Mailpit で coach 宛 `MeetingRequestedMail` を受信
  5. coach で `/coach/meetings` → 該当 Meeting → 承認 → 受講生宛 `MeetingApprovedMail` に `meeting_url_snapshot` が埋め込まれていることを Mailpit で確認 + phpMyAdmin で `meetings.meeting_url_snapshot` カラムに URL が入っていること
  6. student で詳細閲覧 → URL 表示確認
  7. `sail artisan meetings:remind` 手動実行 → 該当 Meeting が 1 時間以内なら通知発火、重複実行で二重送信されないことを確認
  8. coach で「入室」ボタン押下（`scheduled_at` を手動で `now()` 近辺に書き換えて検証）→ `status=in_progress` 遷移
  9. coach で「完了」+ メモ入力 → `status=completed` + `meeting_memos` に行挿入 + student 詳細でメモ閲覧可
  10. 別シナリオ: requested 状態を student 自身が取り下げ → `status=canceled` / `canceled_by_user_id=student->id`
  11. 別シナリオ: approved 状態を coach がキャンセル → 受講生宛 `MeetingCanceledMail` + 文面に「コーチがキャンセル」表示
  12. 異常系: 枠外の `scheduled_at` で申請 → 422 + 「面談可能時間外」フラッシュ
  13. 異常系: 同 coach × 同時刻に 2 件目を申請 → 409 + 「既に予約が入っています」フラッシュ
  14. 異常系: rejected 状態の Meeting を承認しようとする → 409
- [ ] PR 動作確認の動画に上記主要シナリオ + 状態遷移バッジ更新 + 通知メール表示を収録（動的機能のため動画必須）
- [ ] `docs/steering/product.md` の Meeting state diagram 更新分（`requested --> canceled`）を同 PR の diff に含めることを確認
