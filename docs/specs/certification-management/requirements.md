# certification-management 要件定義

> **v3 改修反映**（2026-05-16）:
> - **`certifications` テーブルから 5 カラム削除**: `code` / `slug` / `passing_score` / `total_questions` / `exam_duration_minutes`（4 カラム構成: `name` / `category_id` / `difficulty` / `description` のみ）
> - **`certifications.code` UNIQUE INDEX 削除**（D4 関連、`(status, category_id)` 複合 INDEX は維持）
> - **修了申請承認フロー撤回**: `ApproveCompletionAction` → **`ReceiveCertificateAction`**（v3、受講生「修了証を受け取る」ボタン自己発火、[[enrollment]] 所有）
> - **修了証 PDF から「資格コード」表示を削除**（`code` 撤回のため、必要なら資格 ID 末尾 8 桁等で代替）

## 概要

資格マスタとその周辺リソースを admin が管理し、受講生にカタログ提示・修了証発行を行う Feature。Certify LMS のドメイン中核である「資格」エンティティと、合格時の修了証（Certificate + PDF）を所有する。

主体ロールは admin（資格マスタ CRUD / 公開状態管理 / 担当コーチ割当）と student（公開資格カタログ閲覧 / 自分の修了証ダウンロード）。coach は本 Feature の Controller を持たず、`Certification::scopeAssignedTo()` を通じて [[content-management]] / [[dashboard]] から間接利用する。**修了認定の判定・承認フロー本体は [[enrollment]] が所有**（v3 で受講生自己発火型 `ReceiveCertificateAction`）し、本 Feature は判定済の Enrollment を受けて修了証を発行する責務に専念する。

## ロールごとのストーリー

- **管理者（admin）**: 資格マスタ（**`name` / `category_id` / `difficulty` / `description` の 4 カラム構成**、v3）を起こし、`draft` で準備、`published` で公開、不要になれば `archived` する。資格ごとに担当コーチを割当・解除する。資格分類のマスタも追加・編集・削除する。**`code` / `slug` / `passing_score` / `total_questions` / `exam_duration_minutes` は持たない**（合格点は `MockExam.passing_score` で資格内の模試ごとに設定、試験時間は LMS スコープ外）。
- **受講生（student）**: 公開資格カタログを閲覧して興味のある資格を受講登録する。修了達成後（公開模試すべて合格）、[[enrollment]] の「修了証を受け取る」ボタンを押下すると `IssueCertificateAction` が呼び出されて修了証が発行される（v3 で受講生自己発火、admin 承認なし）。
- **コーチ（coach）**: 本 Feature の Controller を直接持たない。担当資格は [[content-management]] / [[dashboard]] / [[mock-exam]] 等から `Certification::scopeAssignedTo()` 経由で参照される。

## 受け入れ基準（EARS形式）

### 機能要件 — 資格マスタ管理

- **REQ-certification-management-001**: The system shall **4 カラム構成の `certifications` テーブル**（v3）を提供する: ULID 主キー / **`name` string max:100 NOT NULL** / **`category_id` ULID FK to `certification_categories` NOT NULL** / **`difficulty` enum**（`beginner` / `intermediate` / `advanced`） / **`description` text nullable** / `status` enum（`draft` / `published` / `archived`） / `created_by_user_id` ULID FK / `updated_by_user_id` ULID FK / `published_at` datetime nullable / `archived_at` datetime nullable / `created_at` / `updated_at` / `deleted_at`。**`(status, category_id)` 複合 INDEX 維持**。
- **REQ-certification-management-002**: **削除（v3 撤回）**: 旧 `code` / `slug` / `passing_score` / `total_questions` / `exam_duration_minutes` カラムは持たない。`code` UNIQUE INDEX も持たない。
- **REQ-certification-management-010**: When admin が `GET /admin/certifications` にアクセスした際, the system shall 公開状態フィルタ（`status`）+ カテゴリフィルタ（`category_id`）+ 難易度フィルタ（`difficulty`）+ keyword フィルタを提供する。
- **REQ-certification-management-011**: While admin が資格一覧画面で `keyword` を指定している間, the system shall **`certifications.name`** の部分一致検索を実行する（v3 で `code` 検索撤回）。
- **REQ-certification-management-012**: When admin が `POST /admin/certifications` で新規資格を作成した際, the system shall `name` / `category_id` / `difficulty` / `description` を検証して INSERT する（`status = draft` 固定）。
- **REQ-certification-management-013**: **削除（v3 撤回）**: `certifications.code` UNIQUE バリデーションは行わない。
- **REQ-certification-management-014**: When admin が編集フォームから資格マスタを更新した際, the system shall **`name` / `category_id` / `difficulty` / `description` のみ** を UPDATE する（v3 で 4 カラム構成）。`status` は公開状態遷移用エンドポイントから別途行う。
- **REQ-certification-management-015**: When admin が資格マスタを削除しようとした際, the system shall (1) `status === draft`、(2) 関連 Enrollment ゼロ件、を検証し、合格なら SoftDelete する。違反は `CertificationNotDeletableException`（HTTP 409）。
- **REQ-certification-management-016**: When admin が `POST /admin/certifications/{certification}/publish` / `/unpublish` / `/archive` を呼んだ際, the system shall 公開状態遷移を実行する。
- **REQ-certification-management-017**: **削除（v3 撤回）**: `passing_score` バリデーション関連。合格点は `MockExam.passing_score` で資格内の模試ごとに設定するため、本 Feature では持たない。

### 機能要件 — CertificationCategory 管理

- **REQ-certification-management-030**: The system shall ULID 主キー / `slug` UNIQUE / `name` / `sort_order` / `created_at` / `updated_at` / `deleted_at` を備えた `certification_categories` テーブルを提供する。
- **REQ-certification-management-031**: When admin が `/admin/certification-categories` で CRUD を行う際, the system shall `name` / `slug` / `sort_order` の入力を受け付け、`(slug)` UNIQUE を保証する。

### 機能要件 — 担当コーチ割当（`certification_coach_assignments`）

- **REQ-certification-management-040**: The system shall ULID 主キー / `certification_id` FK / `user_id`（coach）FK / `assigned_by_user_id` FK（admin）/ `assigned_at` datetime / `unassigned_at` datetime nullable / SoftDeletes を備えた `certification_coach_assignments` テーブルを提供する。`(certification_id, user_id)` UNIQUE（active 状態）。
- **REQ-certification-management-041**: When admin が `POST /admin/certifications/{certification}/coaches/{coach}` を呼んだ際, the system shall 当該 coach を担当に割当する。
- **REQ-certification-management-042**: When admin が `DELETE /admin/certifications/{certification}/coaches/{coach}` を呼んだ際, the system shall 当該割当を解除する（`unassigned_at = now()` で SoftDelete、関連 [[chat]] の ChatMember を `ChatMemberSyncService` で自動同期）。

### 機能要件 — 受講生向け資格カタログ

- **REQ-certification-management-050**: When 受講生が `GET /certifications` にアクセスした際, the system shall `status = published` の資格一覧をカテゴリ別 / 難易度別フィルタ付きで表示する。
- **REQ-certification-management-051**: The system shall 受講生がカタログから「受講登録」ボタンを押下すると、[[enrollment]] の `StoreEnrollmentAction` を呼ぶ。
- **REQ-certification-management-052**: When 受講生が `GET /certifications/{certification}` で詳細表示した際, the system shall 公開済の場合のみ表示し、`status != published` で 404 を返す。

### 機能要件 — Certificate 発行（受講生自己発火型、v3）

- **REQ-certification-management-060**: The system shall ULID 主キー / `user_id` FK / `enrollment_id` FK UNIQUE / `certification_id` FK / `serial_no` string UNIQUE / `pdf_path` string / `issued_at` datetime / timestamps を備えた `certificates` テーブルを提供する。
- **REQ-certification-management-061**: The system shall `App\Services\CertificateSerialNumberService::generate(): string` で `serial_no` を年月 + 連番形式（例: `CT-202605-00001`）で採番する。
- **REQ-certification-management-062**: When [[enrollment]] の **`ReceiveCertificateAction`**（v3、旧 `ApproveCompletionAction` から rename、受講生自己発火）が本 Feature の `IssueCertificateAction` を呼出した際, the system shall (1) 対象 Enrollment が `status=passed` + `passed_at != null` であることを検証し、(2) `serial_no` を採番し、(3) `pdf_path` を `storage/app/private/certificates/{ulid}.pdf` 形式で予約し、(4) `certificates` テーブルに INSERT し、(5) Blade テンプレート `certificates/pdf.blade.php` を `barryvdh/laravel-dompdf` で同期 PDF 化して当該パスに保存する。すべて `DB::transaction()` 内で実行する。
- **REQ-certification-management-063**: The system shall 受講生が `GET /certificates/{certificate}/download` で自分の修了証 PDF をダウンロードできる（Policy で `$certificate->user_id === $user->id` 検証）。**`EnsureActiveLearning` Middleware は適用しない**（graduated でも DL 可能、product.md L482 と整合、v3）。
- **REQ-certification-management-064**: When admin / coach が他者の修了証 PDF にアクセスした際, the system shall admin は全件 200、coach は担当資格のみ 200、それ以外 403。
- **REQ-certification-management-065**: When 同一 Enrollment に対して `IssueCertificateAction` が二重呼出された場合, the system shall `enrollment_id` UNIQUE 制約違反で `CertificateAlreadyIssuedException`（HTTP 409）を throw する。
- **REQ-certification-management-068**: The system shall 修了証 PDF（`certificates/pdf.blade.php`）に以下の **7 要素** を含む（v3 で「資格コード」削除）。**固定文言**: (1) タイトル「修了証」(2) 証書定型文「上記の者は、本資格の所定の課程を修了したことを証する」(3) 発行元「Certify LMS」。**変数**: (4) 受講生氏名（`$certificate->user->name`）(5) 資格名（`$certificate->certification->name`）(6) 発行日（`$certificate->issued_at`、西暦表記）(7) 証書番号（`$certificate->serial_no`）。+α 要素は採用しない。

### 機能要件 — 認可

- **REQ-certification-management-080**: The system shall `/admin/certifications/*` / `/admin/certification-categories/*` に `auth + role:admin` Middleware を適用する。
- **REQ-certification-management-081**: The system shall `/certifications/*` 受講生向けカタログに `auth + role:student + EnsureActiveLearning` Middleware を適用する（v3、graduated は資格カタログ参照不可、修了証 DL は別途許可）。
- **REQ-certification-management-082**: The system shall `/certificates/{certificate}/download` に `auth` のみ適用し、`EnsureActiveLearning` は **適用しない**（graduated でも DL 可能、v3）。

### 非機能要件

- **NFR-certification-management-001**: The system shall 状態変更を伴う Action（資格 CRUD / 公開遷移 / 担当コーチ割当 / Certificate 発行）を `DB::transaction()` で囲む。
- **NFR-certification-management-002**: The system shall N+1 を Eager Loading で避ける。
- **NFR-certification-management-003**: The system shall 以下 INDEX を提供: `certifications.(status, category_id)` 複合（v3 で維持） / `certification_coach_assignments.(certification_id, user_id)` UNIQUE / `certificates.enrollment_id` UNIQUE。
- **NFR-certification-management-004**: The system shall ドメイン例外を `app/Exceptions/Certification/` 配下に配置する: `CertificationNotDeletableException`(409) / `CertificateAlreadyIssuedException`(409) / `CertificateGenerationFailedException`(500)。
- **NFR-certification-management-005**: The system shall PDF を Storage private driver の `certificates/{ulid}.pdf` パスに保存する。

## スコープ外

- **`certifications.code` / `slug` / `passing_score` / `total_questions` / `exam_duration_minutes`**（v3 で撤回、4 カラム構成に縮減）
- **admin 修了承認フロー**（v3 で撤回、受講生自己発火型 `ReceiveCertificateAction` に集約）
- 修了通知メール / 修了通知 DB 通知の送信 — [[notification]] 所有（本 Feature は Certificate INSERT のみ、Notification dispatch は呼出元の [[enrollment]] `ReceiveCertificateAction` 側責務）
- 受講登録の自己解除 / 履歴閲覧 — [[enrollment]]
- 進捗集計 / ターム判定 — [[learning]] / [[enrollment]]
- 模試マスタ管理 / 模試の `passing_score` — [[mock-exam]]
- 添付ファイル（修了証以外）— スコープ外

## 関連 Feature

- **依存元**（本 Feature を利用する）:
  - [[enrollment]] — **`ReceiveCertificateAction`**（v3 rename）から `IssueCertificateAction` を呼ぶ。Enrollment は `belongsTo(Certification::class)` を持つ
  - [[content-management]] — Part の親として `Certification` を参照
  - [[mock-exam]] — `MockExam.certification_id` で関連、`MockExam.passing_score` で資格内合格点管理
  - [[dashboard]] — 修了済資格セクション（受講生）+ 全体 KPI（admin）で `Certification` を参照
- **依存先**（本 Feature が前提とする）:
  - [[auth]]: `User` モデル、`UserRole` / `UserStatus` Enum、`auth` middleware
