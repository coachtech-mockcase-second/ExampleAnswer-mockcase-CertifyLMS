# learning 要件定義

> **v3 改修反映**（2026-05-16）: `StagnationDetectionService` 削除（滞留検知 MVP 外）、`Enrollment.status = passed` でも閲覧・演習可（status による機能制限なし、Plan 期間内のみで判定）、`EnsureActiveLearning` Middleware で `graduated` ユーザーのプラン機能ロック。

## 概要

受講生の日々の学習体験（**教材ブラウジング / Section 読了マーク / 学習セッション時間トラッキング / 進捗集計 / 学習ストリーク / 学習時間目標**）を一体で提供する Feature。Certify LMS のドメイン構造 4 軸のうち **「学習量と継続のメーター係」** として機能し、[[dashboard]] / [[mock-exam]] / [[quiz-answering]] から消費される **3 集計 Service**（`ProgressService` / `StreakService` / `LearningHourTargetService`）を所有する。**滞留検知（`StagnationDetectionService`）は v3 撤回**（MVP 外、保持しない）。

- 教材階層（Part → Chapter → Section）は [[content-management]] が所有する Eloquent Model を受講生視点で **読み取り再利用** する
- Section の読了は `section_progresses` テーブルで 1 Enrollment × 1 Section の最大 1 行（再マークは UPDATE）で管理する
- 学習時間は **Section 詳細ページ滞在時間** を `learning_sessions` で記録する
- `status = passed` の Enrollment も `status = learning` と同等に閲覧・読了マーク・学習時間記録が可能（プラン期間内であれば復習として活用）

## ロールごとのストーリー

- **受講生（student）**: 受講中の資格を選び、Part → Chapter → Section と階層を辿って教材本文を読む。Section 詳細ページに入ると裏側で学習セッションが自動開始され、離脱時に終了して時間が記録される。読了マークを付けると Section / Chapter / Part / 資格の完了率が即座に更新される。ダッシュボードで連続学習日数（ストリーク）と試験日までの残り時間・日次推奨ペースを確認する。`passed` 資格も同じ画面で復習可能（プラン期間内のみ）。
- **コーチ（coach）**: 担当資格に登録した受講生について [[dashboard]] 経由で本 Feature の `ProgressService` / `StreakService` / `LearningHourTargetService` の **集計結果のみ閲覧** する。
- **管理者（admin）**: 全受講生の集計結果を閲覧（dashboard 経由）。本 Feature の状態を直接編集することはない。

## 受け入れ基準（EARS形式）

### 機能要件 — A. データモデル

- **REQ-learning-001**: The system shall ULID 主キー / `SoftDeletes` / `(enrollment_id, section_id)` UNIQUE 制約を備えた `section_progresses` テーブルを提供し、`enrollment_id` / `section_id` / `completed_at`（NOT NULL, datetime）/ `created_at` / `updated_at` / `deleted_at` を保持する。
- **REQ-learning-002**: The system shall ULID 主キー / `SoftDeletes` を備えた `learning_sessions` テーブルを提供し、`user_id`（NOT NULL, denormalized）/ `enrollment_id`（NOT NULL）/ `section_id`（NOT NULL）/ `started_at`（NOT NULL）/ `ended_at`（nullable）/ `duration_seconds`（nullable, unsigned int）/ `auto_closed`（boolean, default false）/ `created_at` / `updated_at` / `deleted_at` を保持する。
- **REQ-learning-003**: The system shall ULID 主キー / `SoftDeletes` / `enrollment_id` UNIQUE 制約を備えた `learning_hour_targets` テーブルを提供し、`enrollment_id` / `target_total_hours`（NOT NULL, 1..9999）/ `created_at` / `updated_at` / `deleted_at` を保持する。
- **REQ-learning-004**: The system shall 各テーブルの外部キーで Enrollment / Section / User の物理削除を抑止する（restrictOnDelete）。
- **REQ-learning-007**: The system shall 各モデルの $casts で datetime / integer / boolean を適切にキャストする。

### 機能要件 — B. 教材ブラウジング

- **REQ-learning-010**: When 受講生が `/learning` にアクセスした際, the system shall ログイン受講生の `Enrollment::status IN (learning, passed)` 一覧をカード形式で表示する（**`passed` も含めて表示**、v3 改修）。各カードに資格名 / 現在ターム / 直近活動日 / 進捗率 / 試験日カウントダウン / 修了済バッジ（passed のみ）を表示。`failed` Enrollment は本一覧から除外。
- **REQ-learning-011**: When 受講生が `/learning/enrollments/{enrollment}` にアクセスした際, the system shall 当該 Enrollment が所有者本人 + `EnrollmentPolicy::view` を検証し、所有資格の **公開済 Part 一覧** を `order ASC` で表示する。
- **REQ-learning-012**: When 受講生が `/learning/parts/{part}` にアクセスした際, the system shall 親 Certification への受講登録（`enrollments.status IN (learning, passed)`）を検証し、配下の **公開済 Chapter 一覧** を `order ASC` で表示する。
- **REQ-learning-013**: When 受講生が `/learning/chapters/{chapter}` にアクセスした際, the system shall 同様の検証を行い、配下の **公開済 Section 一覧**（読了状態バッジ付き）を `order ASC` で表示する。
- **REQ-learning-014**: When 受講生が `/learning/sections/{section}` にアクセスした際, the system shall 同様の検証を行い、Section 本文を `MarkdownRenderingService::toHtml` で HTML 変換して表示する。本画面には「読了マーク」ボタンと「Section 紐づき問題演習へ」リンク（[[quiz-answering]] への遷移）を含める。
- **REQ-learning-015**: While 受講生が Section 詳細ページに滞在している間, the system shall ページ読み込み完了時に **学習セッション自動開始** を実行する。
- **REQ-learning-016**: If 受講生が Section 詳細を閉じる / 他 URL に遷移する / タブを閉じる / 別 Section に遷移する場合, then the system shall 当該学習セッションを終了する。
- **REQ-learning-017**: If 受講生が登録していない資格に属する Part / Chapter / Section の URL に直接アクセスした場合, then the system shall HTTP 403 を返す。
- **REQ-learning-018**: If 親 Part / Chapter / Section のいずれかが `Draft` 状態または SoftDelete 済の場合, then the system shall 受講生視点では HTTP 404 を返す。
- **REQ-learning-019**: When 受講生のログインユーザーが `User.status != UserStatus::InProgress` の場合, then the system shall **`EnsureActiveLearning` Middleware** で 403 を返す（`graduated` ユーザーは教材閲覧不可、修了済資格の閲覧も含めて全部ロック）。

### 機能要件 — C. Section 読了マーク

- **REQ-learning-020**: When 受講生が `POST /learning/sections/{section}/read` を呼んだ際, the system shall ログイン受講生が所有する Enrollment と当該 Section の親 Certification の整合を検証し、未一致なら HTTP 403 を返す。
- **REQ-learning-021**: When 検証を通過した際, the system shall `section_progresses` を UPSERT（既存があれば `completed_at = now()` で UPDATE、SoftDelete 済なら restore してから UPDATE）する。
- **REQ-learning-022**: When 受講生が `DELETE /learning/sections/{section}/read` を呼んだ際, the system shall 当該 `SectionProgress` を SoftDelete する。
- **REQ-learning-023**: If 対象 Section が非公開（Section / 親 Chapter / 親 Part のいずれかが `Draft` または SoftDelete 済）の場合, then the system shall 読了マーク操作を `SectionUnavailableForProgressException`（HTTP 409）で拒否する。
- **REQ-learning-024**: If 対象 Enrollment が `status = failed` の場合, then the system shall 読了マーク操作を `EnrollmentInactiveException`（HTTP 409）で拒否する。`learning` / `passed` は許容（passed も復習として読了マーク可）。

### 機能要件 — D. 学習セッション

- **REQ-learning-040**: When 受講生が `GET /learning/sections/{section}` で Section 詳細ページを表示する際, the system shall `BrowseController::showSection` の処理内で同期的に `LearningSession\StartAction::__invoke($student, $section)` を呼び、`learning_sessions` に新規行を INSERT する。**JS / クライアント側からの API 呼出は不要**(サーバ側 auto-start、本 Feature は `POST /learning/sessions/start` エンドポイントを持たない)。
- **REQ-learning-041**: When 新規セッション INSERT 直前, the system shall 同一 `user_id` の未終了セッションを `SessionCloseService::closeOpenSessions(User, asAutoClosed: true)` で一括クローズする(別 Section 遷移時の自動切替)。
- **REQ-learning-042**: The system shall `max_session_seconds` を `config('learning.max_session_seconds', 3600)` で設定可能とする（デフォルト 60 分、Schedule Command 閾値と同期）。
- **REQ-learning-043**: When 受講生が「学習を一旦終える」ボタンから `POST /learning/sessions/{session}/stop` を HTML form で送信した際, the system shall `LearningSessionPolicy::update` 検証 + `ended_at` UPDATE + `duration_seconds` clamp 計算 + `auto_closed=false` 設定を実行し、`/learning`(index)へ 302 redirect する。
- **REQ-learning-044**: If `stop` 対象が既にクローズ済, then the system shall idempotent に既存状態を維持し 302 redirect で受理する(エラー扱いしない)。
- **REQ-learning-047**: When Schedule Command `learning:close-stale-sessions` が日次 00:30 に起動する, the system shall 未終了 + 経過時間 `> max_session_seconds` のセッションを `auto_closed=true` で一括クローズする(ブラウザ閉じ / PC スリープ等の不可避ケースの保険)。
- **REQ-learning-048**: If `BrowseController::showSection` 内の auto-start 時、対応する Enrollment が `status = failed` の場合, then the system shall `EnrollmentInactiveException`（HTTP 409）で拒否する。`learning` / `passed` は許容。
- **REQ-learning-049**: The system shall **JavaScript / `navigator.sendBeacon` / `pagehide` / `visibilitychange` / heartbeat / タブ可視性検知を採用しない**(2026-05-16 確定)。学習時間トラッキングの整合性は「サーバ側 auto-start による別 Section 遷移時の自動切替 + Schedule Command の `max_session_seconds` clamp auto-close」の 2 重保険で担保する。

### 機能要件 — E. 進捗集計（ProgressService）

- **REQ-learning-060**: The system shall `App\Services\ProgressService` を提供し、`summarize(Enrollment): ProgressSummary` / `sectionRatio(Enrollment, Part|Chapter|null): float` を公開する。
- **REQ-learning-061**: The system shall `summarize` で `sections_total` / `sections_completed` / `section_completion_ratio` / `chapters_total` / `chapters_completed` / `chapter_completion_ratio` / `parts_total` / `parts_completed` / `part_completion_ratio` / `overall_completion_ratio` を含む DTO を返す。
- **REQ-learning-062**: The system shall 進捗集計の対象を **公開済かつ SoftDelete 済でない** Section に限定する。
- **REQ-learning-065**: The system shall 1 ショット SQL（LEFT JOIN + GROUP BY）で集計する。
- **REQ-learning-067**: The system shall 結果をキャッシュしない（クエリ時集計）。

### 機能要件 — F. 学習ストリーク（StreakService）

- **REQ-learning-080**: The system shall `App\Services\StreakService` を提供し、`calculate(User): StreakSummary` を公開する。
- **REQ-learning-081**: The system shall 「学習活動日」を `DISTINCT DATE(learning_sessions.started_at) WHERE user_id = ?` と定義する。
- **REQ-learning-082**: The system shall `current_streak` / `longest_streak` / `last_active_date` を返す。
- **REQ-learning-085**: The system shall タイムゾーンを `config('app.timezone')` で日付グルーピングする。

### 機能要件 — G. 学習時間目標（LearningHourTargetService）

- **REQ-learning-090**: When 受講生が `GET /learning/enrollments/{enrollment}/hour-target` を呼んだ際, the system shall 自身の Enrollment 配下の `LearningHourTarget`（0 or 1 件）と集計値を返す。
- **REQ-learning-091**: When `PUT` を呼んだ際, the system shall upsert する。
- **REQ-learning-092**: When `DELETE` を呼んだ際, the system shall SoftDelete する。
- **REQ-learning-094**: The system shall `LearningHourTargetService::compute(Enrollment)` で `target_total_hours` / `studied_total_seconds` / `studied_total_hours` / `remaining_hours` / `remaining_days` / `daily_recommended_hours` / `progress_ratio` を返す。
- **REQ-learning-097**: The system shall `LearningHourTargetPolicy` で受講生本人のみ CRUD 許可、coach / admin は閲覧のみ。

### 機能要件 — I. アクセス制御 / 認可

- **REQ-learning-140**: The system shall `/learning/...` 群に `auth + role:student + EnsureActiveLearning` Middleware を適用する。
- **REQ-learning-141**: The system shall `SectionProgressPolicy::view/create/delete` を受講生本人のみ true。
- **REQ-learning-142**: The system shall `LearningSessionPolicy::view/update` を `session.user_id = auth.id` のみ true。
- **REQ-learning-143**: The system shall `LearningHourTargetPolicy` を実装する。
- **REQ-learning-144**: The system shall `PartViewPolicy::view` / `ChapterViewPolicy::view` / `SectionViewPolicy::view` を `user.enrollments()->where('certification_id', $resource->certification_id)->whereIn('status', [learning, passed])->exists()` で判定する（**`passed` も許容、v3 改修**）。

### 非機能要件

- **NFR-learning-001**: The system shall 状態変更を伴う Action を `DB::transaction()` で囲む。
- **NFR-learning-002**: The system shall Service 群をステートレスに実装、キャッシュは持たない。
- **NFR-learning-003**: The system shall 以下 INDEX を提供: `section_progresses.(enrollment_id, section_id)` UNIQUE / `learning_sessions.(user_id, started_at)` 複合 / `learning_sessions.(enrollment_id, started_at)` 複合 / `learning_sessions.(user_id, ended_at)` 複合 / `learning_hour_targets.enrollment_id` UNIQUE。
- **NFR-learning-004**: The system shall ドメイン例外を `app/Exceptions/Learning/` 配下に実装する（`SectionUnavailableForProgressException` / `EnrollmentInactiveException` / `LearningHourTargetInvalidException`）。
- **NFR-learning-007**: The system shall Section 詳細画面に素の JS を読み込み、`Alpine.js` / `Livewire` を採用しない。

## スコープ外

- **教材階層の CRUD** — [[content-management]] が所有
- **問題演習** — [[quiz-answering]] が所有
- **mock-exam 受験時間トラッキング** — [[mock-exam]] が別途持つ
- **AI チャット時間トラッキング** — [[ai-chat]] スコープ
- **滞留検知 / `StagnationDetectionService`** — **v3 撤回**、本 Feature では持たない
- **学習途絶リマインド通知** — v3 撤回
- **学習時間の自動推奨ロジック** — 自由入力のみ
- **連続学習日数の達成バッジ / リーダーボード** — `product.md` 撤回
- **教材ブックマーク / お気に入り** — `product.md` 撤回

## 関連 Feature

- **依存先**:
  - [[auth]] — `User` / `UserRole` / `auth` Middleware / **`EnsureActiveLearning` Middleware**
  - [[enrollment]] — `Enrollment` / `EnrollmentStatus` / `EnrollmentPolicy::view`
  - [[certification-management]] — `Certification` モデル
  - [[content-management]] — `Part` / `Chapter` / `Section` モデル + `MarkdownRenderingService` + `ContentStatus` Enum
- **依存元**:
  - [[dashboard]] — `ProgressService` / `StreakService` / `LearningHourTargetService` を消費
  - [[quiz-answering]] — Section 詳細から本 Feature への遷移リンクを供給
  - [[mock-exam]] — `TermJudgementService` 等で `current_term` が `mock_practice` に切り替わる
  - [[settings-profile]] — `/learning/enrollments/{enrollment}/hour-target` への link 誘導
