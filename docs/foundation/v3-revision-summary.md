# v3 改修サマリ — プラン受講型 LMS への大規模刷新

> **作成**: 2026-05-16 / Phase 1-1 完了直後
> **目的**: 別セッションで Phase 1-2 / 1-3 / 2 を進める際の引き継ぎ資料
> **真実源**: `docs/steering/product.md` を必ず Read してから着手すること

## 1. 改修の背景

ユーザーフィードバック（2026-05-15〜16）により、Certify LMS を以下の方向で根本的に再定義:

| 軸 | Before | After |
|---|---|---|
| 事業モデル | 永続型 LMS（暗黙） | **プラン受講型**（Plan 購入 → 期間内学習 → 卒業） |
| ステータス | User 3 値、Enrollment 4 値、Meeting 6 値 | User 4 値、Enrollment 3 値、Meeting 3 値 |
| 修了 | admin 承認フロー | 受講生「修了証を受け取る」ボタン自己完結 |
| 担当コーチ | `Enrollment.assigned_coach_id` 1:1 | `certification_coach_assignments` N:N（資格 × N コーチ） |
| 面談予約 | 担当コーチに申請 → 承認 | 時刻スロット選択 → 自動コーチ割当 |
| 資格チャット | 受講生 × 担当コーチ 1on1 | 1 資格 1 グループルーム + Pusher リアルタイム |
| 問題テーブル | `Question` 1 テーブル（`section_id` nullable） | `SectionQuestion` + `MockExamQuestion` 完全分離 |
| 資格マスタ | 9 カラム | 4 カラム（`name` / `category_id` / `difficulty` / `description`） |
| Feature 数 | 16 | 18（+ `plan-management` + `meeting-quota`） |

## 2. 確定したデータモデル（差分）

### 2.1 新規テーブル

```sql
plans                    -- Plan マスタ
├ id ULID
├ name / description
├ duration_days unsigned smallint
├ default_meeting_quota unsigned smallint
├ status enum (draft/published/archived)
└ sort_order / timestamps / soft_deletes
※ price カラムは持たない（LMS 外で決済）

user_plan_logs           -- プラン履歴（INSERT only）
├ id ULID
├ user_id (FK)
├ plan_id (FK)
├ event_type enum (assigned/renewed/canceled/expired)
├ plan_started_at / plan_expires_at
├ meeting_quota_initial
├ changed_by_user_id (nullable, FK)
├ changed_reason / occurred_at / timestamps

meeting_quota_plans      -- 追加面談購入用 SKU マスタ（LMS 内）
├ id ULID
├ name (例: "5 回パック")
├ meeting_count unsigned smallint
├ price unsigned int                          ← Stripe 決済用に保持
├ status / sort_order / timestamps / soft_deletes

meeting_quota_transactions  -- 監査ログ（INSERT only、iField 流）
├ id ULID
├ user_id (FK)
├ type enum (granted_initial/purchased/consumed/refunded/admin_grant)
├ amount int signed (消費は -1)
├ related_meeting_id (nullable FK)
├ related_payment_id (nullable FK)
├ granted_by_user_id (nullable FK, admin_grant 時のみ)
├ note / occurred_at / timestamps

payments                 -- Stripe 決済記録
├ id ULID
├ user_id (FK)
├ type enum (extra_meeting_quota)
├ stripe_payment_intent_id (UNIQUE)
├ stripe_checkout_session_id
├ meeting_quota_plan_id (FK)
├ amount / quantity
├ status enum (pending/succeeded/failed/refunded)
├ paid_at / timestamps

section_questions        -- 旧 Question から名称変更
├ id ULID
├ section_id (FK, NOT NULL)                   ← nullable 撤回
├ category_id (FK to question_categories)
├ body / explanation / status / order
└ timestamps / soft_deletes
※ difficulty 削除、certification_id 削除（section から辿る）

section_question_options -- 旧 QuestionOption から名称変更
├ id ULID
├ section_question_id (FK)
├ body / is_correct / order
└ timestamps / soft_deletes

mock_exam_questions      -- 模試マスタの子リソースとして再定義
├ id ULID
├ mock_exam_id (FK, NOT NULL)                 ← 中間テーブルではなく独自リソース
├ category_id (FK to question_categories)
├ body / explanation / order
└ timestamps / soft_deletes
※ 旧 mock_exam_questions（mock_exam_id + question_id の中間）は破棄

mock_exam_question_options -- 新設
├ id ULID
├ mock_exam_question_id (FK)
├ body / is_correct / order
└ timestamps / soft_deletes

chat_members             -- ChatRoom 参加者管理（新設）
├ id ULID
├ chat_room_id (FK)
├ user_id (FK)
├ last_read_at (nullable datetime)
└ timestamps / soft_deletes
※ UNIQUE (chat_room_id, user_id)
```

### 2.2 既存テーブルへの変更

```sql
users テーブルに追加:
+ plan_id (FK, nullable)
+ plan_started_at (datetime, nullable)
+ plan_expires_at (datetime, nullable)
+ max_meetings (unsigned smallint, default 0)
+ meeting_url (string, nullable)              ← コーチオンボーディングで必須入力

users.status enum 拡張:
- 旧: invited / active / withdrawn
+ 新: invited / in_progress / graduated / withdrawn
（`active` を `in_progress` にリネーム、`graduated` 追加）

certifications テーブルから削除:
- code (UNIQUE) DROP
- slug DROP
- passing_score DROP                          ← MockExam に移動
- total_questions DROP
- exam_duration_minutes DROP

enrollments テーブルから削除:
- assigned_coach_id DROP
- completion_requested_at DROP

enrollments.status enum 縮減:
- 旧: learning / paused / passed / failed
+ 新: learning / passed / failed
（paused 削除）

enrollment_status_logs.event_type:
- 旧: status_change / coach_change
+ 新: status_change のみ（coach_change 削除）

chat_rooms テーブルから削除:
- status DROP（ステータス機能撤回）
- 受講生 × 1 コーチ → グループルーム（ChatMember 経由）

旧 questions テーブル: 廃止（section_questions と mock_exam_questions に分離）
旧 question_options テーブル: 廃止（section_question_options と mock_exam_question_options）
旧 mock_exam_questions（中間）: 廃止（新 mock_exam_questions は独自リソース）

meetings テーブルから削除:
- requested / approved / rejected / in_progress 状態の利用撤回
- meeting.status enum: 旧 6 値 → 新 3 値（reserved / canceled / completed）

learning Feature から `StagnationDetectionService` 削除（滞留検知撤回）
```

## 3. 既存実装 4 Feature への影響詳細

### 3.1 auth Feature

**Migration**:
- 新規: `users` に `plan_id` / `plan_started_at` / `plan_expires_at` / `max_meetings` / `meeting_url` 追加
- `UserStatus` enum 拡張: `active` → `in_progress` リネーム + `graduated` 値追加

**Model**:
- `User`: `plan_id` 等の fillable 追加、`belongsTo(Plan)` 追加、`UserStatus::Graduated` 対応
- `UserStatus` enum: `case InProgress = 'in_progress'`, `case Graduated = 'graduated'`、label() 更新

**Action**:
- `IssueInvitationAction` シグネチャに `$planId` 引数追加（必須）
- `OnboardAction`: コーチの場合 `meeting_url` を必須入力として検証
- `OnboardAction`: User UPDATE 時に `status = in_progress`（旧 `active`）
- 新規 `EnsureActiveLearning` Middleware: graduated ユーザーがプラン機能にアクセスしようとした際に 403、プロフィール / 修了証 DL は許可

**FormRequest**:
- `IssueInvitationRequest`: `plan_id` バリデーション追加（`exists:plans,id`）
- `OnboardRequest`: コーチの場合 `meeting_url` を required、URL バリデーション

**Blade**:
- `auth/onboarding.blade.php`: role=coach の場合 `meeting_url` 入力欄表示

**Test**:
- 全 User Factory / テストデータ生成箇所で `status = 'in_progress'` に置換
- `UserStatus::Active` 参照箇所を `UserStatus::InProgress` に置換
- 招待時に `plan_id` を渡すケースを追加

### 3.2 user-management Feature

**Controller / Blade**:
- 招待モーダルに Plan select 追加（`Plan::published()->orderBy('sort_order')->get()`）
- ユーザー詳細から **プロフィール編集 / ロール変更** 動線削除（route + Blade button + Action 削除）
- ユーザー詳細にプラン情報表示（Plan name / plan_expires_at / max_meetings / 残数 = `MeetingQuotaService::remaining()`）
- ユーザー詳細に **「プラン延長」ボタン** 追加（`ExtendCourseAction` を呼ぶ、plan-management 所有）
- ユーザー詳細に **「面談回数 admin 付与」UI** 追加（`MeetingQuotaTransaction::admin_grant` を作成、meeting-quota 所有）
- ユーザー一覧の status フィルタに `graduated` 追加（`withdrawn` と同様に手動指定時のみ表示）

**Action**:
- `IssueInvitationAction` を auth から呼ぶ際に `$planId` を渡す
- 旧 `UpdateAction`（プロフィール編集） / `UpdateRoleAction`（ロール変更）削除

**FormRequest**:
- `StoreInvitationRequest`: `plan_id` バリデーション追加

**Test**:
- プロフィール編集 / ロール変更関連テスト削除
- 招待時の plan_id 検証テスト追加

### 3.3 certification-management Feature

**Migration**:
- 新規 Migration: `certifications` から `code` / `slug` / `passing_score` / `total_questions` / `exam_duration_minutes` カラム DROP
- `certifications.code` UNIQUE INDEX も削除
- `(status, category_id)` 複合 INDEX は維持

**Model**:
- `Certification`: 削除カラムを fillable / cast から除去
- 関連 Resource / Resource API 出力からも除去

**Controller / Blade**:
- 資格作成・編集フォームから `code` / `slug` / `passing_score` / `total_questions` / `exam_duration_minutes` 入力欄削除
- 一覧表示・詳細表示から該当カラム削除

**FormRequest**:
- `StoreCertificationRequest` / `UpdateCertificationRequest` から削除カラムのバリデーション削除

**Action / Service**:
- `IssueCertificateAction` の呼出元変更（`ApproveCompletionAction` from admin → `ReceiveCertificateAction` from student）
- ただし `IssueCertificateAction` 自体のシグネチャは不変（spec の依存先記述のみ修正）

**Test**:
- 資格マスタテスト全般で削除カラム関連を除去
- 修了証発行テスト: 呼出元 Action の変更を反映

### 3.4 content-management Feature

**Migration**（大改修）:
- 新規 Migration:
  - `questions` テーブルを **DROP**（既存実装データは教育 PJ なので消失 OK）
  - `question_options` テーブルを **DROP**
  - `section_questions` テーブル作成（section_id NOT NULL、difficulty なし）
  - `section_question_options` テーブル作成
- `mock_exam_questions` の構造は別途 mock-exam Feature 実装時に変更

**Model**:
- 旧 `Question` Model → `SectionQuestion` にリネーム + section_id NOT NULL
- 旧 `QuestionOption` Model → `SectionQuestionOption`
- `Question.difficulty` 削除、`QuestionDifficulty` enum も削除
- `Question.section_id` の nullable 撤回
- `Question.certification_id` は不要（section から辿るので Direct 関連削除、ただし Eager Load 用に scope 提供）

**Controller / Blade**:
- 問題管理画面で「mock-exam 専用問題」タブを撤回（Section 紐づき問題のみ管理）
- 「mock-exam 専用問題 CRUD」UI は削除
- 模試問題の管理は mock-exam Feature が所有（別画面）

**FormRequest**:
- `StoreQuestionRequest` / `UpdateQuestionRequest` から `section_id` nullable 関連削除
- `difficulty` バリデーション削除

**Test**:
- 全 Question 関連テストで `SectionQuestion` にリネーム
- mock-exam 専用問題作成テスト削除
- difficulty 関連テスト削除

## 4. 影響を受ける未実装 Feature spec の改訂方針

| Feature | 主な改訂内容 |
|---|---|
| **enrollment** | `Enrollment.status` 3 値化、`assigned_coach_id` 削除、`completion_requested_at` 削除、`ReceiveCertificateAction` 追加、coach_change event_type 削除 |
| **learning** | `StagnationDetectionService` 削除、`passed` でも閲覧可（status による機能制限なし） |
| **quiz-answering** | `SectionQuestion` 名称変更、`passed` でも演習可、`difficulty` 関連削除、`Answer` → `SectionQuestionAnswer` リネーム、`QuestionAttempt` → `SectionQuestionAttempt` リネーム |
| **mock-exam** | `MockExamQuestion` 独自リソース化（中間テーブルから昇格）、`MockExamQuestionOption` 新設、`passing_score` を `MockExam` 側で管理、`difficulty` 削除、`passed` でも受験可、修了判定ロジックは [[enrollment]] へ集約 |
| **mentoring** | `Meeting.status` 3 値化（reserved / canceled / completed）、自動コーチ割当（CoachMeetingLoadService）、申請・承認・拒否フロー削除、meetings:auto-complete Schedule Command、面談回数消費連携（[[meeting-quota]] と）、面談予約完了通知はコーチのみ |
| **chat** | 1on1 → グループルーム化、`ChatMember` 中間テーブル新設、`ChatRoom.status` 削除、Pusher Broadcasting リアルタイム配信、未読バッジは `ChatMember.last_read_at` |
| **qa-board** | 大きな変更なし |
| **analytics-export** | `assigned_coach_id` カラム削除影響、`Question` → `SectionQuestion` / `MockExamQuestion` テーブル変更影響、`mock-exam-sessions` の `category_breakdown` 集計クエリ修正 |
| **notification** | `CompletionApproved` 発火元変更（admin 承認 → 受講生 ReceiveCertificateAction）、面談承認/拒否通知削除（自動割当）、面談予約完了通知はコーチ宛のみ、プラン期限間近通知削除、滞留検知通知削除 |
| **dashboard** | 修了済資格セクション追加、admin 修了申請待ち削除、admin プラン期限切れ間近一覧削除、admin 滞留検知削除、受講生プラン情報パネル + 残面談回数 + 追加面談購入 CTA、coach 滞留検知削除 |
| **ai-chat** | 大きな変更なし、ただし graduated でアクセス不可（EnsureActiveLearning Middleware） |
| **settings-profile** | 自己退会動線削除、プラン情報表示削除、追加面談購入 CTA 削除、コーチの場合は CoachAvailability + meeting_url 編集機能のみ |

## 5. 新規 Feature の核仕様（Phase 1-3 で spec 化）

### 5.1 plan-management

**Plan マスタ管理**:
- admin が CRUD（`name` / `description` / `duration_days` / `default_meeting_quota` / `status` / `sort_order`）
- 価格は LMS 内では持たない

**User × Plan 紐づけ**:
- `User.plan_id` / `plan_started_at` / `plan_expires_at` / `max_meetings`
- 招待時に `Plan` が指定され、上記カラムが Plan 値から複写される
- `IssueInvitationAction` が `$planId` を受け取り、`User.plan_*` を初期化

**ExtendCourseAction**:
```
__invoke(User $user, Plan $plan, ?User $admin = null): User
1. $user->plan_expires_at += $plan->duration_days days
2. $user->max_meetings += $plan->default_meeting_quota
3. UserPlanLog INSERT (event_type = renewed)
4. MeetingQuotaTransaction INSERT (type = granted_initial, amount = +plan->default_meeting_quota)
```

**Schedule Command `users:graduate-expired`**:
- 日次起動（午前 3 時等）
- `User::where('status', UserStatus::InProgress)->where('plan_expires_at', '<', now())->get()` を `graduated` に遷移
- UserPlanLog INSERT (event_type = expired)
- 通知は行わない（受講生宛のプラン期限通知は MVP 外）

**PlanExpirationService**:
- `isExpired(User): bool` / `daysRemaining(User): int` を提供

### 5.2 meeting-quota

**MeetingQuotaPlan マスタ**:
- admin が CRUD（`name` / `meeting_count` / `price` / `sort_order` / `status`）
- 複数 SKU（1 回 / 5 回パック / 10 回パック 等）
- 受講生は LMS 内 Stripe で都度購入

**MeetingQuotaTransaction**:
- INSERT only、`type` で出入りを区別
- 残数 = `User.max_meetings + SUM(transactions.amount)`
- `consumed` = -1, `purchased` / `refunded` / `admin_grant` / `granted_initial` = +N

**MeetingQuotaService**:
- `remaining(User): int` を提供
- 各 Feature（mentoring の `ReserveAction` 等）がチェック

**Stripe 連携**:
- `CreateCheckoutSessionAction(User $user, MeetingQuotaPlan $plan)`: Stripe Checkout Session 作成
- `HandleStripeWebhookAction`: `checkout.session.completed` を受信、`Payment` 行 INSERT + `MeetingQuotaTransaction(type=purchased)` 挿入
- `signature_verification` で Webhook 署名検証必須

**admin 手動付与**:
- `AdminGrantQuotaAction(User $target, int $amount, User $admin, string $reason)`: `MeetingQuotaTransaction(type=admin_grant, granted_by=$admin)` 挿入

## 6. Phase 2 実装順序の推奨

```
[必須順序、依存関係で固定]
① plan-management Feature 新規実装
   ├ Migration: plans / user_plan_logs テーブル
   ├ Model: Plan / UserPlanLog
   ├ Action: StoreAction / UpdateAction / DestroyAction / ExtendCourseAction / GraduateExpiredCommand
   └ Test

② auth Feature 修正
   ├ Migration: users に plan_id / plan_expires_at / max_meetings / meeting_url 追加 + UserStatus enum 拡張
   ├ UserStatus enum: active → in_progress / + graduated
   ├ IssueInvitationAction: $planId 引数追加
   ├ OnboardAction: meeting_url 必須化（コーチ）+ status = in_progress
   ├ EnsureActiveLearning Middleware 新設
   └ Test 修正

③ user-management Feature 修正
   ├ 招待モーダルに Plan select 追加
   ├ ユーザー詳細にプラン情報 + プラン延長ボタン + 面談回数付与 UI
   ├ プロフィール編集 / ロール変更 動線削除
   └ Test 修正

④ certification-management Feature 修正
   ├ Migration: certifications から code/slug/passing_score/total_questions/exam_duration DROP
   ├ Model / Controller / Blade / FormRequest 修正
   ├ IssueCertificateAction の呼出元修正（spec 文言のみ）
   └ Test 修正

⑤ content-management Feature 修正
   ├ Migration: questions / question_options DROP + section_questions / section_question_options CREATE
   ├ Model リネーム: Question → SectionQuestion / QuestionOption → SectionQuestionOption
   ├ Controller / Blade: mock-exam 専用問題 UI 削除
   ├ FormRequest: difficulty / section_id nullable 削除
   └ Test リネーム + 修正
```

その後、未実装 Feature を `/feature-implement` で順次実装:

```
⑥ meeting-quota
⑦ enrollment（再実装、現 spec は未着手）
⑧ learning
⑨ quiz-answering
⑩ mock-exam
⑪ mentoring
⑫ chat
⑬ qa-board
⑭ dashboard
⑮ notification
⑯ analytics-export
⑰ ai-chat
⑱ settings-profile
```

## 7. 別セッション開始チェックリスト

新セッションを開始したら、以下を順次実施:

1. `CLAUDE.md` を読む（自動的に context に含まれる）
2. `docs/steering/product.md` を Read（真実源）
3. `docs/foundation/v3-revision-summary.md` を Read（本ファイル）
4. memory の `project_v3_course_model.md` を確認（v3 改修の概要）
5. 該当 Feature の `docs/specs/{feature}/` を Read（Phase 1-2 完了済の spec）
6. 既存実装 4 Feature の現状確認（`模範解答プロジェクト/app/` 配下を Read）
7. 作業着手

---

## 8. Phase 1-2 design.md / tasks.md 更新の進捗トラッカー

**重要**: requirements.md は Task #2 で全 9 Feature 完了済（v3 反映済）。一方、`design.md` / `tasks.md` は元のまま残っており、v3 改修反映の更新作業が必要。各 Feature の更新は **Task #5** で管理。

| Feature | requirements.md | design.md | tasks.md | 主な v3 反映ポイント |
|---|---|---|---|---|
| enrollment | ✅ | ✅ enrollment/design.md（簡潔版で初期化済、要拡充） | ✅ enrollment/tasks.md（同上） | assigned_coach_id 削除 / 修了自己完結 / coach_change event_type 削除 |
| mentoring | ✅ | ✅ 本セッション充実版で更新 | ✅ 本セッション充実版で更新 | 自動コーチ割当 / Meeting.status 3 値 / auto-complete / meeting-quota 連携 |
| chat | ✅ | ✅ 充実版完成 | ✅ 充実版完成 | グループ化 / ChatMember / Pusher リアルタイム / status 削除 |
| mock-exam | ✅ | ✅ 充実版完成 | ✅ 充実版完成 | MockExamQuestion 独自リソース化 / MockExamQuestionOption 新設 / difficulty 削除 |
| content-management | ✅ | ✅ 充実版完成 | ✅ 充実版完成 | Question → SectionQuestion 分離 / difficulty 削除 / mock-exam 専用 UI 撤回 |
| quiz-answering | ✅ | ✅ 充実版完成 | ✅ 充実版完成 | SectionQuestion 名称変更 / passed 許容 / difficulty 関連削除 |
| dashboard | ✅ | ✅ 充実版完成 | ✅ 充実版完成 | 修了済資格セクション / プラン情報パネル / admin 運用モニタ縮減 |
| notification | ✅ | ✅ 充実版完成 | ✅ 充実版完成 | CompletionApproved 発火元変更 / mentoring 通知整理 / chat 通知双方向 |
| learning | ✅ | ✅ 充実版完成 | ✅ 充実版完成 | StagnationDetectionService 削除 / passed 許容 |
| analytics-export | ✅ | ✅ 充実版完成 | ✅ 充実版完成 | assigned_coach 削除 / Question 分離影響 / status enum 拡張 |

### 別セッション開始時の design.md / tasks.md 更新手順

1. 該当 Feature の現行 `docs/specs/{feature}/design.md` と `tasks.md` を Read
2. 同じく `docs/specs/{feature}/requirements.md` を Read（v3 反映済の正本）
3. `docs/foundation/v3-revision-summary.md` の **3. 既存実装 4 Feature への影響詳細** + **4. 影響を受ける未実装 Feature spec の改訂方針** を参照
4. 元の構造を踏襲しつつ、v3 改修反映ポイントを反映した充実版で Write
5. このファイルの進捗トラッカーを Edit で `✅` に更新
