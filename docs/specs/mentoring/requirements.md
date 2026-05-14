# mentoring 要件定義

## 概要

Certify LMS の 1on1 面談（Mentoring）Feature。コーチが事前に登録した **面談可能時間枠**（`CoachAvailability`、曜日 × 開始時刻 × 終了時刻の繰り返し枠）と **固定面談 URL**（`users.meeting_url`、coach 自身が [[settings-profile]] で編集）を基盤に、受講生が **60 分単位** で予約申請 → コーチが承認 / 拒否 → 当日実施 → コーチが面談メモ記録 → 履歴閲覧、という一連のフローを担う。

主モデルは `Meeting` / `MeetingMemo` / `CoachAvailability` の 3 つ。`Meeting` の状態遷移は `product.md` の state diagram（`requested` / `approved` / `rejected` / `canceled` / `in_progress` / `completed`、`requested → canceled` 追加版）に厳格準拠する。本 Feature は状態変化のたびに [[notification]] feature 経由で当事者へ Database + Mail 二段通知を発火し、`dashboard` に「今日の面談」「コーチ稼働状況」を集計提供する。

Basic では Google Calendar 連携なし・繰り返し枠の手動登録のみ。`CoachAvailability` モデル定義と空き枠取得は本 Feature が所有するが、**編集 UI**（`/settings/availability`）は [[settings-profile]] が所有する（責務分離、`product.md`「ロール共通画面の責務分担」準拠）。

## ロールごとのストーリー

- **受講生（student）**: 自分が登録している資格（[[enrollment]] `Enrollment.assigned_coach_id`）の担当コーチに対し、コーチ側の有効な `CoachAvailability` 枠内の任意 60 分スロットで予約を申請する。話したい内容を `topic` に記入する。申請後はコーチの承認 / 拒否を待ち、承認されればコーチの固定面談 URL（メール本文 + 詳細画面）で当日入室する。`requested` のうちは自分で取り下げでき、`approved` 後は面談開始時刻までキャンセル可能。実施後はコーチが記録した面談メモを閲覧できる。過去の面談履歴を時系列で閲覧する。
- **コーチ（coach）**: 自分宛の `requested` 一覧を担当受講生別に確認し、承認 / 拒否（拒否時は理由必須）する。承認済面談は面談開始時刻までキャンセル可能。当日は「入室」ボタンで `in_progress` に遷移、面談終了後にメモ本文を書いて `completed` に遷移する。自分が担当した過去の面談履歴を時系列で閲覧する。`CoachAvailability` の繰り返し枠と固定面談 URL は [[settings-profile]] で編集する（本 Feature は読み取り側）。
- **管理者（admin）**: 本 Feature の直接操作はない（サイドバーにメニュー無し、`product.md` 動線準拠）。ただし [[dashboard]] のコーチ稼働状況パネル（`CoachActivityService` 集計）と、`users.meeting_url` を含むユーザー詳細閲覧（[[user-management]] 経由）から面談データを参照する。

## 受け入れ基準（EARS形式）

### 機能要件 — Meeting モデルと基盤

- **REQ-mentoring-001**: The system shall ULID 主キー / `enrollment_id`（`enrollments.id` への外部キー）/ `coach_id`（`users.id` への外部キー、`role=coach`）/ `student_id`（`users.id` への外部キー、`role=student`、`Enrollment.user_id` と冗長一致）/ `scheduled_at` datetime（開始時刻、終了時刻は常に `scheduled_at + 60 分`）/ `status` enum（`requested` / `approved` / `rejected` / `canceled` / `in_progress` / `completed`）/ `topic` text（受講生入力の話題）/ `rejected_reason` text nullable / `canceled_by_user_id` ulid nullable（`users.id` への外部キー、`SET NULL`）/ `canceled_at` datetime nullable / `meeting_url_snapshot` string nullable（承認時に `coach.meeting_url` を焼き込む）/ `started_at` datetime nullable / `ended_at` datetime nullable / timestamps / softDeletes を備えた `meetings` テーブルを提供する。
- **REQ-mentoring-002**: The system shall `MeetingStatus` PHP backed enum（string、`Requested` / `Approved` / `Rejected` / `Canceled` / `InProgress` / `Completed`）を公開し、`label()` メソッドで日本語表示ラベル（`予約申請中` / `承認済` / `拒否` / `キャンセル` / `面談中` / `完了`）を返す。状態遷移は `product.md` の Meeting state diagram + 本 Feature が追加する `requested → canceled`（受講生取り下げ）パスに厳格準拠する。
- **REQ-mentoring-003**: The `Meeting` model shall `belongsTo(Enrollment::class)` / `belongsTo(User::class, 'coach_id', 'coach')` / `belongsTo(User::class, 'student_id', 'student')` / `belongsTo(User::class, 'canceled_by_user_id', 'canceledBy')` / `hasOne(MeetingMemo::class)` の 5 リレーションを公開する。
- **REQ-mentoring-004**: The `meetings` テーブル shall `(coach_id, scheduled_at)` 複合 INDEX / `(student_id, scheduled_at)` 複合 INDEX / `(enrollment_id)` INDEX / `(status, scheduled_at)` 複合 INDEX を備える（衝突検知 / 受講生別履歴 / 当日リマインドの Schedule Command クエリ最適化）。

### 機能要件 — CoachAvailability モデルと固定面談 URL

- **REQ-mentoring-010**: The system shall ULID 主キー / `coach_id`（`users.id` への外部キー、`role=coach`）/ `day_of_week` tinyInteger（0=日曜, 6=土曜、ISO ではなく Carbon 標準 `dayOfWeek`）/ `start_time` time / `end_time` time / `is_active` boolean default true / timestamps / softDeletes を備えた `coach_availabilities` テーブルを提供する。
- **REQ-mentoring-011**: The system shall `(coach_id, day_of_week)` INDEX / `(coach_id, is_active)` INDEX を備える（受講生の予約画面が「該当コーチの全曜日有効枠」を一括取得する用途）。
- **REQ-mentoring-012**: The system shall 1 コーチ × 1 曜日に **複数の枠を許容する**（例: 月曜 09:00-12:00 と 月曜 14:00-17:00 を両方登録可）。`(coach_id, day_of_week, start_time)` の UNIQUE 制約は採用しない。
- **REQ-mentoring-013**: The `coach_availabilities` テーブル shall `end_time > start_time` をアプリケーション層（FormRequest）で保証する（DB CHECK 制約は MySQL 8 で書けるが、MySQL 5.7 互換性を考慮しコード側で担保）。**日跨ぎ枠は許容しない**（`start_time < end_time` の同日範囲のみ）。
- **REQ-mentoring-014**: The system shall `users` テーブルに `meeting_url` string nullable カラムを追加する（[[settings-profile]] が編集 UI を所有、本 Feature と [[notification]] が読み取り側）。`role=coach` 以外でも DB レベルでは NULL を許容するが、UI / API レベルでは coach のみ編集可。

### 機能要件 — MeetingMemo モデル

- **REQ-mentoring-015**: The system shall ULID 主キー / `meeting_id`（`meetings.id` への外部キー、UNIQUE、cascadeOnDelete）/ `body` text / timestamps / softDeletes を備えた `meeting_memos` テーブルを提供する（1 Meeting : 1 MeetingMemo）。
- **REQ-mentoring-016**: The `MeetingMemo` model shall `belongsTo(Meeting::class)` リレーションを公開する。author は `meeting.coach_id` で一意に決まるため別カラムは持たない（記述者は常にコーチ）。

### 機能要件 — 受講生による予約申請（requested 化）

- **REQ-mentoring-020**: When 受講生が `POST /meetings` で予約申請する際, the system shall リクエストに `enrollment_id`（必須、ulid）/ `scheduled_at`（必須、datetime、未来日時、分単位は `:00` または `:30`）/ `topic`（必須、string max 1000）を要求する。
- **REQ-mentoring-021**: The system shall 単一トランザクション内で (1) `Enrollment.user_id === auth()->id()` の所有確認、(2) `Enrollment.assigned_coach_id` 取得（NULL ならドメイン例外）、(3) `scheduled_at` が `Enrollment.assigned_coach` の有効な `CoachAvailability` 枠内であることの検証、(4) 同コーチ × 同 `scheduled_at` で `status ∈ {requested, approved, in_progress}` の Meeting が存在しないことの検証、(5) `Meeting` 行を `status = requested` で INSERT、(6) [[notification]] の `NotifyMeetingRequestedAction` 呼出（coach へ DB + Mail 通知）を行う。
- **REQ-mentoring-022**: If `scheduled_at` が `CoachAvailability` の有効枠範囲外の場合, then the system shall ドメイン例外 `MeetingOutOfAvailabilityException`（HTTP 422）で拒否する。
- **REQ-mentoring-023**: If `scheduled_at` の分単位が `:00` / `:30` 以外の場合, then the system shall FormRequest バリデーションエラー（日本語メッセージ）で拒否する。
- **REQ-mentoring-024**: If `scheduled_at` が現在時刻以前の場合, then the system shall FormRequest バリデーションエラーで拒否する（`after:now` ルール）。
- **REQ-mentoring-025**: If `Enrollment.assigned_coach_id` が NULL の場合（未割当）, then the system shall ドメイン例外 `EnrollmentCoachNotAssignedException`（HTTP 409）で拒否する。
- **REQ-mentoring-026**: The system shall `GET /meetings/availability?enrollment={ulid}&date={YYYY-MM-DD}` で、指定 Enrollment の担当コーチの **指定日における 60 分単位の空き開始時刻リスト**（`[{slot_start: "2026-05-20T10:00:00+09:00", slot_end: "2026-05-20T11:00:00+09:00"}, ...]`）を JSON 返却する。算出は `CoachAvailability.day_of_week = 該当曜日 AND is_active = true` の枠を 60 分刻みに展開し、同コーチの `status ∈ {requested, approved, in_progress}` の `scheduled_at` を除外する。
- **REQ-mentoring-027**: The system shall 受講生による予約申請の **同時刻重複検査をコーチ単位** で行う（同コーチが同 `scheduled_at` で複数受講生から `requested` を受けることも防ぐ。先着のみ受付、後着は REQ-mentoring-022 の経路ではなく `MeetingTimeSlotTakenException`（HTTP 409）で拒否）。

### 機能要件 — 受講生による取り下げ（requested → canceled）

- **REQ-mentoring-040**: When 受講生が自分の `requested` 状態の Meeting に対し `POST /meetings/{meeting}/cancel` を実行した際, the system shall 単一トランザクション内で (1) `Meeting.status === Requested` の検証、(2) Meeting を `status = Canceled` / `canceled_by_user_id = $student->id` / `canceled_at = now()` に UPDATE、(3) [[notification]] の `NotifyMeetingCanceledAction` 呼出（coach へ「申請取り下げ」通知）を行う。
- **REQ-mentoring-041**: If Meeting が `requested` 以外の状態で受講生取り下げ API が呼ばれた場合, then the system shall ドメイン例外 `MeetingStatusTransitionException`（HTTP 409）で拒否する。

### 機能要件 — コーチによる承認・拒否

- **REQ-mentoring-030**: When コーチが `POST /meetings/{meeting}/approve` で承認する際, the system shall 単一トランザクション内で (1) `Meeting.status === Requested` の検証、(2) `Meeting.coach_id === auth()->id()` の所有確認（Policy で行う、Action はデータ整合性チェックのみ）、(3) 同コーチ × 同 `scheduled_at` で `status ∈ {approved, in_progress}` の他 Meeting 不在の再検査（race condition 防止）、(4) Meeting を `status = Approved` / `meeting_url_snapshot = auth()->user()->meeting_url`（NULL 許容、現時点の coach 固定 URL を焼き込み）に UPDATE、(5) [[notification]] の `NotifyMeetingApprovedAction` 呼出（student へ DB + Mail 通知、メール本文に `meeting_url_snapshot` と日時を埋め込む）を行う。
- **REQ-mentoring-031**: When コーチが `POST /meetings/{meeting}/reject` で拒否する際, the system shall リクエストに `rejected_reason`（必須、string max 500）を要求し、単一トランザクション内で (1) `Meeting.status === Requested` の検証、(2) Meeting を `status = Rejected` / `rejected_reason = $reason` に UPDATE、(3) [[notification]] の `NotifyMeetingRejectedAction` 呼出（student へ DB + Mail 通知、メール本文に拒否理由を含める）を行う。
- **REQ-mentoring-032**: If 承認 / 拒否 API が `Requested` 以外の状態の Meeting に対して呼ばれた場合, then the system shall ドメイン例外 `MeetingStatusTransitionException`（HTTP 409）で拒否する。
- **REQ-mentoring-033**: If 承認 API 実行時に同コーチ × 同 `scheduled_at` の他 approved/in_progress Meeting が存在する場合（race condition）, then the system shall ドメイン例外 `MeetingTimeSlotTakenException`（HTTP 409）で拒否する。

### 機能要件 — 承認後のキャンセル（受講生 / コーチ）

- **REQ-mentoring-042**: When 受講生またはコーチが自分が当事者の `approved` Meeting に対し `POST /meetings/{meeting}/cancel` を実行した際, the system shall 単一トランザクション内で (1) `Meeting.status === Approved` の検証、(2) `Meeting.scheduled_at > now()` の検証（面談開始後はキャンセル不可）、(3) Meeting を `status = Canceled` / `canceled_by_user_id = auth()->id()` / `canceled_at = now()` に UPDATE、(4) [[notification]] の `NotifyMeetingCanceledAction` 呼出（相手方へ DB + Mail 通知、本文にキャンセル実行者の役割を表示）を行う。
- **REQ-mentoring-043**: If approved Meeting のキャンセル API が `Meeting.scheduled_at <= now()` の状態で呼ばれた場合, then the system shall ドメイン例外 `MeetingAlreadyStartedException`（HTTP 409）で拒否する。

### 機能要件 — 当日入室・面談実施・メモ記録

- **REQ-mentoring-050**: When コーチが `POST /meetings/{meeting}/start` で入室開始する際, the system shall 単一トランザクション内で (1) `Meeting.status === Approved` の検証、(2) `Meeting.scheduled_at - 10 分 <= now() < Meeting.scheduled_at + 60 分` の検証（10 分前入室・終了予定までの入室を許可）、(3) Meeting を `status = InProgress` / `started_at = now()` に UPDATE する。
- **REQ-mentoring-051**: If 入室 API が許可時間枠外で呼ばれた場合, then the system shall ドメイン例外 `MeetingNotInStartWindowException`（HTTP 409）で拒否する。
- **REQ-mentoring-052**: When コーチが `POST /meetings/{meeting}/complete` で完了する際, the system shall リクエストに `body`（必須、string max 5000、面談メモ本文）を要求し、単一トランザクション内で (1) `Meeting.status === InProgress` の検証、(2) Meeting を `status = Completed` / `ended_at = now()` に UPDATE、(3) `MeetingMemo` を `meeting_id = $meeting->id` / `body = $body` で INSERT する。完了通知は送らない（受講生は履歴閲覧で確認）。
- **REQ-mentoring-053**: When コーチが `PUT /meetings/{meeting}/memo` で面談メモを後追い編集する際, the system shall `Meeting.status === Completed` かつ `MeetingMemo` が既存であることを検証し、`body` を UPDATE する（追記・修正用、メモ作成自体は REQ-mentoring-052 の `complete` API でのみ行う）。
- **REQ-mentoring-054**: The system shall 受講生に対し面談メモを **閲覧のみ** 許可する（編集不可、新規作成も不可）。

### 機能要件 — 履歴閲覧（一覧 / 詳細）

- **REQ-mentoring-060**: When 受講生が `GET /meetings` でアクセスした際, the system shall `student_id = auth()->id()` の Meeting を `scheduled_at DESC` で paginate(20) し、各行に `enrollment.certification.name` / `coach.name` / `status` / `scheduled_at` を表示する。ステータスフィルタ（`?status=upcoming|past|all`、`upcoming = requested/approved/in_progress`、`past = rejected/canceled/completed`）を提供する。
- **REQ-mentoring-061**: When コーチが `GET /coach/meetings` でアクセスした際, the system shall `coach_id = auth()->id()` の Meeting を同様に paginate(20) する。受講生別フィルタ（`?student={ulid}`）と `Enrollment 別フィルタ`（`?enrollment={ulid}`）を併せて提供する。
- **REQ-mentoring-062**: When 当事者（受講生 or コーチ）が `GET /meetings/{meeting}` でアクセスした際, the system shall Meeting + 関連 Enrollment / Certification / Coach / Student / MeetingMemo（completed の場合）を eager load し、状態に応じた操作ボタン（取り下げ / キャンセル / 承認 / 拒否 / 入室 / 完了）を Blade で出し分ける。
- **REQ-mentoring-063**: When 受講生が `completed` 状態の Meeting 詳細を閲覧した際, the system shall コーチが書いた `MeetingMemo.body` を読み取り専用で表示する。
- **REQ-mentoring-064**: The system shall コーチダッシュボード（[[dashboard]]）と受講生ダッシュボードに **今日と直近 7 日の Meeting** を時系列で表示するため、`scheduled_at BETWEEN today AND today+7days AND status IN (approved, in_progress)` のクエリを提供する（dashboard 側が利用）。

### 機能要件 — 通知 / リマインド

- **REQ-mentoring-070**: The system shall Meeting の status 変化のたびに [[notification]] の各 Action を呼ぶ（REQ-mentoring-021 / -030 / -031 / -040 / -042 で発火）。通知は Database channel + Mail channel の二段を [[notification]] 側で配信する。
- **REQ-mentoring-071**: The system shall Schedule Command `meetings:remind` を提供し、`status = Approved AND scheduled_at BETWEEN now() AND now() + 1 hour` の Meeting に対し、当事者双方へ「1 時間後に面談開始」のリマインド通知を [[notification]] 経由で発火する。本コマンドは `app/Console/Kernel.php::schedule()` で **15 分間隔の `cron('*/15 * * * *')`** で実行する（境界漏れ防止）。同一 Meeting への二重送信は `meetings` テーブルに送信フラグを持たず、`notifications.data->meeting_id` の存在を [[notification]] 側でチェックして重複排除する。
- **REQ-mentoring-072**: The system shall Schedule Command `meetings:remind-eve` を提供し、`status = Approved AND scheduled_at` が翌日中の Meeting に対し、当事者双方へ「明日 X 時に面談」のリマインド通知を [[notification]] 経由で発火する。本コマンドは `dailyAt('18:00')` で実行する。
- **REQ-mentoring-073**: The承認通知メール本文（[[notification]] 側で組み立て）には `Meeting.scheduled_at`（日本語フォーマット `MM月DD日(ddd) HH:mm`）/ コーチ名 / 受講生が書いた `topic` / `meeting_url_snapshot`（NULL なら「URL 未設定。コーチからの個別連絡を待ってください」と表示）を含める。

### 機能要件 — 認可（Policy）

- **REQ-mentoring-080**: The system shall `MeetingPolicy` を実装し、各メソッドのロール別判定を以下とする:
  - `viewAny`: admin / coach / student すべて true（一覧スコープは IndexAction 内で `coach_id` / `student_id` で絞る）
  - `view`: admin true / coach は `$meeting->coach_id === $user->id` のみ / student は `$meeting->student_id === $user->id` のみ
  - `create`: student のみ true（コーチ・admin は作成不可）
  - `cancel`: 受講生取り下げ（requested）は `$meeting->student_id === $user->id` のみ / 承認後キャンセル（approved）は当事者（coach or student）のみ
  - `approve` / `reject`: coach かつ `$meeting->coach_id === $user->id` のみ
  - `start` / `complete`: coach かつ `$meeting->coach_id === $user->id` のみ
  - `updateMemo`: coach かつ `$meeting->coach_id === $user->id` かつ `$meeting->status === Completed` のみ
- **REQ-mentoring-081**: The system shall `CoachAvailabilityPolicy` を実装し、`viewAny` は 全ロール true（受講生は予約画面で他コーチ枠閲覧の権利を持つが、IndexAction 内で coach 単位フィルタを適用）/ `view` 同様 / `create` / `update` / `delete` は `$user->role === Coach` かつ `$availability->coach_id === $user->id` のみとする。**`CoachAvailability` の編集 UI は [[settings-profile]] が所有**するが Policy は本 Feature が所有する。

### 機能要件 — 集計サービス（CoachActivityService）

- **REQ-mentoring-090**: The system shall `CoachActivityService` を `app/Services/CoachActivityService.php` に配置し、admin ダッシュボード向けに「コーチごとの直近 30 日の面談実施数（`status = completed`）/ キャンセル数 / 拒否数 / 平均面談メモ文字数」を集計する `summarize(?Carbon $from = null, ?Carbon $to = null): Collection` を公開する。
- **REQ-mentoring-091**: The system shall `CoachActivityService` の集計結果を [[dashboard]] の admin 画面が読み取り側として利用する。本 Service 自体は dashboard には依存せず、戻り値は `[['coach' => User, 'completed_count' => int, 'canceled_count' => int, 'rejected_count' => int, 'avg_memo_length' => int|null], ...]` の Collection とする。

### 非機能要件

- **NFR-mentoring-001**: The system shall 状態変更を伴うすべての Action（`StoreAction` / `ApproveAction` / `RejectAction` / `CancelAction` / `StartAction` / `CompleteAction` / `UpdateMemoAction`）を `DB::transaction()` で囲む。
- **NFR-mentoring-002**: The system shall すべての Meeting status 遷移を `product.md` の state diagram + 本 Feature 追加の `requested → canceled`（受講生取り下げ）の組合せ以外には許容しない。逸脱遷移は `MeetingStatusTransitionException` で拒否する。
- **NFR-mentoring-003**: The system shall 同コーチ × 同 `scheduled_at` での `status ∈ {requested, approved, in_progress}` 重複を許容しない（受講生申請段階 + コーチ承認段階の両方で検査）。MySQL の partial UNIQUE INDEX が書けないため、Action 側の `SELECT ... LOCK IN SHARE MODE` ではなく `DB::transaction()` + `whereExists` 検査で防御し、衝突時は `MeetingTimeSlotTakenException` を投げる。
- **NFR-mentoring-004**: The system shall ドメイン例外を `app/Exceptions/Mentoring/` 配下に集約する（`backend-exceptions.md` 準拠）: `MeetingOutOfAvailabilityException`（422）/ `MeetingTimeSlotTakenException`（409）/ `MeetingStatusTransitionException`（409）/ `MeetingNotInStartWindowException`（409）/ `MeetingAlreadyStartedException`（409）/ `EnrollmentCoachNotAssignedException`（409、enrollment 文脈だが本 Feature 起因のため Mentoring 配下に置く）。
- **NFR-mentoring-005**: The system shall 面談 1 件の長さを **60 分固定** とする。受講生・コーチが任意の時間長を指定する UI を提供しない。
- **NFR-mentoring-006**: The system shall 日本語メッセージ（例外メッセージ / 通知メール文面 / Blade ラベル）を集約し、ビュー内のマジック文字列を禁止する（`lang/ja/mentoring.php` + 例外コンストラクタ）。
- **NFR-mentoring-007**: The system shall 空き枠取得（`GET /meetings/availability`）を 1 リクエスト 1 日 × 1 コーチに限定し、1 リクエスト当たり `CoachAvailability` テーブル × 1 クエリ + `meetings` テーブル × 1 クエリの計 2 クエリで完結させる（N+1 禁止、Eager Loading 前提）。

## スコープ外

- **Google Calendar OAuth 連携 / 空き枠取得 / event 作成・削除 / Google Meet URL 自動生成**: Advance 範囲で扱う。Basic では `meeting_url_snapshot` は coach の固定 URL のみで、Google Meet URL の動的生成は行わない
- **臨時シフト / 例外除外日**: COACHTECH LMS にある `EmployeeTemporaryShift` / `EmployeeShiftExcludeDate` 相当は採用しない（標準機能でなくスコープ縮減）。「この月曜だけ休み」は CoachAvailability の `is_active` 一時切替か、該当日の枠を一時的に削除する運用で対応
- **ビット演算による空き枠計算**: COACHTECH 流の 30 分刻みビット演算は採用せず、Eloquent range クエリで素直に算出（Basic では十分）
- **動画通話 / 録画機能**: 外部ツール（Zoom / Google Meet 等）想定。`meeting_url_snapshot` を共有するのみ（`product.md` スコープ外）
- **コーチ間の面談枠交換 / 担当コーチ変更時の Meeting 自動移譲**: 担当コーチ変更は [[user-management]] / [[enrollment]] の責務、本 Feature では行わない
- **`CoachAvailability` 編集 UI（`/settings/availability`）**: モデル / Policy は本 Feature 所有だが、編集 Controller / FormRequest / Blade は [[settings-profile]] が所有
- **`users.meeting_url` 編集 UI**: 同上、[[settings-profile]] が所有
- **面談予約・実施に伴う Sanctum SPA / Pusher リアルタイム通知**: Advance 範囲
- **承認時にコーチが個別 `meeting_url` を上書き入力**: 採用しない。固定 URL の焼き込みのみ。Advance で Google Meet URL の動的生成が入った時点でその URL が `meeting_url_snapshot` に入る
- **複数 workspace 概念**: Certify は 1 LMS = 1 workspace 想定で、COACHTECH の `employee_workspace` 構造は持ち込まない

## 関連 Feature

- **依存元**（本 Feature を利用する）:
  - [[dashboard]]: 受講生 Dash の「面談予定」/ コーチ Dash の「今日の面談」/ admin Dash の「コーチ稼働状況（`CoachActivityService` 経由）」
  - [[notification]]: 状態変化通知の発火点（本 Feature が `NotifyMeeting*Action` を呼ぶ）
  - [[settings-profile]]: `CoachAvailability` 編集 UI と `users.meeting_url` 編集 UI を所有（本 Feature は Model + Policy 提供）
  - [[user-management]]: admin がユーザー詳細画面で `meeting_url` を含むプロフィールを閲覧する際に本 Feature の Model を間接参照
- **依存先**（本 Feature が前提とする）:
  - [[auth]]: `User` モデル / `UserRole` enum / `EnsureUserRole` middleware / セッション認証
  - [[user-management]]: `users` テーブルへの `meeting_url` カラム追加 migration の整合（本 Feature は新規 migration で `add_meeting_url_to_users_table` を追加）
  - [[enrollment]]: `Enrollment` モデル / `Enrollment.assigned_coach_id` / `Enrollment.user_id`（受講生 ID）
  - [[certification-management]]: `Certification` モデル（履歴一覧で `Enrollment.certification` を eager load）
