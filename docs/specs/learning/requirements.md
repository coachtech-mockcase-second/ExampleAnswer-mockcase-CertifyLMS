# learning 要件定義

## 概要

受講生の日々の学習体験（**教材ブラウジング / Section 読了マーク / 学習セッション時間トラッキング / 進捗集計 / 学習ストリーク / 学習時間目標 / 滞留検知**）を一体で提供する Feature。Certify LMS のドメイン構造 4 軸（試験日カウントダウン / 合格点ゴール / 問題演習中心 / 苦手分野克服）のうち **「学習量と継続のメーター係」** として機能し、[[dashboard]] / [[notification]] / [[mock-exam]] / [[quiz-answering]] から消費される **4 集計 Service**（`ProgressService` / `StreakService` / `LearningHourTargetService` / `StagnationDetectionService`）を所有する。

- 教材階層（Part → Chapter → Section）は [[content-management]] が所有する Eloquent Model を受講生視点で **読み取り再利用**する。本 Feature は CRUD を持たない
- Section の読了は `section_progresses` テーブルで 1 Enrollment × 1 Section の最大 1 行（再マークは UPDATE）で管理する
- 学習時間は **Section 詳細ページ滞在時間**を `learning_sessions` で記録する（mock-exam 受験時間や AI チャット時間は本 Feature の集計対象外、各 Feature 側で別途トラッキング）
- 学習ストリーク・滞留検知は `learning_sessions.started_at` の DISTINCT DATE を「学習活動」と定義する（Basic スコープ。広域な活動定義への拡張は Advance 領域）
- 学習時間目標は資格単位（1 Enrollment = 0 or 1 LearningHourTarget）で `target_total_hours` のみを持ち、残り時間 / 残り日数 / 日次推奨ペースは `LearningHourTargetService` がクエリ時に算出する

## ロールごとのストーリー

- **受講生（student）**: 受講中の資格を選び、Part → Chapter → Section と階層を辿って教材本文を読む。Section 詳細ページに入ると裏側で学習セッションが自動開始され、離脱時に終了して時間が記録される。読了マークを付けると Section / Chapter / Part / 資格の完了率が即座に更新される。ダッシュボードで連続学習日数（ストリーク）と試験日までの残り時間・日次推奨ペースを確認し、学習計画を立てる。総学習時間目標は資格ごとに 1 値（時間）だけ設定する。
- **コーチ（coach）**: 担当受講生について [[dashboard]] 経由で本 Feature の `ProgressService` / `StreakService` / `StagnationDetectionService` の **集計結果のみ閲覧** する（直接の Controller / Action は本 Feature で持たない）。受講生が 7 日学習途絶していれば滞留検知リストで把握し、[[chat]] / [[mentoring]] で介入する。
- **管理者（admin）**: 全受講生について同様に集計結果を閲覧する。本 Feature の状態を直接編集することはない（運用上の状態調整は [[enrollment]] の admin 系操作で行う）。

## 受け入れ基準（EARS形式）

### 機能要件 — A. データモデル

- **REQ-learning-001**: The system shall ULID 主キー / SoftDeletes / `(enrollment_id, section_id)` UNIQUE 制約を備えた `section_progresses` テーブルを提供し、`enrollment_id` / `section_id` / `completed_at`（NOT NULL, datetime）/ `created_at` / `updated_at` / `deleted_at` カラムを保持する。
- **REQ-learning-002**: The system shall ULID 主キー / SoftDeletes を備えた `learning_sessions` テーブルを提供し、`user_id`（NOT NULL, denormalized）/ `enrollment_id`（NOT NULL）/ `section_id`（NOT NULL）/ `started_at`（NOT NULL, datetime）/ `ended_at`（nullable, datetime）/ `duration_seconds`（nullable, unsigned int）/ `auto_closed`（boolean, default false）/ `created_at` / `updated_at` / `deleted_at` カラムを保持する。
- **REQ-learning-003**: The system shall ULID 主キー / SoftDeletes / `enrollment_id` UNIQUE 制約を備えた `learning_hour_targets` テーブルを提供し、`enrollment_id` / `target_total_hours`（NOT NULL, unsigned smallint, 1 以上 9999 以下）/ `created_at` / `updated_at` / `deleted_at` カラムを保持する。
- **REQ-learning-004**: The system shall `section_progresses.enrollment_id` / `learning_sessions.enrollment_id` / `learning_hour_targets.enrollment_id` を `enrollments.id` への外部キーとして持ち（`->constrained('enrollments')->restrictOnDelete()`）、Enrollment 物理削除を抑止する。Enrollment は SoftDelete のみが許される（[[enrollment]] の `SoftDeletes` 規約に整合）。
- **REQ-learning-005**: The system shall `section_progresses.section_id` / `learning_sessions.section_id` を `sections.id` への外部キーとして持ち（`->constrained('sections')->restrictOnDelete()`）、Section 物理削除を抑止する。
- **REQ-learning-006**: The system shall `learning_sessions.user_id` を `users.id` への外部キーとして持ち（`->constrained('users')->restrictOnDelete()`）、ストリーク集計と認可判定の高速化のために非正規化する。
- **REQ-learning-007**: The system shall `LearningSession::$casts` で `started_at` / `ended_at` を `datetime`、`duration_seconds` を `integer`、`auto_closed` を `boolean` にキャストする。`SectionProgress::$casts` で `completed_at` を `datetime` にキャストする。

### 機能要件 — B. 教材ブラウジング

- **REQ-learning-010**: When 受講生が `/learning` にアクセスした際, the system shall ログイン受講生の **`status=learning` または `status=paused`** の Enrollment 一覧（SoftDelete 除外）をカード形式で表示し、各カードに資格名 / 現在ターム / 直近活動日 / 進捗率 / 試験日カウントダウンを表示する。`passed` / `failed` 状態の Enrollment は本一覧から除外する（履歴は [[enrollment]] の詳細画面で閲覧可）。
- **REQ-learning-011**: When 受講生が `/learning/enrollments/{enrollment}` にアクセスした際, the system shall 当該 Enrollment が所有者本人のものであることを `EnrollmentPolicy::view` で検証し、所有資格の **公開済 Part 一覧**（`scopePublished()` 連鎖適用、[[content-management]] の cascade visibility 規約準拠）を `order ASC` で表示する。
- **REQ-learning-012**: When 受講生が `/learning/parts/{part}` にアクセスした際, the system shall 親 Certification への受講登録（`enrollments` 存在 + `status IN (learning, paused)`）を `PartViewPolicy::view` で検証し、配下の **公開済 Chapter 一覧** を `order ASC` で表示する。下書き状態 / 親 Part 非公開 / SoftDelete 済 Chapter は表示しない。
- **REQ-learning-013**: When 受講生が `/learning/chapters/{chapter}` にアクセスした際, the system shall 親 Certification への受講登録を検証し、配下の **公開済 Section 一覧**（読了状態バッジ付き）を `order ASC` で表示する。
- **REQ-learning-014**: When 受講生が `/learning/sections/{section}` にアクセスした際, the system shall 親 Certification への受講登録を検証し、Section 本文を `[[content-management]] の MarkdownRenderingService::toHtml` を経由して HTML に変換して表示する。本画面には「読了マーク」ボタンと「Section 紐づき問題演習へ」リンク（[[quiz-answering]] への遷移）を含める。
- **REQ-learning-015**: While 受講生が Section 詳細ページに滞在している間, the system shall ページ読み込み完了時に **学習セッション自動開始**（REQ-learning-040 〜 049）を実行する。
- **REQ-learning-016**: If 受講生が Section 詳細を閉じる / 他 URL に遷移する / タブを閉じる / 別 Section に遷移する場合, then the system shall 当該学習セッションを終了する（REQ-learning-045）。
- **REQ-learning-017**: If 受講生が登録していない（または SoftDelete 済 Enrollment しか持たない）資格に属する Part / Chapter / Section の URL に直接アクセスした場合, then the system shall HTTP 403 を返す。
- **REQ-learning-018**: If 親 Part / Chapter / Section のいずれかが `Draft` 状態または SoftDelete 済の場合, then the system shall 受講生視点では HTTP 404 を返す（cascade visibility、`[[content-management]] の scopePublished()` 連鎖が前提）。

### 機能要件 — C. Section 読了マーク

- **REQ-learning-020**: When 受講生が `POST /learning/sections/{section}/read` を呼んだ際, the system shall ログイン受講生が所有する Enrollment と当該 Section の親 Certification の整合（`enrollment.certification_id == section.chapter.part.certification_id`）を `SectionProgressPolicy::create` で検証し、未一致なら HTTP 403 を返す。
- **REQ-learning-021**: When 上記検証を通過した際, the system shall `section_progresses` に `(enrollment_id, section_id)` で既存行がなければ `completed_at = now()` で INSERT、既存行があれば **`completed_at = now()` で UPDATE**（SoftDelete 済の場合は restore してから UPDATE）して読了マーク完了とする。
- **REQ-learning-022**: When 受講生が `DELETE /learning/sections/{section}/read` を呼んだ際, the system shall 当該 `SectionProgress` を SoftDelete する（`completed_at` は保持、再読了で復元）。
- **REQ-learning-023**: If 対象 Section が非公開（Section / 親 Chapter / 親 Part のいずれかが `Draft` または SoftDelete 済）の場合, then the system shall 読了マーク操作を `SectionUnavailableForProgressException`（HTTP 409）で拒否する。
- **REQ-learning-024**: If 対象 Enrollment が `status != learning AND status != paused` の場合（`passed` / `failed` 等）, then the system shall 読了マーク操作を `EnrollmentInactiveException`（HTTP 409）で拒否する。
- **REQ-learning-025**: When 読了マークが成功した直後, the system shall **進捗集計のリアルタイム再計算 Service 呼出はしない**（クエリ時集計、NFR-learning-002）。集計値は次回の `ProgressService::summarize(Enrollment)` 呼出時に最新値が返る。

### 機能要件 — D. 学習セッション

- **REQ-learning-040**: When 受講生が `POST /learning/sessions/start` を呼んだ際（ペイロード: `section_id`）, the system shall Section が所属する Enrollment（`enrollment.user_id = auth.id AND enrollment.certification_id = section.certification_id`）を解決して、`learning_sessions` に新規行を INSERT（`started_at = now()` / `ended_at = null` / `duration_seconds = null` / `auto_closed = false`）し、新規 LearningSession の ULID を JSON 返却する。
- **REQ-learning-041**: When 新規セッションを INSERT する直前, the system shall 同一 `user_id` の **未終了セッション**（`ended_at IS NULL`）を **`SessionCloseService::closeOpenSessions(User, asAutoClosed: true)`** で一括クローズする。クローズ時は `ended_at = min(now(), started_at + max_session_seconds)` / `duration_seconds = ended_at - started_at` / `auto_closed = true` を設定する。
- **REQ-learning-042**: The system shall `max_session_seconds` を `config('app.learning_max_session_seconds', 14400)`（既定 4 時間）で設定可能とし、`SessionCloseService` 内で経過時間をクランプする。
- **REQ-learning-043**: When 受講生が `PATCH /learning/sessions/{session}/stop` を呼んだ際, the system shall (1) `session.user_id = auth.id` を `LearningSessionPolicy::update` で検証、(2) `session.ended_at = null` を確認、(3) `ended_at = min(now(), started_at + max_session_seconds)` / `duration_seconds = ended_at - started_at` / `auto_closed = false` で UPDATE する。
- **REQ-learning-044**: If `PATCH /learning/sessions/{session}/stop` 対象が既にクローズ済（`ended_at != null`）, then the system shall HTTP 200 で既存 LearningSession の値を返す（**冪等性**、`beforeunload` での重複送信を許容する）。
- **REQ-learning-045**: The system shall フロントエンド（`resources/js/learning/session-tracker.js`）で Section 詳細ページの `DOMContentLoaded` で `start` を呼び、`visibilitychange = hidden` / `pagehide` / `beforeunload` のいずれかで `navigator.sendBeacon` 経由 `stop` を呼ぶ。`pagehide` を主トリガとし、`beforeunload` はフォールバック。
- **REQ-learning-046**: When 別 Section 詳細ページに遷移した際, the system shall 旧 Section の `stop` が走った後（または並行して）、新 Section の `start` が走り、REQ-learning-041 により旧 Session が未終了であれば自動クローズされる仕組みを正常系として扱う。
- **REQ-learning-047**: When Schedule Command `learning:close-stale-sessions` が日次 00:30 に起動する, the system shall `ended_at IS NULL AND started_at < now() - INTERVAL :max_session_seconds SECOND` のすべての LearningSession を `SessionCloseService::closeStaleSessions()` で一括クローズし、`ended_at = started_at + max_session_seconds` / `duration_seconds = max_session_seconds` / `auto_closed = true` を設定する。
- **REQ-learning-048**: If 開始リクエストの Section に対応する Enrollment が `status = passed` または `status = failed` の場合, then the system shall セッション開始を `EnrollmentInactiveException`（HTTP 409）で拒否する。`learning` / `paused` は許容する（休止中も学習活動は記録可能）。`failed` Enrollment で再挑戦するには事前に [[enrollment]] の `ResumeAction` で `learning` に戻す必要がある。
- **REQ-learning-049**: The system shall LearningSession が常に少なくとも 1 秒の `duration_seconds` を持つことを保証する（`max(1, ended_at - started_at)` で clamp）。0 秒セッションは集計ノイズになるため避ける。

### 機能要件 — E. 進捗集計（ProgressService）

- **REQ-learning-060**: The system shall `App\Services\ProgressService` を `app/Services/` にフラット配置し、以下の公開メソッドを提供する: `summarize(Enrollment): ProgressSummary`、`sectionRatio(Enrollment, Part|Chapter|null): float`。
- **REQ-learning-061**: The system shall `ProgressService::summarize(Enrollment)` で以下の集計値を含む DTO（`App\Services\ProgressSummary` 値オブジェクト）を返す: `sections_total`（int）/ `sections_completed`（int）/ `section_completion_ratio`（float 0..1）/ `chapters_total` / `chapters_completed`（配下の全公開 Section が読了の Chapter 件数）/ `chapter_completion_ratio` / `parts_total` / `parts_completed` / `part_completion_ratio` / `overall_completion_ratio`（= `sections_completed / sections_total`、Section ベース）。
- **REQ-learning-062**: The system shall 進捗集計の対象を **公開済（`Part.status = Published` AND `Chapter.status = Published` AND `Section.status = Published`）かつ SoftDelete 済でない** Section に限定する。下書き Section は分母にも分子にもカウントしない。
- **REQ-learning-063**: When 受講生が Section を読了した後にコーチが Section を SoftDelete した場合, the system shall 対応 SectionProgress を物理削除せず（外部キー restrict）、集計時は SoftDelete された Section を分子から除外することで自動的に集計値から消える挙動とする。
- **REQ-learning-064**: When 受講生が Section を読了した後にコーチが Section を `Draft` に戻した場合, the system shall 当該 SectionProgress を保持しつつ、集計時は `Section.status = Published` を分母にも分子にも要求するため自動的に集計値から消える。再公開すれば再カウントされる。
- **REQ-learning-065**: The system shall `ProgressService` がクエリを以下の 1 ショットで実行する: `SELECT chapter_id, part_id, COUNT(sections.id) AS total, COUNT(section_progresses.id) AS completed FROM sections LEFT JOIN section_progresses ON ... LEFT JOIN chapters ON ... LEFT JOIN parts ON ... WHERE certification_id = ? AND sections.status='published' AND chapters.status='published' AND parts.status='published' AND deleted_at IS NULL GROUP BY chapter_id, part_id`。`SectionProgress` の SoftDelete も WHERE で除外。
- **REQ-learning-066**: When `ProgressService::summarize(Enrollment)` の対象 Enrollment 配下に公開済 Section が 0 件の場合, the system shall すべての ratio を `0.0` で返す（ゼロ除算を回避）。
- **REQ-learning-067**: The system shall `ProgressService::summarize` の戻り値を **キャッシュしない**（集計責務マトリクスの「クエリ時集計」方針）。dashboard 画面が複数回呼ぶ場合は呼出側で同一リクエスト内のメモ化を行う。

### 機能要件 — F. 学習ストリーク（StreakService）

- **REQ-learning-080**: The system shall `App\Services\StreakService` を提供し、`calculate(User): StreakSummary` を公開する。返却 DTO は `current_streak`（int, 連続日数）/ `longest_streak`（int）/ `last_active_date`（?Carbon）を含む。
- **REQ-learning-081**: The system shall `StreakService` における「学習活動日」を **`DISTINCT DATE(learning_sessions.started_at) WHERE user_id = ? AND deleted_at IS NULL`** と定義する（Basic スコープ、quiz-answering / mock-exam の活動は本 Service の集計対象外）。
- **REQ-learning-082**: The system shall `current_streak` を以下のロジックで計算する: (1) 学習活動日の集合 D を取得、(2) D が空なら 0、(3) 最大日付が `today` または `today - 1 day` でなければ 0、(4) そうでなければ最大日付から下り順に連続する日数をカウント。
- **REQ-learning-083**: The system shall `longest_streak` を学習活動日の集合 D の中で最も長い連続日数として算出する。
- **REQ-learning-084**: The system shall `last_active_date` を `MAX(DATE(learning_sessions.started_at))` として返す（`null` は活動歴なし）。
- **REQ-learning-085**: When `StreakService` がクエリを実行する際, the system shall タイムゾーンを `config('app.timezone')` に従い `DATE(CONVERT_TZ(started_at, '+00:00', :tz))` で日付グルーピングを行う（学習日のローカルタイム判定）。
- **REQ-learning-086**: When dashboard が `StreakService::calculate(auth_user)` を呼ぶ際, the system shall 受講生本人の集計のみを許可し（呼出側 [[dashboard]] の Policy で担保）、coach / admin が他受講生の `StreakService::calculate(targetUser)` を呼ぶ際は本 Service 自体は呼出主に依存しない（認可は呼出側責務）。

### 機能要件 — G. 学習時間目標（LearningHourTargetService）

- **REQ-learning-090**: When 受講生が `GET /learning/enrollments/{enrollment}/hour-target` を呼んだ際, the system shall 自身の Enrollment 配下の `LearningHourTarget`（0 or 1 件）と集計値（`LearningHourTargetService::compute`）を返す。
- **REQ-learning-091**: When 受講生が `PUT /learning/enrollments/{enrollment}/hour-target` を呼んだ際（ペイロード: `target_total_hours`）, the system shall `(enrollment_id)` で既存があれば UPDATE、無ければ INSERT する（upsert）。
- **REQ-learning-092**: When 受講生が `DELETE /learning/enrollments/{enrollment}/hour-target` を呼んだ際, the system shall 当該 `LearningHourTarget` を SoftDelete する。
- **REQ-learning-093**: If `target_total_hours` が `1` 未満または `9999` 超または非整数の場合, then the system shall HTTP 422 バリデーションエラーを返す。
- **REQ-learning-094**: The system shall `App\Services\LearningHourTargetService::compute(Enrollment): LearningHourTargetSummary` を提供し、以下を返す: `target_total_hours`（int, target 未設定なら 0）/ `studied_total_seconds`（int, 当該 Enrollment の全 LearningSession の `SUM(duration_seconds)`、`ended_at != null` のみ集計）/ `studied_total_hours`（float, `studied_total_seconds / 3600` 小数点 2 桁切り捨て）/ `remaining_hours`（float, `target - studied`、target 未設定なら `null`）/ `remaining_days`（int, `exam_date - today` 日数。負なら `0` にクランプ）/ `daily_recommended_hours`（float, `remaining_hours / max(remaining_days, 1)` 小数点 2 桁、remaining_hours が `null` または `<= 0` なら `null`）/ `progress_ratio`（float 0..1, `studied / target`、target 未設定なら `null`）。
- **REQ-learning-095**: When `LearningHourTargetService::compute` の Enrollment に LearningHourTarget が未設定の場合, the system shall `target_total_hours = 0` / `remaining_hours = null` / `progress_ratio = null` / `daily_recommended_hours = null` を返し、`studied_*` のみ正値で返す。
- **REQ-learning-096**: If 対象 Enrollment の `exam_date` が今日以前の場合, then the system shall `remaining_days = 0` / `daily_recommended_hours = remaining_hours`（残り全部を「今日中」推奨）として返す。
- **REQ-learning-097**: The system shall `LearningHourTargetPolicy::view` / `create` / `update` / `delete` を受講生本人（`enrollment.user_id = auth.id`）のみ true とし、coach / admin は `view` のみ true、`create` / `update` / `delete` は false とする（自分の目標は他者が触らない）。
- **REQ-learning-098**: When LearningHourTarget が SoftDelete された後に同一 Enrollment で再設定する際, the system shall **既存 SoftDeleted 行を restore してから UPDATE**（`(enrollment_id)` UNIQUE 制約があるため）を行う。
- **REQ-learning-099**: While LearningHourTarget が SoftDelete 状態の間, the system shall `LearningHourTargetService::compute` で `target_total_hours = 0` 扱いとする（SoftDelete 済を「未設定」と等価に扱う）。

### 機能要件 — H. 滞留検知（StagnationDetectionService）

- **REQ-learning-120**: The system shall `App\Services\StagnationDetectionService` を提供し、以下の公開メソッドを提供する: `isStagnant(Enrollment): bool` / `detectStagnant(): Collection<Enrollment>` / `lastActivityAt(Enrollment): ?Carbon`。
- **REQ-learning-121**: The system shall 「学習途絶」を **`enrollment.status = learning AND lastActivityAt(enrollment) IS NULL OR lastActivityAt(enrollment) < now() - INTERVAL :threshold_days DAY`** と定義する。`:threshold_days` は `config('app.stagnation_days', 7)` から取得する。
- **REQ-learning-122**: The system shall `lastActivityAt(Enrollment)` を `MAX(learning_sessions.started_at WHERE enrollment_id = ? AND deleted_at IS NULL)` として返す。LearningSession 0 件なら `null` を返す。
- **REQ-learning-123**: The system shall `detectStagnant()` で `enrollment.status = learning AND deleted_at IS NULL` の全 Enrollment を対象に、`lastActivityAt` が閾値超過のものを Collection で返す。`paused` / `passed` / `failed` 状態の Enrollment は対象外とする（`product.md` 補足: 学習途絶リマインドは「学習中」のみ）。
- **REQ-learning-124**: When [[notification]] の Schedule Command（`notifications:send-stagnation-reminders`）が日次起動する, the system shall 本 Feature の `StagnationDetectionService::detectStagnant()` を呼んで対象 Enrollment 群を取得し、それぞれに対し受講生宛て通知を [[notification]] が dispatch する。本 Feature 自体は通知発行を行わない（責務分離）。
- **REQ-learning-125**: When [[dashboard]] の admin / coach パネルが滞留検知リストを描画する際, the system shall 本 Feature の `StagnationDetectionService::detectStagnant()` を呼んで結果を返す。coach 用パネルは追加で `enrollment.assigned_coach_id = auth.id` フィルタを `[[dashboard]]` 側で適用する（本 Service は admin 視点の全件返却を契約とする）。
- **REQ-learning-126**: The system shall `StagnationDetectionService::detectStagnant()` の戻り値に `with('user', 'certification', 'assignedCoach')` で Eager Loading 済の Enrollment を含め、N+1 を起こさせない。

### 機能要件 — I. アクセス制御 / 認可

- **REQ-learning-140**: The system shall `routes/web.php` の `/learning/...` 群に `auth + role:student` Middleware を適用する。coach / admin は本 Feature の Controller を経由せず、`ProgressService` / `StreakService` / `LearningHourTargetService` / `StagnationDetectionService` の集計結果のみを [[dashboard]] 経由で利用する。
- **REQ-learning-141**: The system shall `App\Policies\SectionProgressPolicy` を提供し、`view` / `create` / `delete` を受講生本人（`enrollment.user_id = auth.id`）のみ true、それ以外（coach / admin / 他受講生）は false とする。
- **REQ-learning-142**: The system shall `App\Policies\LearningSessionPolicy` を提供し、`view` / `update`（stop 操作含む）を `session.user_id = auth.id` のみ true、`viewAny` を coach / admin の dashboard 利用に対し true（Service 経由消費時の Gate 確認）とする。
- **REQ-learning-143**: The system shall `App\Policies\LearningHourTargetPolicy` を提供し、`view` を受講生本人 + 担当 coach + admin、`create` / `update` / `delete` を受講生本人のみ true とする。
- **REQ-learning-144**: The system shall **`App\Policies\PartViewPolicy::view`** / **`ChapterViewPolicy::view`** / **`SectionViewPolicy::view`** を提供し、`user.enrollments()->where('certification_id', $resource->certification_id)->whereIn('status', [learning, paused])->exists()` を判定式とする。`passed` / `failed` 状態の Enrollment では教材ブラウジング不可（履歴閲覧は [[enrollment]] 詳細画面が担う）。
- **REQ-learning-145**: When coach / admin が誤って `/learning/...` URL に直接アクセスした際, the system shall `auth + role:student` Middleware で HTTP 403 を返す（コーチ / 管理者は受講生 UI を持たない）。
- **REQ-learning-146**: If 受講生が `passed` / `failed` 状態の Enrollment で `POST /learning/sessions/start` を呼んだ場合, then the system shall `EnrollmentInactiveException`（HTTP 409）を返す（REQ-learning-048 と整合）。

### 非機能要件

- **NFR-learning-001**: The system shall すべての状態変更を伴う Action（Section 読了 / セッション開始・終了・自動クローズ / LearningHourTarget upsert / 削除）を `DB::transaction()` で囲む。
- **NFR-learning-002**: The system shall `ProgressService` / `StreakService` / `LearningHourTargetService` / `StagnationDetectionService` を **状態を持たないステートレス Service** として実装する。集計結果のキャッシュは持たず、呼出ごとにクエリを実行する（dashboard が同一リクエスト内で複数回呼ぶ場合は呼出側でメモ化）。
- **NFR-learning-003**: The system shall 以下の INDEX を migration で定義する: `section_progresses.(enrollment_id, section_id)` UNIQUE / `section_progresses.deleted_at` / `learning_sessions.(user_id, started_at)` 複合（StreakService）/ `learning_sessions.(enrollment_id, started_at)` 複合（StagnationDetectionService / LearningHourTargetService）/ `learning_sessions.(user_id, ended_at)` 複合（active session lookup、`ended_at IS NULL` 高速化）/ `learning_sessions.deleted_at` / `learning_hour_targets.enrollment_id` UNIQUE / `learning_hour_targets.deleted_at`。
- **NFR-learning-004**: The system shall ドメイン例外を `app/Exceptions/Learning/` 配下に具象クラスとして実装する: `SectionUnavailableForProgressException`（HTTP 409）/ `EnrollmentInactiveException`（HTTP 409）/ `LearningSessionAlreadyClosedException`（HTTP 200 で冪等返却するため例外発生せず、内部判定用）/ `LearningHourTargetInvalidException`（HTTP 422、target_total_hours バリデーション失敗）。
- **NFR-learning-005**: The system shall `SessionCloseService` を `DB::transaction()` を持たないステートレス Service として実装し、呼出側 Action のトランザクションに乗る（[[enrollment]] の `EnrollmentStatusChangeService` 同流儀、NFR-enrollment-005 に倣う）。
- **NFR-learning-006**: The system shall 進捗集計 / ストリーク集計 / 滞留検知のいずれにおいても `with('certification')` / `with('user')` 等の Eager Loading を呼出側で適切に注入することで N+1 を回避する。Service 内部では Eager Loading 強制は行わない（呼出側責務）。
- **NFR-learning-007**: The system shall Section 詳細画面に **素の JavaScript**（`resources/js/learning/session-tracker.js`）を Wave 0b 共通ユーティリティ `utils/fetch-json.js` 経由で読み込み、`Alpine.js` / `Livewire` を採用しない（`tech.md` のフロントエンド方針に整合）。
- **NFR-learning-008**: The system shall `StagnationDetectionService::detectStagnant()` のクエリを「LEFT JOIN learning_sessions ON enrollment_id + 最大 started_at」「WHERE enrollment.status = learning AND (MAX(started_at) IS NULL OR MAX(started_at) < threshold)」のサブクエリ構造で発行し、`status=learning` の Enrollment 数が増えても 1 クエリで完結させる。
- **NFR-learning-009**: The system shall LearningSession の `pagehide` / `beforeunload` フォールバック処理を `navigator.sendBeacon` で行い、レスポンス待たずに `stop` リクエストを発火する（離脱遅延を避ける）。サーバ側は `sendBeacon` のペイロードが `Content-Type: text/plain` で来る前提で JSON パースを許容する。

## スコープ外

- **教材階層（Part / Chapter / Section）の CRUD** — [[content-management]] が所有。本 Feature は読み取り再利用のみ
- **問題演習（Quiz）の解答送信 / 自動採点 / 解答履歴 / 苦手分野ドリル** — [[quiz-answering]] が所有。本 Feature の `LearningSession` は Section 滞在時間のみを記録し、Quiz 解答時間はカウントしない（Section 詳細から Quiz 画面へ遷移すると Section LearningSession が `stop` され、Quiz 側は別途トラッキング）
- **mock-exam 受験時間のトラッキング** — [[mock-exam]] が `MockExamSession.started_at` / `submitted_at` で別途持つ。本 Feature の `LearningHourTargetService` の `studied_total_seconds` には含まれない（教材読み込み時間のみ）。dashboard で「合計学習時間」を出す場合は呼出側で複数 Service を合算する想定（[[dashboard]] 側責務）
- **AI チャット時間のトラッキング** — [[ai-chat]] スコープ、Advance 範囲。本 Feature の対象外
- **学習ストリークへの広域活動定義（Quiz / mock-exam を含む）** — Basic スコープでは LearningSession のみ。Advance で Service 内部実装を拡張する余地はあるが、要件レベルでは含めない
- **学習時間目標の自動推奨（合格率や類似受講生から逆算）** — 受講生が自由入力した値のみ保持。AI / 統計による推奨ロジックはスコープ外
- **連続学習日数の達成バッジ / リーダーボード / 称号** — `product.md` 明示によりスコープ外（バッジ系は採用しない）
- **学習時間の週次・月次レポート** — `LearningHourTargetService::compute` は累計値のみ返し、期間別集計はスコープ外（dashboard が必要なら独自クエリで提供）
- **LearningHourTarget の複数バージョン管理 / 履歴閲覧** — 1 Enrollment = 0 or 1 行の最新値のみ保持、過去設定値は保持しない
- **「今日の学習時間」「今週の学習時間」のリアルタイム表示** — dashboard ゲージは累計のみ表示。期間別の高速表示はキャッシュ実装が必要で Advance 領域
- **教材ブックマーク / お気に入り** — `product.md` 明示によりスコープ外
- **Section 内のしおり機能（読みかけ位置の保持）** — スコープ外（Section 単位の読了マークのみ）
- **学習途絶リマインドの受講生個別 ON / OFF 切替** — [[notification]] の通知設定（[[settings-profile]] 経由）で `Database` / `Mail` channel を切る方式に依存。本 Feature では切替 UI を持たない

## 関連 Feature

- **依存先**（本 Feature が前提とする）:
  - [[auth]] — `User` モデル / `UserRole` Enum / `auth` Middleware
  - [[enrollment]] — `Enrollment` モデル / `EnrollmentStatus` Enum / `EnrollmentPolicy::view`。SectionProgress / LearningSession / LearningHourTarget の親エンティティ
  - [[certification-management]] — `Certification` モデル / `User::assignedCertifications()`（受講生 ↔ 担当コーチ判定で coach 側が利用）
  - [[content-management]] — `Part` / `Chapter` / `Section` モデル + `Section::scopePublished()` 連鎖 + `App\Services\MarkdownRenderingService` + `ContentStatus` Enum
- **依存元**（本 Feature を利用する）:
  - [[dashboard]] — 受講生 / coach / admin の各パネルで `ProgressService` / `StreakService` / `LearningHourTargetService` / `StagnationDetectionService` を消費。受講生ダッシュボードは進捗ゲージ / ストリーク / 残り時間 / 弱点ヒートマップ（[[mock-exam]] 所有）を集約表示
  - [[notification]] — `StagnationDetectionService::detectStagnant()` を Schedule Command `notifications:send-stagnation-reminders` で消費し、受講生宛て「学習途絶リマインド」通知を `Database + Mail` channel で配信
  - [[quiz-answering]] — Section 詳細から問題演習画面への遷移点。本 Feature の `LearningSession` を `stop` して quiz-answering 側で時間トラッキング開始（個別実装、本 Feature は遷移リンクのみ提供）
  - [[mock-exam]] — `TermJudgementService` 等で `Enrollment.current_term` が `mock_practice` に切り替わると、受講生 UI は本 Feature の Section 一覧から MockExam 一覧に主導線が変わる（本 Feature の URL は引き続き有効、ナビゲーション上の補助線）
  - [[settings-profile]] — 受講生がプロフィール画面から学習時間目標の上書き設定を行う動線は本 Feature ではなく、[[settings-profile]] / 本 Feature の `/learning/enrollments/{enrollment}/hour-target` 両方からアクセス可能（個別 Enrollment 単位の編集 UI）
