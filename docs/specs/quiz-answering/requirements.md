# quiz-answering 要件定義

## 概要

受講生が **Section 紐づき問題（`Question.section_id IS NOT NULL`）を 1 問単位で演習** する基礎ターム中の日常学習機能と、**実践ターム中の苦手分野ドリル**（`WeaknessAnalysisService` の弱点判定に連動したカテゴリ別集中演習）を一体で提供する Feature。問題マスタ（`Question` / `QuestionOption` / `QuestionCategory`）の CRUD は [[content-management]] が所有し、本 Feature は **解答行為の受付 → 即時自動採点 → 解説表示 → 解答ログ（`Answer`）の追記 → Question 単位サマリ（`QuestionAttempt`）の UPSERT** に責務を絞る。**Advance スコープ**では自前 SPA から同一データを叩く JSON API を提供（**Sanctum SPA 認証 = Cookie ベース**、Web セッションを SPA 経由で共有する Laravel 標準仕組み）。Personal Access Token / 個人トークン管理 UI は LMS 全体で採用せず、Sanctum 設定（`config/sanctum.php` / SPA stateful ドメイン設定）のみ Wave 0b で導入される。

- mock-exam とは時間制限なし・修了判定に非関与・1 問ごと即時採点 / 解説表示の点で異なる（`product.md` の補足「Section 紐づき問題 vs mock-exam の責務分担」参照）
- 同じ問題への何度でもの再挑戦を許容し、毎回 `Answer` が時系列追記される。受講生 × Question 単位の正答率は `QuestionAttempt` に UPSERT で集約される
- [[learning]] が Section 詳細画面から本 Feature への遷移リンクを供給する。本 Feature 自身は Section 階層ブラウジングを持たない（演習エントリ画面と出題画面のみ）
- 集計 Service `QuestionAttemptStatsService` を所有し、[[dashboard]] / [[enrollment]] から「学習済問題数」「全体正答率」「カテゴリ別正答率」を消費させる

## ロールごとのストーリー

- **受講生（student）**: 教材本文を読んだ後に Section 詳細画面の「Section の問題を解く」リンクから演習画面に遷移し、配下の公開済 Question を `order ASC` で順次解いて理解度を即時確認する。実践ターム中は苦手分野ドリルから mock-exam 弱点ヒートマップで「苦手」と判定されたカテゴリの問題を集中演習する。自分の解答履歴と Question 単位サマリ（試行回数・正答率・最終解答日）をいつでも閲覧できる。**Advance スコープでは** 同一ドメインで動作する自前 SPA からも同一 API を Cookie 認証（Sanctum SPA）で叩ける。
- **コーチ（coach）**: 直接の Controller / Action は本 Feature では持たない。担当受講生の演習状況は `QuestionAttemptStatsService` の読み取り契約を [[dashboard]] / [[enrollment]] から消費する形で把握する（本 Feature の Service は admin 視点の全件返却が契約、Feature 横断の認可フィルタは呼出側責務）。
- **管理者（admin）**: coach と同様、本 Feature の Controller は持たず、`QuestionAttemptStatsService` の集計値を [[dashboard]] 経由で全受講生分参照できる。

## 受け入れ基準（EARS形式）

### 機能要件 — A. データモデル

- **REQ-quiz-answering-001**: The system shall ULID 主キー / `SoftDeletes` を備えた `answers` テーブルを提供し、`user_id`（NOT NULL, `users.id` 参照）/ `question_id`（NOT NULL, `questions.id` 参照）/ `selected_option_id`（NOT NULL, `question_options.id` 参照）/ `is_correct`（boolean, NOT NULL）/ `source`（enum `section_quiz` / `weak_drill`, NOT NULL）/ `answered_at`（datetime, NOT NULL）/ `created_at` / `updated_at` / `deleted_at` カラムを保持する。
- **REQ-quiz-answering-002**: The system shall ULID 主キー / `SoftDeletes` を備えた `question_attempts` テーブルを提供し、`user_id`（NOT NULL, `users.id` 参照）/ `question_id`（NOT NULL, `questions.id` 参照）/ `attempt_count`（unsigned int, NOT NULL, default 0）/ `correct_count`（unsigned int, NOT NULL, default 0）/ `last_is_correct`（boolean, NOT NULL, default false）/ `last_answered_at`（datetime, NOT NULL）/ `created_at` / `updated_at` / `deleted_at` カラムを保持する。`(user_id, question_id)` UNIQUE 制約で 1 受講生 × 1 Question の最大 1 行とする。
- **REQ-quiz-answering-003**: The system shall `App\Enums\AnswerSource` enum（`SectionQuiz` / `WeakDrill`）を提供し、`label()` メソッドで日本語ラベル（`Section演習` / `苦手分野ドリル`）を返す。
- **REQ-quiz-answering-004**: The system shall `Answer::$casts` で `is_correct` を `boolean`、`answered_at` を `datetime`、`source` を `AnswerSource::class` にキャストする。`QuestionAttempt::$casts` で `attempt_count` / `correct_count` を `integer`、`last_is_correct` を `boolean`、`last_answered_at` を `datetime` にキャストする。
- **REQ-quiz-answering-005**: The system shall `answers.user_id` / `question_attempts.user_id` を `users.id` への外部キーとして持ち（`->constrained('users')->restrictOnDelete()`）、User の物理削除を抑止する（[[auth]] / [[user-management]] が SoftDelete 運用）。
- **REQ-quiz-answering-006**: The system shall `answers.question_id` / `question_attempts.question_id` を `questions.id` への外部キーとして持ち（`->constrained('questions')->restrictOnDelete()`）、Question の物理削除を抑止する（[[content-management]] が SoftDelete 運用）。
- **REQ-quiz-answering-007**: The system shall `answers.selected_option_id` を `question_options.id` への外部キーとして持つ。`QuestionOption` は SoftDelete を採用せず delete-and-insert 方式（[[content-management]] REQ-035）であるため、`cascadeOnDelete` を使わず **`restrictOnDelete`** を指定し、Question 更新時の QuestionOption 物理削除を阻害しないよう Answer は `option_body_snapshot` を別途保持しない（QuestionOption が物理削除されると Answer が外部キーで残せなくなる）。**対策として `selected_option_id` の外部キーは `nullOnDelete` を指定し、Option 物理削除時には Answer 側を NULL にして残す**。代わりに **`selected_option_body`**（string, max 2000, NULL 可）にスナップショットを保持して履歴可読性を保証する。
- **REQ-quiz-answering-008**: The system shall `Answer.user_id` `Answer.question_id` `Answer.source` `Answer.answered_at` `Answer.is_correct` のすべてを **非正規化保存** で持ち、Question / Section / QuestionOption が後から SoftDelete / 物理削除されても解答履歴の表示に支障が出ないようにする（履歴は不変、CQRS 寄りの設計）。

### 機能要件 — B. Section 紐づき問題演習エントリ

- **REQ-quiz-answering-020**: When 受講生が `GET /quiz/sections/{section}` にアクセスした際, the system shall `SectionQuizPolicy::view` で「親 Certification への active Enrollment（`status IN (learning, paused)`）が存在し、かつ Section / 親 Chapter / 親 Part がすべて `status=Published` かつ SoftDelete 済でない」ことを検証し、満たさなければ HTTP 404 を返す。
- **REQ-quiz-answering-021**: When 検証を通過した際, the system shall 当該 Section 配下の公開済 Question を `order ASC, id ASC` で取得し、各 Question について受講生の `QuestionAttempt`（試行回数 / 正答率 / 最新正誤）を eager load した状態で `views/quiz/sections/show.blade.php` を描画する。一覧では Question 本文先頭 80 文字プレビュー / カテゴリ名 / 難易度バッジ / 最終正誤バッジ / 試行回数を表示する。
- **REQ-quiz-answering-022**: The system shall Section エントリ画面に「最初から順に解く」「未解答の問題から解く」「全部やり直す」のボタンを表示する。「未解答の問題から解く」は `Section 配下公開済 Question 群` のうち **受講生の `QuestionAttempt` が存在しない最初の Question** へ遷移する。すべて解答済の場合は「全制覇 → 再挑戦」状態で表示する。
- **REQ-quiz-answering-023**: When 受講生が `GET /quiz/sections/{section}/questions/{question}` にアクセスした際, the system shall (1) `SectionQuizPolicy::view` で B-020 と同条件を検証、(2) 当該 `Question.section_id === section.id` を確認、(3) Question / Section / Chapter / Part が公開済かつ SoftDelete 済でないことを再検証、いずれか違反なら HTTP 404 を返す。
- **REQ-quiz-answering-024**: When B-023 が通過した際, the system shall Question 本文 / `QuestionOption` 一覧（`order ASC`、正答フラグはマスク）/ カテゴリ名 / 難易度を表示し、ラジオボタン形式の選択肢 + 「解答を送信」ボタンを含む解答フォームを描画する。同 Question に対する直前の `Answer`（最新 1 件）があれば、フォーム外で履歴サマリ（試行回数 / 直近正誤 / 最終解答日）を併記表示する。
- **REQ-quiz-answering-025**: If 同 Section 内に次の Question が存在する場合, then the system shall 結果表示後の遷移先として `?next=<question_id>` クエリを設定し、「次の問題」ボタンから自然に遷移できるようにする。Section 配下最終 Question の解答後は「Section に戻る」ボタンのみ提示する。
- **REQ-quiz-answering-026**: When Section 配下 Question が 0 件（公開済が無い）の場合, the system shall Section エントリ画面で `<x-empty-state>` コンポーネントを使い「この Section にはまだ問題が公開されていません」のメッセージを表示する（Controller は 200 を返す）。

### 機能要件 — C. 苦手分野ドリル

- **REQ-quiz-answering-050**: When 受講生が `GET /quiz/drills/{enrollment}` にアクセスした際, the system shall `WeakDrillPolicy::view` で `$enrollment->user_id === auth.id AND $enrollment->status IN (learning, paused)` を検証し、満たさなければ HTTP 403 を返す。
- **REQ-quiz-answering-051**: When C-050 を通過した際, the system shall 当該 Enrollment の `certification_id` 配下の `QuestionCategory` 一覧を `sort_order ASC, created_at DESC` で取得し、各カテゴリについて以下を eager load する: (1) 受講生の `QuestionAttempt` から算出した **カテゴリ別正答率**（`correct_count SUM / attempt_count SUM`、attempt_count = 0 のカテゴリは `null`）、(2) `[[mock-exam]]` の `WeaknessAnalysisService::getWeakCategories(Enrollment)` 戻り値に含まれる **おすすめバッジ**、(3) カテゴリ配下の公開済 Question 件数。
- **REQ-quiz-answering-052**: When 受講生が `GET /quiz/drills/{enrollment}/categories/{questionCategory}` にアクセスした際, the system shall `WeakDrillPolicy::view` の Enrollment 認可に加えて `$questionCategory->certification_id === $enrollment->certification_id` を検証し、不一致なら HTTP 404 を返す。
- **REQ-quiz-answering-053**: When C-052 を通過した際, the system shall 対象 `QuestionCategory` 配下の **公開済 Question** を以下条件で抽出して `views/quiz/drills/show.blade.php` で表示する: (a) `Question.deleted_at IS NULL`、(b) `Question.status = Published`、(c) `Question.section_id` が指す Section / Chapter / Part がすべて `Published` かつ SoftDelete 済でない（Section 紐づき問題の cascade visibility）、(d) `Question.section_id IS NULL`（mock-exam 専用問題）も含めて出題対象とする。並び順は `order ASC, id ASC`。
- **REQ-quiz-answering-054**: The system shall 苦手分野ドリル一覧画面で各 Question について受講生の `QuestionAttempt` 情報（試行回数 / 最新正誤 / 最終解答日）を併記し、ヘッダ部に当該カテゴリの **全体正答率** と **おすすめバッジ** を表示する。
- **REQ-quiz-answering-055**: When 受講生が `GET /quiz/drills/{enrollment}/categories/{questionCategory}/questions/{question}` にアクセスした際, the system shall (1) C-052 と同条件、(2) `$question->category_id === $questionCategory->id`、(3) `$question->certification_id === $enrollment->certification_id`、(4) `$question->section_id IS NULL` または親階層公開検証を通過、のすべてを確認し、違反なら HTTP 404 を返す。通過時は B-024 と同等の出題画面を描画する（出典表示が「Section 演習」ではなく「苦手分野ドリル」）。
- **REQ-quiz-answering-056**: If 当該 Certification に紐づく `QuestionCategory` 配下の公開済 Question が 0 件の場合, then the system shall 出題画面ではなく `<x-empty-state>` で「このカテゴリにはまだ問題が公開されていません」のメッセージを表示する。
- **REQ-quiz-answering-057**: When `[[mock-exam]]` Feature がまだ実装されておらず `WeaknessAnalysisService` が public binding に登録されていない環境では, the system shall 「おすすめバッジ」を **全カテゴリで false** とし、空判定でも UI / 認可が破綻しないこと（フォールバック）。

### 機能要件 — D. 解答送信・自動採点

- **REQ-quiz-answering-080**: When 受講生が `POST /quiz/questions/{question}/answer` を呼んだ際（ペイロード: `selected_option_id` / `source`）, the system shall (1) `AnswerPolicy::create` で 当該 Question を受講生が解答可能（後述）と判定し、(2) `StoreAnswerAction::__invoke(User, Question, Option, AnswerSource): Answer` を呼んで解答を永続化、(3) JSON で `{ answer, correct_option_id, correct_option_body, explanation, attempt }` を返す。Web Blade はフロントエンド JS が同 endpoint を fetch して結果を画面に反映する（ページ遷移しない）。
- **REQ-quiz-answering-081**: The system shall `AnswerPolicy::create(User, Question)` を以下のロジックで判定する: (a) `auth.id === $user.id` であること、(b) `$user->role === Student`、(c) `$user` が `$question->certification_id` を `enrollments.status IN (learning, paused)` で受講中であること、(d) `$question->deleted_at IS NULL AND $question->status = Published`、(e) `$question->section_id IS NOT NULL` の場合は親 Section / Chapter / Part がすべて `Published` かつ SoftDelete 済でないこと、(f) `$question->section_id IS NULL` の場合は追加検証不要。違反は HTTP 403 を返す。
- **REQ-quiz-answering-082**: If `selected_option_id` が `$question->options` のいずれにも該当しない場合, then the system shall HTTP 422 を返す。
- **REQ-quiz-answering-083**: If `source` が `AnswerSource` の値以外の場合, then the system shall HTTP 422 を返す。
- **REQ-quiz-answering-084**: If 受講生の Enrollment が `passed` または `failed` 状態の場合, then the system shall `EnrollmentInactiveForAnswerException`（HTTP 409）を throw して解答送信を拒否する（履歴閲覧 / Question 単位サマリ閲覧は許容）。
- **REQ-quiz-answering-085**: If 対象 Question が SoftDelete 済 / Draft / 親階層 Draft または SoftDelete 済の場合, then the system shall `QuestionUnavailableForAnswerException`（HTTP 409）を throw して解答送信を拒否する（cascade visibility の不変条件、過去履歴は保持）。
- **REQ-quiz-answering-086**: When 解答送信が成功する際, the system shall 単一トランザクション内で以下を実行する: (1) `answers` に `(user_id, question_id, selected_option_id, selected_option_body, is_correct, source, answered_at = now())` を INSERT、(2) `question_attempts` を `(user_id, question_id)` で `withTrashed` 取得し、存在しなければ `attempt_count = 1, correct_count = (is_correct ? 1 : 0), last_is_correct, last_answered_at = now()` で INSERT、SoftDelete 済なら restore + 上記値で UPDATE、アクティブなら `attempt_count += 1, correct_count += (is_correct ? 1 : 0), last_is_correct = $is_correct, last_answered_at = now()` で UPDATE。
- **REQ-quiz-answering-087**: The system shall `is_correct` 判定を **`$selectedOption->is_correct === true`** の真偽値で行う（Question の `is_correct=true` の選択肢はちょうど 1 件であることを [[content-management]] の REQ-content-management-033 が保証）。
- **REQ-quiz-answering-088**: When 解答送信のレスポンスを返す際, the system shall `correct_option_id` と `correct_option_body` を **Question 単位で `is_correct=true` の Option を 1 件取得して** 返却する。解説（`Question.explanation`）は nullable なので null 許容で返却する。
- **REQ-quiz-answering-089**: The system shall 受講生による **連続多重送信**（クライアントの二重クリック）を idempotent 化しない: 各 POST は新規 `Answer` を INSERT し、`QuestionAttempt.attempt_count` も毎回 +1 する（誤クリックは UI 側で disable 制御）。

### 機能要件 — E. 履歴・サマリ閲覧

- **REQ-quiz-answering-120**: When 受講生が `GET /quiz/history/{enrollment}` にアクセスした際, the system shall `EnrollmentPolicy::view` で受講生本人の Enrollment であることを検証し、当該 Enrollment の `certification_id` に属する Question への自身の `Answer` 一覧を `answered_at DESC` で `paginate(20)` する。各 Answer は eager load で `question.section.chapter.part` / `question.category` / `question.options`（正答判定用）を含める。
- **REQ-quiz-answering-121**: The system shall 履歴一覧画面で各 Answer 行に以下を表示する: 解答日時 / 出典バッジ（`source` の label）/ カテゴリ名 / Section パンくず（Section 紐づき問題のみ）/ Question 本文先頭 80 文字 / 選択肢本文（スナップショット `selected_option_body`）/ 正誤バッジ。クリックすると元の Question 出題画面（B-023 or C-055）に遷移できる。
- **REQ-quiz-answering-122**: The system shall 履歴一覧画面でフィルタを提供する: (a) `section_id`（Section 別）、(b) `category_id`（カテゴリ別）、(c) `is_correct`（正解 / 不正解）、(d) `source`（`section_quiz` / `weak_drill`）。フィルタは query string で受け、組み合わせ可能とする。
- **REQ-quiz-answering-123**: When 受講生が `GET /quiz/stats/{enrollment}` にアクセスした際, the system shall 当該 Enrollment の `certification_id` に属する Question への `QuestionAttempt` 一覧を `last_answered_at DESC` で `paginate(20)` し、各行に `attempt_count` / `correct_count` / 正答率（`correct_count / attempt_count` の％表示）/ `last_is_correct` / `last_answered_at` / Question カテゴリ / Section パンくずを表示する。
- **REQ-quiz-answering-124**: The system shall サマリ画面でフィルタを提供する: (a) `section_id`、(b) `category_id`、(c) `last_is_correct`（最新が正解 / 不正解）、(d) ソート（`last_answered_at DESC` / `attempt_count DESC` / 正答率 ASC）。
- **REQ-quiz-answering-125**: When 受講生が他者の `enrollment` を指定した URL でアクセスした際, the system shall `EnrollmentPolicy::view` で HTTP 403 を返す。
- **REQ-quiz-answering-126**: The system shall coach / admin がブラウザから `/quiz/history/{enrollment}` または `/quiz/stats/{enrollment}` に直接アクセスすることを Middleware `role:student` で HTTP 403 で拒否する（集計値の coach / admin 閲覧は [[dashboard]] / [[enrollment]] 経由で `QuestionAttemptStatsService` を呼ぶ専用画面に集約）。

### 機能要件 — F. 集計 Service の公開（QuestionAttemptStatsService）

- **REQ-quiz-answering-150**: The system shall `App\Services\QuestionAttemptStatsService` を `app/Services/` にフラット配置し、以下の公開メソッドを提供する: `summarize(Enrollment): QuestionAttemptStatsSummary` / `byCategory(Enrollment): Collection<CategoryStats>` / `recentAnswers(Enrollment, int $limit = 5): Collection<Answer>`。
- **REQ-quiz-answering-151**: The system shall `summarize(Enrollment)` で以下の集計値を含む `App\Services\QuestionAttemptStatsSummary` 値オブジェクトを返す: `total_questions_attempted`（int, `question_attempts` 件数）/ `total_attempts`（int, `SUM(attempt_count)`）/ `total_correct`（int, `SUM(correct_count)`）/ `overall_accuracy`（float 0..1, `total_correct / total_attempts`、`total_attempts = 0` なら `null`）/ `last_answered_at`（?Carbon, `MAX(last_answered_at)`）。
- **REQ-quiz-answering-152**: The system shall `byCategory(Enrollment)` で `QuestionCategory` 単位の集計を `Collection<CategoryStats>` で返す。各 `CategoryStats` は `category_id` / `category_name` / `questions_attempted` / `total_attempts` / `total_correct` / `accuracy`（attempt 0 なら `null`）を含む。
- **REQ-quiz-answering-153**: The system shall `recentAnswers(Enrollment, int $limit = 5)` で直近の `Answer` を `answered_at DESC` で返し、各 Answer に `question.category` / `question.section.chapter.part` を eager load する（[[dashboard]] / `views/quiz/_recent.blade.php` の N+1 回避）。
- **REQ-quiz-answering-154**: The system shall `QuestionAttemptStatsService` の全クエリで対象 Question を `questions.certification_id = $enrollment->certification_id` で絞り込み、他資格の解答が混入しないことを保証する（受講生が複数資格を同時受講する Certify LMS の前提）。
- **REQ-quiz-answering-155**: The system shall `QuestionAttemptStatsService` を **状態を持たないステートレス Service** として実装し、結果のキャッシュは持たない。dashboard が同一リクエスト内で複数回呼ぶ場合は呼出側でメモ化する（[[learning]] の集計 Service 群と同流儀、NFR-learning-002 / 集計責務マトリクス）。
- **REQ-quiz-answering-156**: When `QuestionAttemptStatsService::summarize(Enrollment)` の対象 Enrollment 配下に `QuestionAttempt` が 0 件の場合, the system shall すべての集計値を `0` または `null`（accuracy / last_answered_at）で返す（ゼロ除算回避）。

### 機能要件 — G. Sanctum SPA 認証 API（Advance スコープ、Cookie ベース）

> **Advance ブランチでのみ実装**。Basic ブランチでは G セクションのすべての REQ-170〜174 は対象外（API Controller / Resource / Route / `routes/api.php` の本 Feature 系登録は Advance ブランチで純粋追加）。Basic ブランチでは Web Blade（D-080 の `POST /quiz/questions/{question}/answer` の CSRF 必須セッション認証エンドポイント）のみが提供される。
>
> **認証方式は Sanctum SPA（Cookie ベース）**: 同一ドメインで動作する自前 SPA から、Laravel Web セッション（Fortify ログイン後の Cookie）を `auth:sanctum` Middleware で再利用する。Personal Access Token / 個人トークン管理 UI / Bearer Token ヘッダ認証は採用しない（[[analytics-export]] の API キー方式とも別物）。SPA から GET `/sanctum/csrf-cookie` → 認証済みリクエストの流れは Laravel Sanctum 公式 SPA Authentication パターンに従う。

- **REQ-quiz-answering-170**: The system shall `routes/api.php` 配下に `auth:sanctum + role:student` Middleware を適用する API 群を提供する: (a) `POST /api/v1/quiz/questions/{question}/answer`（D-080 と同等、JSON 返却）、(b) `GET /api/v1/quiz/enrollments/{enrollment}/history`（E-120 の JSON 版）、(c) `GET /api/v1/quiz/enrollments/{enrollment}/stats`（E-123 の JSON 版）、(d) `GET /api/v1/quiz/enrollments/{enrollment}/drills/categories`（C-051 の JSON 版、おすすめバッジ付き）。
- **REQ-quiz-answering-171**: The system shall API レスポンスを `App\Http\Resources\AnswerResource` / `QuestionAttemptResource` / `QuestionResource` / `QuestionOptionResource`（正答フラグ非表示版）/ `CategoryDrillResource` で整形する。`AnswerResource` は `answered_at` を ISO 8601 形式で返し、`is_correct` / `selected_option_id` / `selected_option_body` / `source` を含める。
- **REQ-quiz-answering-172**: The system shall Sanctum SPA 認証（Cookie ベース）を採用し、`config/sanctum.php` の `stateful` ドメイン設定で自前 SPA のオリジンを許可する。Personal Access Token / `personal_access_tokens` テーブル / 個人トークン管理 UI は本 Feature・LMS 全体で **不採用**。SPA は Fortify ログイン後の Web セッション Cookie をそのまま `auth:sanctum` Middleware に渡して認証通過させる。
- **REQ-quiz-answering-173**: The system shall API レート制限を `throttle:60,1` を `auth:sanctum` グループ全体で適用し、Burst 防止する（`config/throttle.php` の値、Laravel デフォルト）。
- **REQ-quiz-answering-174**: When API リクエストが認可違反 / 状態違反を起こす際, the system shall `app/Exceptions/Handler.php` でドメイン例外（`QuestionUnavailableForAnswerException` / `EnrollmentInactiveForAnswerException` 等）を JSON 形式（`{ message, error_code, status }`）で返却するハンドラを共有する。

### 機能要件 — H. アクセス制御 / 認可

- **REQ-quiz-answering-190**: The system shall `routes/web.php` 配下の `/quiz/...` 群（D-080 の POST 解答送信を除く）に `auth + role:student` Middleware を適用する。POST `/quiz/questions/{question}/answer` は Web セッション CSRF 必須で同 Middleware を適用する。
- **REQ-quiz-answering-191**: The system shall `App\Policies\AnswerPolicy::create(User, Question)` を REQ-quiz-answering-081 の通り実装する。`AnswerPolicy::view(User, Answer)` は `$auth.id === $answer.user_id` のみ true（自分の履歴のみ閲覧可）、coach / admin は本 Policy で false 扱いとし、集計閲覧は Service 経由とする。
- **REQ-quiz-answering-192**: The system shall `App\Policies\QuestionAttemptPolicy::view(User, QuestionAttempt)` を `$auth.id === $attempt.user_id` のみ true、coach / admin は false 扱いとする（同上）。
- **REQ-quiz-answering-193**: The system shall `App\Policies\SectionQuizPolicy::view(User, Section)` を REQ-quiz-answering-020 の通り実装する（受講登録 + cascade visibility）。
- **REQ-quiz-answering-194**: The system shall `App\Policies\WeakDrillPolicy::view(User, Enrollment)` を REQ-quiz-answering-050 の通り実装する。
- **REQ-quiz-answering-195**: When coach / admin が誤って `/quiz/...` URL に直接アクセスする際, the system shall `role:student` Middleware で HTTP 403 を返す（受講生 UI のため）。
- **REQ-quiz-answering-196**: When 受講生が他 Certification の `enrollment_id` を含む URL でアクセスする際, the system shall `EnrollmentPolicy::view` で HTTP 403 を返す。
- **REQ-quiz-answering-197**: When 受講生が他 Certification の Section / Question に紐づく URL でアクセスする際, the system shall `SectionQuizPolicy::view` で「親 Certification 受講中」検証が失敗するため HTTP 404 を返す（資格存在は隠す）。

### 非機能要件

- **NFR-quiz-answering-001**: The system shall 状態変更を伴う Action（`StoreAnswerAction` / `RecountQuestionAttemptAction`）を `DB::transaction()` で囲み、`Answer` INSERT と `QuestionAttempt` UPSERT を原子的に同期する。
- **NFR-quiz-answering-002**: The system shall 演習エントリ画面 / 履歴一覧 / サマリ画面の N+1 を `with()` Eager Loading で避ける: Section 演習画面では `with('options', 'category', 'section.chapter.part')` + `withAttemptOf(auth.id)`（受講生のみ）、履歴一覧では `with('question.section.chapter.part', 'question.category')`、サマリ画面では `with('question.section.chapter.part', 'question.category')` を呼出側 Action / Service で組み立てる。
- **NFR-quiz-answering-003**: The system shall 以下 INDEX を migration で定義する: `answers.(user_id, answered_at)` 複合（履歴一覧の高速取得）/ `answers.(user_id, question_id)` 複合（Question 単位履歴の高速取得 + UPSERT 補助）/ `answers.(question_id, is_correct)` 複合（[[mock-exam]] や dashboard からの問題別正答率集計補助）/ `answers.source` 単体 / `answers.deleted_at` 単体 / `question_attempts.(user_id, question_id)` UNIQUE / `question_attempts.(user_id, last_answered_at)` 複合（サマリ画面ソート用）/ `question_attempts.deleted_at` 単体。
- **NFR-quiz-answering-004**: The system shall ドメイン例外を `app/Exceptions/QuizAnswering/` 配下に独立クラスとして実装する: `EnrollmentInactiveForAnswerException`（HTTP 409、`ConflictHttpException` 継承）/ `QuestionUnavailableForAnswerException`（HTTP 409）/ `QuestionOptionMismatchException`（HTTP 422、`UnprocessableEntityHttpException` 継承）/ `WeakDrillCategoryMismatchException`（HTTP 404、`NotFoundHttpException` 継承）。
- **NFR-quiz-answering-005**: The system shall `QuestionAttemptStatsService` を [[learning]] の `ProgressService` / `StreakService` 等と同等のステートレス Service として実装し、`DB::transaction()` を内部で持たない（NFR-learning-002 / NFR-enrollment-005 と整合）。
- **NFR-quiz-answering-006**: The system shall 解答送信 API のレスポンスに **正答判定 + 解説 + 直近 `QuestionAttempt` サマリ** を含め、追加クエリ無しで結果画面を描画可能とする（クライアント側からの追加リクエストを避ける）。
- **NFR-quiz-answering-007**: The system shall 解答結果画面のクライアントサイド描画を **素の JavaScript**（`resources/js/quiz-answering/answer-form.js`）で行い、Wave 0b 共通ユーティリティ `utils/fetch-json.js` を経由する。`Alpine.js` / `Livewire` は採用しない（`tech.md` のフロントエンド方針に整合）。
- **NFR-quiz-answering-008**: The system shall `views/quiz/**` を Wave 0b で整備済みの共通 Blade コンポーネント（`<x-button>` / `<x-form.radio>` / `<x-card>` / `<x-badge>` / `<x-alert>` / `<x-empty-state>` / `<x-breadcrumb>` / `<x-paginator>` / `<x-table>`）に準拠して構築する。
- **NFR-quiz-answering-009**: The system shall Answer / QuestionAttempt の SoftDelete を **手動オペレーション以外では実施しない**（誤解答取消や受講生退会時に admin 操作で SoftDelete 可能性あり、本 Feature の通常フローでは SoftDelete を発生させない）。SoftDeletes 採用の主目的は履歴保持と退会時の論理削除に備える保険。
- **NFR-quiz-answering-010**: The system shall `[[mock-exam]]` の `WeaknessAnalysisService` が未提供 / 未バインドの環境でも本 Feature の Service / Controller / View が崩壊しないこと（C-057 のフォールバック）。実装上は `WeaknessAnalysisService` を **Interface 経由で type-hint** し、`MockExam` Feature の Service Provider 未登録時には `NullWeaknessAnalysisService`（全カテゴリで `false` を返す Null Object パターン）を `app/Providers/QuizAnsweringServiceProvider.php` でフォールバック bind する。

## スコープ外

- **Question / QuestionOption / QuestionCategory の CRUD** — [[content-management]] が所有。本 Feature は Eloquent Model の **読み取り再利用** のみ
- **mock-exam セッション中の解答記録** — `MockExamAnswer` は [[mock-exam]] が所有（時間制限 + 一括採点の世界）。本 Feature の `Answer` は基礎ターム / 苦手分野ドリルの即時採点世界のみを担う。両者は混じらない
- **mock-exam 弱点ヒートマップの算出** — `WeaknessAnalysisService` は [[mock-exam]] 所有。本 Feature はその契約を消費するのみ（C-051 / C-057）
- **解答時の自信度・解答時間記録** — `product.md` 明示によりスコープ外（両参考 LMS とも未実装、正答率記録 + 弱点ヒートマップで代替）
- **問題演習のセッション化（複数問題を 1 セッション化して時間計測 / 進捗率を出す）** — 1 問単位即時採点の世界に閉じる。セッション概念は mock-exam 側
- **解答時のヒント表示 / 「半分の選択肢を削る」等のヘルパ** — シンプルな選択肢 + 解説のみ（業界 LMS の慣例に従う）
- **記述式問題への対応** — `product.md` 明示によりスコープ外（選択式のみ）。`QuestionOption.is_correct` が判定軸の前提
- **複数選択式問題** — 業界標準寄せで `Question` は単一正答 1 件モデル（[[content-management]] REQ-033 が保証）
- **問題お気に入り / ブックマーク** — `product.md` 明示によりスコープ外（教材・問題ともに）
- **解答取消・履歴削除** — 不変履歴（NFR-009）。誤送信は `attempt_count` に積まれたまま、`QuestionAttempt.correct_count` は正答率に反映される。受講生は履歴の改変権限を持たない
- **苦手分野ドリルの「セッション化」（カテゴリの全問を解いて結果サマリ表示）** — シンプルに 1 問単位の即時採点のみ。「カテゴリ全制覇」サマリは将来拡張領域
- **問題自体のレポート機能（誤植報告等）** — スコープ外。受講生 → コーチへのフィードバックは [[chat]] / [[qa-board]] 経由
- **AI による問題生成 / 自動解説生成** — Advance 範囲だが [[ai-chat]] の文脈で扱い、本 Feature では Static な問題マスタを参照する
- **学習ストリークへの寄与** — [[learning]] の `StreakService` は LearningSession のみを集計対象とする方針（REQ-learning-081）のため、本 Feature の Answer 行為はストリークに直接寄与しない。受講生 UX 上の「学習活動」として混合したい場合は Advance 範囲で `StreakService` 内部実装を拡張する余地として残す（要件レベルでは含めない）。

## 関連 Feature

- **依存先**（本 Feature が前提とする）:
  - [[auth]] — `User` モデル / `UserRole` Enum / `auth` Middleware / `role:student` Middleware
  - [[user-management]] — `User.status` の遷移管理（本 Feature の Answer 行為は status 変化を起こさない）
  - [[certification-management]] — `Certification` モデル（受講中判定で `enrollments.certification_id` を参照）
  - [[content-management]] — `Question` / `QuestionOption` / `QuestionCategory` / `Section` / `Chapter` / `Part` モデル + `Question::scopePublished()` + `ContentStatus` Enum + `QuestionDifficulty` Enum + `MarkdownRenderingService::toHtml`（Question 本文 / 解説の Markdown レンダリングに利用、Markdown 対応するか Plain Text にするかは [[content-management]] 側仕様に従う）
  - [[enrollment]] — `Enrollment` モデル / `EnrollmentStatus` Enum / `EnrollmentPolicy::view`。すべての Action / Policy で `enrollment.status IN (learning, paused)` を前提とする
  - [[learning]] — Section 詳細画面（`/learning/sections/{section}`）からの遷移リンクを供給。本 Feature の Section 演習画面（`/quiz/sections/{section}`）はそのリンク先となる
  - **[[auth]]** — Sanctum SPA 認証は Fortify ログイン後の Web セッション Cookie を再利用するため、auth Feature の通常ログインフローに依存。Wave 0b で `config/sanctum.php` の stateful ドメイン設定が導入される（Personal Access Token 機構は採用しない）
- **依存元**（本 Feature を利用する）:
  - [[mock-exam]] — `WeaknessAnalysisService` から見れば本 Feature は依存先側（C-057 / NFR-010 で「未提供時フォールバック」をするため）。逆向きでは [[mock-exam]] が本 Feature の `Answer` / `QuestionAttempt` を直接参照することは無い（mock-exam 集計は `MockExamAnswer` 主体）
  - [[dashboard]] — 受講生ダッシュボードの「学習済問題数 / 全体正答率 / カテゴリ別正答率」は `QuestionAttemptStatsService::summarize` / `byCategory` / `recentAnswers` を消費。coach ダッシュボードの担当受講生の演習状況 / admin ダッシュボードの全体演習状況も同 Service 経由
  - [[enrollment]] — `EnrollmentNote` 編集画面で coach が担当受講生の演習状況を補助参照する想定（Service 経由、本 Feature は admin 視点で全件返却する契約）
  - [[notification]] — 直接の依存はないが、`mock-exam` 採点完了通知に「苦手分野ドリルへ」のリンクを含める文脈で間接的に本 Feature を起動点とする想定
