# mentoring タスクリスト

> 1 タスク = 1 コミット粒度。Step 単位で順次実装し、完了したものから `[x]` に更新する。
> 関連要件 ID は `requirements.md` の `REQ-mentoring-NNN` / `NFR-mentoring-NNN` を参照。
>
> **v3 改修反映**: 申請承認フロー撤回 → 自動コーチ割当、Meeting.status 3 値、meetings:auto-complete Schedule Command、meeting-quota との連携、コーチ宛のみ通知、`EnsureActiveLearning` Middleware ガード。
>
> **前提**:
> - [[auth]] / [[user-management]] / [[enrollment]] の Step 1（migration + Model）完了済（`User` / `Enrollment` モデルが存在 + `User.meeting_url` カラム / `EnsureActiveLearning` Middleware 既設）
> - [[plan-management]] の Step 1 完了済（`User.max_meetings` カラム既設）
> - [[meeting-quota]] の `MeetingQuotaService` / `ConsumeQuotaAction` / `RefundQuotaAction` / `MeetingQuotaTransaction` モデルが先行 or 並行で実装される
> - [[notification]] の `NotifyMeetingReservedAction` / `NotifyMeetingCanceledAction` / `NotifyMeetingReminderAction` のインターフェースが先行 or 並行で実装される
> - [[settings-profile]] が `/settings/availability`（CoachAvailability CRUD UI）と `/settings/profile`（`meeting_url` 編集 UI）を並行で実装する想定。本 Feature は **`CoachAvailability` Model / `CoachAvailabilityPolicy` / `meetings` + `meeting_memos` テーブル Migration のみ所有**（`users.meeting_url` Migration は **[[auth]] が所有**、D4 確定）

## Step 1: Migration & Model & Enum & Factory

- **`users.meeting_url` カラム追加 Migration は [[auth]] が所有**（D4 確定、本 Feature では作成しない、auth Step 1 完了済が前提）
- [ ] `app/Models/User.php` に `meetingsAsCoach()` / `meetingsAsStudent()` / `coachAvailabilities()` リレーション追加（[[auth]] 既存モデル拡張、`meeting_url` の `$fillable` は [[auth]] で追加済前提）
- [ ] `app/Enums/MeetingStatus.php` — backed enum **3 値** `Reserved` / `Canceled` / `Completed` + `label()` 日本語ラベル（REQ-mentoring-002、v3 で 6 → 3 値）
- [ ] `database/migrations/{date}_create_meetings_table.php` — ULID 主キー / `enrollment_id` / `coach_id` / `student_id` / `scheduled_at` / `status` / `topic` / `canceled_by_user_id` / `canceled_at` / `meeting_url_snapshot` / **`completed_at`**（v3 追加） / **`meeting_quota_transaction_id`**（v3 追加） / timestamps / softDeletes + **`(coach_id, scheduled_at)` UNIQUE 制約**（v3 改修）+ 3 つの複合 INDEX（REQ-mentoring-001, 004）
  - 旧カラム `rejected_reason` / `started_at` / `ended_at` は持たない（v3 撤回）
- [ ] `app/Models/Meeting.php` — `HasUlids` + `SoftDeletes`、`$fillable` / `$casts`（status を `MeetingStatus::class` cast、`scheduled_at` / `canceled_at` / `completed_at` を datetime）、**6 リレーション**（enrollment / coach / student / canceledBy / meetingMemo / **quotaTransaction**）、`scopeUpcoming()`（`status = reserved AND scheduled_at >= now()`）/ `scopePast()`（`status IN (canceled, completed)`）/ `scopeForCoach()` / `scopeForStudent()`（REQ-mentoring-003, 064）
- [ ] `database/factories/MeetingFactory.php` — `reserved()` / `canceled()` / `completed()` state 提供。`forCoach(User)` / `forStudent(User)` / `forEnrollment(Enrollment)` ヘルパ（v3 で旧 state 削除）
- [ ] `database/migrations/{date}_create_meeting_memos_table.php` — ULID 主キー / `meeting_id` UNIQUE FK cascade / `body` text / timestamps / softDeletes（REQ-mentoring-016）
- [ ] `app/Models/MeetingMemo.php` — `HasUlids` + `SoftDeletes`、`$fillable`、`belongsTo(Meeting)`（REQ-mentoring-017）
- [ ] `database/factories/MeetingMemoFactory.php` — `forMeeting(Meeting)` ヘルパ
- [ ] `database/migrations/{date}_create_coach_availabilities_table.php` — ULID 主キー / `coach_id` FK / `day_of_week` tinyint / `start_time` / `end_time` / `is_active` / timestamps / softDeletes + 2 つの複合 INDEX（REQ-mentoring-010, 011）
- [ ] `app/Models/CoachAvailability.php` — `HasUlids` + `SoftDeletes`、`$fillable` / `$casts`（is_active を boolean）、`belongsTo(User, 'coach_id', 'coach')`、`scopeActive()` / `scopeForDay(int $dow)`
- [ ] `database/factories/CoachAvailabilityFactory.php` — `forCoach(User)` / `active()` / `inactive()` / `monday()` / `tuesday()` ... state 提供

## Step 2: Policy

- [ ] `app/Policies/MeetingPolicy.php` — **5 メソッド**（v3 で簡素化）: `viewAny` / `view` / `create` / `cancel`（reserved 限定 + 当事者）/ `upsertMemo`（coach かつ reserved/completed）（REQ-mentoring-080）
- [ ] `app/Policies/CoachAvailabilityPolicy.php` — 5 メソッド（viewAny / view / create / update / delete）（REQ-mentoring-081）
- [ ] `app/Providers/AuthServiceProvider::$policies` に Policy 登録（または自動検出確認）

> v3 で削除した Policy メソッド: `approve` / `reject` / `start` / `complete`（自動割当 + 自動完了で不要）

## Step 3: HTTP 層

- [ ] `app/Http/Controllers/MeetingController.php` — **8 メソッド**（v3 で簡素化）: `index` / `indexAsCoach` / `show` / `create` / `store` / `cancel` / `upsertMemo` / `fetchAvailability`
- [ ] `app/Http/Requests/Meeting/IndexRequest.php` — `filter` nullable in:upcoming,past,all
- [ ] `app/Http/Requests/Meeting/IndexAsCoachRequest.php` — `filter` / `student` / `enrollment` nullable
- [ ] `app/Http/Requests/Meeting/StoreRequest.php` — `enrollment_id` / `scheduled_at`（`after:now` + **regex `:00:00$`** 毎時 00 分のみ、v3 改修） / `topic`（REQ-mentoring-020, 023, 024）
- [ ] `app/Http/Requests/Meeting/UpsertMemoRequest.php` — `body` required max:5000（REQ-mentoring-050）
- [ ] `app/Http/Requests/Meeting/AvailabilityRequest.php` — `enrollment` ulid / `date` date_format:Y-m-d（REQ-mentoring-020）
- [ ] `routes/web.php` — student グループ（`role:student + active.learning`）/ coach グループ（`role:coach`、`/coach` プレフィックス）/ 共通グループ（show / cancel）の 3 ブロック追加

> v3 で削除: `RejectRequest` / `CompleteRequest`、`approve` / `reject` / `start` / `complete` ルート

## Step 4: Action / Service / Exception

### 例外（`app/Exceptions/Mentoring/`）

- [ ] `MeetingOutOfAvailabilityException.php` — HttpException(422)（REQ-mentoring-022）
- [ ] `MeetingNoAvailableCoachException.php` — ConflictHttpException(409)（v3 新規、候補 0 名 + race condition UNIQUE 違反）
- [ ] `MeetingStatusTransitionException.php` — ConflictHttpException(409)
- [ ] `MeetingAlreadyStartedException.php` — ConflictHttpException(409)（REQ-mentoring-032）
- [ ] `InsufficientMeetingQuotaException.php` — ConflictHttpException(409)（REQ-mentoring-021、[[meeting-quota]] と共有）

> v3 で削除: `MeetingTimeSlotTakenException`（→ MeetingNoAvailableCoachException に統合） / `MeetingNotInStartWindowException`（入室手動操作撤回） / `EnrollmentCoachNotAssignedException`（資格 × N コーチ N:N で未割当エラー稀） / `MeetingMemoNotFoundException`（旧 UpdateMemoAction で必要だったが UpsertMemoAction で不要）

### Service（`app/Services/`）

- [ ] `MeetingAvailabilityService.php` — `slotsForCertification(Certification, Carbon $date): Collection`（資格コーチ集合の Union）/ `validateSlot(Certification, Carbon)`（v3 改修、coach 個別 → certification 単位）
- [ ] `CoachMeetingLoadService.php` — `leastLoadedCoach(Collection $candidates): User`（過去 30 日 completed 数最少、同数 ULID 昇順）（v3 新規、REQ-mentoring-092, 093）
- [ ] `CoachActivityService.php` — `summarize(?Carbon $from, ?Carbon $to): Collection<CoachActivitySummaryRow>`（`rejected_count` 撤回、v3 改修、REQ-mentoring-090, 091）

### Action（`app/UseCases/Meeting/`）

- [ ] `StoreAction.php`（`MeetingController::store` と一致、v3 新規、自動コーチ割当 + 面談回数消費 + 通知発火、UNIQUE 制約 race ガード）
- [ ] `CancelAction.php`（`MeetingController::cancel` と一致、reserved → canceled、面談回数返却 + 通知）
- [ ] `AutoCompleteMeetingAction.php`（v3 新規、Schedule Command から呼ばれる）
- [ ] `UpsertMemoAction.php`（v3 新規、reserved/completed 両方可）
- [ ] `IndexAction.php`（受講生用一覧）
- [ ] `IndexAsCoachAction.php`（コーチ用一覧）
- [ ] `ShowAction.php`
- [ ] `FetchAvailabilityAction.php`（資格コーチ集合 Union のスロット返却）

> v3 で削除: `StoreAction`（→ Meeting\StoreAction に統合） / `ApproveAction` / `RejectAction` / `StartAction` / `CompleteAction` / `UpdateMemoAction`（→ UpsertMemoAction に統合） / `CancelAction`（→ Meeting\CancelAction に名称変更）

## Step 5: Schedule Command

- [ ] `app/Console/Commands/Mentoring/AutoCompleteMeetingsCommand.php` — `meetings:auto-complete` signature、`AutoCompleteMeetingAction` を呼ぶ（v3 新規、REQ-mentoring-040）
- [ ] `app/Console/Kernel.php::schedule()` に `$schedule->command('meetings:auto-complete')->cron('*/15 * * * *')` 登録

> v3 で `meetings:remind` / `meetings:remind-eve` は [[notification]] が所有（本 Feature は抽出条件のみ提供）

## Step 6: Blade

- [ ] `resources/views/meetings/index.blade.php` — 受講生の面談一覧（filter タブ upcoming/past、3 値ステータスバッジ）
- [ ] `resources/views/meetings/create.blade.php` — 予約フォーム（Enrollment 選択 → 日付選択 → 空きスロット表示 → topic 入力）。「コーチは自動割当されます」案内表示
- [ ] `resources/views/meetings/show.blade.php` — 当事者共通詳細。reserved 時にキャンセルボタン + meeting_url 表示、completed 時に MeetingMemo 表示
- [ ] `resources/views/meetings/_partials/status-badge.blade.php` — 3 値バッジ
- [ ] `resources/views/meetings/_modals/cancel-confirm.blade.php` — キャンセル確認（「面談回数が返却されます」案内）
- [ ] `resources/views/coach/meetings/index.blade.php` — コーチの面談一覧
- [ ] `resources/views/coach/meetings/_memo_form.blade.php` — メモ入力フォーム（reserved/completed どちらでも表示）
- [ ] `resources/views/emails/meeting-reserved.blade.php` — コーチ宛: 「予約が入りました」Markdown Mailable
- [ ] `resources/views/emails/meeting-canceled.blade.php` — 相手方宛: 「キャンセルされました」+ canceler ロール
- [ ] `resources/views/emails/meeting-reminder.blade.php` — 双方宛: リマインド（前日 / 1 時間前）

> v3 で削除した Blade: `meeting-requested.blade.php` / `meeting-approved.blade.php` / `meeting-rejected.blade.php` / reject-form / complete-form モーダル

## Step 7: JS

- [ ] `resources/js/mentoring/slot-picker.js` — `/meetings/availability` を fetch して空きスロット描画（コーチ名は表示せず `available_coach_count` のみヒント表示）

## Step 8: Test

### Feature テスト（`tests/Feature/`）

- [ ] `tests/Feature/Http/MeetingControllerTest.php`
  - [ ] index: 受講生は自分の Meeting のみ、filter upcoming/past 動作
  - [ ] indexAsCoach: コーチは自分宛の Meeting のみ
  - [ ] show: 当事者は閲覧可、第三者は 403
  - [ ] store: 受講生のみ作成可（coach/admin は 403）
  - [ ] cancel: 当事者のみ可、reserved 限定、scheduled_at 後は 409
  - [ ] upsertMemo: coach のみ可
  - [ ] fetchAvailability: 受講生のみ、JSON 返却形式
- [ ] `tests/Feature/UseCases/Meeting/StoreActionTest.php`
  - [ ] 正常系: コーチ自動割当 + Meeting INSERT + MeetingQuotaTransaction.consumed INSERT + 通知発火
  - [ ] 残数 0 → InsufficientMeetingQuotaException (409)
  - [ ] 枠外 → MeetingOutOfAvailabilityException (422)
  - [ ] 候補コーチ 0 名 → MeetingNoAvailableCoachException (409)
  - [ ] **自動割当: 過去 30 日 completed 数最少コーチが選出される**
  - [ ] **自動割当: 同数の場合 ULID 昇順**
  - [ ] **UNIQUE 制約 race → MeetingNoAvailableCoachException に変換**
  - [ ] graduated ユーザー → 403（EnsureActiveLearning Middleware）
  - [ ] meeting_url_snapshot に選出コーチの `meeting_url` がコピーされる
  - [ ] meeting_quota_transaction_id が紐づく
- [ ] `tests/Feature/UseCases/Meeting/CancelActionTest.php`
  - [ ] 当事者（student/coach）キャンセル成功 + 面談回数 +1 返却 + 通知発火
  - [ ] `scheduled_at <= now()` → MeetingAlreadyStartedException
  - [ ] `status != reserved` → MeetingStatusTransitionException
  - [ ] 第三者 → 403
- [ ] `tests/Feature/UseCases/Meeting/AutoCompleteMeetingActionTest.php`
  - [ ] `scheduled_at + 60min` 超過の reserved が completed 遷移
  - [ ] 既に canceled/completed の Meeting はスキップ
  - [ ] 通知は発火しない
- [ ] `tests/Feature/UseCases/Meeting/UpsertMemoActionTest.php`
  - [ ] reserved / completed どちらでも作成 + 更新可
  - [ ] canceled では MeetingStatusTransitionException
  - [ ] 他コーチからは Policy で 403
- [ ] `tests/Feature/Commands/AutoCompleteMeetingsCommandTest.php`
  - [ ] 該当 Meeting すべてが completed 遷移
  - [ ] chunkById で大量データ対応
- [ ] `tests/Feature/UseCases/Meeting/FetchAvailabilityActionTest.php`
  - [ ] 担当コーチ集合の Union スロットが返る
  - [ ] 既存予約は除外
  - [ ] available_coach_count が正しい

### Unit テスト（`tests/Unit/`）

- [ ] `tests/Unit/Services/CoachMeetingLoadServiceTest.php`
  - [ ] 過去 30 日 completed 数最少コーチを選出
  - [ ] 同数の場合 ULID 昇順
  - [ ] 30 日以前の Meeting は集計対象外
  - [ ] ゼロ件コーチも考慮（LEFT JOIN or PHP 補完）
- [ ] `tests/Unit/Services/MeetingAvailabilityServiceTest.php`
  - [ ] 資格コーチ集合の Union 動作
  - [ ] 既存予約除外
  - [ ] is_active = false 枠は除外
- [ ] `tests/Unit/Services/CoachActivityServiceTest.php`
  - [ ] 期間内 completed/canceled 集計（v3 で rejected 撤回）

## Step 9: Notification 連携確認

- [ ] [[notification]] の `NotifyMeetingReservedAction` / `NotifyMeetingCanceledAction` / `NotifyMeetingReminderAction` のインターフェース最終確認
- [ ] 通知発火タイミング: `DB::afterCommit()` で送信
- [ ] コーチ宛のみ送信を `NotifyMeetingReservedAction` 内で確認（受講生宛は予約 UI で即時確認のため不要）
- [ ] `NotifyMeetingCanceledAction` の actor ロール文面分岐確認

## Step 10: meeting-quota 連携確認

- [ ] [[meeting-quota]] の `MeetingQuotaService::remaining` を `Meeting\StoreAction` 冒頭で呼ぶ
- [ ] [[meeting-quota]] の `ConsumeQuotaAction` を `Meeting\StoreAction` 内で呼ぶ
- [ ] [[meeting-quota]] の `RefundQuotaAction` を `Meeting\CancelAction` 内で呼ぶ
- [ ] DB::transaction 内で連携、片方失敗で全体ロールバック

## Step 10.5: Factory + Seeder

- [ ] `database/factories/MeetingFactory.php`(status 網羅 state: `reserved()` / `canceled()` / `completed()` + `inPast()` / `inFuture()` の時系列 state)
- [ ] CoachAvailabilityFactory は [[settings-profile]] 所有のため、本 Feature では参照のみ
- [ ] **`database/seeders/MentoringSeeder.php`** — `structure.md` Seeder 規約「③ 派生・運用系」分類、コーチ受講生双方の動線網羅:
  - **CoachAvailability 投入**(自動コーチ割当の動作確認用):
    - `coach@certify-lms.test` に翌週分の平日 19:00〜21:00
    - `coach2@certify-lms.test` に翌週分の週末 10:00〜12:00 / 14:00〜16:00
    - その他 Factory 生成 coach にも数件
  - **Meeting status 網羅**(受講生 / コーチ視点の一覧 + 集計用):
    - reserved × 数件(今後数日以内、当日分も含む)
    - completed × 数件(過去日、面談履歴 + meeting_quota 消費済)
    - canceled × 数件(受講生キャンセル + コーチキャンセル両方、refund 動作確認用)
  - **固定参照**: `student@certify-lms.test` に reserved × 1 件 + completed × 1 件
- [ ] `DatabaseSeeder::run()` に `MentoringSeeder::class` を `EnrollmentSeeder` の **後** に登録

## Step 11: lang ファイル + 共通リソース

- [ ] `lang/ja/mentoring.php` — エラーメッセージ + Blade ラベル日本語集約
- [ ] `lang/ja/validation.php` の attribute 追加（scheduled_at / topic / body 等）
