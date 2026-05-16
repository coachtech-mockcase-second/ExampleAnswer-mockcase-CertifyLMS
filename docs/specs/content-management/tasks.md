# content-management タスクリスト

> 1 タスク = 1 コミット粒度。Step 単位で順次実装し、完了したものから `[x]` に更新する。
> 関連要件 ID は `requirements.md` の `REQ-content-management-NNN` / `NFR-content-management-NNN` を参照。
> **v3 改修反映**: 旧 `Question` / `QuestionOption` テーブル + Model を廃止、`SectionQuestion`(`section_id` NOT NULL) + `SectionQuestionOption` に分離。`difficulty` カラム削除。`certification_id` カラム削除(section から辿る)。mock-exam 専用問題管理 UI / Action / Controller / Route 撤回。
> コマンドはすべて `sail` プレフィックス(`tech.md` の「コマンド慣習」参照)。

## Step 1: Migration & Model

### Migration

- [ ] migration: `create_parts_table`(ULID + SoftDeletes + `certification_id` FK + `title string max:200` + `description text nullable` + `order unsigned smallint` + `status enum draft/published` + `published_at datetime nullable` + `(certification_id, order)` / `(certification_id, status)` 複合 INDEX + `deleted_at` INDEX)(REQ-content-management-001, REQ-content-management-002, REQ-content-management-007, NFR-content-management-003)
- [ ] migration: `create_chapters_table`(ULID + SoftDeletes + `part_id` FK + 同上カラム + `(part_id, order)` / `(part_id, status)` INDEX)(REQ-content-management-001, REQ-content-management-003, NFR-content-management-003)
- [ ] migration: `create_sections_table`(ULID + SoftDeletes + `chapter_id` FK + `title` + `description` + **`body longtext`(Markdown max 50000 文字)** + `order` + `status` + `published_at` + `(chapter_id, order)` / `(chapter_id, status)` / `title` 単体 INDEX)(REQ-content-management-001, REQ-content-management-003, NFR-content-management-003)
- [ ] migration: `create_question_categories_table`(ULID + SoftDeletes + `certification_id` FK NOT NULL + `name string max:50` + `slug string max:60` + `sort_order unsigned smallint default 0` + `description text nullable max:500` + `(certification_id, slug)` UNIQUE + `(certification_id, sort_order)` INDEX)(REQ-content-management-042, NFR-content-management-003)
- [ ] **migration: `create_section_questions_table`(v3 新構造)** — ULID + SoftDeletes + **`section_id` FK NOT NULL** `cascadeOnDelete` + `category_id` FK NOT NULL `restrictOnDelete` to `question_categories` + `body text` + `explanation text nullable` + `order unsigned smallint default 0` + `status enum draft/published` + `published_at datetime nullable` + `(section_id, status)` / `(section_id, order)` / `category_id` 複合 INDEX(REQ-content-management-001, REQ-content-management-004, REQ-content-management-007, REQ-content-management-008, NFR-content-management-003)
  - **`certification_id` カラムは持たない**(section から辿る)
  - **`difficulty` カラムは持たない**(v3 撤回)
- [ ] **migration: `create_section_question_options_table`(v3 新構造)** — ULID + SoftDelete 不採用(delete-and-insert で同期) + `section_question_id` FK `cascadeOnDelete` + `body text` + `is_correct boolean default false` + `order unsigned smallint` + `(section_question_id, order)` INDEX(REQ-content-management-001, REQ-content-management-005, NFR-content-management-003)
- [ ] migration: `create_section_images_table`(ULID + SoftDeletes + `section_id` FK + `path string UNIQUE` + `original_filename string` + `mime_type string` + `size_bytes unsigned int` + `(section_id, deleted_at)` INDEX)(REQ-content-management-001, REQ-content-management-006, NFR-content-management-003)

### 明示的に持たない migration(v3 撤回)

- 旧 `questions` テーブル(代わりに `section_questions`)
- 旧 `question_options` テーブル(代わりに `section_question_options`)
- 中間テーブル `mock_exam_questions`(pivot 形式)(代わりに [[mock-exam]] が独自 `mock_exam_questions` を所有)

### Enum

- [ ] Enum: `App\Enums\ContentStatus`(`Draft='draft'` / `Published='published'` + `label()` 日本語)(REQ-content-management-007)

### 明示的に持たない Enum(v3 撤回)

- `App\Enums\QuestionDifficulty`(`difficulty` カラム削除に伴い不要)

### Model

- [ ] Model: `Part`(`HasUlids` + `HasFactory` + `SoftDeletes`、`fillable` / `$casts['status' => ContentStatus::class, 'published_at' => 'datetime']` / `belongsTo(Certification)` / `hasMany(Chapter::class)` / `scopePublished` / `scopeOrdered`)(REQ-content-management-002, REQ-content-management-007)
- [ ] Model: `Chapter`(`belongsTo(Part)` / `hasMany(Section::class)` / `scopePublished`(親 Part を whereHas) / `scopeOrdered`)(REQ-content-management-003, REQ-content-management-022)
- [ ] Model: `Section`(`belongsTo(Chapter)` / **`hasMany(SectionQuestion::class)`(v3)** / `hasMany(SectionImage::class)` / `scopePublished`(Chapter / Part 連鎖 whereHas) / `scopeOrdered` / `scopeKeyword(?string)`)(REQ-content-management-003, REQ-content-management-007, REQ-content-management-022, REQ-content-management-070)
- [ ] **Model: `SectionQuestion`(v3 新規)** — `HasUlids` + `HasFactory` + `SoftDeletes`、`fillable: section_id / category_id / body / explanation / order / status / published_at` / `$casts['status'=>ContentStatus::class, 'published_at'=>'datetime']` / `belongsTo(Section)` / `belongsTo(QuestionCategory, category_id)` / `hasMany(SectionQuestionOption::class)` / `hasMany(SectionQuestionAttempt)`([[quiz-answering]] テーブル) / `scopePublished` / `scopeOfSection` / `scopeByCategory(?string)` / `scopeOrdered`(REQ-content-management-004, REQ-content-management-007, REQ-content-management-008)
- [ ] **Model: `SectionQuestionOption`(v3 新規)** — `HasUlids` + `HasFactory`(SoftDelete 不採用)、`fillable` / `$casts['is_correct'=>'boolean']` / `belongsTo(SectionQuestion)` / `scopeOrdered`(REQ-content-management-005)
- [ ] Model: `QuestionCategory`(共有マスタ、変更なし) — `HasUlids` + `HasFactory` + `SoftDeletes`、`fillable` / `belongsTo(Certification)` / `hasMany(SectionQuestion::class)`(v3) / `hasMany(MockExamQuestion::class)`([[mock-exam]] 所有) / `scopeOrdered`(REQ-content-management-042, REQ-content-management-043)
- [ ] Model: `SectionImage`(`HasUlids` + `HasFactory` + `SoftDeletes`、`belongsTo(Section)`)(REQ-content-management-006)

### 明示的に削除する Model(v3 撤回)

- 旧 `Question` Model → `SectionQuestion` にリネーム
- 旧 `QuestionOption` Model → `SectionQuestionOption` にリネーム

### 関連 Feature の Model 追加

- [ ] [[certification-management]] への追加: `App\Models\Certification` に `hasMany(QuestionCategory)` リレーション追加(既存)
- [ ] [[learning]] / [[quiz-answering]] への影響: 旧 `Question` 参照を `SectionQuestion` に変更(SectionQuestionAttempt / SectionQuestionAnswer のリレーション + Model クラス名)

### Factory

- [ ] `PartFactory`(`draft()` / `published()` / `forCertification($cert)` state)
- [ ] `ChapterFactory`(`draft()` / `published()` / `forPart($part)` state)
- [ ] `SectionFactory`(`draft()` / `published()` / `forChapter($chapter)` / `withBody($markdown)` state)
- [ ] **`SectionQuestionFactory`(v3 で `QuestionFactory` から rename)** — `draft()` / `published()` / `forSection($section)` / `forCategory($category)` / `withOptions(int $count, int $correctIndex)` state、**`difficulty` state なし**
- [ ] **`SectionQuestionOptionFactory`(v3 で `QuestionOptionFactory` から rename)** — `correct()` / `wrong()` state
- [ ] `QuestionCategoryFactory`(変更なし、`forCertification($cert)` state)
- [ ] `SectionImageFactory`(変更なし、`forSection($section)` state)

## Step 2: Policy

- [ ] Policy: `PartPolicy`(`viewAny(User, Certification)` / `view(User, Part)` / `create(User, Certification)` / `update` / `delete` / `publish` / `unpublish` / `reorder(User, Certification)`)(REQ-content-management-081, REQ-content-management-082)
- [ ] Policy: `ChapterPolicy`(同上、親が Part)
- [ ] Policy: `SectionPolicy`(同上、親が Chapter、`view` で `Draft` を admin / 担当 coach のみ true)(REQ-content-management-085)
- [ ] **Policy: `SectionQuestionPolicy`(v3 で `QuestionPolicy` から rename)** — `viewAny(User, Section)` / `view(User, SectionQuestion)` / `create(User, Section)` / `update` / `delete` / `publish` / `unpublish`、ロール × 担当資格判定(`SectionQuestion.section.chapter.part.certification` 経由)(REQ-content-management-081)
- [ ] Policy: `SectionImagePolicy`(`SectionPolicy::update` 委譲)
- [ ] Policy: `QuestionCategoryPolicy`(`viewAny(User, Certification)` / `create` / `update` / `delete`、admin 全 / coach 担当資格)(REQ-content-management-047)
- [ ] `AuthServiceProvider::$policies` に登録 or 自動検出確認

### 明示的に持たない Policy(v3 撤回)

- 旧 `QuestionPolicy`(代わりに `SectionQuestionPolicy`)

## Step 3: HTTP 層

### Controller

- [ ] Controller: `PartController`(`index` / `create` / `store` / `show` / `update` / `destroy` / `publish` / `unpublish` / `reorder`)
- [ ] Controller: `ChapterController`(同上)
- [ ] Controller: `SectionController`(同上 + `preview(Section)` AJAX JSON 応答)
- [ ] **Controller: `SectionQuestionController`(v3 で `QuestionController` から rename)** — `index($section)` / `create($section)` / `store($section, StoreRequest)` / `show($question)` / `update($question, UpdateRequest)` / `destroy($question)` / `publish($question)` / `unpublish($question)`
- [ ] Controller: `SectionImageController`(`store(Section, StoreRequest)` / `destroy(SectionImage)`、JSON 応答)
- [ ] Controller: `QuestionCategoryController`(共有マスタ CRUD)
- [ ] Controller: `ContentSearchController::search`(受講生向け)

### 明示的に持たない Controller(v3 撤回)

- 旧 `QuestionController`(SectionQuestion ベースに変更、`section_id` 経由でのみアクセス)
- mock-exam 専用問題管理 Controller / Route(本 Feature では持たない)

### FormRequest

- [ ] `Part\StoreRequest` / `UpdateRequest`(`title required string max:200` / `description nullable string max:1000`) / `ReorderRequest`(`items.*.id ulid` / `items.*.order integer min:1`)
- [ ] `Chapter\StoreRequest` / `UpdateRequest` / `ReorderRequest`(同様)
- [ ] `Section\StoreRequest` / `UpdateRequest`(`body required string max:50000` を追加) / `ReorderRequest` / `PreviewRequest`(`body required string max:50000`)
- [ ] `SectionImage\StoreRequest`(`file required file mimes:png,jpg,jpeg,webp max:2048`)
- [ ] **`SectionQuestion\IndexRequest`(v3 rename)** — `category_id` / `status` フィルタ
- [ ] **`SectionQuestion\StoreRequest`(v3 rename + 簡素化)** — `body required string` / `explanation nullable string` / `category_id required ulid exists:question_categories,id` / `options required array between:2,6` / `options.*.body required string` / `options.*.is_correct required boolean` / `options.*.order required integer min:0`、`authorize` で `SectionQuestionPolicy::create($section)` 委譲、**`difficulty` rule なし**(REQ-content-management-031, REQ-content-management-033)
- [ ] **`SectionQuestion\UpdateRequest`(v3 rename)** — 同 rules、`section_id` 不可変
- [ ] `QuestionCategory\StoreRequest` / `UpdateRequest`(`name required string max:50` / `slug required string max:60 unique:question_categories,slug,NULL,id,certification_id,{cert.id}` / `sort_order nullable integer min:0` / `description nullable string max:500`)
- [ ] `ContentSearch\SearchRequest`(`certification_id required ulid` / `keyword nullable string max:200`)

### Route

- [ ] `routes/web.php`:
  - admin / coach 系(`auth + role:admin,coach` group, prefix `/admin`):
    - `Route::resource('certifications.parts', PartController::class)->shallow()` + publish / unpublish / reorder
    - `Route::resource('parts.chapters', ChapterController::class)->shallow()` + publish / unpublish
    - `Route::resource('chapters.sections', SectionController::class)->shallow()` + preview / publish
    - **`Route::resource('sections.questions', SectionQuestionController::class)->shallow()`(v3)** + publish
    - `Route::post('sections/{section}/images', SectionImageController@store)` / `Route::delete('section-images/{image}', SectionImageController@destroy)`
    - `Route::resource('certifications.question-categories', QuestionCategoryController::class)->shallow()`
  - 受講生向け(`auth + role:student + EnsureActiveLearning`):
    - `Route::get('contents/search', [ContentSearchController::class, 'search'])`

## Step 4: Action / Service / Exception

### Part Action(`App\UseCases\Part\`)

- [ ] `IndexAction` / `StoreAction` / `ShowAction` / `UpdateAction` / `DestroyAction`(`status=Published` で 409) / `PublishAction` / `UnpublishAction` / `ReorderAction`(ペイロード網羅性検証 + 一括 UPDATE)

### Chapter Action(`App\UseCases\Chapter\`)

- [ ] `StoreAction` / `ShowAction` / `UpdateAction` / `DestroyAction` / `PublishAction` / `UnpublishAction` / `ReorderAction`(Part と同様)

### Section Action(`App\UseCases\Section\`)

- [ ] `StoreAction` / `ShowAction` / `UpdateAction` / `DestroyAction` / `PublishAction` / `UnpublishAction` / `ReorderAction`
- [ ] `PreviewAction`(`MarkdownRenderingService::toHtml` 呼出、認可確認のみで Storage 副作用なし)

### SectionImage Action(`App\UseCases\SectionImage\`)

- [ ] `StoreAction`(`Storage::disk('public')->putFileAs` + DB INSERT を `DB::transaction()` 内で実行、失敗時 `SectionImageStorageException`)(REQ-content-management-050, NFR-content-management-005)
- [ ] `DestroyAction`(Storage 削除 + SoftDelete を 1 トランザクション)(REQ-content-management-053)

### SectionQuestion Action(`App\UseCases\SectionQuestion\`、v3 で大幅刷新)

- [ ] **`IndexAction`** — `with('options', 'category')->orderBy('order')->paginate(20)` + `category_id` / `status` フィルタ(REQ-content-management-030, NFR-content-management-002)
- [ ] **`ShowAction`** — `with('options', 'category')`
- [ ] **`StoreAction`** — category_id × certification 一致検証 / `is_correct` ちょうど 1 検証 / `lockForUpdate` で MAX(order) 取得 / `section_questions` INSERT + `section_question_options` 一括 INSERT、`DB::transaction`(REQ-content-management-031, REQ-content-management-032, REQ-content-management-033)
- [ ] **`UpdateAction`** — `body` / `explanation` / `category_id` UPDATE + `section_question_options` を delete-and-insert で同期、`section_id` 不可変(REQ-content-management-034, REQ-content-management-035)
- [ ] **`DestroyAction`** — SoftDelete(SectionQuestionAttempt / SectionQuestionAnswer は `withTrashed` で参照可能)(REQ-content-management-037)
- [ ] **`PublishAction`** — `status=Draft` ガード + `options.count() >= 2` + `is_correct=true count = 1` 検証 → `Published` UPDATE(REQ-content-management-036)
- [ ] **`UnpublishAction`** — `status=Published` ガード + `Draft` UPDATE

### QuestionCategory Action(`App\UseCases\QuestionCategory\`)

- [ ] `IndexAction` / `StoreAction` / `UpdateAction`
- [ ] **`DestroyAction`(v3 で SectionQuestion + MockExamQuestion 両参照確認)** — `SectionQuestion::where('category_id')->count() > 0` または `MockExamQuestion::where('category_id')->count() > 0` で `QuestionCategoryInUseException`、両ゼロで SoftDelete(REQ-content-management-046)

### ContentSearch Action(`App\UseCases\ContentSearch\`)

- [ ] `SearchAction`(`keyword === ''` で空 paginator / Enrollment(learning + passed) 検査 / cascade visibility を `whereHas` 連鎖で / スニペット生成(`buildSnippet`、前後 80 文字 + ハイライト marker))(REQ-content-management-070〜075)

### Service

- [ ] **`App\Services\MarkdownRenderingService::toHtml(string $markdown): string`** — `league/commonmark` を利用、`html_input=strip` / `allow_unsafe_links=false` / `safe_links_policy`(外部リンク `nofollow noopener noreferrer` + `target=_blank`) / `unallowed_attributes`(`onclick` / `onerror` / `onload` / `style`) / `img_src_whitelist`(`/storage/section-images/` / `https://`)(REQ-content-management-060〜064)

### Exception(`app/Exceptions/Content/`)

- [ ] `ContentNotDeletableException`(HTTP 409)(REQ-content-management-014)
- [ ] `ContentInvalidTransitionException`(HTTP 409)(REQ-content-management-020〜021)
- [ ] `ContentReorderInvalidException`(HTTP 422)(REQ-content-management-024)
- [ ] **`QuestionInvalidOptionsException`(HTTP 422)** — `is_correct` ≠ 1 件で(REQ-content-management-033)
- [ ] **`QuestionNotPublishableException`(HTTP 409)** — options 2 件未満 or is_correct ≠ 1 で(REQ-content-management-036)
- [ ] **`QuestionCategoryMismatchException`(HTTP 422)** — category の certification 不一致で(REQ-content-management-031)
- [ ] **`QuestionCategoryInUseException`(HTTP 409)** — SectionQuestion または MockExamQuestion 参照ありで(REQ-content-management-046)
- [ ] `SectionImageStorageException`(HTTP 500)(REQ-content-management-050)

### 明示的に持たない Exception(v3 撤回)

- `QuestionInUseException`(中間テーブル経由の依存判定が不要に)
- `QuestionCertificationMismatchException`(`certification_id` 直接参照削除に伴い不要)

### Handler

- [ ] `app/Exceptions/Handler.php::register()` で各ドメイン例外を HTTP ステータスに mapping(Web: flash + redirect back / API: JSON 返却)

## Step 5: Blade ビュー + JavaScript

### Blade

- [ ] `admin/contents/parts/index.blade.php` / `show.blade.php`(Part 一覧 + 詳細 + drag-and-drop reorder)
- [ ] `admin/contents/chapters/show.blade.php`(Section 一覧)
- [ ] `admin/contents/sections/show.blade.php`(Section 編集 + Markdown エディタ + プレビュー + 画像アップ)
- [ ] `admin/contents/sections/_partials/markdown-editor.blade.php`
- [ ] `admin/contents/sections/_partials/image-uploader.blade.php`
- [ ] `admin/contents/sections/_partials/image-list.blade.php`
- [ ] **`admin/contents/section-questions/index.blade.php`(v3 rename)** — 当該 Section 配下の SectionQuestion 一覧 + reorder + 各行 edit / delete / publish 切替
- [ ] **`admin/contents/section-questions/create.blade.php`(v3 rename + 簡素化)** — `body` / `explanation` / `category_id` / `options[]` 2..6 件、**`difficulty` 入力欄なし**
- [ ] **`admin/contents/section-questions/show.blade.php` / `edit.blade.php`**
- [ ] **`admin/contents/section-questions/_partials/option-fieldset.blade.php`** — options 入力(is_correct radio 単一選択)
- [ ] **`admin/contents/section-questions/_partials/category-select.blade.php`** — 対象 Certification 配下の QuestionCategory のみ select
- [ ] `admin/contents/question-categories/index.blade.php`(モーダル UI で CRUD)
- [ ] `admin/contents/question-categories/_modals/form.blade.php` / `delete-confirm.blade.php`
- [ ] `admin/contents/_partials/status-pill.blade.php`(Draft / Published バッジ)
- [ ] `admin/contents/_modals/delete-confirm.blade.php` / `publish-confirm.blade.php`
- [ ] `contents/search.blade.php`(受講生検索画面)

### 明示的に持たない Blade(v3 撤回)

- 旧 `admin/contents/questions/*`(代わりに `section-questions/*`)
- mock-exam 専用問題作成タブ / モーダル

### JavaScript(`resources/js/content-management/`)

- [ ] `section-editor.js`(Markdown エディタ + サーバプレビュー API 呼出)
- [ ] `image-uploader.js`(drag-and-drop アップロード)
- [ ] `reorder.js`(drag-and-drop reorder ペイロード送信)

## Step 6: テスト

### Feature(HTTP)

- [ ] `tests/Feature/Http/Admin/Part/{Index,Store,Publish,Destroy,Reorder}Test.php`
- [ ] `tests/Feature/Http/Admin/Chapter/CrudTest.php`
- [ ] `tests/Feature/Http/Admin/Section/CrudTest.php`(body 更新 + Preview API)
- [ ] **`tests/Feature/Http/Admin/SectionQuestion/CrudTest.php`(v3 rename)** — Store / Update / Publish / Destroy + category_id 不一致 422 + is_correct 多重 422 + options 1 件で 422 + 公開化条件チェック + 担当外 coach 403 + `difficulty` 関連テスト削除
- [ ] `tests/Feature/Http/Admin/SectionImage/StoreTest.php`(Storage 保存 + サイズ/MIME 422)
- [ ] `tests/Feature/Http/Admin/QuestionCategory/CrudTest.php`(Store + UNIQUE + **Destroy ガード(SectionQuestion + MockExamQuestion 両参照確認)** + 認可)
- [ ] `tests/Feature/Http/ContentSearch/SearchTest.php`(登録資格内 / Published のみ / cascade visibility / `graduated` ユーザー 403 / 空キーワードゼロ件)

### Feature(UseCases)

- [ ] `tests/Feature/UseCases/SectionQuestion/StoreActionTest.php`(category_id 不一致 / is_correct 多重 / options delete-and-insert)
- [ ] `tests/Feature/UseCases/SectionQuestion/PublishActionTest.php`(options 1 件で 409 / is_correct ≠ 1 で 409 / 正常 200)
- [ ] `tests/Feature/UseCases/QuestionCategory/DestroyActionTest.php`(SectionQuestion 参照あり 409 / MockExamQuestion 参照あり 409 / 両ゼロで SoftDelete)

### Unit(Services)

- [ ] `tests/Unit/Services/MarkdownRenderingServiceTest.php`(基本変換 / `<script>` strip / `<a>` rel 付与 / `<img src>` ホワイトリスト / イベント属性除去)

### 明示的に持たないテスト(v3 撤回)

- 旧 `Admin/Question/*Test.php`(代わりに `Admin/SectionQuestion/*Test.php`)
- `difficulty` バリデーション関連
- mock-exam 専用問題作成テスト

## Step 7: 動作確認 & 整形

- [ ] `sail artisan migrate:fresh --seed`
- [ ] `sail artisan test --filter=Content` 全件 pass
- [ ] `sail artisan test --filter=SectionQuestion` 全件 pass(v3 関連)
- [ ] `sail artisan test` 全体実行で他 Feature への副作用なし(特に [[quiz-answering]] / [[learning]] が `SectionQuestion` 参照に追従しているか)
- [ ] `sail bin pint --dirty` 整形
- [ ] `sail npm run build`(vite で content-management の JS bundle)
- [ ] ブラウザ動作確認シナリオ:
  - [ ] admin で Part / Chapter / Section 作成 → Draft → Published 切替
  - [ ] Section 編集で Markdown 入力 → プレビュー API 呼出 → HTML 表示
  - [ ] 画像アップロード → 「`![](/storage/section-images/{ulid}.png)`」コピペ
  - [ ] SectionQuestion 作成 → category 選択 → options 4 件 + is_correct 1 件 → 保存
  - [ ] is_correct 0 件で送信 → 422
  - [ ] options 1 件で送信 → 422
  - [ ] 他資格の category_id を選択しようとして 422
  - [ ] SectionQuestion 公開試行 → options 条件満たす場合のみ公開可
  - [ ] QuestionCategory 削除試行 → SectionQuestion 参照ありなら 409 / MockExamQuestion 参照ありなら 409
  - [ ] 受講生で `/contents/search?certification_id&keyword=...` → 該当 Section のみ表示(登録資格内 + Published のみ)
  - [ ] cascade visibility: Part Draft 化 → 配下 Section が検索に出ない
  - [ ] `graduated` ユーザーで `/contents/search` → 403
  - [ ] 担当外 coach で他資格の SectionQuestion 編集試行 → 403
