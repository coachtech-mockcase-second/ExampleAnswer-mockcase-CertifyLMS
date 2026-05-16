# Certify LMS データモデル reference

> 全 18 Feature のテーブル・カラム構造と、各 Feature の適切性評価をまとめた reference。
> v3 改修反映済の現状を前提とする。
> 監査日: 2026-05-16
> 監査基準: product.md UX 要件カバレッジ / 必要最低限性 / 最適化 / 概念の独立性 / 集計責務マトリクス整合

## 凡例

- ✅ **必要最低限**: なぜ過剰でないか
- ✅ **最適化されている**: 何がよく考えられているか
- ⚠️ **改善余地**: 具体的に
- ❓ **疑問**: 設計判断の根拠が読み取れない箇所

---

# Part 1: Feature 1-9

## 1. auth

**Feature 概要**: 全ロール対象。招待制 + Fortify セッション認証 + ロール/プラン状態に応じた Middleware ロックを提供する認証基盤。

### users
LMS 全ユーザー（admin / coach / student）の中核テーブル。プロフィール・認証情報・ロール・プラン受講状態を統合保持する。

| カラム | 型 | 役割 |
|---|---|---|
| id | ULID | PK |
| email | string UNIQUE | ログイン ID |
| password | string nullable | 招待中は NULL、オンボーディング時セット |
| role | string (Enum) | `admin` / `coach` / `student` |
| status | string (Enum) | v3: `invited` / `in_progress` / `graduated` / `withdrawn` |
| name / bio / avatar_url | string/text/string | プロフィール |
| profile_setup_completed | boolean | オンボーディング完了フラグ |
| email_verified_at / last_login_at | timestamp | Fortify 連携 |
| plan_id | ULID FK nullable | v3、所属プラン。Migration は plan-management 所有 |
| plan_started_at / plan_expires_at | timestamp nullable | v3、プラン期間。Schedule Command が `plan_expires_at < now()` で `graduated` 遷移 |
| max_meetings | unsignedSmallInt | v3、初期面談付与数（残数は meeting-quota の transaction 合算で算出） |
| meeting_url | string nullable | v3、coach のみ必須。auth Migration 所有、mentoring が `meeting_url_snapshot` に焼き込む |
| remember_token / deleted_at | string / timestamp | Fortify / SoftDelete |

### invitations
招待メール発行履歴。signed URL によるオンボーディング動線を担う。

| カラム | 型 | 役割 |
|---|---|---|
| id | ULID | PK |
| user_id | ULID FK | 紐づく User（招待発行時に invited User を先に INSERT） |
| email / role | string | 冗長保持（User と一致するが招待リスト表示用） |
| invited_by_user_id | ULID FK | 発行 admin |
| expires_at / accepted_at / revoked_at | timestamp | 期限切れ Schedule Command 用 |
| status | string Enum | `pending` / `accepted` / `expired` / `revoked` |

**設計判断**:
- `User` を「招待時点で INSERT、ステータスで状態を表す」ライフサイクル設計。`invited` から始まり `in_progress` を経て `graduated` / `withdrawn` で終端する 4 値ライフサイクルが v3 で確定（旧 `Active` → `InProgress` rename + `Graduated` 新規）
- `plan_id` の Migration を本 Feature でなく [[plan-management]] に置くのは「招待時に Plan が必須＝認証ドメインが Plan に依存する」境界を反映。auth 側は `belongsTo(Plan)` 宣言のみ
- `meeting_url` は coach 専用カラムだが、ロール条件付きカラム分離（別テーブル化）でなく `nullable` で吸収。1:1 関連を作らずシンプルにする判断
- `EnsureActiveLearning` Middleware は `auth` に置きつつ、適用ルートは各 Feature の `routes/web.php` で各自判断。プロフィール / 修了証 DL ルートは除外、graduated でも DL 可能

### 適切性評価
- ✅ **必要最低限**: `users` 16 カラムは多めだが、すべてに UX 上の役割あり（plan-management が users 上に Plan 関連 4 カラム同居させる選択は妥当、別テーブルにすると JOIN コスト増）。`Invitation` も email/role を冗長保持しつつ User と分離する設計は招待履歴 UI のため正当
- ✅ **最適化**: Status を 4 値に絞り「複数資格同時受講可ゆえ User レベル休止状態は不要」と整理されている点。`scopeActive` を `[InProgress, Graduated]` にする v3 改修は、graduated でもログイン可・プロフィール / 修了証 DL 可とする要件に整合
- ⚠️ **改善余地**: `Invitation.email` `Invitation.role` は User と一致するため冗長。UI の招待履歴一覧で User が SoftDelete 済の場合も email 表示したい用途は理解できるが、`withTrashed()` で十分。冗長保持を残すなら理由を design.md にコメント
- ❓ **疑問**: `users.password` を nullable にした上で Fortify との整合性（`invited` ユーザーがログインを試みた場合の挙動）が design.md からは追えない。`AuthenticateUserUsing` で `status IN (InProgress, Graduated)` 条件は記載あり → 妥当だが追記推奨

---

## 2. user-management

**Feature 概要**: admin 専用。コーチ・受講生の招待・退会・プラン延長・面談回数手動付与・ステータス履歴閲覧を提供。

### user_status_logs
User の status 遷移を INSERT only で記録する監査ログ。

| カラム | 型 | 役割 |
|---|---|---|
| id | ULID | PK |
| user_id | ULID FK | 対象 User |
| event_type | string Enum | **2026-05-16 追加**、`UserStatusEventType`(現時点 `status_change` 1 値、将来拡張可)。`UserPlanLog.event_type` とフォーマット統一 |
| from_status / to_status | string Enum (4 値) | 遷移前後 |
| changed_by_user_id | ULID FK nullable | 実行者（Schedule Command 起動時は NULL） |
| changed_reason | text nullable | 自由文（「管理者による退会」「オンボーディング完了」等） |
| changed_at | timestamp | 遷移発生時刻 |

INDEX: `(user_id, changed_at)` + `(event_type, changed_at)`(将来 event_type 拡張時のクエリ高速化)。

**設計判断**:
- INSERT only の append-only 監査パターン。User の現在 status は `users.status` が SSoT、本テーブルは履歴
- `changed_by_user_id` を nullable にして Schedule Command（`users:graduate-expired`）からの自動遷移にも対応
- `UserStatusChangeService::record()` を本 Feature 所有とし、auth / plan-management 等の各 Action から呼ばれる「横断 Service」配置。所有が本 Feature で、計算 Service マトリクスの考え方と整合
- **2026-05-16 確定**: `event_type` カラムを追加し `UserPlanLog` とフォーマット統一。`UserStatusChangeService::record()` は内部で `event_type = StatusChange` を自動挿入し、呼出側は意識しない設計
- v3 で `UpdateAction` / `UpdateRoleAction` を撤回したのは「admin が他者プロフィール / ロールを編集する動線を持たない」設計判断。代わりに「自分自身は [[settings-profile]] で管理」「ロール変更は招待でしか発生しない」と責務分離

### 適切性評価
- ✅ **必要最低限**: `UserStatusLog` 1 テーブルで完結。冗長カラムなし
- ✅ **最適化**: 履歴を別テーブル化することで `users.status` の頻繁な参照と履歴閲覧クエリを分離。`UserStatusChangeService` を Feature 横断 Service として配置したことで、各 Feature の Action 内で散発的に INSERT する責務分散を回避
- ✅ **フォーマット統一**(2026-05-16): `event_type` カラム追加により [[plan-management]] の `UserPlanLog` と同形式 (event_type + 状態スナップショット + 実行者 + 理由 + 発生時刻) に揃った。Pro 生レベルとして「監査ログは event_type ベースで分類する」を学べる
- ❓ **疑問**: `UserStatusLog` の `deleted_at` カラム有無が design.md ER 図に明記されてない（`HasFactory` のみで `SoftDeletes` なし、と推測）。`timestamps` のみと書かれているので append-only 設計の意図と合致だが明示すると良い

---

## 3. certification-management

**Feature 概要**: admin / student。資格マスタ管理、担当コーチ割当、受講生向けカタログ閲覧、修了証発行と PDF 配信を一体提供。

### certifications
資格マスタ。v3 で 4 カラムに絞り込み。

| カラム | 型 | 役割 |
|---|---|---|
| id | ULID | PK |
| name | string max 100 | v3、資格名（`code` / `slug` 撤回） |
| category_id | ULID FK | v3、CertificationCategory |
| difficulty | Enum | v3、`beginner` / `intermediate` / `advanced` |
| description | text nullable | v3 |
| status | Enum | `draft` / `published` / `archived` |
| created_by_user_id / updated_by_user_id | ULID FK | 監査 |
| published_at / archived_at | timestamp nullable | 公開遷移時刻 |
| deleted_at | timestamp nullable | SoftDelete |

### certification_categories
資格カテゴリのマスタ（IT 系 / 語学系 等）。

### certification_coach_assignments
資格 × コーチの N:M 中間テーブル。`unassigned_at` nullable で論理削除（再アサイン履歴を残す設計）。

### certificates
修了証ログ。発行された PDF への参照を保持。

| カラム | 型 | 役割 |
|---|---|---|
| id | ULID | PK |
| user_id / enrollment_id (UNIQUE) / certification_id | ULID FK | 紐づけ |
| serial_no | string UNIQUE | `CT-YYYYMM-00001` 形式の証書番号 |
| pdf_path | string | Storage private driver 上のパス |
| issued_at | timestamp | 発行時刻 |

**設計判断**:
- v3 で `certifications.code` / `slug` / `passing_score` / `total_questions` / `exam_duration_minutes` を撤回。合格点は `MockExam.passing_score` に移動（資格内の模試ごとに合格点が変えられる）、試験時間は LMS スコープ外、`code` / `slug` は使われない（カタログは name 検索）。**過剰抽象削除の好例**
- `certificates.enrollment_id UNIQUE` で「1 Enrollment = 1 修了証」を DB 制約で保証。二重発行を `CertificateAlreadyIssuedException` で弾く Action 層と二重ガード
- 修了証発行ロジック（`IssueCertificateAction`）は本 Feature 所有、ただし発火元は [[enrollment]] の `ReceiveCertificateAction`。本 Feature は「証書」ドメイン、[[enrollment]] は「資格達成」ドメインで責務分離
- `certification_coach_assignments.unassigned_at` nullable は、コーチを担当から外しても履歴を残す設計（ChatMember 同期や面談履歴の整合性のため）

### 適切性評価
- ✅ **必要最低限**: v3 改修で `certifications` から 5 カラム削除した結果、過剰抽象が解消されてシンプル。`Certificate` は 6 カラム（id + 3 FK + serial_no + pdf_path + issued_at + timestamps）と最小限
- ✅ **最適化**: 修了証発行が「[[enrollment]] → [[certification-management]] → PDF」の連鎖。`DB::transaction` + `DB::afterCommit` で通知発火という Laravel 流の堅実な設計。`(status, category_id)` 複合 INDEX はカタログ・admin 一覧の主クエリと整合
- ⚠️ **改善余地**: `Certification.difficulty` enum は撤回検討余地あり。教材ドメインで `difficulty` を撤回したのと整合性を取るなら、資格自体の難易度表示はカテゴリやサブカテゴリで代替できる可能性。ただし「初級/中級/上級」のラベルが受講生のカタログ選定に有用な可能性もあり、判断保留
- ❓ **疑問**: `certification_coach_assignments` に `assigned_at` / `unassigned_at` の両方が必要か。`pivot.withTimestamps()` + `unassigned_at IS NULL` でアクティブ判定すれば `created_at` が assigned 時刻になり、`assigned_at` は冗長になる可能性。design.md からは明確に追えない

---

## 4. content-management

**Feature 概要**: coach 担当資格の教材階層（Part → Chapter → Section）と Section 紐づき問題、教材内画像、問題カテゴリマスタを管理。v3 で問題テーブル分離を完遂。

### parts / chapters / sections
3 階層の教材ツリー。各層に `status` (`Draft` / `Published`) と `order` を持つ。

| カラム（共通） | 型 | 役割 |
|---|---|---|
| id | ULID | PK |
| 親FK (certification_id / part_id / chapter_id) | ULID FK | 階層親 |
| title / description | string / text | UI 表示 |
| order | unsignedSmallInt | 同一親内の並び順 |
| status | Enum | Draft/Published |
| published_at / deleted_at | timestamp nullable | 遷移時刻 / SoftDelete |

`sections.body` のみ longtext 50000 max（Markdown 本文）。

### section_questions（v3 で旧 questions から分離・改名）
Section 紐づき問題。`section_id` NOT NULL（旧 nullable 撤回）。`certification_id` も撤回（section から辿る）。

| カラム | 型 | 役割 |
|---|---|---|
| id | ULID | PK |
| section_id | ULID FK NOT NULL | v3、cascadeOnDelete |
| category_id | ULID FK | QuestionCategory（共有マスタ）、restrictOnDelete |
| body / explanation | text | 問題本文 / 解説 |
| order / status | unsignedSmallInt / Enum | 並び順 / Draft/Published |
| published_at / deleted_at | timestamp nullable | |

### section_question_options（v3 新）
SectionQuestion の選択肢。`is_correct` を持つ正解情報所有テーブル。SoftDelete 非採用（delete-and-insert で同期）。

### question_categories
問題カテゴリ共有マスタ。`(certification_id, slug)` UNIQUE。SectionQuestion と MockExamQuestion 両方から `category_id` で参照される。

### section_images
教材内画像。Storage public driver 配下に `section-images/{ulid}.{ext}` で保存。

**設計判断**:
- **C 案（完全分離 + 模試問題は模試マスタの子）の採用**: 旧 `Question.section_id nullable` で SectionQuestion と MockExamQuestion を兼用していた設計を撤回し、テーブルレベルで分離。SoftDelete 時の `category_id` 削除ガードで両系統参照確認をするのが本 Feature の責務
- `SectionQuestion.certification_id` を持たないのは「section → chapter → part → certification と辿れるので冗長」という正規化判断。引き換えに検索クエリで `whereHas('section.chapter.part', ...)` が長くなるが、`category_id` の certification 整合性は Store/Update Action で検証
- `SectionQuestionOption.is_correct` を選択肢テーブル側に持たせる設計は典型的（採点時に「選んだ option の is_correct」で正誤判定）。option の物理削除を考慮して `SectionQuestionAnswer.selected_option_body` でスナップショット保持
- `QuestionCategory` の共有マスタ化により、「データベース」カテゴリで Section 問題と模試問題両方の正答率を統合分析可能。弱点ドリル（[[quiz-answering]]）が模試弱点（[[mock-exam]] 算出）と連動できる根幹

### 適切性評価
- ✅ **必要最低限**: 階層 3 段 × status 列という構造的繰り返しは「教材階層を明示する」UX 要件のため必要。`SectionImage` を独立テーブル化したのは Storage path の論理管理 + 検索可能性のため正当
- ✅ **最適化**: v3 で `Question.section_id nullable` を捨てて `SectionQuestion` / `MockExamQuestion` 分離は **アーキテクチャ品質の大幅向上**。`Question.certification_id` 撤回もよく考えられた正規化（chain 経由で辿れるなら持たない）。各層に複合 INDEX `(親FK, order)` `(親FK, status)` が定義されており、教材ツリー描画の N+1 を防ぐ
- ⚠️ **改善余地**: `sections.body` longtext 50000 max は LIKE 検索が遅くなる可能性。Section 全文検索（`SearchAction`）は MySQL の LIKE で実装する仕様だが、5 万文字 × Section 数が増えると `%keyword%` 検索が遅延するリスク。`FULLTEXT INDEX` の追加検討余地（ただしスコープ外として明示済）
- ❓ **疑問**: `section_question_options` が SoftDelete 非採用なのは delete-and-insert 同期のためと書かれているが、`SectionQuestionAnswer.selected_option_id` が `nullOnDelete` でないと過去解答が壊れるリスク。design.md からは `nullable` 確認 OK だが、`onDelete` 動作の明示が一部不明

---

## 5. enrollment

**Feature 概要**: student / admin / coach。受講生 × 資格の多対多関係を中核に、個人目標 / コーチ用メモ / 状態履歴 / 修了自己発火を統括。

### enrollments
受講生 × 資格の登録テーブル。Certify LMS の独立性の要。

| カラム | 型 | 役割 |
|---|---|---|
| id | ULID | PK |
| user_id | ULID FK | 受講生 |
| certification_id | ULID FK | 資格 |
| exam_date | date nullable | 目標受験日（LMS外、カウントダウン用） |
| status | Enum | `learning` / `passed` / `failed`（3 値、v3 で `paused` 撤回） |
| current_term | Enum | `basic_learning` / `mock_practice`（初回 mock-exam 開始で自動切替） |
| passed_at | datetime nullable | 修了達成日（LMS内） |
| deleted_at | timestamp nullable | SoftDelete |

UNIQUE `(user_id, certification_id)` + INDEX `(status, exam_date)` + `(certification_id, status)`。**`assigned_coach_id` 撤回（v3、`certification_coach_assignments` で表現）**、**`completion_requested_at` 撤回（v3、修了申請承認フロー廃止）**。

### enrollment_goals
受講生が資格ごとに自由入力する個人目標（Wantedly 風タイムライン用）。

| カラム | 型 | 役割 |
|---|---|---|
| id / enrollment_id | ULID / ULID FK | 紐づけ |
| title / description / target_date | string / text / date | 目標内容 |
| achieved_at | datetime nullable | 達成マーク |
| deleted_at | timestamp nullable | SoftDelete |

### enrollment_notes
コーチが担当資格 × 受講生について書く自由メモ（受講生は閲覧不可）。

| カラム | 型 | 役割 |
|---|---|---|
| id / enrollment_id / coach_user_id | ULID / ULID FK / ULID FK | 紐づけ |
| body | text | 自由メモ |
| deleted_at | timestamp nullable | SoftDelete |

### enrollment_status_logs（INSERT only）
status 遷移履歴。

| カラム | 型 | 役割 |
|---|---|---|
| id / enrollment_id | ULID / ULID FK | 紐づけ |
| event_type | Enum | `status_change`（v3 で `coach_change` 撤回） |
| from_status / to_status | Enum nullable | 遷移前後 |
| changed_by_user_id | ULID FK nullable | 実行者（Schedule Command 時 NULL） |
| changed_reason / changed_at | text / timestamp | 記録 |

**設計判断**:
- 「**目標受験日 / 修了 / 受講状態 / ターム**」の 4 概念を `enrollments` 1 テーブルで集約しつつカラム独立性を保つ設計。product.md で「混同を避けるべき」とされた 2 軸（status バイナリ vs current_term 進行段階）が DB に分離して反映されている
- **`assigned_coach_id` 撤回**は重要な v3 改修。担当コーチを Enrollment レベルでなく `certification_coach_assignments` で表現 →「資格 1 つに N コーチ」「コーチ変更操作不要」「受講生は担当資格コーチ集合とやり取り」という UX に整合
- **3 種類の「目標」分離**: `Enrollment.exam_date`（試験日）/ `EnrollmentGoal`（個人目標、自由）/ `LearningHourTarget`（学習時間目標、learning Feature 所有）は別テーブル / 別カラムで完全分離。product.md の混同回避指針が DB に表現されている
- **修了自己発火**: `ReceiveCertificateAction` 内で `passed_at` セット + Certificate 発行 + StatusLog 記録を 1 トランザクションに束ね、admin 承認を撤回。`CompletionEligibilityService` で公開模試全合格を検証
- `EnrollmentNote` は coach + admin のみ閲覧/編集可、受講生不可。「コーチが受講生について書く自由メモ」というドメインを別テーブル化することで、Policy で完全に閲覧権を切り分け可能

### 適切性評価
- ✅ **必要最低限**: 4 テーブル構成（enrollment / goals / notes / status_logs）は責務分離が綺麗。冗長テーブルなし
- ✅ **最適化**: v3 で `EnrollmentStatusLog.event_type` から `coach_change` 撤回したのは「`assigned_coach_id` を持たない設計」と整合的（記録すべき遷移が status_change のみに収束）。INDEX `(status, exam_date)` は「試験日が近い未修了の Enrollment」抽出（FailExpiredCommand）に最適
- ⚠️ **改善余地**: `EnrollmentStatusLog.event_type` を残す意義が薄くなった（全レコード `status_change` 固定）。今後拡張がないなら削除検討余地あり、ただし「将来的に `term_change` を追加する含み」として残すなら明示するべき。design.md にもその旨記載なし
- ❓ **疑問**: `EnrollmentStatusLog.from_status` が nullable と明記されているが、初回登録時の遷移（`null → learning`）を記録するか design.md からは追えない。spec 上は INSERT only の append-only で「登録時は記録なし」（StatusLog INSERT は Action 内で明示呼出のみ）と推察するが要確認

---

## 6. learning

**Feature 概要**: student。Section 読了マーク / 学習時間トラッキング / 学習時間目標 / ストリーク / 進捗集計を提供。content-management の Model を読み取り再利用。

### section_progresses
Section 単位の読了マーク。

| カラム | 型 | 役割 |
|---|---|---|
| id / enrollment_id / section_id | ULID / ULID FK / ULID FK | 紐づけ |
| completed_at | timestamp | 読了時刻 |
| deleted_at | timestamp nullable | SoftDelete（unmark 時） |

UNIQUE `(enrollment_id, section_id)`。

### learning_sessions
教材閲覧の時間トラッキング。

| カラム | 型 | 役割 |
|---|---|---|
| id | ULID | PK |
| user_id | ULID FK | 学習者（denormalized） |
| enrollment_id / section_id | ULID FK | 紐づけ |
| started_at / ended_at | timestamp / timestamp nullable | 開始/終了 |
| duration_seconds | unsignedInt nullable | 秒数（clamp 済） |
| auto_closed | boolean | Schedule Command による自動クローズフラグ |
| deleted_at | timestamp nullable | SoftDelete |

### learning_hour_targets
資格単位の学習時間目標（1:1 with Enrollment、UNIQUE）。

| カラム | 型 | 役割 |
|---|---|---|
| id / enrollment_id (UNIQUE) | ULID / ULID FK | 紐づけ |
| target_total_hours | unsignedSmallInt | 目標総時間（残時間は逆算） |
| deleted_at | timestamp nullable | SoftDelete |

**設計判断**:
- `LearningSession.user_id` は `enrollment.user_id` で取得可能だが denormalize しているのは、`(user_id, started_at)` INDEX による「ユーザー横断（複数資格に跨いだ学習時間）」集計のため。`StreakService::calculate(User)` が複数 Enrollment を跨いで DISTINCT DATE を集めるクエリで効く
- `LearningHourTarget` は `target_total_hours` のみ保存し、期間は `Enrollment.created_at` 〜 `Enrollment.exam_date` で代用、残時間 / 日次推奨ペースは Service で逆算。**過剰スキーマ回避の好例**
- `SectionProgress` SoftDelete 採用で「読了マーク → unmark → 再 markRead」サイクルを 1 レコードで restore + UPDATE 同期。delete-and-insert より履歴が綺麗
- v3 で `StagnationDetectionService` 撤回（滞留検知 MVP 外）。design.md にも「明示的に持たない」と書かれており、捨てた設計の痕跡を残す書き方が良い
- `auto_closed` は「ユーザー明示停止」/「別 Section auto-start による自動切替」/「Schedule Command による強制クローズ」を区別するフラグ。集計で「実利用時間」と「推定時間」を分離したい場合に使用

**LearningSession ライフサイクル(2026-05-16 確定、JS 撤回)**:
- **Start**: `GET /learning/sections/{section}` の `BrowseController::showSection` 内でサーバ側 auto-start。受講生は何もボタンを押さない暗黙開始(JS 不要)。既存 open session があれば `SELECT FOR UPDATE → auto_closed=true` で先に閉じる(別 Section 切替の自動連鎖)
- **Stop の 3 経路**: (1) 「学習を一旦終える」明示ボタン(form POST → 302 redirect) / (2) 別 Section 遷移時の auto-start による旧 session 自動 close / (3) Schedule Command `learning:close-stale-sessions`(日次 00:30、`started_at < now()-max_session_seconds` を強制 close)
- **JavaScript / sendBeacon / pagehide / visibilitychange / heartbeat は採用しない**(2026-05-16)。集計の正確性は `duration_seconds` の上限 clamp(`config('learning.max_session_seconds', 3600)`) + Schedule Command 自動クローズの 2 重保険で担保
- **マージ方針なし**: 連続セッションも別レコード保存、集計時に `SUM` で合算
- **失敗時フェイルセーフ**: `StartAction` が例外でも Controller が `try-catch` で吸収して Section ページは描画継続、残存 open は Schedule Command で回収

### 適切性評価
- ✅ **必要最低限**: 3 テーブルすべて UX 上の役割明確。`LearningHourTarget` の `target_total_hours` 1 カラム設計は理想的なミニマリズム
- ✅ **最適化**: 複合 INDEX 設計が綿密（`(user_id, started_at)` / `(enrollment_id, started_at)` / `(user_id, ended_at)` / `(enrollment_id, section_id)`）。集計クエリのアクセスパターンに合わせて設計されている
- ✅ **JS 不要のシンプル設計**(2026-05-16): サーバ側 auto-start + 明示停止ボタン + Schedule Command の 3 経路で Stop を完結。Pro 生レベルとして「ブラウザ離脱検知の不確実性を Schedule Command で吸収する」を学べる。`sendBeacon` / `pagehide` の Safari 制約等の落とし穴を回避
- ⚠️ **改善余地**: `LearningSession.duration_seconds` nullable は「終了前は NULL」を意味するが、`ended_at` でも判別可能で冗長性あり。ただしクエリで `SUM(duration_seconds)` する用途を考えれば、`ended_at - started_at` を毎回計算するよりキャッシュ済カラムは妥当
- ❓ **疑問**: 「**学習時間目標 vs 試験日カウントダウン vs 個人目標** の 3 種類の独立性」は product.md で強調されている。DB レベルでは `LearningHourTarget` / `Enrollment.exam_date` / `EnrollmentGoal` で分離されているが、3 つすべて UI 上は「目標管理」エリアに集約。コンセプト分離は DB OK、UX も product.md で言及済

---

## 7. quiz-answering

**Feature 概要**: student。SectionQuestion 演習エントリ・解答送信・自動採点・結果画面・履歴・苦手分野ドリル。**FE は Blade + Form POST + Redirect の純 Laravel 標準パターン**(2026-05-16 確定)。Sanctum SPA / 公開 JSON API / JavaScript Ajax / sendBeacon / API Resource クラスはいずれも採用しない。

### section_question_answers（v3 で旧 answers から rename）
個別解答ログ。1 解答 = 1 レコード（重複可、UPSERT しない append-only）。

| カラム | 型 | 役割 |
|---|---|---|
| id / user_id | ULID / ULID FK | 紐づけ |
| section_question_id | ULID FK | v3、SectionQuestion 参照 |
| selected_option_id | ULID FK nullable | SectionQuestionOption（option 削除時の nullOnDelete） |
| selected_option_body | string max 2000 | スナップショット（option 削除時の保険） |
| is_correct | boolean | 採点結果（INSERT 時に確定、Option の is_correct を写す） |
| source | Enum | `section_quiz` / `weak_drill`（どこから解いたか） |
| answered_at | timestamp | 解答時刻 |
| deleted_at | timestamp nullable | SoftDelete |

### section_question_attempts（v3 で旧 question_attempts から rename）
SectionQuestion 単位のサマリ。UPSERT 同期。

| カラム | 型 | 役割 |
|---|---|---|
| id / user_id / section_question_id | ULID / ULID FK / ULID FK | UNIQUE `(user_id, section_question_id)` |
| attempt_count / correct_count | unsignedInt | 累計回数 / 正答回数 |
| last_is_correct | boolean | 最終解答の正誤 |
| last_answered_at | timestamp | 最終解答時刻 |
| deleted_at | timestamp nullable | SoftDelete |

`accuracy()` accessor で `correct_count / attempt_count` 返却。

**設計判断**:
- **Answer（append-only）+ Attempt（UPSERT サマリ）の 2 テーブル分離**は典型的な学習履歴設計。`StoreAction` の `DB::transaction` 内で INSERT + UPSERT を原子的に同期
- `selected_option_body` スナップショット保持により、Option を物理削除しても過去履歴の選択肢本文が読める。「不変履歴」の確保(スナップショット哲学、Part 3 参照)
- `WeaknessAnalysisServiceContract` Interface を本 Feature が定義し、[[mock-exam]] が正規実装 + `NullWeaknessAnalysisService` をフォールバック登録する **DI による疎結合設計**。mock-exam 未実装環境でも UI が破綻しない
- `source` Enum で「Section 演習」「苦手ドリル」の出題元区別。同じ問題を別経路から解いた集計分離が可能 + Controller での redirect 先分岐(`source=section_quiz` → `quiz.sections.result`、`source=weak_drill` → `quiz.drills.result`)に使用
- 弱点ドリル出題対象は **SectionQuestion のみ**（MockExamQuestion は出題しない）。模試で検出した弱点カテゴリを SectionQuestion で集中演習する設計（`QuestionCategory` 共有マスタ経由）
- **FE フロー(2026-05-16 確定)**: 出題画面 → form POST `.../answer` → `StoreAction` で永続化 → `source` 値で 302 redirect → 結果画面(独立 Blade ルート `.../result/{answer}`) → 「次の問題へ」リンクで連続演習。PRG パターンによりリロード安全 + ブラウザバック安全。**JS / Ajax / Resource クラスはなし**

### 適切性評価
- ✅ **必要最低限**: 2 テーブル構成で過剰なし。`selected_option_body` スナップショットは履歴永続化に必要
- ✅ **最適化**: 複合 INDEX が綿密（`(user_id, answered_at)` / `(user_id, section_question_id)` / `(section_question_id, is_correct)` / `source`）。履歴一覧の `ORDER BY answered_at DESC` と分野別正答率集計の両方に対応
- ⚠️ **改善余地**: `SectionQuestionAttempt.deleted_at` は実質使われない可能性。「sub-row が SoftDelete されるユースケース」が design.md にない（SectionQuestion が SoftDelete されてもサマリは残したい想定）。冗長カラムの可能性
- ❓ **疑問**: `SectionQuestionAnswer.selected_option_id nullable` と `selected_option_body` スナップショットの併用設計の意図は明確（option 物理削除耐性）だが、option は SoftDelete 非採用 + delete-and-insert 同期と書かれている（[[content-management]] design.md）→ Option ID が再作成されると古い `selected_option_id` が新規 Option を指す危険性。`nullOnDelete` で完全に切り離す方が安全だが、design.md からは挙動が読み取れない部分あり

---

## 8. mock-exam

**Feature 概要**: student / coach / admin。本番形式の模擬試験。MockExam マスタ + 問題セット事前固定 + 中断・再開対応 + 一括採点 + 分野別ヒートマップ + 合格可能性スコア。

### mock_exams
模試マスタ。

| カラム | 型 | 役割 |
|---|---|---|
| id / certification_id | ULID / ULID FK | 資格マスタ紐づけ |
| title / description / order | string / text / unsignedSmallInt | 模試名 / 説明 / 並び順 |
| passing_score | unsignedTinyInt 1..100 | 合格点（資格マスタから移動、模試ごとに変更可） |
| is_published / published_at | boolean / timestamp nullable | 公開制御 |
| created_by_user_id / updated_by_user_id | ULID FK | 監査 |
| deleted_at | timestamp nullable | SoftDelete |

**E-3 で `time_limit_minutes` 削除**（時間制限機能完全撤回）。

### mock_exam_questions（v3 独立リソース化）
模試マスタの子リソース。

| カラム | 型 | 役割 |
|---|---|---|
| id / mock_exam_id / category_id | ULID / ULID FK / ULID FK | 紐づけ |
| body / explanation / order | text / text / unsignedSmallInt | |
| deleted_at | timestamp nullable | SoftDelete |

### mock_exam_question_options（v3 新設）
模試問題の選択肢。`is_correct` 所有テーブル。

### mock_exam_sessions
受験セッション。不変履歴 + スナップショット保持。

| カラム | 型 | 役割 |
|---|---|---|
| id / mock_exam_id / enrollment_id / user_id | ULID / ULID FK × 3 | 紐づけ（enrollment 経由でなく user_id も denormalize） |
| status | Enum | `not_started` / `in_progress` / `submitted` / `graded` / `canceled` |
| generated_question_ids | json | MockExamQuestion.id のスナップショット配列 |
| total_questions / passing_score_snapshot | unsignedSmallInt / unsignedTinyInt | スナップショット |
| started_at / submitted_at / graded_at / canceled_at | timestamp nullable | 状態遷移時刻 |
| total_correct / score_percentage | unsignedSmallInt / decimal(5,2) nullable | 採点結果 |
| pass | boolean nullable | 合格判定 |
| deleted_at | timestamp nullable | SoftDelete |

**E-3 で `time_limit_minutes_snapshot` / `time_limit_ends_at` 削除**。

### mock_exam_answers
受験中の解答ログ。

| カラム | 型 | 役割 |
|---|---|---|
| id / mock_exam_session_id / mock_exam_question_id | ULID / ULID FK / ULID FK | UNIQUE `(session_id, question_id)` |
| selected_option_id | ULID FK nullable (nullOnDelete) | MockExamQuestionOption |
| selected_option_body | string max 2000 | スナップショット |
| is_correct | boolean | 採点時に確定 |
| answered_at | timestamp | 解答時刻 |

**設計判断**:
- **大型スキーマだが構造化が綺麗**: 5 テーブル（mock_exams / questions / options / sessions / answers）の各責務が明確。SectionQuestion 系と完全並走する命名で混同回避
- **session.generated_question_ids JSON 配列スナップショット**は重要な v3 設計。MockExam の問題セットを「セッション作成時点で固定」することで、後からマスタが変わっても受験中セッションは影響を受けない。再開時も同じ問題セット
- `passing_score_snapshot` も同様のスナップショット哲学。マスタ側の `passing_score` が変わっても、セッションの合格判定は受験開始時点の値で固定
- **逐次保存設計**: `mock_exam_answers` の UNIQUE `(session_id, question_id)` で UPSERT、受験中のラジオボタン change で即時 PATCH。中断・再開対応。スマートフォン受験等を考慮した実用設計
- E-3 で時間制限撤回。`time_limit_minutes` 系 3 カラム削除 + auto-submit / Schedule Command 削除 + `MockExamSessionTimeExceededException` 削除。**スコープ削減の正しい例**（クライアントタイマー改ざんリスクとサーバ側 enforce 実装複雑度のバランスで撤回）
- `mock_exam_questions` の親が `mock_exam_id`（資格でなく模試）であることで、コーチが「模試 A 用問題セット」「模試 B 用問題セット」を独立管理可能。SectionQuestion との完全分離

### 適切性評価
- ✅ **必要最低限**: 5 テーブル構成は機能複雑度（マスタ + 問題セット + セッション + 解答 + 集計）に対して妥当。スナップショット 2 カラム（`generated_question_ids` / `passing_score_snapshot`）は不変履歴の要
- ✅ **最適化**: 採点フローが 1 トランザクションに束ねられ、`lockForUpdate()` で二重提出ガード。`(enrollment_id, status)` / `(mock_exam_id, pass)` / `(user_id, graded_at)` 複合 INDEX は dashboard 集計と相性良。`generated_question_ids` を JSON 配列で持つことで session-question 中間テーブル不要、JSON クエリで十分（受験中は全件 SELECT IN するクエリパターン）
- ⚠️ **改善余地**: `MockExamSession.user_id` denormalize は `enrollment.user_id` で取得可能。理由は「user 横断の受験履歴集計」だが、`Enrollment.user_id` 経由でも `JOIN` でいけるので冗長性あり。ただし `(user_id, graded_at)` INDEX を使う集計クエリで効くなら正当
- ❓ **疑問**: `MockExamSession.generated_question_ids` JSON 配列の **順序保証**が design.md から明確に読み取れない（`MockExamQuestion.order` ベースなのは記載あり）。再開時に同じ順序で表示する必要があるが、JSON 順序を MySQL で保証する設計の明示は弱い。実装で `array_values($json)` を信頼する形になりそう

---

## 9. mentoring

**Feature 概要**: student / coach。資格単位の面談予約。受講生は時刻スロットだけ選択 → 過去 30 日実施数最少のコーチを自動割当 → 即時 `reserved` 確定 + 面談回数 -1。Schedule Command で自動完了。

### meetings
面談予約。

| カラム | 型 | 役割 |
|---|---|---|
| id / enrollment_id / coach_id (auto-assigned) / student_id | ULID / ULID FK × 3 | 紐づけ |
| scheduled_at | datetime | 60 分固定の予約時刻 |
| status | Enum | `reserved` / `canceled` / `completed`(3 値、v3 で 6 値から削減) |
| topic | text | 受講生入力の議題 |
| canceled_by_user_id | ULID FK nullable | キャンセル実行者 |
| canceled_at / completed_at | datetime nullable | 状態遷移時刻 |
| meeting_url_snapshot | string nullable | コーチの meeting_url を予約時に焼き込み |
| meeting_quota_transaction_id | ULID FK | consumed transaction 参照（refund 時の対応付け） |
| deleted_at | timestamp nullable | SoftDelete |

UNIQUE `(coach_id, scheduled_at)` で DB 制約による二重予約防止。`(status, scheduled_at)` INDEX は Schedule Command 高速化。

### meeting_memos
コーチが任意のタイミングで記録（reserved / completed どちらでも可、1:1 with Meeting）。

| カラム | 型 | 役割 |
|---|---|---|
| id / meeting_id (UNIQUE) | ULID / ULID FK | 1:1 |
| body | text | 自由メモ |
| deleted_at | timestamp nullable | SoftDelete |

### coach_availabilities
コーチの面談可能時間枠（曜日 + 時間帯）。

| カラム | 型 | 役割 |
|---|---|---|
| id / coach_id | ULID / ULID FK (cascadeOnDelete) | 紐づけ |
| day_of_week | tinyint | 0=Sun..6=Sat |
| start_time / end_time | time / time | 時間帯 |
| is_active | boolean | 一時停止用 |
| deleted_at | timestamp nullable | SoftDelete |

**設計判断**:
- **v3 で Meeting.status を 6 値 → 3 値に削減**。`requested` / `approved` / `rejected` / `in_progress` を撤回。「申請承認フロー廃止」「LMS 内に面談中状態を持たない」設計判断と整合。**スコープ削減の好例**
- **UNIQUE `(coach_id, scheduled_at)` で DB 制約による race condition 防御**。並行予約時に INSERT 失敗 → catch → `MeetingNoAvailableCoachException` という堅実な実装パターン
- **`meeting_url_snapshot`**: 予約時にコーチの `users.meeting_url` を焼き込み、後でコーチが URL 変更しても過去の予約には影響なし。スナップショット哲学
- **`meeting_quota_transaction_id` で Meeting と Transaction の双方向参照**: refund 時に「どの consumed を返すか」を明示。整合性のための参照。`MeetingQuotaTransaction.reference_id` 側でも逆引き可能（[[meeting-quota]]）
- **自動コーチ割当ロジック**: `CoachMeetingLoadService::leastLoadedCoach` が「過去 30 日 completed 数最少 + 同数時 ULID 昇順」で選出。デモクラシー設計（負荷分散）
- `CoachAvailability` は曜日 + 時間帯のシンプル構造。臨時シフトや特定日除外は不採用（スコープ外明示）
- `users.meeting_url` の Migration を [[auth]] が所有することで、mentoring は読み取り側として責務分離。snapshot 取得のみ

### 適切性評価
- ✅ **必要最低限**: 3 テーブル構成（meetings / memos / availabilities）は責務明確。冗長なし
- ✅ **最適化**: UNIQUE 制約による race ガード + 複合 INDEX 設計が綿密。`(status, scheduled_at)` は `meetings:auto-complete` Schedule Command の `WHERE status=reserved AND scheduled_at + 60min < now()` クエリと完全整合
- ⚠️ **改善余地**: `Meeting.coach_id` `Meeting.student_id` の denormalize は便利だが、`Enrollment` 経由でも取得可能で部分的に冗長（`student_id` は `Enrollment.user_id` と一致）。ただし `(student_id, scheduled_at)` INDEX を使う「自分の面談一覧」クエリで効く + Enrollment 削除 vs Meeting 削除のタイミング差を吸収可能なので、正当性あり
- ❓ **疑問**: **`MeetingMemo.coach_id` カラム不在**が興味深い設計。`meeting.coach` で一意なので author は不要、と書かれているが、複数コーチが連続して担当する将来拡張時に author 識別不可になるリスク。MVP スコープでは妥当だが、design.md で「将来拡張時に author カラム追加」と明示しておくと安全。また、`MeetingMemo` の `created_by_user_id` 監査カラムもない（更新者追跡不能）

---

# Part 2: Feature 10-18

## 10. chat

**Feature 概要**: 受講生×担当資格コーチ集合のグループチャット。1 Enrollment = 1 ChatRoom、Pusher リアルタイム配信 + 個人別未読バッジ。短期相談用途のため添付機能は v3 で完全撤回。

### chat_rooms
1 Enrollment に 1 つ存在するメッセージコンテナ。

| カラム | 型 | 役割 |
|---|---|---|
| id | ULID | PK |
| enrollment_id | FK | UNIQUE 1:1 で Enrollment に紐づき、グループルームのスコープ（受講生×資格）を一意決定 |
| last_message_at | datetime nullable | 最終発言時刻のデノーマライズ。`booted::created` フックで更新 |
| timestamps | - | created/updated/deleted_at |

### chat_members
ChatRoom 参加者の中間テーブル。受講生 1 名 + 担当資格コーチ全員。

| カラム | 型 | 役割 |
|---|---|---|
| id | ULID | PK |
| chat_room_id | FK | cascadeOnDelete |
| user_id | FK | restrictOnDelete（参加者削除は人事的影響大、本ロジックで明示削除させる） |
| last_read_at | datetime nullable | **個人別既読時刻**。未読バッジ算出の唯一の正 |
| joined_at | datetime | 参加日時 |
| timestamps | - | created/updated/deleted_at |

### chat_messages
個別メッセージ。最大 2000 文字、編集・削除不可（学習相談の改竄防止 + admin 監査）。

| カラム | 型 | 役割 |
|---|---|---|
| id | ULID | PK |
| chat_room_id | FK | cascadeOnDelete |
| sender_user_id | FK | restrictOnDelete（過去メッセージの送信者退会時もログを保持） |
| body | text | 最大 2000 文字 |
| timestamps | - | created/updated/deleted_at |

**設計判断**:
- **`ChatRoom.status` 撤回（v3）**: 「未対応 → 解決済」の状態遷移を持たない単純なメッセージコンテナ化。状態管理は qa-board（`QaThread.status`）に任せて関心事を分離
- **`ChatMember` 中間テーブル化**: 旧設計の `ChatRoom.last_read_at` を個人別へ拡張。グループ chat として「自分の既読時刻」を持つのが自然
- **`(chat_room_id, user_id)` UNIQUE**: 同一ルームへの二重参加防止
- **添付機能完全撤回（E-2）**: `chat_attachments` テーブル / Storage private / signed URL ルートをすべて削除。画像共有は qa-board / 教材へ誘導
- **担当コーチ集合変更の自動同期**: `ChatMemberSyncService` が `CertificationCoachAttached` Event に反応して ChatMember を追加（過去ログ閲覧可、Detach は閲覧不可化）

### 適切性評価
- ✅ **必要最低限**: 添付撤回・status 撤回で構造が極小に。グループチャットを 3 テーブルで成立させており過剰な抽象なし
- ✅ **最適化**: `(chat_room_id, created_at)` 複合 INDEX でスレッド時系列取得高速化、`(user_id, last_read_at)` で未読集計高速化。`last_message_at` のデノーマライズで一覧 N+1 回避
- ⚠️ **改善余地**: `chat_messages.deleted_at` INDEX は持つが、編集・削除不可仕様（"learning相談の改竄防止"）と矛盾するように見える。SoftDeletes は admin 監査用の救済路（不適切メッセージの非表示化）と推測されるが、UI に編集導線がない以上、運用ルールを設計コメントに明記したい
- ❓ **疑問**: `ChatMember.deleted_at` を持つが、コーチが担当を外れた場合は SoftDelete か物理削除か明示なし（"閲覧不可化" のためには `whereNull('deleted_at')` で除外する SoftDelete 想定だが、document 内で曖昧）

---

## 11. qa-board

**Feature 概要**: 受講生の公開技術質問掲示板。資格単位スコープ、解決マーク・全文検索・admin モデレーション付き。chat の private 1on1 に対し集合知型を担う。

### qa_threads
質問スレッド本体。`status` + `resolved_at` の二本立てで状態管理。

| カラム | 型 | 役割 |
|---|---|---|
| id | ULID | PK |
| certification_id | FK | restrictOnDelete（資格の物理削除を qa の存在でブロック） |
| user_id | FK | restrictOnDelete（投稿者） |
| title | varchar(200) | 全文検索対象 |
| body | text | 最大 5000 文字、全文検索対象 |
| status | enum | `open`/`resolved`（Enum クラスで `label()` 提供） |
| resolved_at | datetime nullable | resolved 遷移時に now()、unresolve で NULL |
| timestamps | - | created/updated/deleted_at（admin モデレーション用 SoftDelete） |

### qa_replies
スレッドへの回答。状態を持たないシンプルな投稿。

| カラム | 型 | 役割 |
|---|---|---|
| id | ULID | PK |
| qa_thread_id | FK | restrictOnDelete（投稿者削除フローは admin で SoftDelete） |
| user_id | FK | restrictOnDelete（回答者） |
| body | text | 最大 5000 文字、全文検索対象 |
| timestamps | - | created/updated/deleted_at |

**設計判断**:
- **`status` Enum + `resolved_at` の二本立て**: プロジェクト規約（`Enrollment.status + passed_at`、`MockExamSession.status + submitted_at` 等）と一貫整合。Action 内の同時更新で整合性担保
- **FK 全 `restrictOnDelete`**: 履歴保護優先。`Certification` 物理削除を qa 存在でブロック、回答 0 件以外のスレッド削除は admin のみ
- **`(certification_id, status)` 複合 INDEX**: 一覧のメイン経路（資格別 × 未解決フィルタ）を狙い撃ち
- **添付なし（明示スコープ外）**: chat と異なる方針。公開掲示板の管理コスト・モデレーション負荷を回避
- **`{!! nl2br(e($body)) !!}` 統一**: Markdown レンダリングは行わず XSS 完全防御

### 適切性評価
- ✅ **必要最低限**: テーブル 2 つで完結、過剰な「タグ / カテゴリ / モデレーションキュー」を持たない
- ✅ **最適化**: `(certification_id, status)` 複合 INDEX、`(qa_thread_id, created_at)` でスレッド詳細高速化、`SidebarBadgeComposer` 用の未回答件数集計が `whereDoesntHave('replies')` で 1 クエリ
- ⚠️ **改善余地**: `body` の `not_regex` 空白チェックを FormRequest 側のみで実装している。全文検索の品質を考えると DB 側にもチェック制約 (`CHECK (body != '')`) を入れたい、ただし MySQL 互換性で複雑化するため FormRequest 統一で十分とも言える
- ❓ **疑問**: `qa_threads.user_id` の `restrictOnDelete` は受講生退会時に矛盾しない？user-management の `WithdrawUserAction` が SoftDelete 前提なら問題ないが、物理削除路を持つ場合は外部キー違反を踏む可能性あり

---

## 12. analytics-export

**Feature 概要**: GAS から `X-API-KEY` ヘッダで叩く読み取り専用 JSON API（users / enrollments / mock-exam-sessions の 3 本）。LMS は素データのみ返し、分析・集計は Google Sheets 側責務。

### 独自モデルなし
本 Feature は **独自モデル / Migration / Service / Policy を新設しない**。`config/analytics-export.php` + `ApiKeyMiddleware` のみが新規追加で、データソースは他 Feature 所有のテーブル（`users` / `enrollments` / `mock_exam_sessions`）をそのまま参照。

### 主要構成要素

| 要素 | 種別 | 役割 |
|---|---|---|
| `config/analytics-export.php` | Config | `'api_key' => env('ANALYTICS_API_KEY')` のみ |
| `ApiKeyMiddleware` | Middleware | `hash_equals` でタイミング攻撃耐性のあるキー比較。`config` 空時は 503 `API_KEY_NOT_CONFIGURED`、不一致時は 401 `INVALID_API_KEY` |
| 3 本の `IndexRequest` | FormRequest | クエリパラメータ検証（status/role/include/per_page/page 等） |
| 3 本の `Controller::index` | Controller | Eloquent → Resource→`additional(['_batch' => [...]])` |
| 3 本の `*Resource` | JsonResource | 公開フィールド + センシティブカラム除外 |

### API 出力スキーマ

`UserResource`（v3 で Plan 関連 4 カラム追加）:

| フィールド | 型 | 由来 |
|---|---|---|
| id / name / email / role / status / last_login_at | - | `users` テーブル直接 |
| plan_id / plan_started_at / plan_expires_at / max_meetings | - | v3 新規（plan-management 由来） |
| created_at / updated_at | - | timestamps |

**絶対に含めない**: `password` / `remember_token` / `bio` / `avatar_url` / `meeting_url` / `profile_setup_completed` / `email_verified_at`

`EnrollmentResource`（v3 で `assigned_coach_id` 撤回）:

| フィールド | 由来 |
|---|---|
| id / user_id / certification_id / status / current_term / exam_date / passed_at | `enrollments` テーブル |
| progress_rate | `ProgressService::batchCalculate`（learning 所有、batch 集計） |
| last_activity_at | `LastActivityService::batchLastActivityFor`（v3 新規、`LearningSession.ended_at` + `SectionQuestionAnswer.answered_at` の MAX） |
| user / certification | whenLoaded（`?include=user,certification` で expand） |

`MockExamSessionResource`:

| フィールド | 由来 |
|---|---|
| id / user_id / mock_exam_id / enrollment_id / status / total_correct / passing_score_snapshot / pass / started_at / submitted_at / graded_at | `mock_exam_sessions` テーブル |
| category_breakdown | `WeaknessAnalysisService::batchHeatmap`（v3 で `MockExamQuestion JOIN` ベース、旧 `Question` JOIN 撤回）。各要素は `{category_id, category_name, correct, total, rate}` |

**設計判断**:
- **`ApiKeyMiddleware` のみ + Sanctum・Fortify・Policy 全部不採用**: 認可単位は「API キー1個＝全件 read のみ」と割り切り、ロール単位フィルタは GAS 側責務
- **`hash_equals` 利用**: タイミング攻撃耐性、PHP 標準セキュアパターン
- **`config` 空時 503**: 「キー未設定」と「キー不一致」を明示分離（運用デバッグの容易性）
- **Resource の `additional(['_batch' => ...])`**: per-row でなく batch で集計値を Eloquent コレクション全体に対して 1 回計算 → resource に分配。N+1 完全回避の Laravel 標準パターン
- **読み取り専用**: POST/PUT/DELETE は持たない。書き込みは各 Feature 自前 API の流儀（quiz-answering の `/api/v1/quiz/...` 等）

### 適切性評価
- ✅ **必要最低限**: 独自テーブル・モデル・Policy・Service を新設しない徹底ぶり。設計責務の境界が極めて明確
- ✅ **最適化**: batch 系 Service の再利用で N+1 完全回避、`Http::fake()` 互換、`throttle:60,1` で過剰アクセス防御、`paginate` で大量データ対応
- ⚠️ **改善余地**: 監査ログ（誰がいつ叩いたか）を持たない。GAS のキーが漏洩した時の影響範囲調査が困難になる。教育PJスコープ的にはこのままでよいが、本番運用なら `api_access_logs` を 1 テーブル足したい
- ❓ **疑問**: `LastActivityService` を **「learning に新設 or 本 Feature 内に内製」** と曖昧記述。所有 Feature の最終判定は learning に寄せるべき（集計責務マトリクス整合）。design 内で確定させたい

---

## 13. notification

**Feature 概要**: 全 8 通知種別を Laravel Notification（Database + Mail channel 固定）で配信する受信者向け Feature。各 Feature が `Notify*Action` を起点に呼ぶ、ユーザー設定 UI なしの固定送信モデル。

### Laravel 標準 `notifications` テーブル
Database channel の保存先（本 Feature は migration を新設しない、Laravel 標準を踏襲）。

| カラム | 型 | 役割 |
|---|---|---|
| id | UUID/ULID | PK（`BaseNotification::__construct` で `Str::ulid()` で生成） |
| type | varchar | Notification クラス FQCN |
| notifiable_type / notifiable_id | morphs | 受信者（通常 User） |
| data | json | toDatabase() の戻り値 |
| read_at | datetime nullable | 既読化時刻 |
| timestamps | - | created/updated_at |

### admin_announcements
admin がフォームから配信する一斉お知らせ。Notification とは別に元情報として保存。

| カラム | 型 | 役割 |
|---|---|---|
| id | ULID | PK |
| sender_user_id | FK | 配信した admin |
| title / body | - | 配信内容 |
| target_type | enum | `all` / `certification` / `user`（配信対象スコープ） |
| target_certification_id | FK nullable | scope=certification 時 |
| target_user_id | FK nullable | scope=user 時 |
| sent_at | datetime | 配信完了時刻 |

### 通知種別 8 個（v3 確定）

| # | Notification クラス | 受信者 | 起点 Feature・Action | 配信チャネル |
|---|---|---|---|---|
| 1 | `ChatMessageReceivedNotification` | 受講生→全コーチ / コーチ→受講生 + 他コーチ | [[chat]] `StoreMessageAction` | DB+Mail（コーチ間は DB only） |
| 2 | `QaReplyReceivedNotification` | スレッド投稿者 | [[qa-board]] `QaReply\StoreAction` | DB+Mail |
| 3 | `MockExamGradedNotification` | 受験者本人 | [[mock-exam]] `SubmitAction` 採点完了後 | DB+Mail |
| 4 | `CompletionApprovedNotification` | 受講生本人 | [[enrollment]] `ReceiveCertificateAction`（v3 自己発火） | DB+Mail（Mail に PDF DL URL） |
| 5 | `MeetingReservedNotification` | **担当コーチのみ** | [[mentoring]] `ReserveMeetingAction` | DB+Mail |
| 6 | `MeetingCanceledNotification` | 相手方（student↔coach） | [[mentoring]] `CancelMeetingAction` | DB+Mail |
| 7 | `MeetingReminderNotification` | 受講生 + コーチ両方 | `SendMeetingRemindersCommand`（前日 18:00 + 1h 前） | DB+Mail |
| 8 | `AdminAnnouncementNotification` | 対象 student 集合 | `Admin\AdminAnnouncement\StoreAction` | DB+Mail |

**v3 撤回（明示的に持たない）**: `MeetingRequestedNotification` / `MeetingApprovedNotification` / `MeetingRejectedNotification`（mentoring 申請承認フロー撤回）/ `PlanExpireSoonNotification`（MVP 外）/ `StagnationReminderNotification`（滞留検知 v3 撤回）。

**設計判断**:
- **独自テーブルは `admin_announcements` のみ**: Notification 配信ログは Laravel 標準テーブルに任せ、`AdminAnnouncement` だけ元情報用テーブルを足す
- **`BaseNotification` 抽象基底 + `ShouldQueue`**: Mail 送信を queue 化、`via()` で DB+Mail 固定、ID は ULID
- **chat 通知の双方向化（v3）**: コーチ→他コーチは Database のみ（Mail は過剰）の細やかな配信制御
- **`MeetingReservedNotification` はコーチ宛のみ**: 受講生は予約 UI で即時確認するため通知不要（過剰通知を防ぐ哲学）
- **通知種別 ON/OFF UI を持たない**: 設計の単純化と「メールが多すぎる」予防は通知種別自体を絞ることで対応（Phase 0 確定）
- **`NotifyMeetingReminderAction` 重複排除**: 既存通知の `(meeting_id, window)` ペアを JSON path で検査

### 適切性評価
- ✅ **必要最低限**: 独自モデルは `AdminAnnouncement` 1 個、他は Laravel 標準。8 通知種別の絞り込みも徹底（admin/coach 宛通知は chat 例外を除いて持たない）
- ✅ **最適化**: `DB::afterCommit` で発火、Mail は queue 化、Broadcasting は config 切替で Advance 領域、graduated/withdrawn 受信者は dispatch 前 skip
- ⚠️ **改善余地**: `AdminAnnouncement` の `target_type` enum + `target_*_id` 列構成は **target が排他的に1つしか入らない**前提で動くが、DB 制約で表現されてない。CHECK 制約か Action 側のガードで明示したい
- ❓ **疑問**: `NotifyMeetingReminderAction` の重複検査が「JSON path で `notifications.data` を検査」とあるが、`(meeting_id, window)` ペアを物理 INDEX 化できない（JSON 内のため）。リマインダ件数規模次第ではパフォーマンス影響。`meeting_reminder_logs` 補助テーブルで重複排除する案も検討余地

---

## 14. dashboard

**Feature 概要**: ロール別の読み取り専用集約画面。**独自モデル / Service なし**、他 Feature の Service を DI 消費する「集計責務マトリクス」整合の中心。

### 独自モデルなし
- 新規 migration / Eloquent モデルなし
- 新規 Service なし（NFR-dashboard-003 で明文化、他 Feature の Service 再利用が原則）
- 新規 Policy なし（Controller 内で `auth()->user()` 判定のみ）

### 集約する Service とデータソース

| ロール | Action | 利用 Service・モデル |
|---|---|---|
| **admin** | `FetchAdminDashboardAction` | `EnrollmentStatsService::adminKpi` / `completionRateByCertification`（enrollment 所有） |
| **coach** | `FetchCoachDashboardAction` | `Enrollment.certification.coaches` 経由クエリ / `ChatUnreadCountService::roomCountForUser`（chat 所有）/ `WeaknessAnalysisService::aggregateWeakCategories`（mock-exam 所有）/ `EnrollmentNote`（enrollment 所有） |
| **student**（in_progress） | `FetchStudentDashboardAction` | `ProgressService::summarize`（learning）/ `StreakService::calculate`（learning）/ `LearningHourTargetService::compute`（learning）/ `WeaknessAnalysisService::getPassProbabilityBand` + `getWeakCategories`（mock-exam）/ `CompletionEligibilityService::isEligible`（enrollment）/ `MeetingQuotaService::remaining`（meeting-quota）/ `PlanExpirationService::daysRemaining`（plan-management） |
| **graduated**（v3 新規） | `FetchGraduatedDashboardAction` | `Enrollment.where('status', Passed).with('certification', 'certificate')` のみ |

### ViewModel（readonly DTO）

| クラス | 構成要素 |
|---|---|
| `StudentDashboardViewModel` | `PlanInfoPanel` + `enrollmentCards` + `passedEnrollments` + `streak` + `goalTimeline` + `upcomingMeetings` + `recentNotifications` + `unreadNotificationCount` + `hasNoEnrollment` |
| `PlanInfoPanel` | `planName` + `courseDaysRemaining` + `meetingsRemaining` + `meetingQuotaPlans`（追加面談購入 CTA 用） |
| `StudentEnrollmentCard` | `enrollmentId` + `certificationName` + `status` + `isPassed` + `examDate` + `daysUntilExam` + `progressRatio` + `currentTerm` + `learningHourTarget` + `passProbabilityBand` + `weakCategories` + `canReceiveCertificate` + `certificateDownloadUrl` |
| `CoachDashboardViewModel` | `assignedEnrollments` + `todayAndTomorrowMeetings` + `unreadChatCount` + `recentUnreadChatRooms` + `unansweredQaCount` + `recentQaThreads` + `aggregatedWeakCategories` + `recentEnrollmentNotes` + `recentNotifications` |
| `AdminDashboardViewModel` | `kpi`（learning/passed/failed/by_certification）+ `byCertificationTop10` + `completionRateByCertification` + 通知 |
| `GraduatedDashboardViewModel`（v3 新規） | `graduatedAt`（プラン満了日）+ `passedEnrollments` + `certificateCount` |

**設計判断**:
- **計算 Service を持たない**: 各集計関心事は所有 Feature の Service が担う（product.md「集計責務マトリクス」整合）。dashboard は「集約・整形」のみで、数字の二重計算 / 乖離を構造的に防ぐ
- **`SidebarBadgeComposer` と同一 Service 共有**: REQ-dashboard-005、サイドバーバッジと dashboard 本体の数値整合性を保証
- **graduated 専用 Action / Blade 分岐（v3 新規）**: `users.status === Graduated` で Controller が分岐、プラン機能ロックを明確化（修了証 DL とプロフィールのみの最小 UI）
- **個別 Service 例外境界**(NFR-dashboard-007): 各 Service 呼出を `try/catch` で吸収、一部 Service 失敗時も他パネルは表示
- **撤回された admin 機能**: 修了申請待ち / プラン期限切れ間近 / 滞留検知 / コーチ稼働状況（運用モニタリング MVP 最小限化）

### 適切性評価
- ✅ **必要最低限**: 独自モデル・migration・Service を 1 個も持たない徹底ぶりは正しい。「dashboard は読み取り専用」哲学の完全な反映
- ✅ **最適化**: クエリ計画上限明示（admin 25 / coach 25 / student 20 / graduated 10）、Eager Loading + ViewModel readonly DTO で複数パネルを 1 リクエストで構築
- ⚠️ **改善余地**: `FetchCoachDashboardAction` の `certification.coaches` 経由クエリは `whereHas` の N+1 可能性あり。`Enrollment.certification` を eager load 後の collection filter のほうが安全な場合も。tasks.md で実装時 N+1 検証必須
- ❓ **疑問**: `passProbabilityBand` は学習中（learning）の Enrollment のみ意味があるが、`passed`（復習モード）の Enrollment にも計算するか不明。表示要件次第だが、`isPassed = true` の card では非表示にする UI ルールがあると ViewModel がより小さくできる

---

## 15. ai-chat

**Feature 概要**: 受講生向け Gemini API 連携の学習相談チャット。教材画面のフローティングウィジェット + フル画面 AI 相談画面が同 endpoint を共有、SSE ストリーミング対応。Advance 範囲。

### ai_chat_conversations
会話セッション。Section コンテキスト紐付け（任意）と Enrollment 紐付け（任意）でプロンプトを動的構築。

| カラム | 型 | 役割 |
|---|---|---|
| id | ULID | PK |
| user_id | FK | restrictOnDelete（受講生） |
| enrollment_id | FK nullable | setNullOnDelete（Enrollment 削除時に会話を保持） |
| section_id | FK nullable | setNullOnDelete（Section 削除時に会話を保持） |
| title | string | 会話タイトル（auto-gen or 受講生編集） |
| last_message_at | datetime nullable | 一覧並び順用デノーマライズ |
| timestamps | - | created/updated/deleted_at |

### ai_chat_messages
個別メッセージ。OpenAI 形式の `role` enum（user/assistant）+ ストリーミング状態管理。

| カラム | 型 | 役割 |
|---|---|---|
| id | ULID | PK |
| ai_chat_conversation_id | FK | cascadeOnDelete |
| role | enum | `user`/`assistant`（OpenAI 形式、LangChain / Vercel AI SDK 業界標準） |
| content | text | メッセージ本文（streaming 中は buffer 蓄積） |
| status | enum | `pending`/`streaming`/`completed`/`error`（assistant role のみ意味あり） |
| model | string nullable | 使用 Gemini モデル名（応答後セット） |
| input_tokens / output_tokens | int nullable | usageMetadata 由来、コスト分析用 |
| response_time_ms | int nullable | 応答時間 |
| error_detail | text nullable | エラー時のスタックトレース等 |
| timestamps | - | created/updated_at（SoftDeletes なし、cascade 削除） |

**設計判断**:
- **`role` enum 分離（OpenAI 形式）**: COACHTECH 流 `input/output` ペアより柔軟。LangChain / OpenAI SDK / Vercel AI SDK と整合、受講生の他 LLM API 経験との橋渡しが効く
- **`section_id` nullable FK + `AiChatPromptBuilderService` で本文埋め込み**: Khan Academy Khanmigo / Notion AI の標準パターン。完全 RAG（Embedding + vector）は MySQL ベースの本 PJ で過剰、`section_id` 1 件のみで十分実用的
- **`enrollment_id` / `section_id` `setNullOnDelete`**: 親リソースが削除されても会話履歴は保持（学習ログとして残す）
- **`status` enum 4 値**: 同期版とストリーミング版で状態遷移パスが異なる（同期: pending→completed、SSE: pending→streaming→completed）、エラー時は retry エンドポイントで再生成
- **`(user_id, last_message_at DESC)` 複合 INDEX**: 一覧画面の並び順用
- **`(user_id, section_id)` 複合 INDEX**: フローティングウィジェットの「既存会話再開」検索用（同じ Section に複数会話を立てないための再開判定）
- **`LlmRepositoryInterface` 抽象**: Gemini → OpenAI/Claude 差替を 1 行 binding 切替で可能（Adapter Pattern）

### 適切性評価
- ✅ **必要最低限**: テーブル 2 個で会話管理を成立。Embedding/Vector や Prompt 履歴管理テーブルなど将来機能の足場を作っていない（KISS）
- ✅ **最適化**: `role` enum で OpenAI/業界標準互換、`input_tokens`/`output_tokens`/`response_time_ms` でコスト分析が可能、INDEX 設計が一覧・再開両ユースケースを狙い撃ち
- ⚠️ **改善余地**: SSE 中断時に `content` には部分受信分を保存する設計だが、`status = error` でも `content` を持つ場合の UI 表示ルールが design 内で曖昧。retry 時の元 content の扱い（破棄 or 残置）も Action 内に隠れている
- ❓ **疑問**: `model` カラムを `Notification.id` と同じく ULID にしたほうが将来複数モデル使用時の集計が楽だが、現状は string で十分。コスト分析 UI を作る場合は `model` 別 GROUP BY が必要になる

---

## 16. settings-profile

**Feature 概要**: 全ロールの自己設定画面（プロフィール編集 / パスワード変更 / coach のみ面談可能時間枠設定）。**独自モデル新設なし**、user-management = admin が他者管理 と責務分離。

### 独自モデルなし

本 Feature は **新規モデル / Migration を持たない**。既存モデル（user-management 所有 `User`、mentoring 所有 `CoachAvailability`）の UPDATE / CRUD のみ。

### 関与するカラム（既存テーブルへの参照のみ）

`users`（user-management 所有）への UPDATE:

| カラム | 役割 | 編集権限 |
|---|---|---|
| name | 氏名 | 本人のみ |
| bio | 自己紹介（v3 既存拡張、最大 1000 文字） | 本人のみ |
| avatar_url | アバター URL（Storage public driver） | 本人のみ |
| meeting_url | Google Meet 等の固定 URL | **coach のみ**（student/admin は drop） |
| password | パスワード | Fortify 標準 |

`coach_availabilities`（mentoring 所有）への CRUD:

| カラム | 役割 |
|---|---|
| coach_id | FK to users（本人のみ） |
| day_of_week | 1..7（月〜日） |
| start_time / end_time | H:i 形式 |
| is_active | boolean |

**設計判断**:
- **独自モデル新設なし**: user-management（admin が他者管理）と settings-profile（自己管理）の責務分離を**ルートと Action の責務分離のみで実現**。データ層では追加しない
- **`UserPolicy::updateSelf(auth, target)` 新設**: `$auth->id === $target->id` の単純判定。本 Feature でのみ使用、user-management の `Admin\UserPolicy::update` と分離
- **コーチ専用 `meeting_url` の drop**: `UpdateProfileAction` 内で `$user->role !== Coach` なら `meeting_url` を validated 配列から除外（FormRequest で受け取っても無視）
- **自己退会動線完全撤回（v3）**: `SelfWithdrawController` / `SelfWithdrawAction` / `tab-withdraw.blade.php` 等を一切作らない。退会は admin 依頼経由のみ
- **`EnsureActiveLearning` Middleware 適用しない（v3）**: graduated 受講生も自身のプロフィール管理は可能（product.md L482「プロフィール / 修了証 DL は許可」と整合）
- **通知設定 UI 持たない**: notification 全通知 DB+Mail 固定送信方針と整合、`UserNotificationSetting` テーブルも作らない

### 適切性評価
- ✅ **必要最低限**: 独自モデル 0 個 + Policy 1 メソッド追加のみ。「責務分離はルート + Action で実現、データ層では持たない」哲学が徹底
- ✅ **最適化**: `UpdateAvatarAction` の rollback（Storage 削除 → DB UPDATE 失敗時の新ファイル削除）が transaction 内で完結、旧ファイル削除は best-effort で UX 阻害なし
- ⚠️ **改善余地**: `meeting_url` バリデーション (`url max:500`) は緩い（任意の URL を許容）。Google Meet / Zoom 等の特定ドメイン許可リスト方式にする余地あり、ただし運用柔軟性とのトレードオフ
- ❓ **疑問**: `bio` カラムの最大 1000 文字は仕様確定だが、`users.bio` の DB 型が `text` か `varchar(1000)` か design 内で明示なし。user-management 側の migration を確認したい

---

## 17. plan-management

**Feature 概要**: プラン受講モデルの中核。Plan マスタ CRUD + 受講生への Plan 紐づけ + プラン延長 + 期限満了の自動 `graduated` 遷移 + 履歴管理。価格情報は LMS 内では持たない（初回購入は LMS 外）。

### plans
プランマスタ。`duration_days` + `default_meeting_quota` のセット（例: 1 ヶ月 4 回 / 3 ヶ月 12 回）。

| カラム | 型 | 役割 |
|---|---|---|
| id | ULID | PK |
| name | varchar(100) | 「1ヶ月プラン」等 |
| description | text nullable | - |
| duration_days | unsigned smallint | 1..3650 |
| default_meeting_quota | unsigned smallint | 0..1000 |
| status | enum | `draft`/`published`/`archived` |
| sort_order | unsigned int | 表示順 |
| created_by_user_id / updated_by_user_id | FK restrict | 監査 |
| timestamps | - | created/updated/deleted_at |

**`price` カラムなし**: LMS 内で価格を持たない（決済は LMS 外）。

### users への追加カラム（本 Feature の Migration で追加）

| カラム | 型 | 役割 |
|---|---|---|
| plan_id | FK nullable restrict | 現在のプラン |
| plan_started_at | datetime nullable | プラン開始日 |
| plan_expires_at | datetime nullable | プラン満了日（Schedule Command 判定対象） |
| max_meetings | unsigned smallint default 0 | 累計面談付与回数（初期 + 延長合算） |

`(plan_id)` INDEX、`(status, plan_expires_at)` 複合 INDEX（Schedule Command の期限切れ判定用）。

### user_plan_logs（INSERT only 履歴）

| カラム | 型 | 役割 |
|---|---|---|
| id | ULID | PK |
| user_id | FK restrict | - |
| plan_id | FK restrict | - |
| event_type | enum | `assigned`/`renewed`/`canceled`/`expired` |
| plan_started_at | datetime | スナップショット |
| plan_expires_at | datetime | スナップショット |
| meeting_quota_initial | unsigned smallint | 付与時の `default_meeting_quota` 値 |
| changed_by_user_id | FK nullable | NULL = システム自動（Schedule Command 由来） |
| changed_reason | varchar(200) nullable | 監査用 |
| occurred_at | datetime | イベント発生時刻 |
| timestamps | - | SoftDeletes 不採用 |

### UserStatus enum 拡張（v3、本 Feature の Migration で実施）

| 値 | 説明 |
|---|---|
| `invited` | 招待中 |
| `in_progress`（旧 `active` を rename） | 受講中（プラン期間内、機能フル利用可） |
| `graduated`（新規） | 卒業（プラン満了、修了証 DL + プロフィールのみ可） |
| `withdrawn` | 退会 |

3 ステップ Migration: enum 拡張 5 値 → データ移行 (`active` → `in_progress`) → `active` 削除。

**設計判断**:
- **`price` を持たない**: LMS 内決済は追加面談購入のみ（meeting-quota の `MeetingQuotaPlan` が `price` を持つ）。初回プラン購入は LMS 外（公式サイト）で完結
- **`user_plan_logs` を INSERT only**: 履歴の改竄を防ぐ監査ログ方式（iField LMS 流）、SoftDeletes 不採用
- **`changed_by_user_id` nullable**: Schedule Command 由来は NULL、admin 操作由来は admin user_id を記録
- **`UserStatus` enum 拡張を本 Feature が同梱**: D-2 Blocker 解消、auth Step 2 が前提とする状態を plan-management が確立する依存順序を確定
- **`max_meetings` を `users` に持つ理由**: 監査ログから累計集計するより `User.max_meetings + SUM(meeting_quota_transactions.amount)` の 2 ソース合算（meeting-quota 設計）のほうが残数算出クエリが速い
- **Schedule Command `users:graduate-expired`**: 00:45 起動、auth の `invitations:expire`（00:30）と時刻ずらし + `withoutOverlapping(5)`

### 適切性評価
- ✅ **必要最低限**: Plan マスタ 1 個 + 履歴 1 個 + User カラム 4 個追加 + enum 拡張のみ。事業モデル全体の根幹に対して最小構成
- ✅ **最適化**: `(status, plan_expires_at)` 複合 INDEX で日次 Schedule Command の対象抽出が効率的、`user_plan_logs` の `(user_id, occurred_at)` で時系列クエリ高速化、Migration の 3 ステップで既存データの安全な enum 移行
- ⚠️ **改善余地**: `User.max_meetings` を持つことで「`MeetingQuotaTransaction.granted_initial` と二重カウントしない」運用ルールが複雑化（meeting-quota 設計に明文化済）。これは確かにクエリ高速化と引き換えのトレードオフだが、運用ミスのリスクあり
- ❓ **疑問**: `plans.deleted_at` INDEX を持つが、`PlanNotDeletableException`（published/archived の DELETE 拒否）の運用と組み合わせると、SoftDelete されるのは draft のみ。SoftDelete 実用シーンが限定的

---

## 18. meeting-quota

**Feature 概要**: 面談回数の付与・消費・追加購入機能。`User.max_meetings`（初期付与）+ `MeetingQuotaPlan` マスタ（SKU）+ `MeetingQuotaTransaction`（INSERT only 監査ログ）+ `Payment`（Stripe 決済）の 4 系統で構成。

### meeting_quota_plans
追加面談購入用の SKU マスタ（admin CRUD）。`price` を **LMS 内に持つ唯一のテーブル**（Stripe 決済 SKU として）。

| カラム | 型 | 役割 |
|---|---|---|
| id | ULID | PK |
| name | varchar(100) | 「5回パック」等 |
| description | text nullable | - |
| meeting_count | unsigned smallint | 1..100 |
| price | unsigned int | 円、Stripe SKU |
| stripe_price_id | varchar(255) nullable | 将来 Stripe Price 連携用 |
| status | enum | `draft`/`published`/`archived` |
| sort_order | unsigned int | - |
| created_by/updated_by | FK restrict | 監査 |
| timestamps | - | SoftDeletes 採用 |

### meeting_quota_transactions（INSERT only 監査ログ、SoftDeletes 不採用）

| カラム | 型 | 役割 |
|---|---|---|
| id | ULID | PK |
| user_id | FK restrict | 対象 |
| type | enum | `granted_initial`/`purchased`/`consumed`/`refunded`/`admin_grant` |
| amount | int signed | 消費は -1、その他は +N |
| related_meeting_id | FK nullable | consumed/refunded で対応する Meeting |
| related_payment_id | FK nullable | purchased で対応する Payment |
| granted_by_user_id | FK nullable | admin_grant 時必須、システム自動は NULL |
| note | varchar(500) nullable | admin_grant の reason 等 |
| occurred_at | datetime | イベント時刻 |
| timestamps | - | - |

`(user_id, occurred_at)` / `(related_meeting_id)` / `(related_payment_id)` / `(type)` 各 INDEX。

### payments
Stripe 決済レコード。

| カラム | 型 | 役割 |
|---|---|---|
| id | ULID | PK |
| user_id | FK | - |
| type | enum | `extra_meeting_quota`（将来拡張用 enum） |
| meeting_quota_plan_id | FK restrict | 購入 SKU |
| stripe_payment_intent_id | varchar(255) UNIQUE nullable | succeeded 後にセット |
| stripe_checkout_session_id | varchar(255) UNIQUE | Checkout Session 作成時にセット（冪等性キー） |
| amount | unsigned int | 購入時 `price` スナップショット |
| quantity | unsigned smallint | 購入時 `meeting_count` スナップショット |
| status | enum | `pending`/`succeeded`/`failed`/`refunded` |
| paid_at | datetime nullable | 決済完了時刻 |
| timestamps | - | SoftDeletes |

**設計判断**:
- **`MeetingQuotaTransaction` を INSERT only**: iField LMS 流の監査ログ方式。改竄不能 + 履歴閲覧用 UI が単純に時系列表示
- **`amount` signed int**: 消費は -1、付与は +N で同一カラムに統一。残数集計は単純な `SUM(amount)` で表現可能
- **`User.max_meetings` と `granted_initial` の二重カウント回避**: 残数計算式から `granted_initial` を除外（`max_meetings` カラムが累計を保持）。`granted_initial` は監査ログとしてのみ存在
- **`payments.stripe_checkout_session_id` UNIQUE**: Stripe Webhook の冪等性ガード用キー。`HandleStripeWebhookAction` の 5 ステップで `lockForUpdate` + 既 succeeded skip 判定
- **`payments` に `amount`/`quantity` スナップショット**: `meeting_quota_plans` の `price`/`meeting_count` を将来変更しても、過去購入の金額・回数は不変
- **`type` enum で将来拡張余地**: `extra_meeting_quota` のみだが、将来「教材買切」「コース延長」等が増える前提のスキーマ設計
- **`related_meeting_id` + `related_payment_id` nullable**: type ごとに NOT NULL が変わるが、Schema レベルでは nullable + Action でガード（CHECK 制約は MySQL 互換性で複雑化するため避けた）

### 適切性評価
- ✅ **必要最低限**: 3 テーブルで「SKU + 監査ログ + 決済」を成立。サブスクリプション、返金管理、複数通貨等の足場は持たず、教育PJスコープに収まる
- ✅ **最適化**: `(user_id, occurred_at)` INDEX で残数集計と履歴一覧両方を高速化、`stripe_checkout_session_id` UNIQUE で Webhook 冪等性、Service の `history()` メソッドが Eager Loading 完備（`relatedMeeting.enrollment.certification`/`relatedPayment.meetingQuotaPlan`/`grantedBy`）
- ⚠️ **改善余地**: `MeetingQuotaTransaction.type` ごとに NOT NULL が変わる nullable FK 群（`related_meeting_id` / `related_payment_id` / `granted_by_user_id`）は、Action 側のガードに依存する Eventually consistent な状態。MySQL 8 の CHECK 制約で型 × FK の組合せを宣言できると安全度が上がる
- ❓ **疑問**: `payments.type` enum が `extra_meeting_quota` 1 値のみ（将来拡張前提）だが、現状値が1個しかないなら enum でなくとも boolean / string でも代替可能。「将来拡張」が設計コメントレベルなら enum 化はトレードオフ判断

---

# Part 3: 全 18 Feature 横断の観察

## スナップショット哲学(2026-05-16 独立セクション化、Pro 生レベルの中核設計原則)

> **定義**: マスタ(Plan / Option / MockExam 設定 / コーチプロフィール / SKU 価格等)が将来変更・削除されても、**過去の履歴 / 取引 / 履歴表示が「そのとき表示した値」のまま再現できる**ように、トランザクションテーブル側に値を **焼き込む**(snapshot) 設計原則。Certify LMS では 18 Feature を横断する中核設計判断として一貫採用。

### 採用基準

以下のいずれかを満たす場合に snapshot 列を採用する:

1. **過去履歴で UI 表示が必要**: 解答履歴 / 面談履歴 / 決済履歴 などで「その時何だったか」を表示する
2. **マスタが将来変更されうる**: コーチが Zoom URL を変えたり、admin が MockExam の合格点を改定したりする可能性がある
3. **マスタが削除されうる**: Option が物理削除されても解答履歴を残したい等
4. **監査で「その時点」を読みたい**: 払い戻し / クレーム対応で「当時の金額・条件」を確認する

### 採用しない判断

以下の場合は snapshot を持たず、マスタの現在値を都度参照する:

1. **マスタが不変**: ULID 主キーや変更不可な定数(`UserRole` / `ContentStatus` enum 等)
2. **計算で再現可能**: `Enrollment.exam_date` から残日数を都度計算する等(残日数を snapshot する必要なし)
3. **UI でマスタ最新値を表示したい**: 例 `User.name` は履歴に snapshot せず、現在の表示名で過去履歴に出る(個人情報の最新性優先)

### 採用箇所一覧(2026-05-16 棚卸し)

| Feature | カラム / テーブル | 焼き込む値 | 焼き込む理由 |
|---|---|---|---|
| [[quiz-answering]] | `section_question_answers.selected_option_body` | Option 本文(最大 2000 文字) | Option 編集 / 物理削除後も「自分が何を選んだか」を解答履歴 UI で表示 |
| [[mock-exam]] | `mock_exam_sessions.passing_score_snapshot` | 受験開始時の MockExam.passing_score | MockExam の合格点改定後も、過去の受験セッションの合否判定を固定 |
| [[mock-exam]] | `mock_exam_sessions.generated_question_ids`(JSON) | 受験開始時の問題セット ULID 配列 | MockExamQuestion 改定後も、過去の受験は同じ問題で再表示可能 |
| [[mock-exam]] | `mock_exam_answers.selected_option_body` | Option 本文 | Option 編集 / 削除後も解答履歴を表示 |
| [[mentoring]] | `meetings.meeting_url_snapshot` | 予約時のコーチ Zoom URL | コーチが URL を変えても、過去の面談 URL を改竄しない / 受講生が当時の URL を確認可能 |
| [[mentoring]] | `meeting_memos` の関連 | 面談時のコーチ name / 受講生 name 等を snapshot する場合あり | 退会 / リネーム後も面談記録が読める |
| [[plan-management]] | `user_plan_logs.plan_started_at` / `plan_expires_at` / `meeting_quota_initial` | Plan 付与 / 延長 / 期限満了時の値 | Plan マスタの `duration_days` / `default_meeting_quota` 改定後も、過去のプラン付与履歴は当時の値を保持 |
| [[meeting-quota]] | `payments.amount` / `quantity` | 決済時の SKU 価格 / 数量 | MeetingQuotaPlan の `price` 改定後も、過去の決済記録は当時の金額(払い戻し時の正確性) |
| [[meeting-quota]] | `meeting_quota_transactions.amount` | 取引時の付与 / 消費数量 | SKU 改定後も取引履歴の数量を固定 |
| [[enrollment]] | `enrollment_status_logs.from_status` / `to_status` | 状態遷移前後の Enrollment.status | append-only 監査ログ。現状の `enrollments.status` が SSoT、本ログは履歴 |
| [[user-management]] | `user_status_logs.from_status` / `to_status` / `event_type`(2026-05-16 追加) | 状態遷移前後の User.status + event 分類 | append-only 監査ログ、`UserPlanLog` とフォーマット統一 |
| [[notification]] | `notifications.data`(JSON) | 通知発火時の関連エンティティ情報 | 関連エンティティが削除されても通知本文は読める(Laravel 標準パターン) |
| [[chat]] | `chat_messages.body` / `chat_messages.sender_name`(あれば) | 送信時のメッセージ本文 / 送信者名 | 送信者退会後もメッセージが残る(本文は元々不変だが、送信者名の snapshot 採否は要検討) |

### 採用しない判断箇所(明示)

| 箇所 | 採用しない理由 |
|---|---|
| `User.name` / `User.avatar_url` の履歴 snapshot | 個人情報の最新性優先(リネーム / アバター変更を過去 UI にも反映)。退会 / SoftDelete 後の表示は `User::withTrashed()` で対応 |
| `Enrollment.exam_date` の snapshot | 受講生が試験日を変更すれば過去計算も追従する仕様(snapshot 不要、UI でも「現在の試験日」を表示) |
| `LearningSession` の Section 名 snapshot | Section リネーム後も最新名で履歴表示する(教材コンテンツの最新性優先) |
| `SectionProgress` の Section 名 snapshot | 同上 |
| `MockExam.title` の snapshot in `mock_exam_sessions` | MockExam リネーム後も最新タイトルを履歴で表示する(現状不採用、必要なら将来追加) |

### Pro 生レベルの判断軸

snapshot 採用判断は「UX 上 そのとき表示したい か / 最新を表示したい か」の 2 択。Pro 生候補は以下を即答できる必要がある:

- 「過去の選択肢本文を表示するなら snapshot」「過去の試験日を表示するなら都度参照」
- 「金額系は必ず snapshot」「個人プロフィール系は基本 snapshot しない」
- 「マスタが物理削除されうるなら snapshot」「SoftDelete で十分なら snapshot 不要」

これを spec / コードレビュー時に瞬時に判断できるよう、本セクションを Pro 生候補の学習目標として明文化する。

---

## 設計パターンの一貫性

| 観点 | 評価 | 該当箇所 |
|---|---|---|
| **スナップショット哲学** | ✅ 一貫採用、独立セクション化(上記参照) | 採用箇所一覧表参照 |
| **UNIQUE 制約による DB 級ガード** | ✅ 徹底 | `(coach_id, scheduled_at)` / `(user_id, certification_id)` / `(user_id, section_question_id)` / `enrollment_id (UNIQUE)` for Certificate / `(mock_exam_session_id, mock_exam_question_id)` / `(chat_room_id, user_id)` / `stripe_checkout_session_id` 等、競合状態を DB 制約で防御 |
| **append-only 監査ログ vs 状態テーブル** | ✅ 一貫採用 | `UserStatusLog` / `EnrollmentStatusLog` / `UserPlanLog` / `MeetingQuotaTransaction` 等の履歴系は INSERT only、現在状態は `users.status` / `enrollments.status` 等の Snapshot 列。読み取り頻度の高い「現在値」と監査用「履歴」の分離 |
| **共有マスタ + N:M 設計** | ✅ 適切 | `QuestionCategory`（SectionQuestion + MockExamQuestion 両参照）/ `CertificationCoachAssignment`（資格 × コーチ N:M）。データの正規化と再利用性の両立 |
| **v3 改修で削除された冗長性** | ✅ 引き締まり | `Certification` 5 カラム削減 / `Question.section_id nullable` 撤回 → `SectionQuestion`/`MockExamQuestion` 分離 / `Enrollment.assigned_coach_id` 撤回 / `Meeting.status` 6 値 → 3 値 / `mock_exams.time_limit_*` 撤回 / `StagnationDetectionService` 撤回 / chat 添付撤回 / settings-profile 自己退会撤回。**「持たない」判断が明確** |
| **責務マトリクス管理** | ✅ 明示 | 集計 Service の所有 Feature が product.md で明示され、各 Feature がどこから何を借りるかが追跡可能。`WeaknessAnalysisServiceContract` の DI Interface 設計 + `NullObject` fallback は特に光る |
| **概念混同回避** | ✅ DB に物理表現 | product.md で警告される「修了 vs 目標受験日」「status vs current_term」「3 種類の目標」「プラン期間満了 vs 資格修了」がすべて別カラム / 別テーブル / 別 Enum で物理分離 |
| **ULID 一貫採用** | ✅ 全テーブル | PK が ULID。Notification 標準テーブルにも `BaseNotification::__construct` で ULID を強制セット |
| **`SoftDeletes` の使い分け方針** | ✅ 一貫 | 監査・履歴系（`user_plan_logs` / `meeting_quota_transactions`）は INSERT only で SoftDelete 不採用、ユーザー操作系（`chat_messages` / `qa_threads` / `enrollments`）は SoftDelete 採用 |
| **`restrictOnDelete` 優先** | ✅ 安全側 | ほぼ全 FK が `restrictOnDelete`、`setNullOnDelete` は ai-chat の `enrollment_id` / `section_id` のような「親が削除されても子データを保持したい」場面のみ。`cascadeOnDelete` は `chat_members.chat_room_id` / `ai_chat_messages.ai_chat_conversation_id` のような明確な親子関係に限定 |

## 共通の改善余地

| 項目 | 該当 Feature | 推奨 |
|---|---|---|
| **CHECK 制約の不在** | notification（AdminAnnouncement.target_*） / meeting-quota（MeetingQuotaTransaction.related_*） | MySQL 8 の CHECK 制約活用余地あり、ただし MySQL 互換性とのトレードオフ |
| **JSON path の検索効率** | notification（`NotifyMeetingReminderAction` の重複検査） | リマインダ件数規模次第でパフォーマンス影響、`meeting_reminder_logs` 補助テーブル案を検討 |
| **`LastActivityService` の所有 Feature 確定** | analytics-export | 集計責務マトリクス整合的には learning に所有させるべき |
| **冗長 event_type / カラム** | ✅ **対応済**(2026-05-16): UserStatusLog.event_type 追加で UserPlanLog とフォーマット統一 / learning/design.md に `duration_seconds` 冗長性正当化(集計クエリ高速化・clamp 物理表現・open セッション識別の 3 理由)を明記 / quiz-answering Attempt.deleted_at は P3-1 で別途撤回検討 | — |
| **denormalize カラムの正当化** | ✅ **対応済**(2026-05-16、learning にプロトタイプ反映): learning/design.md の「`LearningSession.user_id` denormalize の正当化」セクションに INDEX 利用クエリパターン明記(StreakService 横断集計 / dashboard 総学習時間 / Schedule Command 残骸抽出 + `(user_id, started_at)` 複合 INDEX 設計)。mock-exam(`mock_exam_sessions.user_id`)・mentoring(`meetings.student_id`)も同じ判断軸で正当化される(複数 Enrollment 跨ぎの集計 + 複合 INDEX 利用)、必要に応じて各 design.md で同パターンを参照 | — |
| **author / created_by 監査カラム不在** | mentoring（MeetingMemo.created_by_user_id 不在） | 将来拡張時の identification 困難リスク、design.md に追記推奨 |

## 全体評価

- **適切性**: ✅ product.md の UX 概念（プラン期間満了 vs 資格修了 / グループチャット / 通知種別 8 個 / 個人別既読 / 残面談回数の出入り / 概念混同回避指針）がすべて DB スキーマに表現されている
- **必要最低限性**: ✅ dashboard / settings-profile が独自モデルを 0 個に抑え、analytics-export が独自テーブルを持たないなど、責務分離をデータ層ではなくルート + Action 層で実現する徹底ぶり。v3 改修で過剰抽象を多数撤回しており「持たない」判断が綺麗
- **最適化**: ✅ INDEX 設計が各 Feature の主要クエリ経路を狙い撃ち、`additional(['_batch' => ...])` パターンで N+1 完全回避、`stripe_checkout_session_id` UNIQUE で Webhook 冪等性等、業界標準パターンが網羅
- **概念の独立性**: ✅ プラン期間（`User.plan_expires_at`）vs 資格修了（`Enrollment.passed_at`）/ グループチャット（`ChatMember` 中間テーブル）/ 8 通知種別の明示列挙 / `granted_initial` と `max_meetings` の二重カウント回避ルール等が DB に正しく表現
- **集計責務マトリクス整合**: ✅ dashboard / notification / analytics-export はいずれも「他 Feature の Service を DI 消費」モデルが design.md に明文化、独自計算ロジックを持たないことが確認できる

**総評**: v3 改修によりスキーマの過剰抽象が大幅に削減され、「実装する / しない」の線引きが明示的になっている。データモデルは UX 要件カバレッジを確保しつつ、必要最低限を維持。スナップショット哲学と UNIQUE 制約による堅牢性、append-only 監査と現在値スナップショットの分離設計が一貫しており、Pro 級の設計。改善余地は軽微な冗長カラム（denormalize の正当性、event_type の固定値化、author カラム不在）に留まり、致命的な構造問題は見当たらない。
