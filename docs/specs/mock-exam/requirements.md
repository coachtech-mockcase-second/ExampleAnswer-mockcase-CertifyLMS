# mock-exam 要件定義

> **v3 改修反映**(2026-05-16) + **E-3 time_limit_minutes 削除**:
> - `MockExamQuestion` を中間テーブルから **模試マスタの子リソース** に昇格(`mock_exam_id` NOT NULL)、`MockExamQuestionOption` 新設、`MockExamAnswer.question_id` → `mock_exam_question_id` rename
> - `difficulty` カラム削除、修了判定ロジックは [[enrollment]] の `CompletionEligibilityService` に集約
> - `passed` でも受験可(復習モード)、`EnsureActiveLearning` Middleware で `graduated` ロック
> - **`time_limit_minutes` / `time_limit_minutes_snapshot` / `time_limit_ends_at` カラム削除**(E-3、資格マスタ側 `exam_duration_minutes` 削除の経緯と整合、時間制限なしで何度でも復習可)
> - **タイマー JS / auto-submit / `mock-exam:auto-submit-expired` Schedule Command すべて撤回**(E-3、実装複雑度削減)

## 概要

Certify LMS の中核となる **本番形式模擬試験 Feature**。コーチ／admin が資格ごとに **MockExam マスタ** を作成し、**模試マスタの子リソースとして `MockExamQuestion` を直接 CRUD** → 受講生が公開模試を **いつでも・何度でも受験** → **一括採点** → **分野別正答率ヒートマップ + 合格可能性スコア(3 バンド) + 苦手分野ドリル導線** を提供する。受講生の **基礎ターム → 実践ターム自動切替** の唯一の起点。**修了判定** の真実源データを供給する。

**E-3 で時間制限機能完全撤回**(資格マスタ側 `exam_duration_minutes` 削除の経緯と整合)。模試は時間制限なしで受講生が自分のペースで解答し、いつでも明示提出して採点を受ける。中断・再開対応は各問の **逐次保存(自動 PATCH)** で実現する(タイマーなし、いつでも続きから開始可能)。

集計 Service `WeaknessAnalysisService` を所有し、`WeaknessAnalysisServiceContract::getWeakCategories(Enrollment): Collection<QuestionCategory>` を [[quiz-answering]] のフォールバック契約に対し正規実装として bind する。

## ロールごとのストーリー

- **受講生(student)**: 受講中資格の公開模試一覧から受験したい模試を選び、セッションを作成 → 受験開始(`in_progress`)。初回受験で `Enrollment.current_term` が `mock_practice` に自動切替。受験中は各問の選択肢クリックで逐次保存が動き、ブラウザを閉じても再アクセスで進行中バナーから **続きから再開可能**(時間制限なし、E-3)。明示提出で一括採点 → 結果画面で総得点 / 合否 / 分野別ヒートマップ / 合格可能性スコアを確認し、苦手分野ドリル([[quiz-answering]])に遷移する。公開模試すべて合格達成で [[enrollment]] の「修了証を受け取る」ボタンが活性化される。
- **コーチ(coach)**: 担当資格の模試マスタを作成・編集・削除し、**模試マスタ詳細画面で MockExamQuestion を直接 CRUD**。担当受講生の受験結果一覧 / 結果詳細 / 分野別ヒートマップを `MockExamSession` 経由で閲覧。
- **管理者(admin)**: 全資格・全コーチに対して同等の操作を行う。

## 受け入れ基準(EARS形式)

### 機能要件 — A. データモデル

- **REQ-mock-exam-001**: The system shall ULID 主キー / `SoftDeletes` を備えた `mock_exams` テーブルを提供し、`certification_id`(NOT NULL)/ `title`(max 100)/ `description`(NULL 可)/ `order`(NOT NULL, default 0)/ `passing_score`(NOT NULL, 1..100、百分率)/ `is_published`(NOT NULL, default false)/ `published_at`(NULL 可)/ `created_by_user_id` / `updated_by_user_id` / timestamps / `deleted_at` を保持する。**`time_limit_minutes` カラムは持たない**(E-3 撤回)。
- **REQ-mock-exam-002**: The system shall ULID 主キー / `SoftDeletes` を備えた **`mock_exam_questions` テーブル**(模試マスタの子リソースとして独立)を提供し、`mock_exam_id`(FK, NOT NULL, cascadeOnDelete)/ `category_id`(FK to `question_categories`, NOT NULL)/ `body`(text, NOT NULL)/ `explanation`(text, NULL 可)/ `order`(NOT NULL, default 0)/ timestamps / `deleted_at` を保持する。`difficulty` カラムは持たない。
- **REQ-mock-exam-003**: The system shall ULID 主キー / `SoftDeletes` を備えた **`mock_exam_question_options` テーブル**(新設)を提供し、`mock_exam_question_id`(FK, NOT NULL, cascadeOnDelete)/ `body`(text, NOT NULL)/ `is_correct`(boolean, NOT NULL)/ `order`(NOT NULL)/ timestamps / `deleted_at` を保持する。
- **REQ-mock-exam-004**: The system shall ULID 主キー / `SoftDeletes` を備えた `mock_exam_sessions` テーブルを提供し、`mock_exam_id`(FK, restrictOnDelete)/ `enrollment_id`(FK, restrictOnDelete)/ `user_id`(FK, 非正規化)/ `status` enum(`not_started`/`in_progress`/`submitted`/`graded`/`canceled`)/ `generated_question_ids`(JSON、MockExamQuestion.id 配列スナップショット)/ `total_questions` / `passing_score_snapshot` / `started_at` / `submitted_at` / `graded_at` / `canceled_at` / `total_correct` / `score_percentage` / `pass` カラムを保持する。**`time_limit_minutes_snapshot` / `time_limit_ends_at` カラムは持たない**(E-3 撤回)。
- **REQ-mock-exam-005**: The system shall ULID 主キー(SoftDelete 非採用)を備えた `mock_exam_answers` テーブルを提供し、`mock_exam_session_id`(FK, cascadeOnDelete)/ `mock_exam_question_id`(FK, restrictOnDelete)/ `selected_option_id`(FK to `mock_exam_question_options`, nullOnDelete)/ `selected_option_body`(max 2000、スナップショット)/ `is_correct`(boolean, NOT NULL, default false、採点時に確定)/ `answered_at`(datetime, NOT NULL)/ timestamps を保持する。`(mock_exam_session_id, mock_exam_question_id)` UNIQUE。
- **REQ-mock-exam-006**: The system shall `App\Enums\MockExamSessionStatus` enum(`NotStarted` / `InProgress` / `Submitted` / `Graded` / `Canceled`)を提供する。
- **REQ-mock-exam-007**: The system shall `App\Enums\PassProbabilityBand` enum(`Safe` / `Warning` / `Danger` / `Unknown`)を提供する。
- **REQ-mock-exam-008**: The system shall `MockExam`、`MockExamQuestion`、`MockExamQuestionOption`、`MockExamSession`、`MockExamAnswer` の Eloquent モデルにそれぞれ親子リレーションを実装する。

### 機能要件 — B. MockExam マスタ管理(admin / coach)

- **REQ-mock-exam-040**: When admin または担当 coach が `GET /admin/mock-exams` にアクセスした際, the system shall `MockExamPolicy::viewAny` を通過したロールに対して、admin は全資格、coach は割当済資格の MockExam 一覧を `with('certification', 'createdBy', 'updatedBy')->withCount('mockExamQuestions')` で取得し `paginate(20)` する。
- **REQ-mock-exam-041**: The system shall 一覧画面でフィルタを提供する: `certification_id` / `is_published` / `keyword`。
- **REQ-mock-exam-043**: When admin / 担当 coach が `POST /admin/mock-exams` を呼んだ際, the system shall `StoreMockExamRequest` で `certification_id` / `title` / `description` / `order` / `passing_score` を検証し、`is_published = false` / `created_by_user_id` / `updated_by_user_id` を $user で固定して INSERT する(E-3 で `time_limit_minutes` バリデーションなし)。
- **REQ-mock-exam-045**: When admin / 担当 coach が `PUT /admin/mock-exams/{mockExam}` を呼んだ際, the system shall 同セットのカラムを更新可能とし、`certification_id` は変更不可とする。
- **REQ-mock-exam-046**: When admin / 担当 coach が `DELETE /admin/mock-exams/{mockExam}` を呼んだ際, the system shall (1) `is_published=false` であること、(2) 当該 MockExam に紐づく `MockExamSession` がすべて `canceled` 状態であること、を `DestroyAction` で検証し、違反は `MockExamInUseException`(HTTP 409)、合格なら SoftDelete する。
- **REQ-mock-exam-047**: When admin / 担当 coach が `POST /admin/mock-exams/{mockExam}/publish` を呼んだ際, the system shall (1) `is_published === false`、(2) MockExamQuestion が 1 件以上組成済、を検証し、合格なら `is_published=true` / `published_at=now()` で UPDATE する。

### 機能要件 — C. MockExamQuestion 管理(模試マスタの子リソース、独立 CRUD)

- **REQ-mock-exam-060**: When admin / 担当 coach が `GET /admin/mock-exams/{mockExam}/questions` にアクセスした際, the system shall 当該 MockExam 配下の MockExamQuestion 一覧を `order ASC` で表示する。
- **REQ-mock-exam-061**: When admin / 担当 coach が `POST /admin/mock-exams/{mockExam}/questions` で MockExamQuestion を新規作成した際, the system shall `body` / `explanation` / `category_id` / 2〜6 個の `mock_exam_question_options[]` を受け取り、`order = COALESCE(MAX(order), -1) + 1` で末尾に挿入する。`category_id` 不整合時は `QuestionCategoryMismatchException`(HTTP 422)。
- **REQ-mock-exam-062**: The system shall MockExamQuestion 作成・更新時に `is_correct=true` の選択肢がちょうど 1 件であることを検証し、違反時は `QuestionInvalidOptionsException`(HTTP 422)。
- **REQ-mock-exam-063**: When admin / 担当 coach が `PUT /admin/mock-exam-questions/{question}` で更新した際, the system shall `body` / `explanation` / `category_id` を更新可能とし、`mock_exam_id` は変更不可。
- **REQ-mock-exam-064**: When admin / 担当 coach が `DELETE /admin/mock-exam-questions/{question}` で削除した際, the system shall (1) 親 MockExam が `is_published = false` であること、または (2) 削除後も MockExamQuestion が 1 件以上残ること、を検証し SoftDelete する。
- **REQ-mock-exam-065**: When admin / 担当 coach が `PUT /admin/mock-exams/{mockExam}/questions/reorder` を呼んだ際, the system shall 同 MockExam 内の `mock_exam_questions.order` を一括 UPDATE する。
- **REQ-mock-exam-066**: The system shall MockExamQuestion CRUD の認可を `MockExamQuestionPolicy::manage($mockExam)` で統一する。

### 機能要件 — D. 受講生 模試一覧・セッション作成

- **REQ-mock-exam-100**: When 受講生が `GET /mock-exams` にアクセスした際, the system shall 受講生の `enrollments WHERE status IN (learning, passed)` を取得、各 `certification_id` 配下の `MockExam WHERE is_published = true` を eager load して `mockExamQuestions` カウント + 最新セッション / 進行中セッションを併記して表示する。
- **REQ-mock-exam-101**: The system shall `User.status != UserStatus::InProgress` の場合、`EnsureActiveLearning` Middleware で 403。
- **REQ-mock-exam-102**: When 受講生が `GET /mock-exams/{mockExam}` にアクセスした際, the system shall `MockExamPolicy::take` で「受講中(learning + passed)」を検証、`is_published = true` を検証、違反で HTTP 404。
- **REQ-mock-exam-103**: When 受講生が `POST /mock-exams/{mockExam}/sessions` を呼んだ際, the system shall (1) 認可・公開チェック、(2) 重複進行中ガード、を `MockExamSession\StoreAction`(`MockExamSessionController::store` と一致) で検証し、合格時は新規 `MockExamSession` を以下で INSERT: `status = not_started` / `generated_question_ids = mockExamQuestions->orderBy('order')->pluck('id')->all()` / `total_questions` / `passing_score_snapshot = mockExam.passing_score`(**`time_limit_minutes_snapshot` フィールドなし**、E-3)。
- **REQ-mock-exam-104**: If MockExam に紐づく `mock_exam_questions` が 0 件の場合, then the system shall `MockExamHasNoQuestionsException`(HTTP 409)を throw。
- **REQ-mock-exam-105**: When 受講生が `DELETE /mock-exam-sessions/{session}` を呼んだ際, the system shall `MockExamSession\DestroyAction`(`MockExamSessionController::destroy` と一致) で `not_started` のみキャンセル許容、`status = Canceled` / `canceled_at = now()` を UPDATE、`TermJudgementService::recalculate` を呼ぶ。

### 機能要件 — E. 受験開始・受験中・逐次保存(E-3 で時間制限なし)

- **REQ-mock-exam-150**: When 受講生が `POST /mock-exam-sessions/{session}/start` を呼んだ際, the system shall `MockExamSession\StartAction`(`MockExamSessionController::start` と一致) で (1) Policy 検証、(2) `status = NotStarted` を検証、(3) `MockExam.is_published === true` を検証、(4) `DB::transaction()` 内で `status = InProgress` / `started_at = now()` を UPDATE(**`time_limit_ends_at` セットなし**、E-3)、(5) `TermJudgementService::recalculate` を呼ぶ。
- **REQ-mock-exam-151**: When 受講生が `GET /mock-exam-sessions/{session}` にアクセスした際, the system shall `status` に応じて分岐: `NotStarted` → lobby / `InProgress` → take / `Submitted/Graded` → result / `Canceled` → canceled view。
- **REQ-mock-exam-152**: When `take` Blade を描画する際, the system shall `generated_question_ids` の順に MockExamQuestion + MockExamQuestionOption を eager load、既存の `MockExamAnswer` を取得し `keyBy`(**`time_limit_ends_at` JS 渡しなし**、E-3)。
- **REQ-mock-exam-154**: When 受講生が `PATCH /mock-exam-sessions/{session}/answers` を呼んだ際(ペイロード: `mock_exam_question_id` / `selected_option_id`), the system shall `MockExamAnswer\UpdateAction`(`MockExamAnswerController::update` と一致) で (1) Policy、(2) `status === InProgress`、(3) `mock_exam_question_id IN session.generated_question_ids`、(4) `selected_option_id IN mockExamQuestion.options`、を検証(**時間制限超過検査なし**、E-3)、UPSERT で保存。`is_correct` は採点時確定。

### 機能要件 — F. 提出・採点

- **REQ-mock-exam-200**: When 受講生が `POST /mock-exam-sessions/{session}/submit` を呼んだ際, the system shall `SubmitAction` で (1) Policy、(2) `status === InProgress` を検証、(3) `DB::transaction()` 内で `status = Submitted` / `submitted_at = now()` を UPDATE、(4) 採点ロジックを即時実行して `status = Graded` / `graded_at = now()` / `total_correct` / `score_percentage` / `pass` を UPDATE、(5) `TermJudgementService::recalculate` を呼ぶ、(6) `DB::afterCommit()` で `NotifyMockExamGradedAction` 発火。
- **REQ-mock-exam-201**: **削除(E-3 撤回)**: 旧「時間外で `SubmitAction` が走った際 ... 未解答は `is_correct=false` 扱い」。時間制限なしのため、未解答問題は提出時点で `MockExamAnswer` が存在しなければ `is_correct=false` 扱いとする(同等動作だが「時間切れ」概念はない)。
- **REQ-mock-exam-205**: The system shall `SubmitAction` 内で `lockForUpdate()` を session 行に当て、二重提出を防ぐ。

### 機能要件 — G. 採点ロジック(GradeAction、内部)

- **REQ-mock-exam-220**: The system shall 採点を `GradeAction::__invoke(MockExamSession $session): void` で実装し、`SubmitAction` の transaction 内で呼ぶ。
- **REQ-mock-exam-221**: When `GradeAction` が走る際, the system shall 各 `MockExamAnswer.is_correct` を以下で確定する: `selected_option_id IS NOT NULL AND MockExamQuestionOption.is_correct = true`。
- **REQ-mock-exam-222**: When `GradeAction` が走る際, the system shall `total_correct` / `score_percentage = ROUND(total_correct / total_questions * 100, 2)` / `pass = (score_percentage >= passing_score_snapshot)` を確定する。
- **REQ-mock-exam-223**: When MockExamQuestion / MockExamQuestionOption が採点時点で SoftDelete されていた場合, the system shall `withTrashed()` で正答取得を試み、cascade null されていた場合は `is_correct = false` で確定する。

### 機能要件 — H. 結果画面・履歴閲覧

- **REQ-mock-exam-250**: When 受講生が `GET /mock-exam-sessions/{session}` で `status === Graded` の場合, the system shall `result.blade.php` を描画し、(a) 総得点 / `score_percentage`、(b) 合否バッジ、(c) ヒートマップ、(d) 合格可能性スコア、(e) 各分野の「苦手分野ドリルへ」リンク、(f) 「もう一度受験」ボタン、(g) 「結果一覧へ戻る」リンクを表示する。
- **REQ-mock-exam-251**: When 受講生が `GET /mock-exam-sessions` にアクセスした際, the system shall 受講生本人の Session を `graded` / `canceled` のみ paginate(20)。
- **REQ-mock-exam-252**: The system shall フィルタ提供: `certification_id` / `mock_exam_id` / `pass`。

### 機能要件 — I. コーチ / admin 結果閲覧

- **REQ-mock-exam-300**: When admin / coach が `GET /admin/mock-exam-sessions` にアクセスした際, the system shall admin は全セッション、coach は **担当資格に登録した受講生** のセッション(`enrollment.certification.coaches` 経由)を paginate(20)。
- **REQ-mock-exam-302**: When admin / coach が `GET /admin/mock-exam-sessions/{session}` にアクセスした際, the system shall 認可検証し、認可済なら結果ビューを表示。

### 機能要件 — J. 集計 Service(WeaknessAnalysisService)

- **REQ-mock-exam-350**: The system shall `App\Services\WeaknessAnalysisService` を提供し、`getWeakCategories(Enrollment): Collection<QuestionCategory>` を [[quiz-answering]] の Contract に対する正規実装として bind する。
- **REQ-mock-exam-351**: The system shall `getWeakCategories` を以下で実装する: 直近 3 件の Graded `MockExamSession` の `MockExamAnswer` を `JOIN mock_exam_questions JOIN question_categories` で `GROUP BY category_id`、`AVG(is_correct) * 100 < passing_score_snapshot * 0.70` のカテゴリを「弱点」と判定。
- **REQ-mock-exam-352**: The system shall `getHeatmap(MockExamSession): Collection<CategoryHeatmapCell>` を提供。
- **REQ-mock-exam-353**: The system shall `getPassProbabilityBand(Enrollment): PassProbabilityBand` を提供(直近 3 件の平均得点率を `passing_score` × 0.90 / 0.70 で 3 バンド分け、採点済 0 件は `Unknown`)。
- **REQ-mock-exam-355**: The system shall `App\Providers\MockExamServiceProvider::register()` で `WeaknessAnalysisServiceContract::class` を `WeaknessAnalysisService::class` に bind する。

### 機能要件 — K. 修了判定との連携([[enrollment]] が所有)

- **REQ-mock-exam-380**: The system shall 修了判定ロジック(`CompletionEligibilityService::isEligible(Enrollment)`)の **真実源データを提供する側** に徹する。
- **REQ-mock-exam-381**: The system shall 修了申請承認フローを **提供しない**(受講生「修了証を受け取る」ボタン自己完結、v3)。

### 機能要件 — L. アクセス制御 / 認可

- **REQ-mock-exam-400**: The system shall `/admin/mock-exams/...` 群に `auth + role:admin|coach` を、`/mock-exams/...` 群に `auth + role:student + EnsureActiveLearning` を適用する。
- **REQ-mock-exam-401**: The system shall `MockExamPolicy` を提供し、admin / coach 担当 / student 受講中(learning + passed)の判定を行う。
- **REQ-mock-exam-402**: The system shall `MockExamSessionPolicy` を提供する。
- **REQ-mock-exam-403**: When coach が他コーチ担当資格のリソースに触ろうとした際, the system shall HTTP 403 を返す。

### 非機能要件

- **NFR-mock-exam-001**: The system shall 状態変更を伴うすべての Action を `DB::transaction()` で囲み、`TermJudgementService::recalculate` を同 transaction で呼ぶ。
- **NFR-mock-exam-002**: The system shall N+1 を Eager Loading で避ける。
- **NFR-mock-exam-003**: The system shall 以下 INDEX を提供: `mock_exams.(certification_id, is_published, order)` / `mock_exam_questions.(mock_exam_id, order)` / `mock_exam_question_options.(mock_exam_question_id, order)` / `mock_exam_sessions.(enrollment_id, status)` / `mock_exam_sessions.(mock_exam_id, pass)` / `mock_exam_sessions.(user_id, graded_at)` / `mock_exam_answers.(mock_exam_session_id, mock_exam_question_id)` UNIQUE。
- **NFR-mock-exam-004**: The system shall ドメイン例外を `app/Exceptions/MockExam/` 配下に実装する(**`MockExamSessionTimeExceededException` 削除**、E-3)。
- **NFR-mock-exam-006**: The system shall 逐次保存のクライアント JS(自動 PATCH)を素の JavaScript で実装する(**タイマー JS / auto-submit JS なし**、E-3)。
- **NFR-mock-exam-008**: The system shall 受験中画面に正答情報を埋め込まない(クライアント JS / API Resource いずれにも露出させない)。
- **NFR-mock-exam-009**: The system shall `MockExamSession` 状態遷移を `lockForUpdate()` でガードする。

## スコープ外

- **時間制限機能**(E-3 で撤回) — タイマー UI / サーバ時刻ベース残り時間計算 / auto-submit / Schedule Command による期限切れ自動採点はすべて持たない。模試は時間制限なし、受講生は自分のペースで解答 + 明示提出
- **mock-exam 専用問題の content-management 管理** — v3 撤回
- **`difficulty` 管理** — v3 撤回
- **修了申請承認フロー** — v3 撤回、受講生自己完結
- mock-exam セッション中のリアルタイム他者表示 / 共同受験
- 採点後の解答振り返り画面 — 結果画面では分野別ヒートマップのみ
- 複数受講生での同時採点キュー化(Advance Queue)
- Advance Broadcasting で「コーチに採点完了をリアルタイム push」

## 関連 Feature

- **依存先**:
  - [[auth]] — `User` / `UserRole` / `auth` / `role:student|coach|admin` / `EnsureActiveLearning` Middleware
  - [[certification-management]] — `Certification` + `certification_coach_assignments`
  - [[content-management]] — `QuestionCategory` 共有マスタ
  - [[enrollment]] — `Enrollment` / `EnrollmentStatus` / `TermJudgementService::recalculate` / `CompletionEligibilityService`
  - [[quiz-answering]] — `WeaknessAnalysisServiceContract` Interface
- **依存元**:
  - [[enrollment]] — `CompletionEligibilityService` が本 Feature の `MockExam` / `MockExamSession` をクエリ
  - [[quiz-answering]] — 苦手分野ドリルが `WeaknessAnalysisServiceContract::getWeakCategories` で本 Feature の Service を消費
  - [[dashboard]] — 受講生の弱点分析パネル / 合格可能性スコア / 進行中模試あり / coach の担当受講生弱点ヒートマップ
  - [[notification]] — 採点完了通知の dispatch 起点
