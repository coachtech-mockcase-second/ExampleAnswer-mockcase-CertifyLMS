# dashboard 要件定義

> **v3 改修反映**（2026-05-16）: 受講生に **修了済資格セクション**（PDF DL のみ、復習モード遷移リンクは撤回）+ **プラン情報パネル**（残面談回数 + プラン残日数 + 追加面談購入 CTA）追加、admin の **修了申請待ち一覧 / プラン期限切れ間近一覧 / 滞留検知 / 直近通知** 削除（運用モニタリング MVP 最小限 + notification spec REQ-026「admin 宛通知は発火しない」整合）、coach の **滞留検知リスト / 受講生メモ / 弱点カテゴリ集約 / 最終活動日降順ソート** 削除、coach の担当範囲を「担当受講生」→「**担当資格に登録した受講生**」に変更、graduated 専用ダッシュボードを **修了済資格一覧のみ** に縮小（卒業日 / プラン機能ロック表示 / プロフィール閲覧リンク削除）。

## 概要

ログイン直後の `/dashboard` で、ロール（admin / coach / student）に応じた学習・運用状況のサマリーを集約表示する **読み取り専用** Feature。本 Feature は独自モデル / 独自集計 Service を持たず、他 Feature が公開している Service（`ProgressService` / `StreakService` / `LearningHourTargetService` / `WeaknessAnalysisService` / `EnrollmentStatsService` / `CompletionEligibilityService` / `ChatUnreadCountService` / `MeetingQuotaService` / `PlanExpirationService`）と Eloquent モデルを DI で消費する集約点として機能する。**`StagnationDetectionService` は v3 で撤回されたため利用しない**。**`CoachActivityService` も v3(D3 確定)で dashboard では呼ばない**(個別管理画面で必要なら mentoring が提供を続ける)。

## ロールごとのストーリー

- **受講生（student）**: ログイン直後に「**プラン情報**（残面談回数 + プラン残日数 + 追加面談購入 CTA） / 試験日カウントダウン / 受講中資格の進捗 / 学習ストリーク / 直近模試の合格可能性スコア / 学習時間目標 / 個人目標タイムライン / **修了済資格セクション** / 直近通知 / 今後の面談予定」を 1 画面で確認し、能動的に次の学習行動を決める。修了条件（公開模試すべて合格）を満たした受講中資格は **「修了証を受け取る」ボタン** を押下できる（[[enrollment]] の `ReceiveCertificateAction` 起動）。
- **コーチ（coach）**: ログイン直後に「**担当資格に登録した受講生** の一覧（最終活動日表示） / 今日・明日の面談予定 / 未読 chat 件数 / 未回答 Q&A 件数 / コーチ宛通知」を 1 画面で確認し、介入優先度を判断する。**弱点カテゴリ集計 / 受講生メモの最近更新 / 最終活動日降順ソートは v3 撤回**（個別画面で対応）。
- **管理者（admin）**: ログイン直後に「全体 KPI（learning / passed / failed Enrollment 件数）/ 資格別の受講中人数 / 資格別修了率」を 1 画面で確認する。**修了申請承認 / プラン期限切れ間近一覧 / 滞留検知 / 直近通知は v3 撤回**（admin 宛通知は notification spec REQ-026 で発火しないため、運用情報は本 dashboard / 各 Feature 管理画面で集約）。

## 受け入れ基準（EARS形式）

### 機能要件 — 共通動作（ルーティング / ロール分岐）

- **REQ-dashboard-001**: The system shall `GET /dashboard` を `dashboard.index` ルートとして提供し、`auth` middleware で未ログインユーザーを `/login` へリダイレクトする。
- **REQ-dashboard-002**: When 認証済ユーザーが `/dashboard` にアクセスした際, the system shall `auth()->user()->role` を判定し、`admin` → `dashboard/admin.blade.php` / `coach` → `dashboard/coach.blade.php` / `student` → `dashboard/student.blade.php` を描画する。
- **REQ-dashboard-003**: The system shall ロール別 Blade を 3 ファイルに分離し、`@switch($user->role)` 分岐は採用しない。
- **REQ-dashboard-004**: When `User.status === UserStatus::Graduated` のユーザーが `/dashboard` にアクセスした際, the system shall **graduated 専用の縮減ダッシュボード** を描画する（修了証 PDF 一覧 + プロフィール閲覧リンクのみ、プラン機能はロック表示）。
- **REQ-dashboard-005**: The system shall `App\View\Composers\SidebarBadgeComposer` で集計するバッジ値（未読 chat 件数 / 未回答 Q&A 件数 / 今日の面談件数 / 未読通知件数）と dashboard 本体の同等値を **同一 Service / 同一クエリ** から取得し数字が乖離しないようにする。**admin ロールの notifications バッジは常時 0** とする（notification spec REQ-026「admin 宛通知は発火しない」整合）。v3 撤回の `pendingCompletions` キーは Composer から削除する。
- **REQ-dashboard-006**: While 各セクションが空データを返す場合, the system shall 「データを取得できませんでした」ではなく **意味のある empty state 文言と関連 Feature への CTA リンク** を表示する。
- **REQ-dashboard-007**: The system shall 各ウィジェットを独立してレンダリングし、1 つの Service 呼び出しが例外を投げた場合は該当ウィジェットだけが「データを取得できませんでした」表示となり、画面全体が 500 エラーで落ちない。Action 内 `safe()` ヘルパー（`try { return $fn(); } catch (\Throwable $e) { report($e); return null; }`）で各セクション build を包み、ViewModel プロパティを **nullable** にして Blade で null 判定する。

### 機能要件 — 受講生ダッシュボード（プラン情報パネル）

- **REQ-dashboard-100**: The system shall ログイン受講生の dashboard 上部に **プラン情報パネル** を最重要セクションとして配置する。表示内容:
  - `User.plan.name`（Plan 名）
  - **プラン残日数** = `(plan_expires_at - now())` 日数（負なら「期限切れ」表示、ただし graduated 遷移後は graduated 専用ダッシュボードに遷移するため通常は到達しない）
  - **残面談回数** = `MeetingQuotaService::remaining(auth_user)`
  - **追加面談購入 CTA** = `MeetingPack` 一覧（admin が CRUD）から選択 → Stripe checkout に遷移
- **REQ-dashboard-101**: When プラン情報パネルで「残面談回数: 0」の場合, the system shall **追加面談購入 CTA を強調表示**（「面談回数を購入する」ボタンを目立つ位置に配置、`MeetingPack` 一覧をモーダル表示）。

### 機能要件 — 受講生ダッシュボード（受講中資格カード）

- **REQ-dashboard-110**: The system shall ログイン受講生の `status IN (learning, passed)` Enrollment を対象に受講中資格カード一覧を表示し、`passed` の Enrollment は **修了済バッジ + 復習モード表示** とする（status による機能制限なし）。`failed` は本一覧から除外（履歴は `/enrollments` で確認）。
- **REQ-dashboard-111**: The system shall 各受講中資格カードに **試験日カウントダウン**（`Enrollment.exam_date - 今日`、`exam_date IS NULL` の場合は「未設定」表示）を表示する。
- **REQ-dashboard-120**: The system shall 各受講中資格カードに `ProgressService::summarize($enrollment)` の `overallCompletionRatio` を進捗ゲージ表示する。
- **REQ-dashboard-121**: The system shall 各受講中資格カードに `Enrollment.current_term`（`basic_learning` / `mock_practice`）を表示する。
- **REQ-dashboard-130**: The system shall 各受講中資格カードに `LearningHourTargetService::compute($enrollment)` の戻り値を表示する。
- **REQ-dashboard-140**: The system shall 各受講中資格カードに `WeaknessAnalysisService::getPassProbabilityBand($enrollment)` のバッジ（合格圏 / 注意 / 危険 / 判定不可）を表示する。
- **REQ-dashboard-150**: The system shall 各受講中資格カードに `WeaknessAnalysisService::getWeakCategories($enrollment)` の上位 3 件をチップで表示する。
- **REQ-dashboard-151**: The system shall 弱点カテゴリチップから [[quiz-answering]] の苦手ドリル画面（`/quiz/drills/{enrollment}/categories/{category_id}`）への遷移リンクを提供する。

### 機能要件 — 受講生ダッシュボード（修了証を受け取るボタン）

- **REQ-dashboard-160**: The system shall 受講中資格カード内に **「修了証を受け取る」ボタン** を配置し、活性判定は `CompletionEligibilityService::isEligible($enrollment) === true && $enrollment->status === EnrollmentStatus::Learning` の論理積で行う。
- **REQ-dashboard-161**: While ボタンが活性, the system shall 押下時に `POST /enrollments/{enrollment}/receive-certificate`（[[enrollment]] 所有ルート）へ form submit する。
- **REQ-dashboard-162**: If `CompletionEligibilityService::isEligible === false`, then the system shall ボタンを不活性表示し「公開模試すべての合格点超えが条件です」のヒントを併記する。
- **REQ-dashboard-163**: If `$enrollment->status === EnrollmentStatus::Passed`, then the system shall 「修了済み」バッジと修了証 PDF ダウンロードリンク（[[certification-management]] の `certificates.download`）を表示する。

### 機能要件 — 受講生ダッシュボード（修了済資格セクション、v3 新規）

- **REQ-dashboard-170**: The system shall ログイン受講生の `Enrollment::where('user_id', $student->id)->where('status', EnrollmentStatus::Passed)->whereNotNull('passed_at')->with('certification', 'certificate')->orderByDesc('passed_at')->get()` を **修了済資格セクション** として表示する。
- **REQ-dashboard-171**: The system shall 修了済資格セクションの各行に `certification.name` / `passed_at`（年月日 + 経過日数）/ **修了証 PDF DL リンク**（`certificates.download`）を表示する。**復習モード遷移リンクは v3 撤回**（受講中資格カードに復習導線が集約されるため）。
- **REQ-dashboard-172**: When 修了済資格が 1 件もない場合, the system shall 「まだ修了した資格はありません」と表示し、現受講中資格を頑張るメッセージを併記する。
- **REQ-dashboard-173**: When `User.status === UserStatus::Graduated` のユーザーの場合, the system shall **修了済資格一覧のみ** を表示する graduated 専用ダッシュボードに遷移し、修了証 PDF DL は永続可能とする。**卒業日 / プラン機能ロック表示 / プロフィール閲覧リンクは v3 撤回**（dashboard 中核は達成記録に集中、プロフィール編集はサイドバーから直接アクセス）。

### 機能要件 — 受講生ダッシュボード（横断ウィジェット）

- **REQ-dashboard-200**: The system shall `StreakService::calculate($student)` を学習ストリークパネルに表示する。
- **REQ-dashboard-210**: The system shall ログイン受講生の `EnrollmentGoal`（受講中資格に紐づく）を **目標タイムライン** として Wantedly 風表示する。
- **REQ-dashboard-220**: The system shall `notifications()` の最新 5 件 + 未読件数を直近通知パネルに表示する。
- **REQ-dashboard-230**: The system shall 受講生が当事者の `Meeting` のうち `status = reserved AND scheduled_at >= 今日 0:00` の最大 5 件を昇順で「今後の面談予定」として表示する。
- **REQ-dashboard-240**: If 受講中 Enrollment が 1 件もない場合, then the system shall 「資格カタログから登録してください」CTA を表示する。

### 機能要件 — コーチダッシュボード

- **REQ-dashboard-300**: The system shall ログインコーチの **担当資格に登録した受講生集合** を取得（`Enrollment::whereHas('certification.coaches', fn ($q) => $q->where('users.id', $coach->id))->whereIn('status', [Learning, Passed])`）し、担当受講生一覧として表示する。
- **REQ-dashboard-301**: The system shall 担当受講生一覧に各 Enrollment の `user.name` / `certification.name` / 現在ターム / 進捗率 / 最終活動日（`LearningSession.started_at` の MAX、Action 側で `withMax('learningSessions as last_activity_at', 'started_at')` で集約取得し N+1 回避）を表示する。
- **REQ-dashboard-302**: **削除（v3 撤回）** — 担当受講生一覧の最終活動日降順ソート要件は撤廃する。表示はデフォルト順（DB 主キー順）とし、停滞検知用途は本 Feature の責務外とする。
- **REQ-dashboard-310**: The system shall ログインコーチの `Meeting::where('coach_id', $coach->id)->where('status', Reserved)->whereBetween('scheduled_at', [今日, 明日終]) ` を「今日 / 明日の面談予定」として表示する。
- **REQ-dashboard-320**: The system shall `ChatUnreadCountService::roomCountForUser($coach)` を未読 chat 件数として表示する。
- **REQ-dashboard-321**: The system shall コーチ宛て未読 ChatRoom 最大 5 件をリスト表示する。
- **REQ-dashboard-330**: The system shall 担当資格スコープの未回答 `QaThread` の件数と直近 5 件を表示する。
- **REQ-dashboard-340**: **削除（v3 撤回）** — 担当受講生集合への弱点カテゴリ集約表示は撤廃する（集約値で全体施策に動く意思決定が現場で発生しないため、個別 chat / QA / 面談で対応）。
- **REQ-dashboard-360**: **削除（v3 撤回）** — 最近更新した受講生メモ一覧表示は撤廃する（dashboard 主軸の介入優先度判断は担当受講生一覧 + 未読 chat + 未回答 QA + 面談予定で十分、メモは個別 enrollment 画面で参照）。
- **REQ-dashboard-370**: The system shall コーチ宛て直近通知 5 件 + 未読件数を表示する。
- **REQ-dashboard-380**: If 担当資格に受講生が 1 件もない場合, then the system shall 「担当資格にまだ受講生がいません」と表示する。

### 機能要件 — 管理者ダッシュボード（縮減版）

- **REQ-dashboard-500**: The system shall `EnrollmentStatsService::adminKpi()` の戻り値 `array{learning_count, passed_count, failed_count, by_certification}` を全体 KPI パネルに表示する。**`pending_count`（修了申請待ち）は v3 撤回**（admin 承認フロー削除のため）。
- **REQ-dashboard-501**: The system shall `by_certification` 上位 10 件を表示し、各行から該当資格詳細への遷移リンクを提供する。
- **REQ-dashboard-510**: The system shall **資格別修了率** （`Enrollment::where('certification_id', $cert->id)` のうち `passed` 件数 / 全件数）を表示する。
- **REQ-dashboard-520**: **削除（v3 撤回）** — admin 宛て直近通知表示は撤廃する。notification spec REQ-026 で「`UserRole::Admin` 宛通知は発火しない」と明文化されており、`admin->notifications()` は常に空配列で死に機能になるため。
- **REQ-dashboard-530**: **削除（v3 撤回）**: 旧「修了申請待ち一覧」「プラン期限切れ間近一覧」「滞留検知リスト」「コーチ稼働状況」は提供しない。`CoachActivityService` は維持するが dashboard では呼ばない（必要なら個別管理画面で利用）。
- **REQ-dashboard-540**: If 全体 KPI のすべてが 0 の場合, then the system shall 「まずは Plan を作成してユーザーを招待してください」CTA を表示し、`/admin/plans` / `/admin/users` への遷移リンクを目立つ位置に表示する。

### 非機能要件

- **NFR-dashboard-001**: The system shall 各ロールの dashboard 描画で使用するクエリの合計を **student 30 / coach 25 / admin 20 / graduated 10** クエリ以内に収める。受講中資格カードの進捗集計は `ProgressService::batchCalculate(Collection $enrollments)` を利用して N+1 を回避する。`LearningHourTargetService::compute` / `WeaknessAnalysisService` は dashboard Feature の責務外として最適化不要だが、Enrollment 数の線形増加（1 件あたり 5 クエリ程度）に収まることを保証する。
- **NFR-dashboard-002**: The system shall 担当受講生一覧 / 受講中資格カード一覧で N+1 を発生させないよう `with([...])` Eager Loading + `withMax('learningSessions as last_activity_at', 'started_at')` を Service 呼び出し前に確定させる。
- **NFR-dashboard-003**: The system shall dashboard 独自の集計 Service を新設せず、他 Feature の Service のみを消費する。集約点は `App\UseCases\Dashboard\Fetch{Role}DashboardAction` とし、Service 化しない。
- **NFR-dashboard-004**: The system shall WCAG 2.1 AA 相当のアクセシビリティを満たす。
- **NFR-dashboard-005**: The system shall キャッシュ層を持たず、各リクエストで都度集計する。
- **NFR-dashboard-006**: The system shall ロール別 Blade を `resources/views/dashboard/{admin,coach,student,graduated}.blade.php` の 4 ファイルに分離する。
- **NFR-dashboard-007**: The system shall Action（`Fetch{Role}DashboardAction`）の戻り値を readonly ViewModel DTO として定義し、Blade からはプロパティアクセスのみで描画可能にする。各 build セクションは個別 `safe()` ヘルパー（try/catch + `report()` + null 返却）で包み、ViewModel プロパティを **nullable** として例外時の null を許容、Blade で null 判定して empty-state 表示に切り替える（REQ-dashboard-007 整合）。
- **NFR-dashboard-008**: The system shall dashboard 専用 Policy / Migration / Model を作らず、認可は `auth` middleware + 各 Feature の Service / Eloquent が内部で行うフィルタリングに委ねる。Enrollment Model に `lastLearningSession` リレーションは追加せず、Action 側で `withMax` で集約取得する。

## スコープ外

- 修了申請待ち一覧 / プラン期限切れ間近一覧 / 滞留検知 — v3 撤回
- **admin 宛通知** — notification spec REQ-026 で発火しないため（v3 撤回）
- **coach の担当受講生弱点カテゴリ集約 / 最近更新した受講生メモ** — v3 撤回（個別画面で対応）
- **coach の担当受講生一覧の最終活動日降順ソート** — v3 撤回（ソート要件撤廃、表示のみ残す）
- **修了済資格セクションの復習モード遷移リンク** — v3 撤回（受講中資格カードに復習導線集約）
- **graduated 専用ダッシュボードの卒業日 / プラン機能ロック表示 / プロフィール閲覧リンク** — v3 撤回（修了済資格一覧のみ）
- リアルタイム自動更新 — Pusher は [[notification]] / [[chat]] のみ
- 独自集計 Service の新設
- `Enrollment` Model への `lastLearningSession` リレーション追加 — Action 側で `withMax` で対応

## 関連 Feature

- **依存先**: [[learning]] / [[mock-exam]] / [[enrollment]] / [[mentoring]] / [[chat]] / [[qa-board]] / [[notification]] / [[certification-management]] / [[auth]] / [[user-management]] / [[plan-management]] / [[meeting-quota]]
- **依存元**: なし
