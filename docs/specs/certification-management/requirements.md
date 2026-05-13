# certification-management 要件定義

## 概要

資格マスタとその周辺リソースを admin が管理し、受講生にカタログ提示・修了証発行を行う Feature。Certify LMS のドメイン中核である「資格」エンティティと、合格時の修了証（Certificate + PDF）を所有する。

主体ロールは admin（資格マスタ CRUD / 公開状態管理 / 担当コーチ割当）と student（公開資格カタログ閲覧 / 自分の修了証ダウンロード）。coach は本 Feature の Controller を持たず、`Certification::scopeAssignedTo()` を通じて [[content-management]] / [[dashboard]] から間接利用する。修了認定の判定・承認フロー本体は [[enrollment]] が所有し、本 Feature は判定済の Enrollment を受けて修了証を発行する責務に専念する。

## ロールごとのストーリー

- 管理者（admin）: 資格マスタ（コード / 名称 / 分類 / 難易度 / 合格点 / 試験時間 / 総問題数）を起こし、`draft` で準備、`published` で公開、不要になれば `archived` する。資格ごとに担当コーチを割当・解除する。修了申請承認時に、自動的に Certificate が発行される（[[enrollment]] の `ApproveCompletionAction` から本 Feature の `IssueCertificateAction` が呼ばれる）。資格分類のマスタも追加・編集・削除する。
- 受講生（student）: 公開済資格のカタログ一覧を見て、興味のある資格の詳細を確認する。受講中の資格は別タブで区別表示される。修了認定後は達成画面で修了証を閲覧し、PDF 形式でダウンロードする。
- コーチ（coach）: 本 Feature の Controller は持たないが、[[content-management]] / [[dashboard]] から `Certification::scopeAssignedTo($coach)` を通じて担当資格のみを取得する。本 Feature の UI で資格カタログ詳細画面を閲覧することは可能（公開済資格は誰でも閲覧可）。

## 受け入れ基準（EARS形式）

### 機能要件 — 資格マスタ（Certification）の admin CRUD

- **REQ-certification-management-001**: The system shall ULID 主キー / `code` UNIQUE / `category_id` FK / `name` / `slug` / `description` / `difficulty` / `passing_score` / `total_questions` / `exam_duration_minutes` / `status` / `created_by_user_id` / `updated_by_user_id` / `published_at` / `archived_at` / `created_at` / `updated_at` / `deleted_at` を備えた `certifications` テーブルを提供する。
- **REQ-certification-management-002**: The system shall `certifications.status` を `draft` / `published` / `archived` の 3 値で表現し、PHP backed enum `CertificationStatus`（`label()` 日本語ラベル付き）で管理する。
- **REQ-certification-management-003**: The system shall `certifications.difficulty` を `beginner` / `intermediate` / `advanced` / `expert` の 4 値の PHP backed enum `CertificationDifficulty`（`label()` 付き）で表現する。
- **REQ-certification-management-010**: When admin が資格一覧画面（`/admin/certifications`）にアクセスした際, the system shall フィルタ（`status` / `category_id` / `difficulty` / `keyword`）+ ページネーション（1 ページ 20 件）付きで Certification を一覧表示する。
- **REQ-certification-management-011**: While admin が資格一覧画面で `keyword` を指定している間, the system shall `certifications.code` または `certifications.name` の部分一致検索（LIKE `%keyword%`）を SQL レベルで実行する。
- **REQ-certification-management-012**: When admin が新規作成フォームに資格マスタ項目を入力して送信した際, the system shall FormRequest でバリデーションを通過した値を `status=draft` / `created_by_user_id=admin` 固定で `certifications` に INSERT する。
- **REQ-certification-management-013**: If admin が `certifications.code` を既存資格と同一の値で送信した場合, then the system shall FormRequest の `unique:certifications,code` 違反としてバリデーションエラー（422）を返す。
- **REQ-certification-management-014**: When admin が編集フォームから資格マスタを更新した際, the system shall `name` / `slug` / `description` / `category_id` / `difficulty` / `passing_score` / `total_questions` / `exam_duration_minutes` / `code` を UPDATE する。ただし `status` は本エンドポイントで変更できない（公開状態遷移用エンドポイント REQ-certification-management-020〜023 から行う）。
- **REQ-certification-management-015**: If admin が `published` または `archived` 状態の Certification に対して DELETE を要求した場合, then the system shall HTTP 409 Conflict で `CertificationNotDeletableException` を返す（既存 Enrollment や Certificate の参照整合性を守るため、draft のみ削除可）。
- **REQ-certification-management-016**: When admin が `draft` 状態の Certification に対して DELETE を要求した際, the system shall SoftDeletes で `deleted_at` をセットして論理削除する（物理削除は行わない）。
- **REQ-certification-management-017**: If admin が `passing_score` を `0` 以下または `100` 超で送信した場合, then the system shall FormRequest のバリデーションエラー（422）を返す（合格点は 0 < x <= 100 のパーセンテージ）。

### 機能要件 — Certification 公開状態遷移

- **REQ-certification-management-020**: When admin が `draft` 状態の Certification を `publish` 操作した際, the system shall `status=published` / `published_at=now()` を UPDATE する。
- **REQ-certification-management-021**: If admin が `draft` 以外の状態の Certification に `publish` 操作を要求した場合, then the system shall HTTP 409 Conflict で `CertificationInvalidTransitionException` を返す。
- **REQ-certification-management-022**: When admin が `published` 状態の Certification を `archive` 操作した際, the system shall `status=archived` / `archived_at=now()` を UPDATE する。
- **REQ-certification-management-023**: When admin が `archived` 状態の Certification を `unarchive` 操作した際, the system shall `status=draft` / `archived_at=null` / `published_at=null` を UPDATE する（再下書き化、再公開は別途 publish 操作が必要）。

### 機能要件 — CertificationCategory（資格分類マスタ）

- **REQ-certification-management-030**: The system shall ULID 主キー / `slug` UNIQUE / `name` / `sort_order` / `created_at` / `updated_at` / `deleted_at` を備えた `certification_categories` テーブルを提供する。
- **REQ-certification-management-031**: When admin が資格分類一覧画面（`/admin/certification-categories`）にアクセスした際, the system shall `sort_order ASC, created_at DESC` 並びで CertificationCategory を一覧表示する。
- **REQ-certification-management-032**: When admin が資格分類を新規作成・更新・削除した際, the system shall FormRequest を通したうえで `certification_categories` を INSERT / UPDATE / SoftDelete する。
- **REQ-certification-management-033**: If admin が他の Certification から参照中の CertificationCategory に対して DELETE を要求した場合, then the system shall HTTP 409 Conflict で `CertificationCategoryInUseException` を返す（参照整合性を守るため、参照ゼロ件のみ削除可）。

### 機能要件 — CertificationCoachAssignment（担当コーチ割当）

- **REQ-certification-management-040**: The system shall ULID 主キー / `certification_id` FK / `coach_user_id` FK / `assigned_by_user_id` FK / `assigned_at` / `created_at` / `updated_at` を備えた `certification_coach_assignments` テーブルを提供する。
- **REQ-certification-management-041**: The system shall `(certification_id, coach_user_id)` の組み合わせに対して UNIQUE 制約を設け、同一資格への同一コーチの重複割当を禁止する。
- **REQ-certification-management-042**: When admin が資格詳細画面から担当コーチを追加した際, the system shall 対象 User の `role === coach` を検証したうえで `certification_coach_assignments` に INSERT し、`assigned_by_user_id=admin` / `assigned_at=now()` を記録する。
- **REQ-certification-management-043**: If admin が `role !== coach` の User を担当コーチとして指定した場合, then the system shall HTTP 422 Unprocessable Entity で `NotCoachUserException` を返す。
- **REQ-certification-management-044**: When admin が資格詳細画面から既存の担当コーチを解除した際, the system shall 対応する `certification_coach_assignments` レコードを DELETE する（物理削除でよい、履歴は不要）。
- **REQ-certification-management-045**: The system shall `Certification` に `coaches()` BelongsToMany リレーション（`certification_coach_assignments` 経由）+ `User` に `assignedCertifications()` BelongsToMany リレーション + `Certification::scopeAssignedTo(User $coach)` スコープを提供し、[[content-management]] / [[dashboard]] から再利用可能にする。

### 機能要件 — 受講生カタログ（CertificationCatalog）

- **REQ-certification-management-050**: When 受講生が `/certifications` にアクセスした際, the system shall `status=published` の Certification のみを一覧表示する（`draft` / `archived` / `deleted_at != null` を除外）。
- **REQ-certification-management-051**: While 受講生が一覧画面で「受講中タブ」を選択している間, the system shall 当該受講生の `Enrollment.status IN ('learning','paused','passed','failed')` に紐付く Certification のみをカテゴリ別に表示する。
- **REQ-certification-management-052**: While 受講生が一覧画面で「カタログタブ」を選択している間, the system shall `status=published` のすべての Certification（受講中含む）を表示し、受講中の資格にはバッジを付ける。
- **REQ-certification-management-053**: The system shall カタログ一覧でカテゴリ別フィルタ・難易度フィルタを提供する。**キーワード検索は採用しない**（Certify LMS の資格マスタは数十件規模を想定、フィルタで十分に絞れる）。
- **REQ-certification-management-054**: When 受講生または admin が `/certifications/{certification}` にアクセスした際, the system shall 当該 Certification の詳細（名称 / 分類 / 難易度 / 合格点 / 試験時間 / 総問題数 / 担当コーチ一覧）を表示する。
- **REQ-certification-management-055**: If 公開済でない（`status != published`）または SoftDelete 済の Certification を受講生が直接 URL で指定した場合, then the system shall HTTP 404 Not Found で `CertificationNotFoundException` を返す（admin は閲覧可、受講生のみ 404）。

### 機能要件 — Certificate（修了証）発行

- **REQ-certification-management-060**: The system shall ULID 主キー / `user_id` FK / `enrollment_id` FK UNIQUE / `certification_id` FK / `serial_no` UNIQUE / `issued_at` / `pdf_path` / `issued_by_user_id` FK / `created_at` / `updated_at` / `deleted_at` を備えた `certificates` テーブルを提供する。
- **REQ-certification-management-061**: The system shall `certificates.enrollment_id` に UNIQUE 制約を設け、1 Enrollment あたり最大 1 Certificate であることを保証する（修了証の二重発行を禁止）。
- **REQ-certification-management-062**: When [[enrollment]] の `ApproveCompletionAction` が本 Feature の `IssueCertificateAction` を呼出した際, the system shall (1) 対象 Enrollment が `status=passed` + `passed_at != null` であることを検証し、(2) `serial_no` を `CertificateSerialNumberService` で年月 + 連番形式（例: `CT-202605-00001`）で採番し、(3) `pdf_path` を `storage/app/private/certificates/{ulid}.pdf` 形式で予約し、(4) `certificates` テーブルに INSERT し、(5) Blade テンプレート `certificates/pdf.blade.php` を `barryvdh/laravel-dompdf` で同期 PDF 化して当該パスに保存する。すべて `DB::transaction()` 内で実行する。
- **REQ-certification-management-063**: If `IssueCertificateAction` が同一 Enrollment に対して 2 回呼ばれた場合, then the system shall 2 回目以降は既存 Certificate を返却し、Certificate INSERT も PDF 再生成も行わない（冪等性保証、`certificates.enrollment_id` UNIQUE 制約と組み合わせる）。
- **REQ-certification-management-064**: The system shall `CertificateSerialNumberService` で `CT-{YYYYMM}-{NNNNN}` 形式（NNNNN は当月内連番、5 桁 0 埋め）の `serial_no` を採番する。当月内の最大連番取得は `SELECT MAX(serial_no)` で `FOR UPDATE` ロックして競合を防ぐ。
- **REQ-certification-management-065**: When 受講生または admin が `/certificates/{certificate}` にアクセスした際, the system shall Blade テンプレート `certificates/show.blade.php` で達成画面（受講生名 / 資格名 / 合格点 / 発行日 / `serial_no` / PDF ダウンロードボタン）を表示する。
- **REQ-certification-management-066**: When 受講生または admin が `/certificates/{certificate}/download` を要求した際, the system shall `CertificatePolicy::download` で当事者（`certificate.user_id === auth()->user()->id`）または admin であることを検証し、Storage private driver から `pdf_path` のファイルを `application/pdf` で配信する。
- **REQ-certification-management-067**: If 当事者でも admin でもないユーザーが他者の修了証 PDF にアクセスした場合, then the system shall HTTP 403 Forbidden を返す（Policy 違反、enumeration を避けるため URL 自体は valid に見える）。
- **REQ-certification-management-068**: The system shall 修了証 PDF（`certificates/pdf.blade.php`）に以下の **8 要素** を含む。**固定文言**: (1) タイトル「修了証」(2) 証書定型文「上記の者は、本資格の所定の課程を修了したことを証する」(3) 発行元「Certify LMS」。**変数**: (4) 受講生氏名（`$certificate->user->name`）(5) 資格名（`$certificate->certification->name`）(6) 資格コード（`$certificate->certification->code`、小書き）(7) 発行日（`$certificate->issued_at`、西暦表記）(8) 証書番号（`$certificate->serial_no`）。+α 要素（QR コード / 担当コーチ名 / 試験日 / 和暦 / 印章 / 装飾デザイン）は **採用しない**（教育PJスコープに合致する最小構成）。

### 非機能要件

- **NFR-certification-management-001**: The system shall すべての状態変更を伴う Action（`IssueCertificateAction` / 公開状態遷移 / コーチ割当 等）を `DB::transaction()` 内で実行する。
- **NFR-certification-management-002**: The system shall `certifications` テーブルに `(status, category_id)` 複合 INDEX と `(deleted_at)` 単体 INDEX を設定し、受講生カタログ一覧の絞り込みクエリを高速化する。
- **NFR-certification-management-003**: The system shall `certificates.serial_no` に UNIQUE INDEX、`certificates.user_id` に単体 INDEX、`certificates.enrollment_id` に UNIQUE INDEX を設定する。
- **NFR-certification-management-004**: The system shall ドメイン例外を `app/Exceptions/Certification/` 配下にクラスとして定義し、汎用 `\Exception` は使わない（`backend-exceptions.md` 準拠）。
- **NFR-certification-management-005**: The system shall 修了証 PDF を Laravel Storage の private driver に保存し、Web から直接配信されないようにする。配信は `CertificateController::download` 経由でのみ可能とする。
- **NFR-certification-management-006**: The system shall 修了証 PDF の Blade テンプレート（`certificates/pdf.blade.php`）を共通レイアウト（Wave 0b で確立予定）から独立させ、`dompdf` で正しくレンダリングできる軽量な HTML/CSS で記述する（外部 CSS / JS / Tailwind ランタイム依存なし、インラインスタイルか `<style>` ブロック）。
- **NFR-certification-management-007**: The system shall すべての admin 操作ルートに `auth + role:admin` Middleware を、受講生カタログには `auth` Middleware を、Certificate 配信には `auth` + `CertificatePolicy` を適用する。

## スコープ外

- 修了申請ボタンの活性判定（`CompletionEligibilityService`）— [[enrollment]] 所有
- 修了申請の受付（`completion_requested_at` セット）— [[enrollment]] 所有
- admin の修了申請承認画面（一覧 / 承認ボタン）— [[enrollment]] / [[dashboard]] 所有
- 修了通知メール / 修了通知 DB 通知の送信 — [[notification]] 所有（本 Feature は Certificate INSERT のみ、Notification dispatch は呼出元の [[enrollment]] `ApproveCompletionAction` 側責務）
- coach 専用 UI（担当資格カタログ一覧画面）— [[content-management]] / [[dashboard]] 所有
- 教材内コンテンツ（Part / Chapter / Section / Question）の CRUD — [[content-management]] 所有
- 受講登録（Enrollment 作成）— [[enrollment]] 所有
- 公開模試マスタ（MockExam）の所属資格との紐付け実装は [[mock-exam]] 側で `belongsTo(Certification::class)` を張る。本 Feature では Certification 側に `mockExams()` リレーション（`hasMany`）の宣言提供のみ。

## 関連 Feature

- **依存元**（本 Feature を利用する）:
  - [[enrollment]] — `ApproveCompletionAction` から `IssueCertificateAction` を呼ぶ。Enrollment は `belongsTo(Certification::class)` を持つ
  - [[content-management]] — coach 教材作成時に `Certification::scopeAssignedTo($coach)` で担当資格絞込
  - [[learning]] — 受講生の受講中資格表示
  - [[mock-exam]] — `MockExam` は `belongsTo(Certification::class)`
  - [[dashboard]] — admin の修了申請待ち一覧、coach の担当資格一覧、student のカウントダウン（資格 + Enrollment 起点）
  - [[notification]] — 修了証発行通知の本文に Certification 名・Certificate.serial_no を含める
- **依存先**（本 Feature が前提とする）:
  - [[auth]] — `User` モデル / `UserRole` enum / `auth` middleware / `role:admin` middleware
  - [[user-management]] — admin の User 検索（コーチ割当 UI の対象選択）。直接の Action 依存はなし、Blade UI 上で `User::role=coach` を一覧取得するだけ
