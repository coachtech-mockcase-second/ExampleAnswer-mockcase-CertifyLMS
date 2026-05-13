# certification-management タスクリスト

> 1 タスク = 1 コミット粒度。Step 単位で順次実装し、完了したものから `[x]` に更新する。
> 関連要件 ID は `requirements.md` の `REQ-certification-management-NNN` / `NFR-certification-management-NNN` を参照。
> 開発コマンドはすべて Sail プレフィックス必須（`tech.md` の「コマンド慣習」参照）。

## Step 1: Migration & Model

- [ ] migration: `create_certification_categories_table`（ULID + SoftDeletes + `slug` UNIQUE + `sort_order` INDEX）（REQ-certification-management-030）
- [ ] migration: `create_certifications_table`（ULID + SoftDeletes + `code` UNIQUE + `slug` UNIQUE + `(status, category_id)` 複合 INDEX + `deleted_at` INDEX + `category_id` FK restrict + `created_by_user_id` / `updated_by_user_id` FK restrict）（REQ-certification-management-001, NFR-certification-management-002）
- [ ] migration: `create_certification_coach_assignments_table`（ULID + `(certification_id, coach_user_id)` UNIQUE + FK cascade for certification / restrict for users）（REQ-certification-management-040, REQ-certification-management-041）
- [ ] migration: `create_certificates_table`（ULID + SoftDeletes + `enrollment_id` UNIQUE + `serial_no` UNIQUE + `user_id` INDEX + 各種 FK restrict）（REQ-certification-management-060, NFR-certification-management-003）
- [ ] Enum: `App\Enums\CertificationStatus`（`Draft` / `Published` / `Archived` + `label()`）（REQ-certification-management-002）
- [ ] Enum: `App\Enums\CertificationDifficulty`（`Beginner` / `Intermediate` / `Advanced` / `Expert` + `label()`）（REQ-certification-management-003）
- [ ] Model: `App\Models\CertificationCategory`（`HasUlids` / `HasFactory` / `SoftDeletes` / `$fillable` / `$casts` / `certifications()` / `scopeOrdered()`）（REQ-certification-management-030）
- [ ] Model: `App\Models\Certification`（`HasUlids` / `HasFactory` / `SoftDeletes` / `$fillable` / `$casts`（status + difficulty enum 化）/ `category()` / `coaches()` BelongsToMany / `certificates()` / `enrollments()` / `mockExams()` 宣言 / `scopePublished()` / `scopeAssignedTo()` / `scopeKeyword()`）（REQ-certification-management-001, REQ-certification-management-045, REQ-certification-management-050）
- [ ] Model: `App\Models\Certificate`（`HasUlids` / `HasFactory` / `SoftDeletes` / `$fillable` / `$casts` / `user()` / `enrollment()` / `certification()` / `issuedBy()` / `scopeIssuedThisMonth()`）（REQ-certification-management-060）
- [ ] User Model 拡張: `assignedCertifications()` BelongsToMany リレーション追加（[[user-management]] / [[auth]] と整合、本 Feature で本リレーションを定義）（REQ-certification-management-045）
- [ ] Factory: `CertificationCategoryFactory`（`tech()` / `language()` 等 state）
- [ ] Factory: `CertificationFactory`（`draft()` / `published()` / `archived()` state）
- [ ] Factory: `CertificateFactory`（`for($user)` / `for($enrollment)` 等 state、`serial_no` は Sequence で重複防止）

## Step 2: Policy

- [ ] `App\Policies\CertificationPolicy`（`viewAny` / `view` / `create` / `update` / `delete` / `publish` / `archive` / `unarchive`）（REQ-certification-management-010, REQ-certification-management-055, NFR-certification-management-007）
- [ ] `App\Policies\CertificationCategoryPolicy`（`viewAny` / `create` / `update` / `delete`）（REQ-certification-management-031, REQ-certification-management-032）
- [ ] `App\Policies\CertificationCoachAssignmentPolicy`（`create` / `delete`）（REQ-certification-management-042, REQ-certification-management-044）
- [ ] `App\Policies\CertificatePolicy`（`view` / `download`、当事者 or admin）（REQ-certification-management-066, REQ-certification-management-067）
- [ ] `AuthServiceProvider::$policies` に 4 Policy を登録 or 自動検出が効くか確認

## Step 3: HTTP 層

- [ ] `App\Http\Controllers\CertificationController` スケルトン（`index` / `show` / `create` / `store` / `edit` / `update` / `destroy` / `publish` / `archive` / `unarchive`、薄く保つ）（REQ-certification-management-010〜023）
- [ ] `App\Http\Controllers\CertificationCategoryController`（`index` / `store` / `update` / `destroy`）（REQ-certification-management-031〜033）
- [ ] `App\Http\Controllers\CertificationCoachAssignmentController`（`store` / `destroy`）（REQ-certification-management-042, REQ-certification-management-044）
- [ ] `App\Http\Controllers\CertificationCatalogController`（`index` / `show`）（REQ-certification-management-050〜055）
- [ ] `App\Http\Controllers\CertificateController`（`show` / `download`）（REQ-certification-management-065, REQ-certification-management-066）
- [ ] FormRequest: `Certification\IndexRequest`（REQ-certification-management-010）
- [ ] FormRequest: `Certification\StoreRequest`（`code` UNIQUE + `passing_score` 1-100 等）（REQ-certification-management-012, REQ-certification-management-013, REQ-certification-management-017）
- [ ] FormRequest: `Certification\UpdateRequest`（ルート除外 unique + `status` 不可）（REQ-certification-management-014）
- [ ] FormRequest: `CertificationCategory\StoreRequest` / `UpdateRequest`（REQ-certification-management-032）
- [ ] FormRequest: `CertificationCoachAssignment\StoreRequest`（REQ-certification-management-042）
- [ ] FormRequest: `CertificationCatalog\IndexRequest`（REQ-certification-management-053）
- [ ] `routes/web.php` に admin / catalog / certificate のルート定義（`auth + role:admin` middleware group + `auth` group の構成、`Route::resource` + 公開状態遷移ルート 3 本 + コーチ割当 2 本 + カテゴリ resource + カタログ 2 本 + Certificate 2 本）（NFR-certification-management-007）

## Step 4: Action / Service / Exception

### Action — Certification
- [ ] `App\UseCases\Certification\IndexAction`（フィルタ + ページネーション）（REQ-certification-management-010, REQ-certification-management-011）
- [ ] `App\UseCases\Certification\ShowAction`（eager load）（REQ-certification-management-010）
- [ ] `App\UseCases\Certification\StoreAction`（`status=draft` 固定、`created_by_user_id` セット）（REQ-certification-management-012）
- [ ] `App\UseCases\Certification\UpdateAction`（`status` 不変、`updated_by_user_id` 更新）（REQ-certification-management-014）
- [ ] `App\UseCases\Certification\DestroyAction`（`draft` ガード + SoftDelete）（REQ-certification-management-015, REQ-certification-management-016）
- [ ] `App\UseCases\Certification\PublishAction`（draft → published）（REQ-certification-management-020, REQ-certification-management-021）
- [ ] `App\UseCases\Certification\ArchiveAction`（published → archived）（REQ-certification-management-022）
- [ ] `App\UseCases\Certification\UnarchiveAction`（archived → draft、`published_at` / `archived_at` リセット）（REQ-certification-management-023）

### Action — CertificationCategory
- [ ] `App\UseCases\CertificationCategory\IndexAction`（REQ-certification-management-031）
- [ ] `App\UseCases\CertificationCategory\StoreAction`（REQ-certification-management-032）
- [ ] `App\UseCases\CertificationCategory\UpdateAction`（REQ-certification-management-032）
- [ ] `App\UseCases\CertificationCategory\DestroyAction`（`certifications()->exists()` ガード）（REQ-certification-management-033）

### Action — CertificationCoachAssignment
- [ ] `App\UseCases\CertificationCoachAssignment\StoreAction`（`role === Coach` ガード + `syncWithoutDetaching`）（REQ-certification-management-042, REQ-certification-management-043）
- [ ] `App\UseCases\CertificationCoachAssignment\DestroyAction`（`detach`）（REQ-certification-management-044）

### Action — CertificationCatalog
- [ ] `App\UseCases\CertificationCatalog\IndexAction`（catalog / enrolled の 2 配列返却）（REQ-certification-management-050, REQ-certification-management-051, REQ-certification-management-052, REQ-certification-management-053）
- [ ] `App\UseCases\CertificationCatalog\ShowAction`（eager load）（REQ-certification-management-054）

### Action — Certificate
- [ ] `App\UseCases\Certificate\IssueAction`（[[enrollment]] からの呼出口、Enrollment ガード + `lockForUpdate` 冪等性 + serial 採番 + PDF 生成）（REQ-certification-management-062, REQ-certification-management-063）
- [ ] `App\UseCases\Certificate\ShowAction`（eager load）（REQ-certification-management-065）
- [ ] `App\UseCases\Certificate\DownloadAction`（Storage::download + `CertificatePdfNotFoundException`）（REQ-certification-management-066）

### Service
- [ ] `App\Services\CertificateSerialNumberService::generate`（`CT-YYYYMM-NNNNN` + `lockForUpdate`）（REQ-certification-management-064）
- [ ] `App\Services\CertificatePdfGenerator::generate`（`Pdf::loadView` + `Storage::disk('private')->put`）（REQ-certification-management-062, NFR-certification-management-005, NFR-certification-management-006）

### Exception（`app/Exceptions/Certification/`）
- [ ] `CertificationNotFoundException`（404）（REQ-certification-management-055）
- [ ] `CertificationNotDeletableException`（409）（REQ-certification-management-015）
- [ ] `CertificationInvalidTransitionException`（409、from/to 引数）（REQ-certification-management-021）
- [ ] `CertificationCategoryInUseException`（409）（REQ-certification-management-033）
- [ ] `NotCoachUserException`（422）（REQ-certification-management-043）
- [ ] `EnrollmentNotPassedException`（409）（REQ-certification-management-062）
- [ ] `CertificatePdfNotFoundException`（404）（REQ-certification-management-066）

### 共通基盤
- [ ] `config/dompdf.php` に日本語フォント登録設定（`IPAGothic` 等、Wave 0b 共通基盤に依存）（NFR-certification-management-006）
- [ ] `config/filesystems.php` に `private` disk が定義されていることを確認（[[chat]] と共有想定、Wave 0b 範疇）

## Step 5: Blade ビュー

### admin 用
- [ ] `resources/views/admin/certifications/index.blade.php`（フィルタ + ページネーション + 「+新規作成」ボタン）
- [ ] `resources/views/admin/certifications/create.blade.php`（新規作成フォーム）
- [ ] `resources/views/admin/certifications/edit.blade.php`（編集フォーム、`status` フィールドなし）
- [ ] `resources/views/admin/certifications/show.blade.php`（情報カード + 状態遷移ボタン + コーチ割当セクション + 発行済 Certificate 一覧）
- [ ] `resources/views/admin/certifications/_partials/info-card.blade.php`
- [ ] `resources/views/admin/certifications/_partials/coach-list.blade.php`
- [ ] `resources/views/admin/certifications/_partials/recent-certificates.blade.php`
- [ ] `resources/views/admin/certifications/_modals/assign-coach-form.blade.php`（coach ロール User を select）
- [ ] `resources/views/admin/certifications/_modals/transition-confirm.blade.php`（publish / archive / unarchive 共用）
- [ ] `resources/views/admin/certifications/_modals/delete-confirm.blade.php`（draft 時のみ表示）
- [ ] `resources/views/admin/certification-categories/index.blade.php`
- [ ] `resources/views/admin/certification-categories/_modals/form.blade.php`

### student / カタログ用
- [ ] `resources/views/certifications/index.blade.php`（タブ: カタログ / 受講中 + 検索バー + フィルタ + 資格カードグリッド）
- [ ] `resources/views/certifications/show.blade.php`（資格詳細 + 担当コーチ + 公開模試サマリ + 受講開始導線）
- [ ] `resources/views/certifications/_partials/certification-card.blade.php`（受講中バッジ対応）

### 修了証
- [ ] `resources/views/certificates/show.blade.php`（達成画面、Wave 0b 共通レイアウト継承）
- [ ] `resources/views/certificates/pdf.blade.php`（**dompdf 用、共通レイアウト非継承、インライン `<style>` のみ、日本語フォント指定**）（NFR-certification-management-006）

## Step 6: テスト

### Policy（Unit）
- [ ] `tests/Unit/Policies/CertificationPolicyTest.php`（admin / coach / student × viewAny / view / create / update / delete / publish / archive / unarchive、状態別の組合せ）
- [ ] `tests/Unit/Policies/CertificationCategoryPolicyTest.php`
- [ ] `tests/Unit/Policies/CertificationCoachAssignmentPolicyTest.php`
- [ ] `tests/Unit/Policies/CertificatePolicyTest.php`（admin / 当事者 / 他者 student × view / download）

### Service（Unit）
- [ ] `tests/Unit/Services/CertificateSerialNumberServiceTest.php`（同月初回 = `CT-{YYYYMM}-00001` / 連番加算 / 月跨ぎでリセット）
- [ ] `tests/Unit/Services/CertificatePdfGeneratorTest.php`（`Storage::fake` + `Pdf::fake` 相当で `pdf_path` への put 呼出を検証）

### Action（Unit / Feature mixed）
- [ ] `tests/Feature/UseCases/Certificate/IssueActionTest.php`（passed Enrollment で発行成功 / 非 passed で `EnrollmentNotPassedException` / 同じ Enrollment 2 回呼出で同一 Certificate 返却・PDF 再生成なし）
- [ ] `tests/Feature/UseCases/Certification/PublishActionTest.php`（draft → published / published 時に `CertificationInvalidTransitionException`）
- [ ] `tests/Feature/UseCases/Certification/DestroyActionTest.php`（draft 時 SoftDelete / published・archived 時に `CertificationNotDeletableException`）

### Controller / 認可（Feature）
- [ ] `tests/Feature/Http/Certification/IndexTest.php`（admin 200 / coach 403 / student 403、フィルタ動作）
- [ ] `tests/Feature/Http/Certification/StoreTest.php`（admin 成功 / バリデーション失敗 / `code` 重複 422 / coach 403）
- [ ] `tests/Feature/Http/Certification/UpdateTest.php`（admin 成功 / `status` フィールド送信時は無視）
- [ ] `tests/Feature/Http/Certification/DestroyTest.php`（draft SoftDelete / published 409）
- [ ] `tests/Feature/Http/Certification/PublishTest.php`（draft → published 200 / archived 状態 409）
- [ ] `tests/Feature/Http/Certification/ArchiveTest.php`
- [ ] `tests/Feature/Http/Certification/UnarchiveTest.php`
- [ ] `tests/Feature/Http/CertificationCategory/StoreTest.php`
- [ ] `tests/Feature/Http/CertificationCategory/DestroyTest.php`（参照中 409 / 参照ゼロで SoftDelete）
- [ ] `tests/Feature/Http/CertificationCoachAssignment/StoreTest.php`（coach 割当成功 / student を coach に指定で `NotCoachUserException` 422 / 重複 INSERT がノーオペ）
- [ ] `tests/Feature/Http/CertificationCoachAssignment/DestroyTest.php`
- [ ] `tests/Feature/Http/CertificationCatalog/IndexTest.php`（student / coach / admin が公開済資格を見れる / 受講中タブが自分の Enrollment のみ / draft 資格は表示されない / archived 資格は表示されない）
- [ ] `tests/Feature/Http/CertificationCatalog/ShowTest.php`（student が published を 200 / draft / archived は student 404 / admin は 200）
- [ ] `tests/Feature/Http/Certificate/ShowTest.php`（当事者 / admin が 200 / 他者 student が 403）
- [ ] `tests/Feature/Http/Certificate/DownloadTest.php`（`Storage::fake('private')` + put 済み PDF の download 確認 / 他者 student の 403 / PDF ファイル不在で `CertificatePdfNotFoundException` 404）

### モデルスコープ（Unit）
- [ ] `tests/Unit/Models/CertificationScopesTest.php`（`scopePublished` / `scopeAssignedTo` / `scopeKeyword` の絞込結果）

## Step 7: 動作確認 & 整形

- [ ] `sail artisan migrate:fresh --seed` で migration 全通過（カテゴリ + 資格の seeder データが入る）
- [ ] `sail artisan test --filter=Certification` / `--filter=Certificate` で本 Feature テスト群通過
- [ ] `sail bin pint --dirty` 整形
- [ ] ブラウザ動作確認（admin）:
  - [ ] `/admin/certifications` 一覧でフィルタ・検索が機能する
  - [ ] 新規作成 → `draft` 状態で保存される
  - [ ] 編集 → status は変わらない
  - [ ] `Publish` ボタンで draft → published に遷移、受講生カタログに即時反映
  - [ ] `Archive` / `Unarchive` の往復遷移
  - [ ] draft 状態で削除ボタン押下 → SoftDelete、published で削除ボタンが非表示
  - [ ] コーチ割当モーダルで coach ロール User のみ選択可、admin / student を指定すると 422
  - [ ] 同じ coach を 2 回割当しても重複しない
- [ ] ブラウザ動作確認（受講生）:
  - [ ] `/certifications` にカタログタブと受講中タブが表示される
  - [ ] 受講中タブには自分の Enrollment 紐付き資格のみ
  - [ ] カタログタブで受講中の資格にバッジが付く
  - [ ] draft / archived 資格は URL 直叩きで 404 になる
  - [ ] 公開済資格の詳細画面に担当コーチと公開模試一覧が表示される
- [ ] ブラウザ動作確認（修了証発行）:
  - [ ] [[enrollment]] の `ApproveCompletionAction` 経由で Certificate INSERT + PDF 生成（手順は [[enrollment]] の tasks.md に従う、本 Feature の `IssueAction` が呼ばれることを確認）
  - [ ] `/certificates/{certificate}` で受講生が達成画面を閲覧できる
  - [ ] PDF ダウンロードボタンで `application/pdf` が `attachment` 配信される
  - [ ] 他者 student で同 URL アクセスで 403
  - [ ] PDF の Blade テンプレートで日本語が文字化けしない（`IPAGothic` フォント反映確認）
- [ ] Schedule / Queue は本 Feature では使わない（Basic 範囲）
