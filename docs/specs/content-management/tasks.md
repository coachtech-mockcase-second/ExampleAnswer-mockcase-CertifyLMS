# content-management タスクリスト

> 1タスク = 1コミット粒度。Step 単位で順次実装し、完了したものから `[x]` に更新する。
> 関連要件 ID は `requirements.md` の `REQ-content-management-NNN` / `NFR-content-management-NNN` を参照。
> コマンドは `tech.md`「コマンド慣習」セクションの通り Sail プレフィックスで統一する（`sail artisan ...` / `sail npm ...` / `sail bin pint ...`）。

## Step 1: Migration & Model

- [x] migration: `create_parts_table`（ULID, `certification_id` FK, `title`, `description nullable`, `order unsigned`, `status enum draft/published`, `published_at nullable`, `timestamps`, `softDeletes`、`(certification_id, order)` / `(certification_id, status)` 複合 INDEX, `deleted_at` INDEX）（REQ-content-management-001, REQ-content-management-002, REQ-content-management-007, REQ-content-management-009, NFR-content-management-003）
- [x] migration: `create_chapters_table`（ULID, `part_id` FK, `title`, `description nullable`, `order`, `status`, `published_at`, `timestamps`, `softDeletes`、`(part_id, order)` / `(part_id, status)` INDEX）（REQ-content-management-001, REQ-content-management-003, REQ-content-management-009, NFR-content-management-003）
- [x] migration: `create_sections_table`（ULID, `chapter_id` FK, `title`, `description nullable`, `body longtext`, `order`, `status`, `published_at`, `timestamps`, `softDeletes`、`(chapter_id, order)` / `(chapter_id, status)` / `sections.title` INDEX）（REQ-content-management-001, REQ-content-management-003, REQ-content-management-009, NFR-content-management-003）
- [x] migration: `create_question_categories_table`（ULID, `certification_id` FK NOT NULL, `name string 50`, `slug string 60`, `sort_order unsigned int default 0`, `description text nullable max 500`, `timestamps`, `softDeletes`、`(certification_id, slug)` UNIQUE / `(certification_id, sort_order)` INDEX、業界標準寄せ）（REQ-content-management-042, NFR-content-management-003）
- [x] migration: `create_questions_table`（ULID, `certification_id` FK NOT NULL, `section_id` FK NULLABLE, `category_id` FK NOT NULL to question_categories restrict, `body text`, `explanation text nullable`, `difficulty enum easy/medium/hard`, `order`, `status`, `published_at`, `timestamps`, `softDeletes`、`(certification_id, status)` / `section_id` / `category_id` / `(certification_id, difficulty)` INDEX）（REQ-content-management-001, REQ-content-management-004, REQ-content-management-007, REQ-content-management-008, NFR-content-management-003）
- [x] migration: `create_question_options_table`（ULID, `question_id` FK cascadeOnDelete, `body text`, `is_correct boolean default false`, `order`, `timestamps`、`(question_id, order)` INDEX、SoftDeletes は採用しない）（REQ-content-management-001, REQ-content-management-005, NFR-content-management-003）
- [x] migration: `create_section_images_table`（ULID, `section_id` FK, `path string UNIQUE`, `original_filename string`, `mime_type string`, `size_bytes unsigned int`, `timestamps`, `softDeletes`、`(section_id, deleted_at)` INDEX）（REQ-content-management-001, REQ-content-management-006, NFR-content-management-003）
- [x] Enum: `ContentStatus`（`Draft` / `Published` + `label()`）（REQ-content-management-007）
- [x] Enum: `QuestionDifficulty`（`Easy` / `Medium` / `Hard` + `label()`）（REQ-content-management-008）
- [x] Model: `Part`（`fillable`, `$casts['status' => ContentStatus::class, 'published_at' => 'datetime']`, `certification()` belongsTo, `chapters()` hasMany, `scopePublished()`, `scopeOrdered()`）（REQ-content-management-002, REQ-content-management-007）
- [x] Model: `Chapter`（`fillable`, `$casts`, `part()` belongsTo, `sections()` hasMany, `scopePublished()`（親 Part を whereHas）, `scopeOrdered()`）（REQ-content-management-003, REQ-content-management-007, REQ-content-management-022）
- [x] Model: `Section`（`fillable`, `$casts`, `chapter()` belongsTo, `questions()` hasMany, `images()` hasMany, `scopePublished()`（親 Chapter / Part を連鎖 whereHas）, `scopeOrdered()`, `scopeKeyword(?string)`）（REQ-content-management-003, REQ-content-management-007, REQ-content-management-022, REQ-content-management-070）
- [x] Model: `Question`（`fillable`（`category_id` 含む）, `$casts['status', 'difficulty']`, `certification()` belongsTo, `section()` belongsTo nullable, `category()` belongsTo QuestionCategory, `options()` hasMany, `mockExams()` belongsToMany, `scopePublished()`, `scopeBySection()`, `scopeStandalone()`, `scopeByCategory(?string $categoryId)`, `scopeDifficulty()`）（REQ-content-management-004, REQ-content-management-007, REQ-content-management-008, REQ-content-management-040）
- [x] Model: `QuestionCategory`（`HasUlids`, `HasFactory`, `SoftDeletes`, `fillable`, `belongsTo(Certification)`, `hasMany(Question)`, `scopeOrdered()`、業界標準寄せのカテゴリマスタ）（REQ-content-management-042, REQ-content-management-043）
- [x] Model: `QuestionOption`（`fillable`, `$casts['is_correct' => 'boolean']`, `question()` belongsTo, `scopeOrdered()`）（REQ-content-management-005）
- [x] Model: `SectionImage`（`fillable`, `section()` belongsTo, SoftDeletes）（REQ-content-management-006, REQ-content-management-055）
- [x] [[certification-management]] Certification Model 拡張: `questionCategories()` hasMany リレーション追加（REQ-content-management-042）
- [x] Factory: `PartFactory`（`draft()` / `published()` state、`forCertification($cert)` state）
- [x] Factory: `ChapterFactory`（`draft()` / `published()` state、`forPart($part)` state）
- [x] Factory: `SectionFactory`（`draft()` / `published()` state、`forChapter($chapter)` state、`withBody($markdown)` state）
- [x] Factory: `QuestionFactory`（`draft()` / `published()` state、`forCertification` state、`forSection($section)` state、`standalone()` state、`forCategory($category)` state、`withOptions(int $count, int $correctIndex)` state）
- [x] Factory: `QuestionCategoryFactory`（`forCertification($cert)` state、テクノロジー系 / マネジメント系等のサンプル name 生成）
- [x] Factory: `QuestionOptionFactory`（`correct()` / `wrong()` state）
- [x] Factory: `SectionImageFactory`（`forSection($section)` state）
- [x] [[certification-management]] への追加: `User::assignedCertifications()` BelongsToMany リレーション（`certification_coach_assignments` 経由、coach の担当判定で使用）（REQ-content-management-081）

## Step 2: Policy

- [x] Policy: `PartPolicy`（`viewAny(User, Certification)` / `view(User, Part)` / `create(User, Certification)` / `update` / `delete` / `publish` / `unpublish` / `reorder(User, Certification)`）（REQ-content-management-081, REQ-content-management-082, REQ-content-management-084）
- [x] Policy: `ChapterPolicy`（同上、ただし親が Part）（REQ-content-management-081）
- [x] Policy: `SectionPolicy`（同上、ただし親が Chapter、`view` で `Draft` を admin / 担当 coach のみ true、それ以外は false → Handler 側で 404 化）（REQ-content-management-081, REQ-content-management-084）
- [x] Policy: `QuestionPolicy`（`viewAny(User, Certification)` / `view` / `create(User, Certification)` / `update` / `delete` / `publish` / `unpublish`、ロール × 担当資格 × 登録資格判定）（REQ-content-management-081, REQ-content-management-082, REQ-content-management-085）
- [x] Policy: `SectionImagePolicy`（`create(User, Section)` / `delete(User, SectionImage)`、内部で `SectionPolicy::update` を委譲呼出）（REQ-content-management-081）
- [x] Policy: `QuestionCategoryPolicy`（`viewAny(User, Certification)` / `create(User, Certification)` / `update(User, QuestionCategory)` / `delete(User, QuestionCategory)`、admin は全資格 / coach は担当資格のみ）（REQ-content-management-047）
- [x] `AuthServiceProvider::$policies` への登録または自動検出確認

## Step 3: HTTP 層

- [x] Controller: `PartController`
- [x] Controller: `ChapterController`
- [x] Controller: `SectionController`（`preview` メソッドは AJAX JSON 応答）
- [x] Controller: `SectionImageController`（`store` / `destroy`、JSON 応答）
- [x] Controller: `QuestionController`（CRUD + publish / unpublish、`create` メソッドで `$certification->questionCategories->ordered()` を Blade に渡す）
- [x] Controller: `QuestionCategoryController`
- [x] Controller: `ContentSearchController::search`
- [x] FormRequest: `Part\StoreRequest` / `UpdateRequest` / `ReorderRequest`
- [x] FormRequest: `Chapter\StoreRequest` / `UpdateRequest` / `ReorderRequest`
- [x] FormRequest: `Section\StoreRequest` / `UpdateRequest` / `ReorderRequest` / `PreviewRequest`
- [x] FormRequest: `SectionImage\StoreRequest`
- [x] FormRequest: `Question\IndexRequest` / `StoreRequest` / `UpdateRequest`
- [x] FormRequest: `QuestionCategory\StoreRequest` / `UpdateRequest`
- [x] FormRequest: `ContentSearch\SearchRequest`
- [x] routes/web.php: `admin/...` ルートを `auth + role:admin,coach` group で定義
- [x] routes/web.php: `/contents/search` を `auth` group で定義

## Step 4: Action / Service / Exception

- [x] Action: `App\UseCases\Part\IndexAction`
- [x] Action: `App\UseCases\Part\StoreAction`
- [x] Action: `App\UseCases\Part\ShowAction`
- [x] Action: `App\UseCases\Part\UpdateAction`
- [x] Action: `App\UseCases\Part\DestroyAction`
- [x] Action: `App\UseCases\Part\PublishAction` / `UnpublishAction`
- [x] Action: `App\UseCases\Part\ReorderAction`
- [x] Action: `App\UseCases\Chapter\StoreAction` / `ShowAction` / `UpdateAction` / `DestroyAction` / `PublishAction` / `UnpublishAction` / `ReorderAction`
- [x] Action: `App\UseCases\Section\StoreAction` / `ShowAction` / `UpdateAction` / `DestroyAction` / `PublishAction` / `UnpublishAction` / `ReorderAction`
- [x] Action: `App\UseCases\Section\PreviewAction`
- [x] Action: `App\UseCases\SectionImage\StoreAction`
- [x] Action: `App\UseCases\SectionImage\DestroyAction`
- [x] Action: `App\UseCases\Question\IndexAction`
- [x] Action: `App\UseCases\Question\ShowAction`
- [x] Action: `App\UseCases\Question\StoreAction`
- [x] Action: `App\UseCases\Question\UpdateAction`
- [x] Action: `App\UseCases\Question\DestroyAction`
- [x] Action: `App\UseCases\Question\PublishAction`
- [x] Action: `App\UseCases\Question\UnpublishAction`
- [x] Action: `App\UseCases\QuestionCategory\IndexAction`
- [x] Action: `App\UseCases\QuestionCategory\StoreAction`
- [x] Action: `App\UseCases\QuestionCategory\UpdateAction`
- [x] Action: `App\UseCases\QuestionCategory\DestroyAction`
- [x] Exception: `app/Exceptions/Content/QuestionCategoryMismatchException`
- [x] Exception: `app/Exceptions/Content/QuestionCategoryInUseException`
- [x] Action: `App\UseCases\ContentSearch\SearchAction`
- [x] Service: `App\Services\MarkdownRenderingService`
- [x] Exception: `app/Exceptions/Content/ContentNotDeletableException`
- [x] Exception: `app/Exceptions/Content/ContentInvalidTransitionException`
- [x] Exception: `app/Exceptions/Content/ContentReorderInvalidException`
- [x] Exception: `app/Exceptions/Content/QuestionInvalidOptionsException`
- [x] Exception: `app/Exceptions/Content/QuestionNotPublishableException`
- [x] Exception: `app/Exceptions/Content/QuestionInUseException`
- [x] Exception: `app/Exceptions/Content/QuestionCertificationMismatchException`
- [x] Exception: `app/Exceptions/Content/SectionImageStorageException`
- [x] `app/Exceptions/Handler.php` の `HttpException` 系 catch を本 Feature 例外で動作確認（既存ハンドラの拡張、追加実装不要なら確認のみ）

## Step 5: Blade ビュー + JavaScript

- [x] Blade: `resources/views/admin/contents/parts/index.blade.php`
- [x] Blade: `resources/views/admin/contents/parts/show.blade.php`
- [x] Blade: `resources/views/admin/contents/chapters/show.blade.php`
- [x] Blade: `resources/views/admin/contents/sections/show.blade.php`
- [x] Blade: `resources/views/admin/contents/sections/_partials/markdown-editor.blade.php`
- [x] Blade: `resources/views/admin/contents/sections/_partials/image-uploader.blade.php`
- [x] Blade: `resources/views/admin/contents/sections/_partials/image-list.blade.php`
- [x] Blade: `resources/views/admin/contents/questions/index.blade.php`
- [x] Blade: `resources/views/admin/contents/questions/create.blade.php`
- [x] Blade: `resources/views/admin/contents/questions/show.blade.php`
- [x] Blade: `resources/views/admin/contents/questions/_partials/option-fieldset.blade.php`
- [x] Blade: `resources/views/admin/contents/questions/_partials/category-select.blade.php`
- [x] Blade: `resources/views/admin/contents/question-categories/index.blade.php`
- [x] Blade: `resources/views/admin/contents/question-categories/_modals/form.blade.php`
- [x] Blade: `resources/views/admin/contents/question-categories/_modals/delete-confirm.blade.php`
- [x] Blade: `resources/views/admin/contents/_partials/status-pill.blade.php`
- [x] Blade: `resources/views/admin/contents/_modals/delete-confirm.blade.php`
- [x] Blade: `resources/views/admin/contents/_modals/publish-confirm.blade.php`
- [x] Blade: `resources/views/contents/search.blade.php`
- [x] JavaScript: `resources/js/content-management/section-editor.js`
- [x] JavaScript: `resources/js/content-management/image-uploader.js`
- [x] JavaScript: `resources/js/content-management/reorder.js`

## Step 6: テスト

- [x] tests/Feature/Http/Admin/Part/IndexTest.php
- [x] tests/Feature/Http/Admin/Part/StoreTest.php
- [x] tests/Feature/Http/Admin/Part/PublishTest.php (publish / unpublish 統合)
- [x] tests/Feature/Http/Admin/Part/DestroyTest.php
- [x] tests/Feature/Http/Admin/Part/ReorderTest.php
- [x] tests/Feature/Http/Admin/Chapter/CrudTest.php
- [x] tests/Feature/Http/Admin/Section/CrudTest.php (body 更新 + Preview API)
- [x] tests/Feature/Http/Admin/SectionImage/StoreTest.php (Storage 保存確認 + サイズ / MIME 422)
- [x] tests/Feature/Http/Admin/Question/CrudTest.php (Store/Update/Publish + category mismatch + options 検証)
- [x] tests/Feature/Http/Admin/QuestionCategory/CrudTest.php (Store + UNIQUE + Destroy ガード + 認可)
- [x] tests/Feature/Http/ContentSearch/SearchTest.php
- [x] tests/Unit/Services/MarkdownRenderingServiceTest.php

## Step 7: 動作確認 & 整形

- [x] `sail artisan migrate:fresh --seed` 通過
- [x] `sail artisan test` 全 233 件 PASS（content-management 追加 50+件含む）
- [x] `sail bin pint --dirty` 整形 passed
- [x] `sail npm run build` 通過（vite.config.js に content-management/*.js を入力エントリ追加）
- [x] Phase 3 視覚検証: Part 一覧 / Section 編集 (Markdown preview 動作) / Question 作成 / QuestionCategory マスタ をブラウザで確認 — Tropical Teal デザイントークン適用済
- [ ] (実機で確認するシナリオは完了報告のチェックリスト参照)
