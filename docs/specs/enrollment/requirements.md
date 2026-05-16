# enrollment 要件定義

> **v3 改修反映**（2026-05-16）: `assigned_coach_id` 削除（資格 × N コーチ N:N に変更）、`paused` 削除（status 3 値化）、修了申請承認フロー削除（受講生「修了証を受け取る」ボタンで自己完結）、`coach_change` event_type 削除。

## 概要

受講生 × 資格 の多対多紐づけ（Enrollment）を中心に、**受講登録 / 受講状態管理 / 学習ターム管理 / 個人目標 CRUD / コーチ用受講生メモ / 修了の自己完結** を一手に担う Feature。Certify LMS の中核ハブとして、[[learning]] / [[mock-exam]] / [[dashboard]] / [[notification]] / [[certification-management]] から「現在ターム判定」「修了判定」「受講進捗 KPI」が参照される。

- 1 受講生が複数資格を同時受講する Certify LMS 独自の柔軟性を、`enrollments` 中間テーブルが個別の試験日 / 状態 / ターム / 目標を保持することで実現する
- **担当コーチは Enrollment に紐づかない**（資格 × N コーチ N:N、`certification_coach_assignments` 中間テーブルで管理）。1 受講生が登録した資格に対しては、その資格に割当てられた全コーチが担当する関係になる
- 修了は **LMS 内の達成判定**（公開模試すべての合格点超え → 受講生「修了証を受け取る」ボタン押下 → 自己完結発火）。admin 承認フローは撤回。LMS 外の本試験日（`exam_date`）とは独立した概念
- 学習進行は教習所メタファー（学科 → 技能）を採用。`current_term` が `basic_learning` → `mock_practice` を表現し、初回 mock-exam セッション開始で自動切替

## ロールごとのストーリー

- **受講生（student）**: 公開済資格カタログから自己登録し、目標受験日（任意）を設定する。複数資格を同時受講しながら、現在ターム / 進捗 / 試験日カウントダウンを把握する。個人目標を自由に立てて達成を記録し、公開模試すべて合格達成時に **「修了証を受け取る」ボタン** で即時修了する。
- **コーチ（coach）**: **自分が担当する資格に登録した受講生集合**（`certification_coach_assignments` 経由）に対し、`EnrollmentNote` で面談以外の日々の観察を時系列で記録する。担当範囲の受講生の個人目標は閲覧のみで介入せず、必要に応じて [[chat]] / [[qa-board]] で声をかける。
- **管理者（admin）**: 全 Enrollment を閲覧 / 手動割当 / 状態強制更新できる。修了は受講生主導の自己完結なので admin 承認は不要。

## 受け入れ基準（EARS形式）

### 機能要件 — Enrollment 基本エンティティ

- **REQ-enrollment-001**: The system shall ULID 主キー / SoftDeletes / `user_id` × `certification_id` UNIQUE 制約を備えた `enrollments` テーブルを提供する。
- **REQ-enrollment-002**: The system shall `enrollments` に `user_id` / `certification_id` / `exam_date`（DATE, nullable）/ `status`（`learning` / `passed` / `failed`）/ `current_term`（`basic_learning` / `mock_practice`）/ `passed_at`（nullable, datetime）/ `created_at` / `updated_at` / `deleted_at` カラムを保持する。**`assigned_coach_id` カラムは持たない**（担当コーチは `certification_coach_assignments` で資格経由）。**`completion_requested_at` カラムは持たない**（修了は受講生「修了証を受け取る」で即時 `passed`、admin 承認なし）。
- **REQ-enrollment-003**: The system shall `Enrollment.status` を `App\Enums\EnrollmentStatus`（`Learning` / `Passed` / `Failed`）として表現し、各値に日本語ラベル（`学習中` / `修了` / `学習中止`）を `label()` メソッドで返す。`Paused`（休止中）は採用しない。
- **REQ-enrollment-004**: The system shall `Enrollment.current_term` を `App\Enums\TermType`（`BasicLearning` / `MockPractice`）として表現し、各値に日本語ラベル（`基礎ターム` / `実践ターム`）を `label()` メソッドで返す。
- **REQ-enrollment-005**: The system shall 受講登録時に `status = learning` / `current_term = basic_learning` / `passed_at = null` を初期値として設定する。

### 機能要件 — 受講登録（student の自己登録）

- **REQ-enrollment-010**: When 受講生が公開済資格の詳細画面で受講登録する, the system shall `enrollments` に `user_id = 受講生.id` / `certification_id = 対象資格.id` / `exam_date = 入力値（任意、nullable 許容）` / `status = learning` / `current_term = basic_learning` を INSERT する。
- **REQ-enrollment-011**: If 対象資格が `Certification.status != published` または SoftDelete 済の場合, then the system shall HTTP 404 を返す。
- **REQ-enrollment-012**: If 同一 `user_id` × `certification_id` の Enrollment が SoftDelete されていない状態で既に存在する場合, then the system shall `EnrollmentAlreadyEnrolledException`（HTTP 409）を返す。
- **REQ-enrollment-013**: If `exam_date` が指定された場合かつ当日以前の場合, then the system shall バリデーションエラー（HTTP 422）を返す。`exam_date` 未指定（NULL）も許容する。
- **REQ-enrollment-014**: When 受講登録が成功した直後, the system shall `EnrollmentStatusLog` に `event_type = status_change` / `to_status = learning` / `changed_reason = '新規登録'` を 1 件 INSERT する。担当コーチの自動設定は行わない（資格 × N コーチ N:N、`certification_coach_assignments` 経由）。
- **REQ-enrollment-015**: When 受講生のログインユーザーが `User.status != UserStatus::InProgress` の場合, the system shall 受講登録を `EnsureActiveLearning` Middleware（[[auth]] 所有）でブロックし、HTTP 403 を返す（`graduated` ユーザーはプラン機能利用不可）。

### 機能要件 — 受講登録（admin の手動割当）

- **REQ-enrollment-020**: When admin が受講生 × 資格を手動割当する, the system shall 受講生 (`user_id`) と資格 (`certification_id`) を admin が指定し、`exam_date`（任意）を入力させて Enrollment を INSERT する。
- **REQ-enrollment-021**: If 対象受講生が `User.status != in_progress` または `User.role != student` の場合, then the system shall HTTP 422 を返す（`invited` / `graduated` / `withdrawn` の受講生には受講登録できない）。
- **REQ-enrollment-022**: When admin が手動割当を実行した直後, the system shall `EnrollmentStatusLog` に `event_type = status_change` / `to_status = learning` / `changed_by_user_id = admin.id` / `changed_reason = 'admin による割当'`（または admin が入力した理由）を 1 件 INSERT する。
- **REQ-enrollment-023**: When admin が Enrollment の `exam_date` を変更する, the system shall `status != passed` を検証し、`exam_date` のみを UPDATE する（`status` / `current_term` / `passed_at` は本操作で更新しない、各々の専用 Action 経由のみとする）。

### 機能要件 — 受講中資格閲覧

- **REQ-enrollment-030**: When 受講生が `/enrollments` にアクセスする, the system shall ログイン受講生の Enrollment 一覧（SoftDelete 除外）を `current_term ASC, exam_date ASC NULLS LAST` でソートして返し、各 Enrollment に資格情報 / 担当コーチ集合（`certification.coaches`） / 進捗率 / 試験日カウントダウン日数（exam_date 設定時のみ） / 個人目標件数を eager load する。
- **REQ-enrollment-031**: When 受講生が `/enrollments/{enrollment}` にアクセスする, the system shall ログイン受講生が所有する Enrollment のみ閲覧を許可し、他者の Enrollment は HTTP 403 を返す。
- **REQ-enrollment-032**: When admin が `/admin/enrollments` にアクセスする, the system shall 全 Enrollment 一覧（SoftDelete 除外、`withTrashed` パラメータ指定時は履歴含む）を提供し、`status` / `certification_id` / キーワード（受講生名 / メール 部分一致）でフィルタ可能とする。`assigned_coach_id` フィルタは不採用（資格経由）。
- **REQ-enrollment-033**: When coach が `/coach/students` 経由で担当受講生詳細を見る, the system shall **当該 coach が `certification_coach_assignments` で割り当てられた資格の Enrollment** のみ閲覧を許可する。「担当受講生」概念ではなく「担当資格に登録した受講生」スコープ。
- **REQ-enrollment-034**: While Enrollment が SoftDelete 済の状態, when admin がパス指定で `/admin/enrollments/{enrollment}` を開く, the system shall `withTrashed()` で復元解決して詳細を表示する。

### 機能要件 — 受講状態遷移

- **REQ-enrollment-040**: When admin が任意の Enrollment を手動で `failed` に更新する, the system shall `status = learning` の Enrollment にのみ手動失敗マークを許可し、`status` を `failed` に更新し、`EnrollmentStatusLog` に `event_type = status_change` / `from_status = learning` / `to_status = failed` / `changed_by_user_id = admin.id` / `changed_reason = admin 入力値` を記録する。
- **REQ-enrollment-041**: When 受講生または admin が `failed` Enrollment を再挑戦のため `learning` に戻す, the system shall `status` を `learning` に更新し、`EnrollmentStatusLog` に `event_type = status_change` / `from_status = failed` / `to_status = learning` / `changed_by_user_id = 操作者.id` / `changed_reason = '再挑戦'` を記録する。
- **REQ-enrollment-042**: The system shall `status = passed` の Enrollment に対する状態変更操作（admin 含む）をすべて拒否し、`EnrollmentAlreadyPassedException`（HTTP 409）を返す。
- **REQ-enrollment-043**: The system shall 状態遷移マトリクスを以下の通り強制する: `learning → passed`（受講生「修了証を受け取る」自己発火）、`learning → failed`（admin 手動 or 試験日超過 Schedule Command）、`failed → learning`（再挑戦）。それ以外の遷移はすべて `EnrollmentInvalidTransitionException`（HTTP 409）。`paused` 状態は採用しない。

### 機能要件 — ターム管理（current_term）

- **REQ-enrollment-060**: When `[[mock-exam]]` Feature の MockExamSession が `not_started → in_progress` に遷移した（または `submitted` / `graded` に進んだ）, the system shall `TermJudgementService::recalculate(Enrollment)` を呼び、`current_term` を再計算する。
- **REQ-enrollment-061**: When MockExamSession が `not_started → canceled` に遷移した（または物理的に削除された）, the system shall `TermJudgementService::recalculate(Enrollment)` を呼び、`current_term` を再計算する。
- **REQ-enrollment-062**: The system shall `TermJudgementService::recalculate(Enrollment)` を以下のロジックで実装する: `EXISTS(MockExamSession WHERE enrollment_id = X AND status IN ('in_progress', 'submitted', 'graded'))` が真なら `current_term = mock_practice`、偽なら `current_term = basic_learning`。
- **REQ-enrollment-063**: When `current_term` が変化した場合のみ, the system shall `enrollments.current_term` を UPDATE する。変化しない場合は UPDATE しない（不要な書き込みを避ける）。
- **REQ-enrollment-064**: The system shall `current_term` 変化を `EnrollmentStatusLog` に記録しない（高頻度操作のためログを汚さない）。

### 機能要件 — 個人目標（EnrollmentGoal）

- **REQ-enrollment-070**: The system shall ULID 主キー / SoftDeletes を備えた `enrollment_goals` テーブルを提供し、`enrollment_id` / `title`（max 100）/ `description`（nullable, max 1000）/ `target_date`（nullable, DATE）/ `achieved_at`（nullable, datetime）/ `created_at` / `updated_at` / `deleted_at` カラムを保持する。
- **REQ-enrollment-071**: When 受講生が自身の Enrollment 配下に目標を追加する, the system shall `EnrollmentGoal` を INSERT し、`achieved_at = null` を初期値とする。
- **REQ-enrollment-072**: When 受講生が自身の目標を編集する, the system shall `title` / `description` / `target_date` の更新を許可する。
- **REQ-enrollment-073**: When 受講生が自身の目標を削除する, the system shall SoftDelete する。
- **REQ-enrollment-074**: When 受講生が自身の目標を達成マークする, the system shall `achieved_at = now()` を UPDATE する。
- **REQ-enrollment-075**: When 受講生が自身の達成マークを取消する, the system shall `achieved_at = null` を UPDATE する。
- **REQ-enrollment-076**: While 個人目標を閲覧する状況, the system shall 当該 Enrollment が SoftDelete 済の場合は目標も非表示にする（一覧で除外）。
- **REQ-enrollment-077**: The system shall coach（資格担当者）/ admin に対し担当範囲の Enrollment 配下の目標を **閲覧専用** で許可し、CRUD / 達成マーク操作はすべて HTTP 403 で拒否する。

### 機能要件 — コーチ用受講生メモ（EnrollmentNote）

- **REQ-enrollment-080**: The system shall ULID 主キー / SoftDeletes を備えた `enrollment_notes` テーブルを提供し、`enrollment_id` / `coach_user_id`（NOT NULL, `users.id` 参照, 作成者）/ `body`（max 2000）/ `created_at` / `updated_at` / `deleted_at` カラムを保持する。
- **REQ-enrollment-081**: When coach が **担当資格に登録した受講生の Enrollment**（`certification_coach_assignments` 経由判定）配下にノートを追加する, the system shall `EnrollmentNote` を INSERT し、`coach_user_id = 操作 coach.id` を記録する。
- **REQ-enrollment-082**: When admin が任意 Enrollment 配下にノートを追加する, the system shall `EnrollmentNote` を INSERT し、`coach_user_id = 操作 admin.id` を記録する。
- **REQ-enrollment-083**: When coach が自身が作成したノートを編集 / 削除する, the system shall 当該操作を許可する。
- **REQ-enrollment-084**: When admin が任意のノートを編集 / 削除する, the system shall 当該操作を許可する。
- **REQ-enrollment-085**: If coach が **他コーチが作成したノート** を編集 / 削除しようとした場合, then the system shall HTTP 403 を返す（admin のみ越境可能）。
- **REQ-enrollment-086**: The system shall 受講生に対し自身の Enrollment 配下の `EnrollmentNote` 一覧 / 詳細をすべて HTTP 403 で拒否する（閲覧 / 操作のいずれも不可）。
- **REQ-enrollment-087**: While `EnrollmentNote` 一覧を表示する状況, the system shall ノートを `created_at DESC` で時系列降順表示する。

### 機能要件 — 修了の自己完結（ReceiveCertificateAction）

- **REQ-enrollment-090**: When 受講生が `/enrollments/{enrollment}/receive-certificate` POST で「修了証を受け取る」ボタンを押下する, the system shall `ReceiveCertificateAction::__invoke(Enrollment $enrollment): Certificate` を実行する。
- **REQ-enrollment-091**: The system shall `ReceiveCertificateAction` で以下ロジックを `DB::transaction()` 内で実行する: (1) `CompletionEligibilityService::isEligible($enrollment)` を呼んで判定、不合格なら `CompletionNotEligibleException`（HTTP 409）を throw、(2) `Enrollment.status = passed` / `passed_at = now()` を UPDATE、(3) `EnrollmentStatusLog` に `event_type = status_change` / `from_status = learning` / `to_status = passed` / `changed_by_user_id = $student->id` / `changed_reason = '受講生による修了証受領'` を記録、(4) [[certification-management]] の `IssueCertificateAction` を呼んで `Certificate` INSERT + PDF 生成、(5) [[notification]] の `NotifyCompletionApprovedAction` を `DB::afterCommit()` で dispatch（受講生本人宛て、修了証 PDF DL リンク含む）。
- **REQ-enrollment-092**: The system shall `CompletionEligibilityService::isEligible(Enrollment)` を以下ロジックで実装する: 対象 Enrollment の `certification_id` に紐付く **公開済 MockExam（`is_published = true`）の件数** と、**当該 Enrollment 配下の MockExamSession で `pass = true` かつ DISTINCT な `mock_exam_id` の件数** が一致したとき真を返す（公開模試 0 件の場合は false）。
- **REQ-enrollment-093**: If 受講生が `status != learning` の Enrollment で `ReceiveCertificateAction` を呼んだ場合, then the system shall `EnrollmentNotLearningException`（HTTP 409）を返す。
- **REQ-enrollment-094**: If 受講生が他者の Enrollment に対して `ReceiveCertificateAction` を呼んだ場合, then the system shall `EnrollmentPolicy::receiveCertificate` で HTTP 403 を返す。
- **REQ-enrollment-095**: When 受講生のログインユーザーが `User.status != UserStatus::InProgress` の場合, the system shall `ReceiveCertificateAction` を `EnsureActiveLearning` Middleware でブロック（`graduated` ユーザーは新規修了証受領不可、過去発行済修了証 DL のみ可）。
- **REQ-enrollment-096**: The system shall admin による修了申請承認フロー（旧 `Admin\Enrollment\ApproveCompletionAction`）を **提供しない**（受講生自己完結に統一）。

### 機能要件 — 試験日超過自動失敗

- **REQ-enrollment-100**: When Schedule Command `enrollments:fail-expired` が日次 00:00 に起動する, the system shall `status = learning AND exam_date IS NOT NULL AND exam_date < CURRENT_DATE` の Enrollment を抽出して `status = failed` に更新し、`EnrollmentStatusLog` に `event_type = status_change` / `from_status = learning` / `to_status = failed` / `changed_by_user_id = null` / `changed_reason = '試験日超過による自動失敗'` を記録する。
- **REQ-enrollment-101**: The system shall `exam_date IS NULL` の Enrollment は試験日超過自動失敗の対象外とする（目標受験日未設定は任意）。

### 機能要件 — EnrollmentStatusLog

- **REQ-enrollment-110**: The system shall ULID 主キー / SoftDeletes 非採用（履歴は不可逆）を備えた `enrollment_status_logs` テーブルを提供し、`enrollment_id` / `event_type`（`status_change` のみ、`coach_change` は削除）/ `from_status`（nullable, EnrollmentStatus）/ `to_status`（nullable, EnrollmentStatus）/ `changed_by_user_id`（nullable, `users.id` 参照、null はシステム自動）/ `changed_at` / `changed_reason`（nullable, max 200）/ `created_at` / `updated_at` カラムを保持する。
- **REQ-enrollment-111**: The system shall `event_type = status_change` のとき `from_status` / `to_status` を必須とする。
- **REQ-enrollment-112**: The system shall `EnrollmentStatusChangeService::recordStatusChange` メソッドを公開し、各 Action がトランザクション内で呼び出すことで履歴を 1 か所に集約する。`recordCoachChange` メソッドは **削除**（担当コーチは Enrollment に紐づかない、`certification_coach_assignments` の変更は本 Feature の責務外）。

### 機能要件 — 集計 Service の公開（他 Feature への提供契約）

- **REQ-enrollment-120**: The system shall `CompletionEligibilityService::isEligible(Enrollment): bool` を公開し、[[dashboard]]（修了証を受け取るボタンの活性判定）と本 Feature の `ReceiveCertificateAction` から呼べる契約とする。
- **REQ-enrollment-121**: The system shall `TermJudgementService::recalculate(Enrollment): TermType` を公開し、[[mock-exam]] の MockExamSession 状態変化を伴う各 Action（StartAction / SubmitAction / CancelAction 等）がトランザクション内で呼べる契約とする。
- **REQ-enrollment-122**: The system shall `EnrollmentStatsService` を公開し、[[dashboard]] の admin パネル用に **受講中件数** / **修了件数** / **失敗件数** / **資格別受講者数** などの KPI を返す。`paused` 関連の集計は削除。

### 非機能要件

- **NFR-enrollment-001**: The system shall 状態変更を伴うすべての Action（登録 / 状態遷移 / `ReceiveCertificateAction` / 目標 / ノート）を `DB::transaction()` で囲み、`EnrollmentStatusLog` への記録と本体更新を原子的に同期する。
- **NFR-enrollment-002**: The system shall 受講中資格一覧 / Enrollment 詳細 / admin 一覧の N+1 を `with()` Eager Loading で避け、`certification.category` / `certification.coaches` / `goals` / `latestStatusLog` などを必要に応じて Eager Load する。
- **NFR-enrollment-003**: The system shall 以下 INDEX を `enrollments` に付与する: `(user_id, certification_id)` UNIQUE、`(user_id, status)` 複合、`(certification_id, status)` 複合、`(status, exam_date)` 複合（Schedule Command の高速化）、`deleted_at`。`assigned_coach_id` 関連 INDEX は削除。
- **NFR-enrollment-004**: The system shall ドメイン例外を `app/Exceptions/Enrollment/` 配下の独立クラス（`EnrollmentAlreadyEnrolledException` / `EnrollmentInvalidTransitionException` / `EnrollmentNotLearningException` / `EnrollmentAlreadyPassedException` / `CompletionNotEligibleException`）として実装し、各々の HTTP ステータスを `backend-exceptions.md` の親クラス対応表に沿って設定する。`CompletionAlreadyRequestedException` / `CompletionNotRequestedException` / `CoachNotAssignedToCertificationException` は撤回（修了申請フロー削除に伴う）。
- **NFR-enrollment-005**: The system shall `EnrollmentStatusChangeService::recordStatusChange` を INSERT のみのステートレス Service として実装し、本 Service 内で `DB::transaction()` を持たない（呼び出し側 Action のトランザクションに乗る）。
- **NFR-enrollment-006**: The system shall コーチが他コーチのノートを編集 / 削除できないことを `EnrollmentNotePolicy::update` / `delete` で `note.coach_user_id === auth.id || auth.role === Admin` の判定で担保する。
- **NFR-enrollment-007**: The system shall 受講生が個人目標 / ノート / 他人の Enrollment にアクセスできないことを `EnrollmentPolicy` / `EnrollmentGoalPolicy` / `EnrollmentNotePolicy` で **ロール + 当事者** の二重判定で担保する。
- **NFR-enrollment-008**: The system shall コーチが担当資格 Enrollment にアクセスできることを `EnrollmentPolicy::view` で `$enrollment->certification->coaches->contains($user->id)` の判定で担保する（`assigned_coach_id` 廃止に伴う変更）。

## スコープ外

- **修了取消フロー** — 受講生が「修了証を受け取る」ボタンを押下後の取消は提供しない（Certificate 発行が伴うため運用上複雑、誤押下防止は UI 側の確認ダイアログで対応）
- **修了申請承認フロー** — 撤回（受講生自己完結に統一、Progate 流）
- **休止（paused）** — 撤回（複数資格同時受講可モデルでは「他資格に集中」で代替可能、Plan 期間が一律に進む前提では明示的な休止状態は不要）
- **資格非紐づきの総合目標** — 「複数資格をまたぐ生活習慣等」の総合目標は `product.md` 明示によりスコープ外。EnrollmentGoal は Enrollment 単位（資格紐づき）のみ
- **目標テンプレートのマスタ管理** — `product.md` 明示。受講生個別の自由入力のみ
- **担当コーチの個別割当・変更**（受講生 × 資格単位）— 撤回（担当コーチは資格 × N コーチ N:N、`certification_coach_assignments` で資格経由で割当）
- **EnrollmentNote の検索** — 部分一致検索 UI は提供しない（時系列降順閲覧のみ）
- **EnrollmentNote の coach 間共有モード設定** — `body` は coach 全員 / admin が常に閲覧可（個別 coach 非公開フラグなし）。`backend-tests.md` のロール固有テストでこの境界を担保
- **個人目標達成時の自動通知** — 受講生の自己マークで完結、コーチや admin への push 通知は送らない
- **滞留検知ロジック** — `StagnationDetectionService` 自体を持たない（MVP スコープ外、本 Feature には関連なし）

## 関連 Feature

- **依存先**（本 Feature が前提とする）
  - [[auth]] — `User` モデル + `UserStatus` / `UserRole` Enum + `User.withdraw()` ヘルパ + **`EnsureActiveLearning` Middleware**（プラン機能ロック）
  - [[plan-management]] — `User.plan_id` / `plan_started_at` / `plan_expires_at` / `max_meetings` の前提（招待時設定済）
  - [[user-management]] — `UserStatusLog` の流儀（INSERT only、UPDATE 禁止）を本 Feature の `EnrollmentStatusLog` で踏襲
  - [[certification-management]] — `Certification` モデル + `Certificate\IssueAction`（`ReceiveCertificateAction` 内から呼ぶ）+ `CertificationCoachAssignment`（資格 × N コーチ N:N、担当コーチ集合判定）
- **依存元**（本 Feature を利用する）
  - [[learning]] — `Enrollment` をベースに `SectionProgress` / `LearningSession` を集計、`status IN (learning, passed)` で学習・閲覧許可
  - [[mock-exam]] — `MockExamSession` の状態変化時に `TermJudgementService::recalculate` を呼ぶ
  - [[notification]] — 修了証受領時通知の dispatch 起点（`ReceiveCertificateAction` 内から呼ぶ）
  - [[dashboard]] — 受講生ダッシュボード（試験日カウントダウン / 修了証を受け取るボタン / 修了済資格セクション）/ admin ダッシュボード（KPI）/ coach ダッシュボード（担当資格受講生一覧）が本 Feature の Service 群を消費
  - [[settings-profile]] — 自己退会動線は撤回されたが、admin が退会させる際の Enrollment SoftDelete 流儀は本 Feature が担保
