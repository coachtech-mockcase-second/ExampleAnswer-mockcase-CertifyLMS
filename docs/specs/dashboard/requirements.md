# dashboard 要件定義

## 概要

ログイン直後の `/dashboard` で、ロール（admin / coach / student）に応じた学習・運用状況のサマリーを集約表示する **読み取り専用** Feature。本 Feature は独自モデル / 独自集計 Service を持たず、`product.md` 集計責務マトリクスに従って他 Feature が公開している Service（`ProgressService` / `StreakService` / `LearningHourTargetService` / `StagnationDetectionService` / `WeaknessAnalysisService` / `EnrollmentStatsService` / `CompletionEligibilityService` / `CoachActivityService` / `ChatUnreadCountService`）と Eloquent モデルを DI で消費する集約点として機能する。サイドバーの未対応件数バッジは `App\View\Composers\SidebarBadgeComposer`（Wave 0b 整備済）と同一 Service を再利用し、dashboard 本体とバッジで集計値の二重実装を排除する。

## ロールごとのストーリー

- **受講生（student）**: ログイン直後に「試験日までの残り日数 / 受講中資格の進捗 / 学習ストリーク / 直近模試の合格可能性スコア / 学習時間目標の達成具合 / 個人目標タイムライン / 直近通知 / 今後の面談予定」を 1 画面で確認し、能動的に次の学習行動を決める。修了条件（公開模試すべて合格）を満たした時は修了申請ボタンを押せる。
- **コーチ（coach）**: ログイン直後に「担当受講生一覧と最終活動日 / 今日・明日の面談予定 / 未対応 chat 件数 / 未回答 Q&A 件数 / 担当受講生の弱点カテゴリ集計 / 滞留検知（自分の担当のみ）/ 受講生メモの最近更新 / コーチ宛通知」を 1 画面で確認し、介入優先度を判断する。
- **管理者（admin）**: ログイン直後に「全体 KPI（learning / paused / passed / failed / 修了申請待ち）/ 資格別の受講中人数 / 修了申請待ち一覧 / 滞留検知（全受講生）/ コーチ稼働状況 / admin 宛通知」を 1 画面で確認し、プラットフォーム運営状態を把握する。

## 受け入れ基準（EARS形式）

### 機能要件 — 共通動作（ルーティング / ロール分岐 / 集計再利用）

- **REQ-dashboard-001**: The system shall `GET /dashboard` を `dashboard.index` の名前付きルートとして提供し、`auth` middleware で未ログインユーザーを `/login` へリダイレクトする。
- **REQ-dashboard-002**: When 認証済ユーザーが `/dashboard` にアクセスした際, the system shall `auth()->user()->role` を判定し、`admin` → `dashboard/admin.blade.php` / `coach` → `dashboard/coach.blade.php` / `student` → `dashboard/student.blade.php` のロール専用 Blade を描画する。
- **REQ-dashboard-003**: The system shall ロール別 Blade を 3 ファイルに分離し、単一 Blade 内での `@switch($user->role)` 分岐は採用しない。
- **REQ-dashboard-004**: The system shall ロール別ウィジェット集合を Blade ファイル単位で分離することで、ログインユーザーは自分のロール向け Blade 以外を描画されない。
- **REQ-dashboard-005**: The system shall `App\View\Composers\SidebarBadgeComposer` で集計するバッジ値（未対応 chat 件数 / 未回答 Q&A 件数 / 修了申請待ち件数 / 今日の面談件数 / 未読通知件数 / 滞留検知件数 等）と dashboard 本体の同等値を **同一 Service / 同一クエリ** から取得し、数字が乖離しない構造で実装する。
- **REQ-dashboard-006**: While 各セクションが空データを返す場合, the system shall 「データを取得できませんでした」ではなく **意味のある empty state 文言と関連 Feature への CTA リンク** を表示する（例: 「受講中の資格がありません → 資格カタログへ」「学習活動がありません → 教材を開く」）。
- **REQ-dashboard-007**: The system shall 各ウィジェットを独立してレンダリングし、1 つの Service 呼び出しが例外を投げた場合は該当ウィジェットだけが「データを取得できませんでした」表示となり、画面全体が 500 エラーで落ちない。
- **REQ-dashboard-008**: The system shall ロール別 Blade と Action（`Fetch{Role}DashboardAction`）の対応関係を 1:1 に保ち、admin / coach / student の各 Action は他ロール向け Action を内部呼出しない。
- **REQ-dashboard-009**: The system shall dashboard 内でデータの作成・更新を行わず、CRUD アクションは各 Feature のルートへの遷移 / form submit に限定する（例外: 修了申請ボタンのみ受講生 dashboard 内から `enrollments.completion-request.store` ルートへ POST する、REQ-dashboard-170）。
- **REQ-dashboard-010**: The system shall ロール別の `/dashboard` への自動リダイレクトを行わず、admin / coach / student いずれも `/dashboard` で自分のロール用ホームを表示する（iField 流の admin → `/users` リダイレクトは採用しない）。

### 機能要件 — 受講生ダッシュボード（資格カード）

- **REQ-dashboard-100**: The system shall ログイン受講生の `status IN (learning, paused)` Enrollment のみを対象に受講中資格カード一覧を表示し、`passed` / `failed` の Enrollment は本画面に表示しない（履歴閲覧は `/enrollments` 経由）。
- **REQ-dashboard-110**: The system shall 各受講中資格カードに **試験日カウントダウン**（`Enrollment.exam_date - 今日` の日数差）を表示し、`exam_date < today` の場合は「試験日を過ぎています」表示にする。
- **REQ-dashboard-120**: The system shall 各受講中資格カードに `App\Services\ProgressService::summarize($enrollment)` の戻り値 `ProgressSummary.overallCompletionRatio` を進捗ゲージ（0〜100%）として表示する。
- **REQ-dashboard-121**: The system shall 各受講中資格カードに `Enrollment.current_term`（`basic_learning` / `mock_practice`）を表示する。
- **REQ-dashboard-130**: The system shall 各受講中資格カードに `App\Services\LearningHourTargetService::compute($enrollment)` の戻り値 `LearningHourTargetSummary`（target_total_hours / studied_total_hours / remaining_hours / remaining_days / daily_recommended_hours / progress_ratio）を表示する。
- **REQ-dashboard-131**: If `LearningHourTargetSummary.targetTotalHours === 0`（学習時間目標未設定）, then the system shall 「学習時間目標が未設定です」と表示し、`/enrollments/{enrollment}/hour-target/edit` への CTA リンクを提示する。
- **REQ-dashboard-140**: The system shall 各受講中資格カードに `App\Services\WeaknessAnalysisService::getPassProbabilityBand($enrollment)` の戻り値（`Safe` / `Warning` / `Danger` / `Unknown`）を `合格圏` / `注意` / `危険` / `判定不可` のバッジで表示する。
- **REQ-dashboard-150**: The system shall 各受講中資格カードに `App\Services\WeaknessAnalysisService::getWeakCategories($enrollment)` の上位 3 件の QuestionCategory 名をチップで表示し、ゼロ件時は「弱点はまだ検出されていません」と表示する。
- **REQ-dashboard-151**: The system shall 弱点カテゴリチップから [[quiz-answering]] の苦手ドリル画面（`/quiz/drill?enrollment={id}&category={category_id}`）への遷移リンクを提供する。

### 機能要件 — 受講生ダッシュボード（修了申請ボタン）

- **REQ-dashboard-160**: The system shall 受講中資格カード内に **修了申請ボタン** を配置し、活性判定は `App\Services\CompletionEligibilityService::isEligible($enrollment) === true && $enrollment->status === EnrollmentStatus::Learning && $enrollment->completion_requested_at === null` の論理積で行う。
- **REQ-dashboard-161**: While 修了申請ボタンが活性, the system shall ボタン押下時に `POST /enrollments/{enrollment}/completion-request` （[[enrollment]] 所有ルート、`enrollments.completion-request.store`）へ form submit する。
- **REQ-dashboard-162**: If `CompletionEligibilityService::isEligible === false`, then the system shall ボタンを不活性表示し「公開模試すべての合格点超えが条件です」のヒントを併記する。
- **REQ-dashboard-163**: While `$enrollment->completion_requested_at !== null && $enrollment->status === EnrollmentStatus::Learning`, the system shall 「修了申請中」バッジと **取消ボタン** を表示し、取消は `DELETE /enrollments/{enrollment}/completion-request` （`enrollments.completion-request.destroy`）へ form submit する。
- **REQ-dashboard-164**: If `$enrollment->status === EnrollmentStatus::Passed`, then the system shall 「修了済み」バッジと修了証 PDF ダウンロードリンク（[[certification-management]] の `certificates.download`）を表示する。

### 機能要件 — 受講生ダッシュボード（横断ウィジェット）

- **REQ-dashboard-200**: The system shall `App\Services\StreakService::calculate($student)` の戻り値 `StreakSummary`（currentStreak / longestStreak / lastActiveDate）を学習ストリークパネルに表示する。
- **REQ-dashboard-201**: If `StreakSummary.currentStreak === 0 && StreakSummary.lastActiveDate === null`, then the system shall 「まずは Section を読了して連続学習を始めましょう」と表示し、教材一覧 `/contents` への CTA リンクを提示する。
- **REQ-dashboard-210**: The system shall ログイン受講生の `EnrollmentGoal`（受講中資格に紐づくもの）を **目標タイムライン** として Wantedly 風に表示し、ソートは「未達成（target_date 昇順 → 期限なしは末尾）→ 達成済（achieved_at 降順）」で混在配列とする。
- **REQ-dashboard-211**: The system shall 目標タイムラインに各目標の `title` / `description` / `target_date` / `achieved_at` / 紐づく `Certification.name` を表示し、各行から [[enrollment]] の目標編集画面 `/enrollments/{enrollment}/goals/{goal}/edit` への遷移リンクを提供する。
- **REQ-dashboard-212**: If 受講生が `EnrollmentGoal` を 1 件も持たない場合, then the system shall 目標タイムライン領域に「目標を設定しましょう」CTA と `/enrollments/{enrollment}/goals/create` への遷移リンクを表示する。
- **REQ-dashboard-220**: The system shall ログイン受講生の `notifications()` の最新 5 件を直近通知パネルに表示し、各行から通知一覧画面 `/notifications` への「すべて見る」リンクを提示する。
- **REQ-dashboard-221**: The system shall 未読通知件数（`$user->unreadNotifications()->count()`）を直近通知パネルのヘッダに件数バッジとして表示する。
- **REQ-dashboard-230**: The system shall ログイン受講生が当事者の `Meeting` のうち `status IN (approved, in_progress)` かつ `scheduled_at >= 今日の 0 時` の最大 5 件を昇順で「今後の面談予定」として表示し、各行から `/meetings/{meeting}` 詳細画面への遷移リンクを提供する。
- **REQ-dashboard-231**: If 今後の面談予定がゼロ件, then the system shall 「面談予定はありません」と表示し、`/meetings/create` への CTA リンクを提示する。
- **REQ-dashboard-240**: If 受講中 Enrollment（`status IN (learning, paused)`）が 1 件もない場合, then the system shall 「まだ資格を受講していません。資格カタログから登録してください」と表示し、`/certifications` への CTA リンクのみを表示し、他ウィジェット（ストリーク・目標タイムライン・面談・通知）は通常通り表示する。

### 機能要件 — コーチダッシュボード（担当受講生 / 介入）

- **REQ-dashboard-300**: The system shall `Enrollment::where('assigned_coach_id', $coach->id)->whereIn('status', [Learning, Paused])` を起点に担当受講生一覧を表示し、`with(['user', 'certification', 'learningSessions' => fn ($q) => $q->latest('started_at')->limit(1)])` で 1 リクエスト 1 クエリ束ねる。
- **REQ-dashboard-301**: The system shall 担当受講生一覧に各 Enrollment の `user.name` / `certification.name` / 現在ターム / `ProgressService::summarize` の overallCompletionRatio / `StagnationDetectionService::lastActivityAt($enrollment)` の最終活動日時を表示する。
- **REQ-dashboard-302**: The system shall 担当受講生一覧を **最終活動日時降順** にソートする（null は末尾）。
- **REQ-dashboard-310**: The system shall ログインコーチの `Meeting::where('coach_id', $coach->id)->whereIn('status', [Approved, InProgress])->whereBetween('scheduled_at', [今日 0:00, 明日 23:59])` を「今日 / 明日の面談予定」として時系列昇順で表示する。
- **REQ-dashboard-320**: The system shall `App\Services\ChatUnreadCountService::roomCountForUser($coach)` の戻り値（自分が宛先で未読のある ChatRoom 件数）を未対応 chat 件数として表示する。
- **REQ-dashboard-321**: The system shall コーチ宛て直近の未対応 / 対応中 ChatRoom 最大 5 件（`ChatRoom::where('enrollment.assigned_coach_id', $coach->id)->whereIn('status', [Unattended, InProgress])->orderByLastMessage()->limit(5)`）をリスト表示し、各行から `route('chat.show', $room)` 詳細画面への遷移リンクを提供する（chat 詳細は admin / coach / student 共通 URL `/chat-rooms/{room}`）。
- **REQ-dashboard-330**: The system shall 担当資格スコープの未回答 `QaThread`（`certification_id IN ($coach 担当資格 ids) AND status = QaThreadStatus::Open`）の件数と直近 5 件を表示し、各行から `route('qa-board.show', $thread)` への遷移リンクを提供する（qa-board 詳細は coach / student 共通 URL `/qa-board/{thread}`）。
- **REQ-dashboard-340**: The system shall 担当受講生の Enrollment 集合に対して `WeaknessAnalysisService::getWeakCategories` を集約し（QuestionCategory ごとの出現回数で降順ソート、上位 5 件）、「担当受講生の頻出弱点カテゴリ」として表示する。
- **REQ-dashboard-350**: The system shall `StagnationDetectionService::detectStagnant()` の戻り値を `assigned_coach_id = $coach->id` でフィルタした Collection を「滞留検知（自分の担当）」として表示し、各行から該当 enrollment 詳細への遷移リンクを提供する。
- **REQ-dashboard-360**: The system shall `EnrollmentNote::whereHas('enrollment', fn ($q) => $q->where('assigned_coach_id', $coach->id))->latest('updated_at')->limit(5)` を「最近更新した受講生メモ」として表示し、各行から enrollment 詳細画面（`/enrollments/{enrollment}/edit` の memo セクション）への遷移リンクを提供する（dashboard 内に inline 編集 UI は持たない）。
- **REQ-dashboard-370**: The system shall コーチ宛て直近通知 5 件と未読件数を表示し、「すべて見る」で `/notifications` へ遷移する。
- **REQ-dashboard-380**: If 担当 Enrollment が 1 件もない場合, then the system shall 「担当している受講生がまだいません。管理者が割当てると一覧が表示されます」と表示し、他ウィジェット（通知・chat 件数・QA 件数）は通常表示する。

### 機能要件 — 管理者ダッシュボード（運用 KPI / 修了承認 / 介入リスト）

- **REQ-dashboard-500**: The system shall `App\Services\EnrollmentStatsService::adminKpi()` の戻り値 `array{learning_count, paused_count, passed_count, failed_count, pending_count, by_certification}` を全体 KPI パネルに表示する。
- **REQ-dashboard-501**: The system shall `by_certification`（Certification ごとの learning 件数）を上位 10 件まで表示し、各行から該当資格詳細 `/admin/certifications/{certification}` への遷移リンクを提供する。
- **REQ-dashboard-510**: The system shall `Enrollment::pending()` スコープ（`completion_requested_at IS NOT NULL AND status = learning`、[[enrollment]] が提供）の戻り値の最新 10 件を「修了申請待ち一覧」として表示する。
- **REQ-dashboard-511**: The system shall 修了申請待ち一覧の各行に `user.name` / `certification.name` / `completion_requested_at`（経過日数）を表示し、`/admin/enrollments/{enrollment}/edit#completion-approval` への遷移リンクを提供する。
- **REQ-dashboard-512**: The system shall 修了申請待ち件数を全体 KPI パネルの `pending_count` と一致させ、サイドバー `修了申請承認 [check-badge] (N)` バッジとも同期する（同一 Service / 同一クエリを使用、REQ-dashboard-005 と同根）。
- **REQ-dashboard-520**: The system shall `StagnationDetectionService::detectStagnant()` の戻り値（全受講生）の最新 10 件を「滞留検知リスト」として表示し、各行から `/admin/users/{user}` への遷移リンクを提供する。
- **REQ-dashboard-530**: The system shall `App\Services\CoachActivityService::summarize()` の戻り値（直近 30 日の coach × 面談実施 / キャンセル / 拒否件数 / 平均メモ長）を「コーチ稼働状況」として表示し、件数降順上位 10 件まで表示する。
- **REQ-dashboard-540**: The system shall admin 宛て直近通知 5 件と未読件数を表示し、「すべて見る」で `/notifications` へ遷移する。
- **REQ-dashboard-550**: If 全体 KPI のすべてが 0（プラットフォーム初期状態）, then the system shall 「まずは受講生を招待してください」CTA と `route('admin.users.index')`（招待モーダル動線を含む一覧画面）への遷移リンクを目立つ位置に表示する。

### 非機能要件

- **NFR-dashboard-001**: The system shall 各ロールの dashboard 描画で使用する Service / Eloquent クエリの合計を **20 クエリ以内** に収める（admin / coach は集計が多いため上限 25 クエリまで許容、Basic 段階の目安。Advance 範囲で `db:monitor` / インデックス最適化の題材）。
- **NFR-dashboard-002**: The system shall 担当受講生一覧 / 受講中資格カード一覧で N+1 を発生させないよう、各 Action 内で `with([...])` Eager Loading を Service 呼び出し **前** に確定させる。
- **NFR-dashboard-003**: The system shall dashboard 独自の集計 Service を新設せず、[[learning]] / [[mock-exam]] / [[enrollment]] / [[mentoring]] / [[chat]] が公開する Service のみを消費する（`product.md` 集計責務マトリクス遵守）。
- **NFR-dashboard-004**: The system shall WCAG 2.1 AA 相当のアクセシビリティを満たす（focus-visible リング / 装飾アイコンの `aria-hidden` / 意味的アイコンの `aria-label` / コントラスト 4.5:1 / KPI 数値の `aria-live` polite）。
- **NFR-dashboard-005**: The system shall キャッシュ層を持たず、各リクエストで都度集計する（Basic 段階）。Cache facade / Redis 等は本 Feature では使用しない。
- **NFR-dashboard-006**: The system shall ロール別 Blade を `resources/views/dashboard/{admin,coach,student}.blade.php` の 3 ファイルに分離し、ロール共有の部分は `_partials/{widget}.blade.php` に切り出す。
- **NFR-dashboard-007**: The system shall Action（`Fetch{Role}DashboardAction`）の戻り値を readonly ViewModel DTO（`{Role}DashboardViewModel`）として定義し、Blade からはプロパティアクセスのみで描画可能にする（Blade 内でロジック / クエリを書かない）。
- **NFR-dashboard-008**: The system shall dashboard 専用 Policy / Migration / Model を作らず、認可は `auth` middleware + 各 Feature の Service / Eloquent が内部で行うフィルタリングに委ねる。

## スコープ外

- リアルタイム自動更新（Pusher / WebSocket）— [[notification]] の Advance Broadcasting でベル通知のみリアルタイム化、dashboard 本体は Basic 段階では都度リロード方式
- クライアントサイドキャッシュ / SWR 的なフェッチ戦略
- dashboard 内データの作成・更新（修了申請ボタン / 取消ボタンは例外的に [[enrollment]] のルートへ submit）
- 全文検索 / フィルタ UI（dashboard は俯瞰用途、深掘りは [[enrollment]] / [[mock-exam]] / [[chat]] / [[qa-board]] 等の各 Feature 一覧画面）
- ロール混在 dashboard（admin が coach 用ウィジェットを見る等）— 1 ユーザー = 1 ロールが LMS 全体の前提（[[auth]] / [[user-management]] 準拠）
- 「学習を再開する」CTA の「最後に学習していた Section」への直接遷移 — Phase 0 議論で B 案採用、受講中資格カードからターム別の遷移先（basic_learning → 教材トップ / mock_practice → 模試一覧）に縮退
- dashboard 内 EnrollmentNote 編集 UI — Phase 0 議論で A 案採用、一覧 + 詳細画面リンクのみ
- 独自集計 Service の新設 — `product.md` 集計責務マトリクスで dashboard 所有の Service が 0 件
- dashboard の各 widget に対する個別 Policy — 集計値は他 Feature の Service が内部で `assigned_coach_id` / `user_id` フィルタを既に持ち、本 Feature で別途 Policy を被せる必要なし

## 関連 Feature

- **依存先**（本 Feature が前提とする）:
  - [[learning]] — `ProgressService` / `StreakService` / `LearningHourTargetService` / `StagnationDetectionService` を DI 消費
  - [[mock-exam]] — `WeaknessAnalysisService` を DI 消費
  - [[enrollment]] — `EnrollmentStatsService` / `CompletionEligibilityService` を DI 消費、`Enrollment` / `EnrollmentGoal` / `EnrollmentNote` を読み取り、`enrollments.completion-request.store/destroy` ルートへ submit
  - [[mentoring]] — `CoachActivityService` を DI 消費、`Meeting` を読み取り
  - [[chat]] — `ChatUnreadCountService` を DI 消費、`ChatRoom` を読み取り
  - [[qa-board]] — `QaThread` を担当資格スコープで読み取り
  - [[notification]] — `User::notifications()` / `User::unreadNotifications()` を読み取り、通知一覧画面 `/notifications` へ遷移
  - [[certification-management]] — `Certification` / `Certificate` を読み取り、`certificates.download` への遷移
  - [[auth]] — `User` / `UserRole` Enum
  - [[user-management]] — `/admin/users/{user}` への遷移（admin 滞留検知リストから）

- **依存元**（本 Feature を利用する）:
  - なし（dashboard は他 Feature から呼ばれず、エンドユーザーが直接アクセスする集約画面）
