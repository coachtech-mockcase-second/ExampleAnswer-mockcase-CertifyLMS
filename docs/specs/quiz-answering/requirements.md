# quiz-answering 要件定義

> **v3 改修反映**（2026-05-16）: `Question` → `SectionQuestion` 名称変更、`Answer` → `SectionQuestionAnswer` 名称変更、`QuestionAttempt` → `SectionQuestionAttempt` 名称変更、`difficulty` 関連削除、`passed` でも演習可、弱点ドリルは [[mock-exam]] の `WeaknessAnalysisService` 経由で苦手カテゴリの `SectionQuestion` を出題。**FE は Blade + Form POST + Redirect の純 Laravel 標準パターンに統一**（2026-05-16 確定）。Sanctum SPA / 公開 JSON API / JavaScript Ajax / sendBeacon は本 Feature では採用しない。

## 概要

受講生が **`SectionQuestion`（[[content-management]] が所有、Section 紐づき問題）を 1 問単位で演習** する基礎ターム中の日常学習機能と、**実践ターム中の苦手分野ドリル**（[[mock-exam]] の `WeaknessAnalysisService` の弱点判定に連動したカテゴリ別集中演習）を一体で提供する Feature。問題マスタの CRUD は [[content-management]] が所有し、本 Feature は **解答行為の受付 → 即時自動採点 → 解説表示 → 解答ログ（`SectionQuestionAnswer`）の追記 → SectionQuestion 単位サマリ（`SectionQuestionAttempt`）の UPSERT** に責務を絞る。模試問題（`MockExamQuestion`）は本 Feature の対象外（[[mock-exam]] が所有、本番形式の一括採点とは世界が異なる）。

FE は **Blade + Form POST + Redirect** の純 Laravel 標準パターン。解答送信は HTML フォームで POST → サーバで自動採点 + DB 反映 → **結果画面（独立 Blade ルート）へ 302 redirect** → 結果ペインに正誤・解説・自分の選択肢・正解を表示 → 「次の問題へ」リンクで連続演習。JavaScript（Ajax fetch / sendBeacon / DOM 操作）は本 Feature では採用しない。

## ロールごとのストーリー

- **受講生（student）**: 教材本文を読んだ後に Section 詳細画面の「Section の問題を解く」リンクから演習画面に遷移し、配下の公開済 SectionQuestion を `order ASC` で順次解いて理解度を即時確認する。実践ターム中は苦手分野ドリルから [[mock-exam]] の弱点ヒートマップで「苦手」と判定されたカテゴリの SectionQuestion を集中演習する。自分の解答履歴と SectionQuestion 単位サマリ（試行回数・正答率・最終解答日）をいつでも閲覧できる。`passed` 状態の Enrollment でも引き続き演習可能（status による機能制限なし、プラン期間内のみ）。
- **コーチ（coach）**: 直接の Controller / Action は持たない。担当資格に登録した受講生の演習状況は `SectionQuestionAttemptStatsService` の読み取り契約を [[dashboard]] / [[enrollment]] から消費する形で把握する。
- **管理者（admin）**: coach と同様、本 Feature の Controller は持たず、Service の集計値を [[dashboard]] 経由で全受講生分参照できる。

## 受け入れ基準（EARS形式）

### 機能要件 — A. データモデル

- **REQ-quiz-answering-001**: The system shall ULID 主キー / `SoftDeletes` を備えた `section_question_answers` テーブルを提供し、`user_id`（FK, restrictOnDelete）/ `section_question_id`（FK to `section_questions`, restrictOnDelete）/ `selected_option_id`（FK to `section_question_options`, nullOnDelete）/ `selected_option_body`（max 2000、スナップショット）/ `is_correct`（boolean, NOT NULL）/ `source`（enum `section_quiz` / `weak_drill`, NOT NULL）/ `answered_at`（datetime, NOT NULL）/ `created_at` / `updated_at` / `deleted_at` を保持する。
- **REQ-quiz-answering-002**: The system shall ULID 主キー / `SoftDeletes` を備えた `section_question_attempts` テーブルを提供し、`user_id`（FK, restrictOnDelete）/ `section_question_id`（FK, restrictOnDelete）/ `attempt_count`（unsigned int, default 0）/ `correct_count`（unsigned int, default 0）/ `last_is_correct`（boolean, default false）/ `last_answered_at`（datetime, NOT NULL）/ `created_at` / `updated_at` / `deleted_at` を保持する。`(user_id, section_question_id)` UNIQUE。
- **REQ-quiz-answering-003**: The system shall `App\Enums\AnswerSource` enum（`SectionQuiz` / `WeakDrill`）を提供し、`label()` で日本語ラベル（`Section演習` / `苦手分野ドリル`）を返す。
- **REQ-quiz-answering-005**: The system shall `section_question_answers.user_id` / `section_question_attempts.user_id` を `users.id` への外部キーとして持ち、User の物理削除を抑止する。
- **REQ-quiz-answering-007**: The system shall `selected_option_id` の外部キーを `nullOnDelete` 指定し、SectionQuestionOption の物理削除時に Answer 側を NULL にして残す。`selected_option_body` スナップショットで履歴可読性を保証する。
- **REQ-quiz-answering-008**: The system shall `SectionQuestionAnswer` を **非正規化保存**（user_id / section_question_id / source / answered_at / is_correct）で持ち、SectionQuestion / Section / Option が後から SoftDelete / 物理削除されても解答履歴の表示に支障が出ないようにする。

### 機能要件 — B. Section 紐づき問題演習エントリ

- **REQ-quiz-answering-020**: When 受講生が `GET /quiz/sections/{section}` にアクセスした際, the system shall `SectionQuizPolicy::view` で「親 Certification への active Enrollment（`status IN (learning, passed)`）が存在し、かつ Section / 親 Chapter / 親 Part がすべて `status=Published` かつ SoftDelete 済でない」ことを検証し、満たさなければ HTTP 404 を返す。
- **REQ-quiz-answering-021**: When 検証を通過した際, the system shall 当該 Section 配下の公開済 SectionQuestion を `order ASC, id ASC` で取得し、各 SectionQuestion について受講生の `SectionQuestionAttempt` を eager load した状態で `views/quiz/sections/show.blade.php` を描画する。一覧では SectionQuestion 本文先頭 80 文字 / カテゴリ名 / 最終正誤バッジ / 試行回数を表示する。**`difficulty` は表示しない**（v3 撤回）。
- **REQ-quiz-answering-022**: The system shall Section エントリ画面に「最初から順に解く」「未解答の問題から解く」「全部やり直す」のボタンを表示する。
- **REQ-quiz-answering-023**: When 受講生が `GET /quiz/sections/{section}/questions/{question}` にアクセスした際, the system shall (1) `SectionQuizPolicy::view` 検証、(2) `$question->section_id === $section->id` を確認、(3) Question / Section / Chapter / Part が公開済かつ SoftDelete 済でないことを再検証、違反時は HTTP 404 を返す。
- **REQ-quiz-answering-024**: When B-023 が通過した際, the system shall 問題本文 / 選択肢一覧（`order ASC`、正答フラグはマスク）/ カテゴリ名を表示し、ラジオボタン形式の選択肢 + 「解答を送信」ボタンを含む解答フォームを描画する。
- **REQ-quiz-answering-026**: When 受講生のログインユーザーが `User.status != UserStatus::InProgress` の場合, then the system shall `EnsureActiveLearning` Middleware で 403 を返す（`graduated` ユーザーは演習不可）。

### 機能要件 — C. 苦手分野ドリル

- **REQ-quiz-answering-050**: When 受講生が `GET /quiz/drills/{enrollment}` にアクセスした際, the system shall `WeakDrillPolicy::view` で `$enrollment->user_id === auth.id AND $enrollment->status IN (learning, passed)` を検証し、満たさなければ HTTP 403 を返す。
- **REQ-quiz-answering-051**: When C-050 を通過した際, the system shall 当該 Enrollment の `certification_id` 配下の `QuestionCategory` 一覧を `sort_order ASC` で取得し、各カテゴリについて (1) 受講生の `SectionQuestionAttempt` から算出した SectionQuestion カテゴリ別正答率、(2) [[mock-exam]] の `WeaknessAnalysisServiceContract::getWeakCategories(Enrollment)` 戻り値に含まれる **おすすめバッジ**、(3) カテゴリ配下の公開済 SectionQuestion 件数、を eager load する。
- **REQ-quiz-answering-052**: When 受講生が `GET /quiz/drills/{enrollment}/categories/{questionCategory}` にアクセスした際, the system shall `$questionCategory->certification_id === $enrollment->certification_id` を検証し、不一致なら HTTP 404 を返す。
- **REQ-quiz-answering-053**: When C-052 を通過した際, the system shall 対象 `QuestionCategory` 配下の **公開済 SectionQuestion** を以下条件で抽出する: (a) `deleted_at IS NULL`、(b) `status = Published`、(c) `section.chapter.part` がすべて `Published` かつ SoftDelete 済でない（cascade visibility）。**模試問題（`MockExamQuestion`）は出題対象に含めない**（v3 改修、`SectionQuestion` のみ）。並び順は `order ASC, id ASC`。
- **REQ-quiz-answering-055**: When 受講生が `GET /quiz/drills/{enrollment}/categories/{questionCategory}/questions/{question}` にアクセスした際, the system shall 同様の検証を行い、出題画面を `source = weak_drill` で描画する。
- **REQ-quiz-answering-057**: When `[[mock-exam]]` Feature がまだ実装されておらず `WeaknessAnalysisService` が public binding に登録されていない環境では, the system shall 「おすすめバッジ」を **全カテゴリで false** とし、空判定でも UI / 認可が破綻しないこと（フォールバック、Null Object Pattern）。

### 機能要件 — D. 解答送信・自動採点

- **REQ-quiz-answering-080**: When 受講生が `POST /quiz/questions/{question}/answer` を HTML フォームから呼んだ際（ペイロード: `selected_option_id` / `source` / `source=section_quiz` の場合は `section_id` / `source=weak_drill` の場合は `enrollment_id` + `question_category_id`、いずれも hidden input）, the system shall (1) `SectionQuestionAnswerPolicy::create` で解答可能性を判定、(2) `SectionQuestionAnswer\StoreAction::__invoke(User, SectionQuestion, SectionQuestionOption, AnswerSource): AnswerResult`(`SectionQuestionAnswerController::store` と一致) を呼んで解答を永続化、(3) `source` 値に応じて結果画面ルートへ 302 redirect する（`section_quiz` → `quiz.sections.result`、`weak_drill` → `quiz.drills.result`）。JSON 返却・JS Ajax は採用しない。
- **REQ-quiz-answering-081**: The system shall `SectionQuestionAnswerPolicy::create(User, SectionQuestion)` を以下で判定する: (a) `auth.id === $user.id`、(b) `$user->role === Student`、(c) `$user->status === UserStatus::InProgress`、(d) `$user` が `$question->section->part->certification_id` を `enrollments.status IN (learning, passed)` で受講中、(e) `$question->deleted_at IS NULL AND $question->status = Published`、(f) 親 Section / Chapter / Part がすべて `Published`。違反は HTTP 403。
- **REQ-quiz-answering-082**: If `selected_option_id` が `$question->options` のいずれにも該当しない場合, then the system shall HTTP 422 を返す。
- **REQ-quiz-answering-086**: When 解答送信が成功する際, the system shall 単一トランザクション内で (1) `SectionQuestionAnswer` を `(user_id, section_question_id, selected_option_id, selected_option_body, is_correct, source, answered_at = now())` で INSERT、(2) `SectionQuestionAttempt` を `(user_id, section_question_id)` で UPSERT（`attempt_count += 1, correct_count += (is_correct ? 1 : 0), last_is_correct, last_answered_at = now()`）する。
- **REQ-quiz-answering-087**: The system shall `is_correct` 判定を **`$selectedOption->is_correct === true`** の真偽値で行う（SectionQuestionOption が正解情報を持つ）。
- **REQ-quiz-answering-088**: When 結果画面（`GET /quiz/sections/{section}/questions/{question}/result/{answer}` または `GET /quiz/drills/{enrollment}/categories/{questionCategory}/questions/{question}/result/{answer}`）を表示する際, the system shall `correct_option_id` と `correct_option_body` を **SectionQuestion 単位で `is_correct=true` の Option を取得して** Blade に渡す。解説は `SectionQuestion.explanation`（nullable）が Blade に渡される。
- **REQ-quiz-answering-089**: The system shall 結果画面ルートを以下 2 経路で提供する: (a) `GET /quiz/sections/{section}/questions/{question}/result/{answer}`、(b) `GET /quiz/drills/{enrollment}/categories/{questionCategory}/questions/{question}/result/{answer}`。両者とも `SectionQuestionAnswerPolicy::view(User, SectionQuestionAnswer)` で本人のみ閲覧可能とし、`answer.section_question_id === $question.id` を検証し、不一致なら HTTP 404 を返す。Blade は正誤バッジ / 自分の選択肢 / 正解選択肢 / 解説 / 該当 SectionQuestion の累計 attempt（試行回数 / 正答率） / 「次の問題へ」リンク / 「Section エントリへ戻る」（または「カテゴリリストへ戻る」）リンクを表示する。次の問題は同 Section / 同カテゴリの公開済 SectionQuestion を `order ASC, id ASC` で並べ、現問題の `order` より大きい最初の 1 件を採用し、存在しない場合は「次の問題へ」リンクを非表示にする。

### 機能要件 — E. 履歴・サマリ閲覧

- **REQ-quiz-answering-120**: When 受講生が `GET /quiz/history/{enrollment}` にアクセスした際, the system shall `EnrollmentPolicy::view` で受講生本人の Enrollment を検証し、当該 Enrollment の `certification_id` に属する SectionQuestion への自身の `SectionQuestionAnswer` 一覧を `answered_at DESC` で `paginate(20)` する。Eager Load で `sectionQuestion.section.chapter.part` / `sectionQuestion.category` / `sectionQuestion.options`（正答判定用）を含める。
- **REQ-quiz-answering-121**: The system shall 履歴一覧で各 Answer 行に 解答日時 / 出典バッジ / カテゴリ名 / Section パンくず / 本文先頭 80 文字 / 選択肢本文 / 正誤バッジを表示する。
- **REQ-quiz-answering-122**: The system shall 履歴一覧フィルタ提供: `section_id` / `category_id` / `is_correct` / `source`。
- **REQ-quiz-answering-123**: When 受講生が `GET /quiz/stats/{enrollment}` にアクセスした際, the system shall `SectionQuestionAttempt` 一覧を `last_answered_at DESC` で `paginate(20)` する。
- **REQ-quiz-answering-126**: The system shall coach / admin が直接 `/quiz/history/{enrollment}` / `/quiz/stats/{enrollment}` にアクセスすることを `role:student` Middleware で 403。集計値の coach / admin 閲覧は [[dashboard]] / [[enrollment]] 経由で Service を呼ぶ専用画面に集約。

### 機能要件 — F. 集計 Service（SectionQuestionAttemptStatsService）

- **REQ-quiz-answering-150**: The system shall `App\Services\SectionQuestionAttemptStatsService` を提供し、`summarize(Enrollment): StatsSummary` / `byCategory(Enrollment): Collection<CategoryStats>` / `recentAnswers(Enrollment, int $limit = 5): Collection<SectionQuestionAnswer>` を公開する。
- **REQ-quiz-answering-151**: The system shall `summarize` で `total_questions_attempted` / `total_attempts` / `total_correct` / `overall_accuracy` / `last_answered_at` を返す。
- **REQ-quiz-answering-152**: The system shall `byCategory` で `QuestionCategory` 単位の集計を返す。
- **REQ-quiz-answering-154**: The system shall 全クエリで対象 SectionQuestion を `section.part.certification_id = $enrollment->certification_id` で絞り込み、他資格の解答が混入しないことを保証する。
- **REQ-quiz-answering-155**: The system shall 本 Service をステートレス Service として実装し、結果のキャッシュは持たない。

### 機能要件 — H. アクセス制御 / 認可

- **REQ-quiz-answering-190**: The system shall `routes/web.php` 配下の `/quiz/...` 群に `auth + role:student + EnsureActiveLearning` Middleware を適用する。
- **REQ-quiz-answering-191**: The system shall `SectionQuestionAnswerPolicy::create(User, SectionQuestion)` を REQ-081 の通り実装する。`view(User, SectionQuestionAnswer)` は `$auth.id === $answer.user_id` のみ true。
- **REQ-quiz-answering-192**: The system shall `SectionQuestionAttemptPolicy::view` を `$auth.id === $attempt.user_id` のみ true。
- **REQ-quiz-answering-193**: The system shall `SectionQuizPolicy::view(User, Section)` を REQ-020 の通り実装する。
- **REQ-quiz-answering-194**: The system shall `WeakDrillPolicy::view(User, Enrollment)` を REQ-050 の通り実装する。

### 非機能要件

- **NFR-quiz-answering-001**: The system shall `SectionQuestionAnswer\StoreAction` を `DB::transaction()` で囲み、Answer INSERT + Attempt UPSERT を原子的に同期する。
- **NFR-quiz-answering-002**: The system shall N+1 を Eager Loading で避ける。
- **NFR-quiz-answering-003**: The system shall 以下 INDEX を提供: `section_question_answers.(user_id, answered_at)` / `(user_id, section_question_id)` / `(section_question_id, is_correct)` / `source` / `deleted_at` / `section_question_attempts.(user_id, section_question_id)` UNIQUE / `(user_id, last_answered_at)` / `deleted_at`。
- **NFR-quiz-answering-004**: The system shall ドメイン例外を `app/Exceptions/QuizAnswering/` 配下に実装する（`SectionQuestionUnavailableForAnswerException` / `SectionQuestionOptionMismatchException` / `WeakDrillCategoryMismatchException` 等）。
- **NFR-quiz-answering-010**: The system shall `[[mock-exam]]` の `WeaknessAnalysisService` が未バインドの環境でも Null Object パターンで動作するよう、`QuizAnsweringServiceProvider` で `WeaknessAnalysisServiceContract` を `NullWeaknessAnalysisService` に `bindIf` する。

## スコープ外

- **SectionQuestion / SectionQuestionOption / QuestionCategory の CRUD** — [[content-management]] が所有
- **mock-exam セッション中の解答記録** — [[mock-exam]] の `MockExamAnswer` が所有、本 Feature の `SectionQuestionAnswer` とは別物
- **mock-exam 弱点ヒートマップの算出** — [[mock-exam]] の `WeaknessAnalysisService` が所有
- **`difficulty` 表示・フィルタ** — v3 撤回
- **記述式問題 / 複数選択式問題** — 単一正答 1 件モデル
- **問題お気に入り / ブックマーク** — `product.md` 撤回
- **解答取消・履歴削除** — 不変履歴
- **AI による問題生成 / 自動解説生成** — Advance 範囲だが [[ai-chat]] の文脈で扱う
- **Sanctum SPA 認証 / 自前 FE SPA / 公開 JSON API** — 2026-05-16 撤回。ドメイン的動機がなく、Blade + Form POST + Redirect で十分実装可能
- **JavaScript（Ajax fetch / sendBeacon / DOM 操作）** — 採用しない。解答送信は HTML form POST で完結、結果表示は独立 Blade ルート、選択肢 disabled / 正解 highlight 等の UI 効果は不要
- **API Resource クラス** — 公開 API を提供しないため不要。Web Controller の戻り値は `RedirectResponse`、Blade に渡すデータは Eloquent Model / Collection をそのまま使う

## 関連 Feature

- **依存先**:
  - [[auth]] — `User` / `UserRole` / `auth` / `role:student` / `EnsureActiveLearning` Middleware
  - [[certification-management]] — `Certification` モデル
  - [[content-management]] — `SectionQuestion` / `SectionQuestionOption` / `QuestionCategory` / `Section` / `Chapter` / `Part` モデル
  - [[enrollment]] — `Enrollment` / `EnrollmentStatus` / `EnrollmentPolicy::view`、`status IN (learning, passed)` を前提
  - [[learning]] — Section 詳細から本 Feature への遷移リンクを提供
- **依存元**:
  - [[mock-exam]] — `WeaknessAnalysisService` の正規実装を bind、本 Feature は `WeaknessAnalysisServiceContract` で型を持つ
  - [[dashboard]] — 受講生の「学習済問題数 / 全体正答率 / カテゴリ別正答率」は本 Service を消費
