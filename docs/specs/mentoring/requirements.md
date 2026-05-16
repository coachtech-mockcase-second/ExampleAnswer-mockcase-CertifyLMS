# mentoring 要件定義

> **v3 改修反映**（2026-05-16）: 申請・承認・拒否フロー削除（自動コーチ割当に変更）、`Meeting.status` 6 値 → 3 値（`reserved` / `canceled` / `completed`）、`meetings:auto-complete` Schedule Command 追加、面談回数消費は [[meeting-quota]] と連携、受講生宛通知撤回（コーチ宛のみ）、臨時シフト不採用。

## 概要

Certify LMS の 1on1 面談（Mentoring）Feature。コーチが事前に登録した **面談可能時間枠**（`CoachAvailability`、曜日 × 開始時刻 × 終了時刻の繰り返し枠）と **固定面談 URL**（`users.meeting_url`、coach がオンボーディング時に必須入力 + [[settings-profile]] で編集可）を基盤に、**受講生が時刻スロットを選択すると、過去 30 日の面談実施数が少ないコーチが自動割当される** 方式を採用する。申請・承認・拒否フローは撤回（COACHTECH LMS 流のシンプル運用）。

主モデルは `Meeting` / `MeetingMemo` / `CoachAvailability` の 3 つ。`Meeting` の状態遷移は **3 値**（`reserved` / `canceled` / `completed`）、本 Feature は状態変化のたびに [[notification]] feature 経由でコーチへ通知を発火、`dashboard` に「今日の面談」「コーチ稼働状況」を集計提供する。**面談回数の消費・返却は [[meeting-quota]] と連携**（`reserved` で `consumed (-1)`、`canceled` で `refunded (+1)`）。

Basic では Google Calendar 連携なし・繰り返し枠の手動登録のみ。`CoachAvailability` モデル定義と空き枠取得は本 Feature が所有するが、**編集 UI**（`/settings/availability`）は [[settings-profile]] が所有する（責務分離、`product.md`「ロール共通画面の責務分担」準拠）。臨時シフト（COACHTECH の `EmployeeTemporaryShift` 相当）は **採用しない**。

## ロールごとのストーリー

- **受講生（student）**: 受講中の資格に紐づく担当コーチ集合（`certification_coach_assignments` 経由）に対し、**時刻スロットだけを選択** して予約する。コーチは自動割当（過去 30 日実施数最少）で決まる。残面談回数が 0 の場合は予約ボタンが不活性 + 追加購入 CTA（[[meeting-quota]] / Stripe）が表示される。承認・拒否を待つ必要はなく、予約は即時 `reserved` で確定する。当日はコーチの固定面談 URL（メール本文 + 詳細画面）から入室。実施後はコーチが記録した面談メモを閲覧できる。過去の面談履歴を時系列で閲覧する。
- **コーチ（coach）**: 受講生が予約した自分宛の `reserved` 面談を一覧で確認する。承認・拒否のアクションはなく、当日に Google Meet 等の外部ツールで実施。終了時刻超過で自動的に `completed` に遷移する（Schedule Command）。実施前後の任意のタイミングで `MeetingMemo` を記録できる。`CoachAvailability` の繰り返し枠と固定面談 URL は [[settings-profile]] で編集する（本 Feature は読み取り側）。
- **管理者（admin）**: 本 Feature の直接操作はない（サイドバーにメニュー無し、`product.md` 動線準拠）。`users.meeting_url` を含むユーザー詳細閲覧（[[user-management]] 経由）から面談データを参照する。**`CoachActivityService` 経由の dashboard 表示は v3(D3 確定)で撤回**(必要なら個別管理画面で参照)。

## 受け入れ基準（EARS形式）

### 機能要件 — Meeting モデルと基盤

- **REQ-mentoring-001**: The system shall ULID 主キー / `enrollment_id`（`enrollments.id` への外部キー）/ `coach_id`（`users.id` への外部キー、`role=coach`、**自動割当の結果として確定**）/ `student_id`（`users.id` への外部キー、`role=student`、`Enrollment.user_id` と冗長一致）/ `scheduled_at` datetime（開始時刻、終了時刻は常に `scheduled_at + 60 分`）/ `status` enum（**`reserved` / `canceled` / `completed`** の 3 値）/ `topic` text（受講生入力の話題）/ `canceled_by_user_id` ulid nullable（`users.id` への外部キー、`SET NULL`、`canceled` 遷移時のみセット）/ `canceled_at` datetime nullable / `meeting_url_snapshot` string nullable（`reserved` 遷移時に `coach.meeting_url` を焼き込み）/ `completed_at` datetime nullable / `meeting_quota_transaction_id` ulid（`reserved` 遷移時に [[meeting-quota]] の `consumed` トランザクションを参照、`canceled` 時の refund 元として利用）/ timestamps / softDeletes を備えた `meetings` テーブルを提供する。**`approved_at` / `rejected_reason` / `started_at` / `ended_at` カラムは持たない**（申請承認フロー撤回 + 入室手動操作撤回）。
- **REQ-mentoring-002**: The system shall `MeetingStatus` PHP backed enum（string、`Reserved = 'reserved'` / `Canceled = 'canceled'` / `Completed = 'completed'`）を公開し、`label()` メソッドで日本語表示ラベル（`予約済` / `キャンセル` / `完了`）を返す。状態遷移は `product.md` E. Meeting state diagram に厳格準拠する。
- **REQ-mentoring-003**: The `Meeting` model shall `belongsTo(Enrollment::class)` / `belongsTo(User::class, 'coach_id', 'coach')` / `belongsTo(User::class, 'student_id', 'student')` / `belongsTo(User::class, 'canceled_by_user_id', 'canceledBy')` / `hasOne(MeetingMemo::class)` / `belongsTo(MeetingQuotaTransaction::class, 'meeting_quota_transaction_id')` の 6 リレーションを公開する。
- **REQ-mentoring-004**: The `meetings` テーブル shall `(coach_id, scheduled_at)` UNIQUE 制約（同コーチ × 同時刻の二重予約禁止、status 関係なく）/ `(student_id, scheduled_at)` 複合 INDEX / `(enrollment_id)` INDEX / `(status, scheduled_at)` 複合 INDEX（auto-complete Schedule Command の高速化）を備える。

### 機能要件 — CoachAvailability モデルと固定面談 URL

- **REQ-mentoring-010**: The system shall ULID 主キー / `coach_id`（`users.id` への外部キー、`role=coach`）/ `day_of_week` tinyInteger（0=日曜, 6=土曜、Carbon 標準 `dayOfWeek`）/ `start_time` time / `end_time` time / `is_active` boolean default true / timestamps / softDeletes を備えた `coach_availabilities` テーブルを提供する。
- **REQ-mentoring-011**: The system shall `(coach_id, day_of_week)` INDEX / `(coach_id, is_active)` INDEX を備える（受講生の予約画面が「該当資格コーチ集合の全曜日有効枠」を一括取得する用途）。
- **REQ-mentoring-012**: The system shall 1 コーチ × 1 曜日に **複数の枠を許容する**（例: 月曜 09:00-12:00 と 月曜 14:00-17:00 を両方登録可）。`(coach_id, day_of_week, start_time)` の UNIQUE 制約は採用しない。
- **REQ-mentoring-013**: The `coach_availabilities` テーブル shall `end_time > start_time` をアプリケーション層（FormRequest）で保証する。**日跨ぎ枠は許容しない**（`start_time < end_time` の同日範囲のみ）。
- **REQ-mentoring-014**: The system shall `users` テーブルに `meeting_url` string nullable カラムを追加する（[[auth]] / [[settings-profile]] が編集 UI を所有、本 Feature と [[notification]] が読み取り側）。**コーチオンボーディング時の必須入力** は [[auth]] の `OnboardAction` で担保される。
- **REQ-mentoring-015**: The system shall 臨時シフト / 例外除外日（COACHTECH の `EmployeeTemporaryShift` / `EmployeeShiftExcludeDate` 相当）を **採用しない**。「この月曜だけ休み」などは `CoachAvailability.is_active` の一時切替か該当日の枠の一時削除で対応する運用とする。

### 機能要件 — MeetingMemo モデル

- **REQ-mentoring-016**: The system shall ULID 主キー / `meeting_id`（`meetings.id` への外部キー、UNIQUE、cascadeOnDelete）/ `body` text / timestamps / softDeletes を備えた `meeting_memos` テーブルを提供する（1 Meeting : 1 MeetingMemo）。
- **REQ-mentoring-017**: The `MeetingMemo` model shall `belongsTo(Meeting::class)` リレーションを公開する。author は `meeting.coach_id` で一意に決まるため別カラムは持たない（記述者は常にコーチ）。
- **REQ-mentoring-018**: When `Meeting.status` が `reserved` の段階（実施前）でも, the system shall コーチが MeetingMemo を記録することを許可する（事前メモ用途）。

### 機能要件 — 受講生による予約（自動コーチ割当）

- **REQ-mentoring-020**: When 受講生が `GET /meetings/availability?enrollment={ulid}&date={YYYY-MM-DD}` で空き枠を検索する, the system shall 指定 Enrollment の **担当資格に割り当てられた全コーチ**（`certification_coach_assignments`）について、指定日における 60 分単位の空き開始時刻リストを集計して JSON 返却する。算出は (1) `CoachAvailability.day_of_week = 該当曜日 AND is_active = true` の枠を 60 分刻みに展開、(2) 同コーチの `status IN (reserved, completed)` の `scheduled_at` を除外、(3) 全コーチの集合和をスロット単位で `{ "slot_start": ISO8601, "slot_end": ISO8601, "available_coach_count": N }` 形式で返す（コーチ個別は受講生に見せない、自動割当のため）。
- **REQ-mentoring-021**: When 受講生が `POST /meetings` で予約する, the system shall リクエストに `enrollment_id`（必須、ulid）/ `scheduled_at`（必須、datetime、未来日時、分単位は `:00`）/ `topic`（必須、string max 1000）を要求する。
- **REQ-mentoring-022**: The system shall `Meeting\StoreAction::__invoke(Enrollment $enrollment, Carbon $scheduledAt, string $topic): Meeting` を実行する。Action は単一トランザクション内で以下を実行する: (1) `Enrollment.user_id === auth()->id()` の所有確認、(2) 受講生の残面談回数 `MeetingQuotaService::remaining($student)` が 1 以上であることを検証、不足なら `InsufficientMeetingQuotaException`（HTTP 409）を throw、(3) `scheduled_at` が `Enrollment.certification` 担当コーチ集合の有効な `CoachAvailability` 枠内であることの検証、(4) **同 `scheduled_at` で空きコーチを選出**: `certification_coach_assignments` 経由のコーチのうち、(a) 当該時刻に `CoachAvailability.is_active = true` 枠を持つ、(b) 当該時刻に `Meeting.status IN (reserved, completed)` がない、を満たすコーチ集合を取得、(5) 取得集合に対して **`CoachMeetingLoadService::leastLoadedCoach(Collection<User>): User`** を呼び、過去 30 日の `Meeting WHERE status = completed` の件数が最少のコーチ 1 名を選出（同数なら ULID 昇順）、(6) `Meeting` 行を `coach_id = 選出コーチ.id` / `student_id = 受講生.id` / `status = reserved` / `meeting_url_snapshot = 選出コーチ.meeting_url` で INSERT、(7) [[meeting-quota]] の `ConsumeQuotaAction($student, $meeting)` を呼ぶ（`MeetingQuotaTransaction` INSERT `type=consumed amount=-1 reference_id=meeting.id`）、(8) [[notification]] の `NotifyMeetingReservedAction($meeting)` 呼出（**コーチ宛のみ** DB + Mail 通知、受講生宛は予約 UI で即時確認のため不要）。
- **REQ-mentoring-023**: If `scheduled_at` の分単位が `:00` 以外の場合, then the system shall FormRequest バリデーションエラー（日本語メッセージ）で拒否する。
- **REQ-mentoring-024**: If `scheduled_at` が現在時刻以前の場合, then the system shall FormRequest バリデーションエラーで拒否する（`after:now` ルール）。
- **REQ-mentoring-025**: If REQ-022 の (4) で空きコーチが 0 名の場合（race condition 含む）, then the system shall `MeetingNoAvailableCoachException`（HTTP 409、日本語メッセージ「指定された時刻には空きコーチがいません」）で拒否する。
- **REQ-mentoring-026**: If 受講生のログインユーザーが `User.status != UserStatus::InProgress` の場合, then the system shall `Meeting\StoreAction` を `EnsureActiveLearning` Middleware（[[auth]] 所有）でブロックし、HTTP 403 を返す。

### 機能要件 — キャンセル（受講生 / コーチ）

- **REQ-mentoring-030**: When 受講生またはコーチが自分が当事者の `reserved` Meeting に対し `POST /meetings/{meeting}/cancel` を実行した際, the system shall `Meeting\CancelAction::__invoke(Meeting $meeting, User $actor): Meeting` を単一トランザクション内で実行する。Action は (1) `Meeting.status === Reserved` の検証、(2) `Meeting.scheduled_at > now()` の検証（面談開始後はキャンセル不可、過去面談のキャンセル禁止）、(3) Meeting を `status = Canceled` / `canceled_by_user_id = $actor->id` / `canceled_at = now()` に UPDATE、(4) [[meeting-quota]] の `RefundQuotaAction($meeting)` を呼ぶ（`MeetingQuotaTransaction` INSERT `type=refunded amount=+1 reference_id=meeting.id`）、(5) [[notification]] の `NotifyMeetingCanceledAction($meeting, $actor)` 呼出（**相手方宛て** に DB + Mail 通知、送信者 role で文面を分岐）を行う。
- **REQ-mentoring-031**: If `reserved` 以外の状態（`canceled` / `completed`）の Meeting でキャンセル API が呼ばれた場合, then the system shall `MeetingStatusTransitionException`（HTTP 409）で拒否する。
- **REQ-mentoring-032**: If キャンセル API が `Meeting.scheduled_at <= now()` の状態で呼ばれた場合, then the system shall `MeetingAlreadyStartedException`（HTTP 409、日本語メッセージ「面談開始時刻を過ぎた予約はキャンセルできません」）で拒否する。

### 機能要件 — 自動完了（Schedule Command）

- **REQ-mentoring-040**: The system shall Schedule Command `meetings:auto-complete` を提供し、`status = Reserved AND scheduled_at + 60 minutes < now()` の Meeting を抽出して、各 Meeting に対して `AutoCompleteMeetingAction::__invoke(Meeting $meeting): Meeting` を実行する。Action は (1) Meeting を `status = Completed` / `completed_at = now()` に UPDATE する（通知は発火しない、受講生もコーチも自分の履歴で確認できる）。
- **REQ-mentoring-041**: The system shall `meetings:auto-complete` を `app/Console/Kernel.php::schedule()` で **15 分間隔の `cron('*/15 * * * *')`** で実行する（COACHTECH 流の Schedule Command + 細かい刻みでリアルタイム性確保）。
- **REQ-mentoring-042**: When 受講生またはコーチが `completed` Meeting の MeetingMemo を閲覧するため `GET /meetings/{meeting}` にアクセスする, the system shall 該当 Meeting + MeetingMemo を eager load で返す（READ 専用、状態遷移なし）。

### 機能要件 — MeetingMemo 記録（コーチのみ）

- **REQ-mentoring-050**: When コーチが `POST /meetings/{meeting}/memo` または `PUT /meetings/{meeting}/memo` で MeetingMemo を記録 / 編集する, the system shall リクエストに `body`（必須、string max 5000）を要求し、(1) `Meeting.coach_id === auth()->id()` の所有確認（Policy で行う）、(2) `Meeting.status IN (reserved, completed)` の検証（`canceled` メモは不可）、(3) `MeetingMemo` を `meeting_id = $meeting->id` に対して upsert（既存があれば UPDATE、無ければ INSERT）する。
- **REQ-mentoring-051**: The system shall 受講生に対し面談メモを **閲覧のみ** 許可する（編集不可、新規作成も不可）。
- **REQ-mentoring-052**: When 受講生が `completed` 状態の Meeting 詳細を閲覧した際, the system shall コーチが書いた `MeetingMemo.body` を読み取り専用で表示する。`reserved` 状態の MeetingMemo は受講生からは見えない（コーチの事前メモは内部用）。

### 機能要件 — 履歴閲覧（一覧 / 詳細）

- **REQ-mentoring-060**: When 受講生が `GET /meetings` でアクセスした際, the system shall `student_id = auth()->id()` の Meeting を `scheduled_at DESC` で paginate(20) し、各行に `enrollment.certification.name` / `coach.name` / `status` / `scheduled_at` を表示する。ステータスフィルタ（`?status=upcoming|past|all`、`upcoming = reserved`、`past = completed | canceled`）を提供する。
- **REQ-mentoring-061**: When コーチが `GET /coach/meetings` でアクセスした際, the system shall `coach_id = auth()->id()` の Meeting を同様に paginate(20) する。受講生別フィルタ（`?student={ulid}`）と Enrollment 別フィルタ（`?enrollment={ulid}`）を併せて提供する。
- **REQ-mentoring-062**: When 当事者（受講生 or コーチ）が `GET /meetings/{meeting}` でアクセスした際, the system shall Meeting + 関連 Enrollment / Certification / Coach / Student / MeetingMemo を eager load し、状態に応じた操作ボタン（キャンセル / メモ記録）を Blade で出し分ける。
- **REQ-mentoring-063**: When 受講生が `completed` 状態の Meeting 詳細を閲覧した際, the system shall コーチが書いた `MeetingMemo.body` を読み取り専用で表示する。
- **REQ-mentoring-064**: The system shall コーチダッシュボード（[[dashboard]]）と受講生ダッシュボードに **今日と直近 7 日の Meeting** を時系列で表示するため、`scheduled_at BETWEEN today AND today+7days AND status = reserved` のクエリを提供する（dashboard 側が利用）。

### 機能要件 — 通知連動

- **REQ-mentoring-070**: When 受講生が予約 (`Meeting\StoreAction`) 成功した直後, the system shall **担当コーチ宛のみ** に [[notification]] の `NotifyMeetingReservedAction($meeting)` を呼ぶ（受講生宛は予約 UI で即時確認のため不要）。
- **REQ-mentoring-071**: When 当事者がキャンセル (`Meeting\CancelAction`) を実行した直後, the system shall **相手方** に [[notification]] の `NotifyMeetingCanceledAction($meeting, $actor)` を呼ぶ。
- **REQ-mentoring-072**: The system shall Schedule Command `meetings:remind` を提供し、`status = Reserved AND scheduled_at BETWEEN now() AND now() + 1 hour` の Meeting に対し、**当事者双方** へ「1 時間後に面談開始」のリマインド通知を [[notification]] 経由で発火する。本コマンドは `cron('*/15 * * * *')` で実行する（境界漏れ防止）。
- **REQ-mentoring-073**: The system shall Schedule Command `meetings:remind-eve` を提供し、`status = Reserved AND scheduled_at` が翌日中の Meeting に対し、当事者双方へ「明日 X 時に面談」のリマインド通知を [[notification]] 経由で発火する。本コマンドは `dailyAt('18:00')` で実行する。
- **REQ-mentoring-074**: The system shall コーチ宛予約通知メール本文（[[notification]] 側で組み立て）には `Meeting.scheduled_at`（日本語フォーマット `MM月DD日(ddd) HH:mm`）/ 受講生名 / `topic` / `meeting_url_snapshot` を含める。`meeting_url_snapshot` が NULL の場合は「URL 未設定。早急に [[settings-profile]] で設定してください」と表示。
- **REQ-mentoring-075**: The system shall `completed` 遷移時の通知 / `reserved → completed` 自動遷移時の通知を **発火しない**（受講生もコーチも履歴一覧で確認可能、通知過剰を避ける）。

### 機能要件 — 認可（Policy）

- **REQ-mentoring-080**: The system shall `MeetingPolicy` を実装し、各メソッドのロール別判定を以下とする:
  - `viewAny`: admin / coach / student すべて true（一覧スコープは IndexAction 内で `coach_id` / `student_id` で絞る）
  - `view`: admin true / coach は `$meeting->coach_id === $user->id` のみ / student は `$meeting->student_id === $user->id` のみ
  - `create`: student のみ true（コーチ・admin は作成不可、自動割当のため admin 介入もなし）
  - `cancel`: 当事者（coach or student）のみ true
  - `updateMemo`: coach かつ `$meeting->coach_id === $user->id` かつ `$meeting->status IN (reserved, completed)` のみ
- **REQ-mentoring-081**: The system shall `CoachAvailabilityPolicy` を実装し、`viewAny` は 全ロール true（受講生は予約画面で他コーチ枠閲覧の権利を持つが、IndexAction 内で coach 単位フィルタを適用）/ `view` 同様 / `create` / `update` / `delete` は `$user->role === Coach` かつ `$availability->coach_id === $user->id` のみとする。**`CoachAvailability` の編集 UI は [[settings-profile]] が所有** するが Policy は本 Feature が所有する。

### 機能要件 — 集計サービス（CoachActivityService + CoachMeetingLoadService）

- **REQ-mentoring-090**: The system shall `CoachActivityService` を `app/Services/CoachActivityService.php` に配置し、admin ダッシュボード向けに「コーチごとの直近 30 日の面談実施数（`status = completed`）/ キャンセル数 / 平均面談メモ文字数」を集計する `summarize(?Carbon $from = null, ?Carbon $to = null): Collection` を公開する。
- **REQ-mentoring-091**: The system shall `CoachActivityService` の集計結果を [[dashboard]] の admin 画面が読み取り側として利用する。本 Service 自体は dashboard には依存せず、戻り値は `[['coach' => User, 'completed_count' => int, 'canceled_count' => int, 'avg_memo_length' => int|null], ...]` の Collection とする。
- **REQ-mentoring-092**: The system shall `CoachMeetingLoadService::leastLoadedCoach(Collection<User>): User` を `app/Services/CoachMeetingLoadService.php` に配置し、引数のコーチ集合の中から **過去 30 日の `Meeting WHERE status = completed AND coach_id IN (...)` の件数が最少のコーチ** を 1 名返す。同数の場合は ULID 昇順で先頭を選択。
- **REQ-mentoring-093**: The system shall `CoachMeetingLoadService` のクエリを単一 SQL で発行する（`SELECT coach_id, COUNT(*) FROM meetings WHERE coach_id IN (...) AND status = 'completed' AND scheduled_at > now() - 30 days GROUP BY coach_id`）。引数集合のうち過去 30 日に実績ゼロのコーチも考慮（LEFT JOIN または PHP 側で 0 件補完）。

### 非機能要件

- **NFR-mentoring-001**: The system shall 状態変更を伴うすべての Action（`Meeting\StoreAction` / `Meeting\CancelAction` / `AutoCompleteMeetingAction` / `UpdateMemoAction`）を `DB::transaction()` で囲む。[[meeting-quota]] との連携（`ConsumeQuotaAction` / `RefundQuotaAction`）も同一トランザクション内で実行する。
- **NFR-mentoring-002**: The system shall すべての Meeting status 遷移を `product.md` E. Meeting state diagram に準拠（`reserved → canceled` / `reserved → completed` / `[*] → reserved` のみ）。逸脱遷移は `MeetingStatusTransitionException` で拒否する。
- **NFR-mentoring-003**: The system shall 同コーチ × 同 `scheduled_at` での `status IN (reserved, completed)` 重複を **DB レベル UNIQUE 制約** で禁止する（`(coach_id, scheduled_at)` UNIQUE）。race condition は `INSERT` 失敗で検知し、Action 内で `MeetingNoAvailableCoachException` に変換する。
- **NFR-mentoring-004**: The system shall ドメイン例外を `app/Exceptions/Mentoring/` 配下に集約する: `MeetingNoAvailableCoachException`（409）/ `MeetingStatusTransitionException`（409）/ `MeetingAlreadyStartedException`（409）/ `InsufficientMeetingQuotaException`（409、面談回数 0 時）。`MeetingOutOfAvailabilityException` は `availability` 枠検証で利用可。`EnrollmentCoachNotAssignedException` は **撤回**（資格に N コーチ N:N、未割当エラーは起きえない）。
- **NFR-mentoring-005**: The system shall 面談 1 件の長さを **60 分固定** とする。受講生・コーチが任意の時間長を指定する UI を提供しない。
- **NFR-mentoring-006**: The system shall 日本語メッセージ（例外メッセージ / 通知メール文面 / Blade ラベル）を集約し、ビュー内のマジック文字列を禁止する（`lang/ja/mentoring.php` + 例外コンストラクタ）。
- **NFR-mentoring-007**: The system shall 空き枠取得（`GET /meetings/availability`）を 1 リクエスト 1 日 × 1 Enrollment（=該当資格コーチ集合）に限定し、1 リクエスト当たり `CoachAvailability` テーブル × 1 クエリ + `meetings` テーブル × 1 クエリの計 2 クエリで完結させる（N+1 禁止、Eager Loading 前提）。
- **NFR-mentoring-008**: The system shall `meetings:auto-complete` Schedule Command が複数台で並行起動しても重複処理しないよう、`DB::transaction()` + `LOCK IN SHARE MODE` または Eloquent の `lockForUpdate()` で対象行を取得する。

## スコープ外

- **Google Calendar OAuth 連携 / 空き枠取得 / event 作成・削除 / Google Meet URL 自動生成**: Advance 範囲で扱う。Basic では `meeting_url_snapshot` は coach の固定 URL のみで、Google Meet URL の動的生成は行わない
- **臨時シフト / 例外除外日**: COACHTECH LMS にある `EmployeeTemporaryShift` / `EmployeeShiftExcludeDate` 相当は **採用しない**。「この月曜だけ休み」は CoachAvailability の `is_active` 一時切替か、該当日の枠を一時的に削除する運用で対応
- **ビット演算による空き枠計算**: COACHTECH 流の 30 分刻みビット演算は採用せず、Eloquent range クエリで素直に算出（Basic では十分）
- **動画通話 / 録画機能**: 外部ツール（Zoom / Google Meet 等）想定。`meeting_url_snapshot` を共有するのみ（`product.md` スコープ外）
- **コーチ間の面談枠交換 / 担当コーチ変更時の Meeting 自動移譲**: 担当コーチ集合の変更は [[certification-management]] の責務、本 Feature では行わない（既存予約は維持、新規予約から新コーチ集合反映）
- **`CoachAvailability` 編集 UI（`/settings/availability`）**: モデル / Policy は本 Feature 所有だが、編集 Controller / FormRequest / Blade は [[settings-profile]] が所有
- **`users.meeting_url` 編集 UI**: 同上、[[settings-profile]] が所有（コーチ初回入力は [[auth]] のオンボーディングが必須化）
- **承認・拒否フロー**: **撤回**（自動コーチ割当に統一、申請・承認概念なし）
- **承認時にコーチが個別 `meeting_url` を上書き入力**: 採用しない。固定 URL の焼き込みのみ。Advance で Google Meet URL の動的生成が入った時点でその URL が `meeting_url_snapshot` に入る
- **複数 workspace 概念**: Certify は 1 LMS = 1 workspace 想定で、COACHTECH の `employee_workspace` 構造は持ち込まない
- **受講生宛 予約完了通知**: 撤回（予約 UI で即時確認のため通知不要）
- **コーチ指名予約**（受講生が特定コーチを選んで予約）: 採用しない、自動割当のみ
- **`Meeting.status = in_progress`（面談中）**: 採用しない。LMS は外部ツールでの実施を仲介するのみ、面談中の状態管理は持たない

## 関連 Feature

- **依存元**（本 Feature を利用する）:
  - [[dashboard]]: 受講生 Dash の「面談予定」/ コーチ Dash の「今日の面談」(**admin Dash の「コーチ稼働状況」は v3(D3) で撤回**)
  - [[notification]]: 状態変化通知の発火点（本 Feature が `NotifyMeeting*Action` を呼ぶ、コーチ宛のみ）
  - [[settings-profile]]: `CoachAvailability` 編集 UI と `users.meeting_url` 編集 UI を所有（本 Feature は Model + Policy 提供）
  - [[user-management]]: admin がユーザー詳細画面で `meeting_url` を含むプロフィールを閲覧する際に本 Feature の Model を間接参照
  - [[meeting-quota]]: `MeetingQuotaService::remaining` で残数チェック、`ConsumeQuotaAction` / `RefundQuotaAction` で消費・返却
- **依存先**（本 Feature が前提とする）:
  - [[auth]]: `User` モデル / `UserRole` enum / `EnsureUserRole` middleware / **`EnsureActiveLearning` Middleware**（`graduated` ユーザーの面談予約をブロック）/ セッション認証 / コーチオンボーディング時の `meeting_url` 必須入力
  - [[auth]]: `users.meeting_url` カラム追加 Migration は **[[auth]] が所有**（D4 確定）、本 Feature は読み取り側として `Meeting\StoreAction` 内で `meeting_url_snapshot` に焼き込む
  - [[enrollment]]: `Enrollment` モデル / `Enrollment.user_id`（受講生 ID）。`assigned_coach_id` は持たないため使わない
  - [[certification-management]]: `Certification` モデル + `certification_coach_assignments` Pivot（担当コーチ集合の取得 + `Certification.coaches()` BelongsToMany リレーション）
  - [[meeting-quota]]: `MeetingQuotaService` / `ConsumeQuotaAction` / `RefundQuotaAction` / `MeetingQuotaTransaction` モデル
