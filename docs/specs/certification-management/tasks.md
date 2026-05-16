# certification-management タスクリスト

> 1 タスク = 1 コミット粒度。
> 関連要件 ID は `requirements.md` の `REQ-certification-management-NNN` / `NFR-certification-management-NNN` を参照。
> **v3 改修反映**: `certifications` 4 カラム化(`code` / `slug` / `passing_score` / `total_questions` / `exam_duration_minutes` 撤回) / `ReceiveCertificateAction` から発火 / 修了証 PDF 7 要素(資格コード撤回)。
> コマンドはすべて `sail` プレフィックス。

## Step 1: Migration & Enum & Model

### Migration

- [ ] `database/migrations/{date}_create_certification_categories_table.php`(ULID + SoftDeletes + `slug` UNIQUE + `sort_order` INDEX)
- [ ] **`database/migrations/{date}_create_certifications_table.php`(v3 で 4 カラム化)** — ULID + SoftDeletes + **`name` string max:100 NOT NULL** + **`category_id` FK restrict** + **`difficulty` enum**(`beginner` / `intermediate` / `advanced`) + **`description` text nullable** + `status` enum + `created_by_user_id` / `updated_by_user_id` FK restrict + `published_at` / `archived_at` datetime nullable + `(status, category_id)` 複合 INDEX(v3 で維持) + `deleted_at` INDEX
  - **`code` / `slug` / `passing_score` / `total_questions` / `exam_duration_minutes` カラムは持たない**(v3 撤回)
  - **`code` UNIQUE INDEX は持たない**(v3 撤回)
- [ ] `database/migrations/{date}_create_certification_coach_assignments_table.php`(ULID + SoftDeletes + `certification_id` / `user_id` / `assigned_by_user_id` FK restrict + `assigned_at` + `unassigned_at` nullable + UNIQUE(`certification_id`, `user_id`) where deleted_at IS NULL)
- [ ] `database/migrations/{date}_create_certificates_table.php`(ULID + `user_id` / `enrollment_id` UNIQUE / `certification_id` FK restrict + `serial_no` string UNIQUE + `pdf_path` string + `issued_at` datetime + timestamps)

### Enum

- [ ] `App\Enums\CertificationDifficulty`(`Beginner` / `Intermediate` / `Advanced` + `label()`)
- [ ] `App\Enums\CertificationStatus`(`Draft` / `Published` / `Archived` + `label()`)

### Model

- [ ] **`App\Models\Certification`(v3 で 4 カラム)** — `HasUlids` + `HasFactory` + `SoftDeletes` + fillable + `$casts['difficulty'=>CertificationDifficulty, 'status'=>CertificationStatus, 'published_at'=>'datetime', 'archived_at'=>'datetime']` + `belongsTo(CertificationCategory)` + `hasMany(Part)` + `hasMany(MockExam)` + `hasMany(Enrollment)` + `belongsToMany(User, 'certification_coach_assignments')->wherePivot('unassigned_at', null)` を `coaches()` で公開 + `scopePublished` / `scopeAssignedTo(User)` / `scopeKeyword(?string)`(**v3 で `name` のみ LIKE**)
- [ ] `App\Models\CertificationCategory`(`HasUlids` + `HasFactory` + `SoftDeletes` + `hasMany(Certification)` + `hasMany(QuestionCategory)`)
- [ ] `App\Models\Certificate`(`HasUlids` + `HasFactory`、SoftDelete 不採用 + `$casts['issued_at'=>'datetime']` + `belongsTo(User)` + `belongsTo(Enrollment)` + `belongsTo(Certification)`)

### Factory

- [ ] **`CertificationFactory`(v3 更新)** — `published()` / `draft()` / `archived()` / `forCategory(CertificationCategory)` state、**`difficulty` は enum cast**(beginner / intermediate / advanced)
- [ ] `CertificationCategoryFactory`(`withSlug(string)` state)
- [ ] `CertificateFactory`(`forEnrollment` / `withSerial(string)` state)

## Step 2: Policy

- [ ] `App\Policies\CertificationPolicy`(`viewAny` / `view` / `create` / `update` / `delete` / `publish` / `unpublish` / `archive` / `attachCoach` / `detachCoach`、admin true / coach 担当のみ view true / student published のみ view true)
- [ ] `App\Policies\CertificationCategoryPolicy`(admin true)
- [ ] `App\Policies\CertificateDownloadPolicy::download(User, Certificate)`(admin true / coach 担当資格 / student 本人)
- [ ] `AuthServiceProvider::$policies` 登録

## Step 3: HTTP 層

### Controller

- [ ] `Admin\CertificationController`(`index` / `create` / `store` / `show` / `edit` / `update` / `destroy` / `publish` / `unpublish` / `archive`)
- [ ] `Admin\CertificationCategoryController`(CRUD)
- [ ] `Admin\CertificationCoachAssignmentController`(`attach($certification, $coach)` / `detach($certification, $coach)`)
- [ ] `CertificationCatalogController`(`index` / `show`、student 用)
- [ ] `CertificateController`(`download($certificate)`)

### FormRequest

- [ ] **`Admin\Certification\StoreRequest`(v3 で 4 フィールド)** — `name: required string max:100` / `category_id: required ulid exists:certification_categories,id` / `difficulty: required Rule::enum(CertificationDifficulty)` / `description: nullable string max:1000`
- [ ] **`Admin\Certification\UpdateRequest`** — 同 rules(v3 で 4 フィールドのみ)
- [ ] **削除(v3 撤回)**: `code` / `slug` / `passing_score` / `total_questions` / `exam_duration_minutes` のバリデーション
- [ ] `Admin\Certification\IndexRequest`(`status` / `category_id` / `difficulty` / `keyword` 任意フィルタ)
- [ ] `Admin\CertificationCategory\StoreRequest` / `UpdateRequest`(`name` / `slug` / `sort_order`)
- [ ] `Catalog\IndexRequest`(`category_id` / `difficulty` 任意フィルタ)

### Route

- [ ] `routes/web.php`:
  - admin: `auth + role:admin` group + `prefix('admin')`:
    - `Route::resource('certifications', ...)` + publish / unpublish / archive
    - `Route::resource('certification-categories', ...)`
    - `Route::post('certifications/{cert}/coaches/{coach}', attach)` / `Route::delete(detach)`
  - student catalog: **`auth + role:student + EnsureActiveLearning`** group(v3): `Route::get('certifications', ...)` / `Route::get('certifications/{cert}', ...)`
  - 修了証 DL: **`auth` のみ**(v3 で `EnsureActiveLearning` 非適用、graduated でも DL 可): `Route::get('certificates/{certificate}/download', ...)`

## Step 4: Action / Service / Exception / Event

### Action

- [ ] `Certification\IndexAction` / `StoreAction` / `ShowAction` / `UpdateAction`(v3 で 4 フィールド UPDATE のみ) / `DestroyAction`(409 ガード) / `PublishAction` / `UnpublishAction` / `ArchiveAction`
- [ ] `CertificationCategory\*Action`
- [ ] `CertificationCoachAssignment\AttachAction`(`CertificationCoachAttached` event 発火) / `DetachAction`(`CertificationCoachDetached` event 発火)
- [ ] `Catalog\IndexAction` / `ShowAction`
- [ ] **`Certificate\IssueAction`(v3、発火元変更)** — [[enrollment]] `ReceiveCertificateAction`(v3 rename) から呼ばれる、Enrollment passed 検証 + 二重発行検査 + serial_no 採番 + INSERT + PDF 生成 + Storage 保存、`DB::transaction`

### Service

- [ ] `App\Services\CertificateSerialNumberService::generate(): string`(`CT-{YYYYMM}-{連番5桁}` 形式)

### Event

- [ ] `App\Events\CertificationCoachAttached`(certification_id / coach_user_id)
- [ ] `App\Events\CertificationCoachDetached`(同上)

### ドメイン例外(`app/Exceptions/Certification/`)

- [ ] `CertificationNotDeletableException`(HTTP 409)
- [ ] `CertificateAlreadyIssuedException`(HTTP 409、enrollment_id UNIQUE 違反)
- [ ] `CertificateGenerationFailedException`(HTTP 500、PDF 生成失敗)

## Step 5: Blade ビュー

- [ ] `admin/certifications/index.blade.php` / `create.blade.php` / `edit.blade.php` / `show.blade.php`(**v3 で 4 フィールド入力**: name / category_id / difficulty / description、**`code` / `slug` / `passing_score` / `total_questions` / `exam_duration_minutes` 入力欄なし**)
- [ ] `admin/certification-categories/index.blade.php` 等(モーダル UI)
- [ ] `certifications/index.blade.php` / `show.blade.php`(受講生カタログ、**`code` 表示なし**(v3))
- [ ] **`certificates/pdf.blade.php`(v3 で 7 要素)** — タイトル + 証書定型文 + 発行元 + **受講生氏名 / 資格名 / 発行日 / 証書番号**(資格コード撤回、+α 要素なし)

## Step 6: テスト

### Feature(HTTP)

- [ ] `Admin/Certification/IndexTest`(フィルタ動作、**`keyword` で `name` のみ LIKE 検索**(v3))
- [ ] **`Admin/Certification/StoreTest`(v3 更新)** — name + category_id + difficulty + description で 200 / **`code` / `passing_score` 等送信時に DB に保存されない**(rule で許容しない) / 必須欠落で 422
- [ ] **`Admin/Certification/UpdateTest`(v3 更新)** — 4 フィールドのみ UPDATE 可能
- [ ] `Admin/Certification/PublishTest` / `UnpublishTest` / `ArchiveTest`
- [ ] `Admin/Certification/DestroyTest`(draft + Enrollment 0 件で SoftDelete / それ以外 409)
- [ ] `Admin/CertificationCoachAssignment/{Attach,Detach}Test`(event 発火検証)
- [ ] `Catalog/IndexTest` / `ShowTest`(**`EnsureActiveLearning` 適用**(v3)、graduated で 403)
- [ ] **`Certificate/DownloadTest`(v3)** — 本人 200 / 他者 403 / **`graduated` でも 200**(EnsureActiveLearning 非適用、v3) / admin 全件 200 / coach 担当のみ 200

### Feature(UseCases)

- [ ] **`Certificate/IssueActionTest`(v3)** — Enrollment passed 検証 / 二重発行 409 / serial_no 採番 / PDF 生成 + Storage 保存 / **呼出元は [[enrollment]] `ReceiveCertificateAction`**(v3 で `ApproveCompletionAction` から rename) / **PDF 内容に `code`(資格コード)含まれない**(v3、7 要素のみ)

### Unit(Services / Policies)

- [ ] `CertificateSerialNumberServiceTest`(年月別連番)
- [ ] `CertificationPolicyTest`(admin / coach / student 真偽値網羅)
- [ ] `CertificateDownloadPolicyTest`

## Step 7: 動作確認 & 整形

- [ ] `sail artisan migrate:fresh --seed`
- [ ] `sail artisan test --filter=Certification` 全件 pass
- [ ] `sail bin pint --dirty` 整形
- [ ] ブラウザ動作確認シナリオ:
  - [ ] admin で /admin/certifications → 一覧表示、フィルタ動作
  - [ ] admin で資格作成 → **4 フィールドのみ入力**(name / category_id / difficulty / description) → 成功
  - [ ] admin で資格編集 → 同 4 フィールドのみ更新可能
  - [ ] admin で公開 → published 遷移 → 受講生カタログに表示
  - [ ] 受講生で /certifications → 公開資格一覧 + 詳細閲覧
  - [ ] **`graduated` ユーザーで /certifications → 403**(EnsureActiveLearning、v3)
  - [ ] 修了証 DL → 本人で /certificates/{id}/download 成功
  - [ ] **`graduated` ユーザーで /certificates/{id}/download → 200**(v3、永続 DL 可能)
- [ ] **v3 撤回確認**:
  - [ ] 旧 `code` / `slug` / `passing_score` 等フィールドが Blade に表示されない
  - [ ] PDF に資格コード表示なし(7 要素のみ)
  - [ ] 修了証 PDF に「受講生氏名 / 資格名 / 発行日 / 証書番号」が表示される
