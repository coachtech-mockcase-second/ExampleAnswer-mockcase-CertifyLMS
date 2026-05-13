# content-management タスクリスト

> 1タスク = 1コミット粒度。Step 単位で順次実装し、完了したものから `[x]` に更新する。
> 関連要件 ID は `requirements.md` の `REQ-content-management-NNN` / `NFR-content-management-NNN` を参照。
> コマンドは `tech.md`「コマンド慣習」セクションの通り Sail プレフィックスで統一する（`sail artisan ...` / `sail npm ...` / `sail bin pint ...`）。

## Step 1: Migration & Model

- [ ] migration: `create_parts_table`（ULID, `certification_id` FK, `title`, `description nullable`, `order unsigned`, `status enum draft/published`, `published_at nullable`, `timestamps`, `softDeletes`、`(certification_id, order)` / `(certification_id, status)` 複合 INDEX, `deleted_at` INDEX）（REQ-content-management-001, REQ-content-management-002, REQ-content-management-007, REQ-content-management-009, NFR-content-management-003）
- [ ] migration: `create_chapters_table`（ULID, `part_id` FK, `title`, `description nullable`, `order`, `status`, `published_at`, `timestamps`, `softDeletes`、`(part_id, order)` / `(part_id, status)` INDEX）（REQ-content-management-001, REQ-content-management-003, REQ-content-management-009, NFR-content-management-003）
- [ ] migration: `create_sections_table`（ULID, `chapter_id` FK, `title`, `description nullable`, `body longtext`, `order`, `status`, `published_at`, `timestamps`, `softDeletes`、`(chapter_id, order)` / `(chapter_id, status)` / `sections.title` INDEX）（REQ-content-management-001, REQ-content-management-003, REQ-content-management-009, NFR-content-management-003）
- [ ] migration: `create_questions_table`（ULID, `certification_id` FK NOT NULL, `section_id` FK NULLABLE, `body text`, `explanation text nullable`, `category string 50`, `difficulty enum easy/medium/hard`, `order`, `status`, `published_at`, `timestamps`, `softDeletes`、`(certification_id, status)` / `section_id` / `category` / `(certification_id, difficulty)` INDEX）（REQ-content-management-001, REQ-content-management-004, REQ-content-management-007, REQ-content-management-008, NFR-content-management-003）
- [ ] migration: `create_question_options_table`（ULID, `question_id` FK cascadeOnDelete, `body text`, `is_correct boolean default false`, `order`, `timestamps`、`(question_id, order)` INDEX、SoftDeletes は採用しない）（REQ-content-management-001, REQ-content-management-005, NFR-content-management-003）
- [ ] migration: `create_section_images_table`（ULID, `section_id` FK, `path string UNIQUE`, `original_filename string`, `mime_type string`, `size_bytes unsigned int`, `timestamps`, `softDeletes`、`(section_id, deleted_at)` INDEX）（REQ-content-management-001, REQ-content-management-006, NFR-content-management-003）
- [ ] Enum: `ContentStatus`（`Draft` / `Published` + `label()`）（REQ-content-management-007）
- [ ] Enum: `QuestionDifficulty`（`Easy` / `Medium` / `Hard` + `label()`）（REQ-content-management-008）
- [ ] Model: `Part`（`fillable`, `$casts['status' => ContentStatus::class, 'published_at' => 'datetime']`, `certification()` belongsTo, `chapters()` hasMany, `scopePublished()`, `scopeOrdered()`）（REQ-content-management-002, REQ-content-management-007）
- [ ] Model: `Chapter`（`fillable`, `$casts`, `part()` belongsTo, `sections()` hasMany, `scopePublished()`（親 Part を whereHas）, `scopeOrdered()`）（REQ-content-management-003, REQ-content-management-007, REQ-content-management-022）
- [ ] Model: `Section`（`fillable`, `$casts`, `chapter()` belongsTo, `questions()` hasMany, `images()` hasMany, `scopePublished()`（親 Chapter / Part を連鎖 whereHas）, `scopeOrdered()`, `scopeKeyword(?string)`）（REQ-content-management-003, REQ-content-management-007, REQ-content-management-022, REQ-content-management-070）
- [ ] Model: `Question`（`fillable`, `$casts['status', 'difficulty']`, `certification()` belongsTo, `section()` belongsTo nullable, `options()` hasMany, `mockExams()` belongsToMany, `scopePublished()`, `scopeBySection()`, `scopeStandalone()`, `scopeCategory()`, `scopeDifficulty()`）（REQ-content-management-004, REQ-content-management-007, REQ-content-management-040）
- [ ] Model: `QuestionOption`（`fillable`, `$casts['is_correct' => 'boolean']`, `question()` belongsTo, `scopeOrdered()`）（REQ-content-management-005）
- [ ] Model: `SectionImage`（`fillable`, `section()` belongsTo, SoftDeletes）（REQ-content-management-006, REQ-content-management-055）
- [ ] Factory: `PartFactory`（`draft()` / `published()` state、`forCertification($cert)` state）
- [ ] Factory: `ChapterFactory`（`draft()` / `published()` state、`forPart($part)` state）
- [ ] Factory: `SectionFactory`（`draft()` / `published()` state、`forChapter($chapter)` state、`withBody($markdown)` state）
- [ ] Factory: `QuestionFactory`（`draft()` / `published()` state、`forCertification` state、`forSection($section)` state、`standalone()` state、`withOptions(int $count, int $correctIndex)` state）
- [ ] Factory: `QuestionOptionFactory`（`correct()` / `wrong()` state）
- [ ] Factory: `SectionImageFactory`（`forSection($section)` state）
- [ ] [[certification-management]] への追加: `User::assignedCertifications()` BelongsToMany リレーション（`certification_coach_assignments` 経由、coach の担当判定で使用）（REQ-content-management-081）

## Step 2: Policy

- [ ] Policy: `PartPolicy`（`viewAny(User, Certification)` / `view(User, Part)` / `create(User, Certification)` / `update` / `delete` / `publish` / `unpublish` / `reorder(User, Certification)`）（REQ-content-management-081, REQ-content-management-082, REQ-content-management-084）
- [ ] Policy: `ChapterPolicy`（同上、ただし親が Part）（REQ-content-management-081）
- [ ] Policy: `SectionPolicy`（同上、ただし親が Chapter、`view` で `Draft` を admin / 担当 coach のみ true、それ以外は false → Handler 側で 404 化）（REQ-content-management-081, REQ-content-management-084）
- [ ] Policy: `QuestionPolicy`（`viewAny(User, Certification)` / `view` / `create(User, Certification)` / `update` / `delete` / `publish` / `unpublish`、ロール × 担当資格 × 登録資格判定）（REQ-content-management-081, REQ-content-management-082, REQ-content-management-085）
- [ ] Policy: `SectionImagePolicy`（`create(User, Section)` / `delete(User, SectionImage)`、内部で `SectionPolicy::update` を委譲呼出）（REQ-content-management-081）
- [ ] `AuthServiceProvider::$policies` への登録または自動検出確認

## Step 3: HTTP 層

- [ ] Controller: `PartController`（薄く保つ、各メソッドは同名 Action を `__invoke` で呼ぶ）（REQ-content-management-010, REQ-content-management-011, REQ-content-management-012, REQ-content-management-013, REQ-content-management-020, REQ-content-management-021, REQ-content-management-023）
- [ ] Controller: `ChapterController`（同上）
- [ ] Controller: `SectionController`（`preview` メソッドは AJAX JSON 応答）（REQ-content-management-011, REQ-content-management-012, NFR-content-management-007）
- [ ] Controller: `SectionImageController`（`store` / `destroy`、JSON 応答）（REQ-content-management-050, REQ-content-management-053）
- [ ] Controller: `QuestionController`（CRUD + publish / unpublish）（REQ-content-management-030, REQ-content-management-031, REQ-content-management-034, REQ-content-management-036, REQ-content-management-037）
- [ ] Controller: `ContentSearchController::search`（受講生向け、`/contents/search` ルート）（REQ-content-management-070, REQ-content-management-072）
- [ ] FormRequest: `Part\StoreRequest` / `UpdateRequest` / `ReorderRequest`（authorize で Policy 呼出、rules で max 値）（REQ-content-management-012, REQ-content-management-024）
- [ ] FormRequest: `Chapter\StoreRequest` / `UpdateRequest` / `ReorderRequest`
- [ ] FormRequest: `Section\StoreRequest` / `UpdateRequest` / `ReorderRequest` / `PreviewRequest`（body max 50000）（REQ-content-management-012）
- [ ] FormRequest: `SectionImage\StoreRequest`（`file: required file mimes:png,jpg,jpeg,webp max:2048`）（REQ-content-management-051, REQ-content-management-052）
- [ ] FormRequest: `Question\IndexRequest` / `StoreRequest` / `UpdateRequest`（`options` array, `is_correct` boolean, `section_id` nullable ulid）（REQ-content-management-030, REQ-content-management-031, REQ-content-management-032, REQ-content-management-034）
- [ ] FormRequest: `ContentSearch\SearchRequest`（`certification_id` required ulid, `keyword` nullable string max 200）（REQ-content-management-070）
- [ ] routes/web.php: `admin/...` ルートを `auth + role:admin,coach` group で定義（Part / Chapter / Section / SectionImage / Question / Preview / Reorder の各エンドポイント）（REQ-content-management-080）
- [ ] routes/web.php: `/contents/search` を `auth` group で定義（REQ-content-management-070, REQ-content-management-080）

## Step 4: Action / Service / Exception

- [ ] Action: `App\UseCases\Part\IndexAction`（`with('chapters')` + `withCount('sections')` Eager Loading）（REQ-content-management-010, NFR-content-management-002）
- [ ] Action: `App\UseCases\Part\StoreAction`（`lockForUpdate` で `MAX(order)+1`、`status=Draft` 固定）（REQ-content-management-011, NFR-content-management-001）
- [ ] Action: `App\UseCases\Part\ShowAction`（`with('chapters.sections')`）（REQ-content-management-010, NFR-content-management-002）
- [ ] Action: `App\UseCases\Part\UpdateAction`（`title` / `description` のみ更新）（REQ-content-management-012, NFR-content-management-001）
- [ ] Action: `App\UseCases\Part\DestroyAction`（`Draft` 以外なら `ContentNotDeletableException`）（REQ-content-management-013, REQ-content-management-014, NFR-content-management-001）
- [ ] Action: `App\UseCases\Part\PublishAction` / `UnpublishAction`（遷移ガード + `ContentInvalidTransitionException`）（REQ-content-management-020, REQ-content-management-021, NFR-content-management-001）
- [ ] Action: `App\UseCases\Part\ReorderAction`（ID 検証 + `(certification_id, order)` 一斉 UPDATE）（REQ-content-management-023, REQ-content-management-024, NFR-content-management-001）
- [ ] Action: `App\UseCases\Chapter\StoreAction` / `ShowAction` / `UpdateAction` / `DestroyAction` / `PublishAction` / `UnpublishAction` / `ReorderAction`（Part と同パターン）（REQ-content-management-011, REQ-content-management-012, REQ-content-management-013, REQ-content-management-014, REQ-content-management-020, REQ-content-management-021, REQ-content-management-023, REQ-content-management-024）
- [ ] Action: `App\UseCases\Section\StoreAction` / `ShowAction` / `UpdateAction` / `DestroyAction` / `PublishAction` / `UnpublishAction` / `ReorderAction`（`body` 更新を含む）（REQ-content-management-011, REQ-content-management-012, REQ-content-management-013, REQ-content-management-014, REQ-content-management-020, REQ-content-management-021, REQ-content-management-023, REQ-content-management-024）
- [ ] Action: `App\UseCases\Section\PreviewAction`（`MarkdownRenderingService::toHtml` を呼ぶ pure passthrough、DB 操作なし）（NFR-content-management-007）
- [ ] Action: `App\UseCases\SectionImage\StoreAction`（Storage 書き込み + DB INSERT、DB 失敗時に Storage 手動巻き戻し）（REQ-content-management-050, REQ-content-management-054, NFR-content-management-005）
- [ ] Action: `App\UseCases\SectionImage\DestroyAction`（SoftDelete + `DB::afterCommit` で Storage 物理削除）（REQ-content-management-053, NFR-content-management-005）
- [ ] Action: `App\UseCases\Question\IndexAction`（フィルタ + `with('section.chapter.part', 'options')` Eager Loading + `paginate(20)`）（REQ-content-management-030, REQ-content-management-040, NFR-content-management-002）
- [ ] Action: `App\UseCases\Question\ShowAction`（`with('options')`）
- [ ] Action: `App\UseCases\Question\StoreAction`（`section_id` certification 一致検証 + `is_correct=true` 件数検証 + 一括 INSERT）（REQ-content-management-031, REQ-content-management-032, REQ-content-management-033, REQ-content-management-041, NFR-content-management-001）
- [ ] Action: `App\UseCases\Question\UpdateAction`（`certification_id` 変更不可 + `section_id` 整合検証 + `options` delete-and-insert）（REQ-content-management-034, REQ-content-management-035, REQ-content-management-041, NFR-content-management-001）
- [ ] Action: `App\UseCases\Question\DestroyAction`（`mock_exam_questions` 参照チェック + SoftDelete）（REQ-content-management-037, NFR-content-management-001）
- [ ] Action: `App\UseCases\Question\PublishAction`（options >=2 + is_correct=1 二重ガード + 遷移）（REQ-content-management-036, NFR-content-management-001）
- [ ] Action: `App\UseCases\Question\UnpublishAction`（遷移ガード）（REQ-content-management-021）
- [ ] Action: `App\UseCases\ContentSearch\SearchAction`（keyword 空 / 未登録時の空 paginator、`paginate(20)`、`extractSnippet` 呼出）（REQ-content-management-070, REQ-content-management-071, REQ-content-management-072, REQ-content-management-073, REQ-content-management-074, REQ-content-management-075, REQ-content-management-083）
- [ ] Service: `App\Services\MarkdownRenderingService`（`toHtml` + `extractSnippet`、`league/commonmark` の `html_input=strip` + `allow_unsafe_links=false` 設定）（REQ-content-management-060, REQ-content-management-061, REQ-content-management-062, REQ-content-management-063, REQ-content-management-064, REQ-content-management-065, REQ-content-management-073）
- [ ] Exception: `app/Exceptions/Content/ContentNotDeletableException`（ConflictHttpException 409）（REQ-content-management-014, NFR-content-management-004）
- [ ] Exception: `app/Exceptions/Content/ContentInvalidTransitionException`（ConflictHttpException 409）（REQ-content-management-020, REQ-content-management-021, NFR-content-management-004）
- [ ] Exception: `app/Exceptions/Content/ContentReorderInvalidException`（UnprocessableEntityHttpException 422）（REQ-content-management-024, NFR-content-management-004）
- [ ] Exception: `app/Exceptions/Content/QuestionInvalidOptionsException`（UnprocessableEntityHttpException 422）（REQ-content-management-033, NFR-content-management-004）
- [ ] Exception: `app/Exceptions/Content/QuestionNotPublishableException`（ConflictHttpException 409）（REQ-content-management-036, NFR-content-management-004）
- [ ] Exception: `app/Exceptions/Content/QuestionInUseException`（ConflictHttpException 409）（REQ-content-management-037, NFR-content-management-004）
- [ ] Exception: `app/Exceptions/Content/QuestionCertificationMismatchException`（UnprocessableEntityHttpException 422）（REQ-content-management-041, NFR-content-management-004）
- [ ] Exception: `app/Exceptions/Content/SectionImageStorageException`（HttpException 500）（REQ-content-management-050, NFR-content-management-004）
- [ ] `app/Exceptions/Handler.php` の `HttpException` 系 catch を本 Feature 例外で動作確認（既存ハンドラの拡張、追加実装不要なら確認のみ）

## Step 5: Blade ビュー + JavaScript

- [ ] Blade: `resources/views/admin/contents/parts/index.blade.php`（Part 一覧 + 「+新規」 + reorder ハンドル）
- [ ] Blade: `resources/views/admin/contents/parts/show.blade.php`（Part 詳細 + 編集フォーム + Chapter 一覧）
- [ ] Blade: `resources/views/admin/contents/chapters/show.blade.php`（Chapter 詳細 + 編集 + Section 一覧）
- [ ] Blade: `resources/views/admin/contents/sections/show.blade.php`（Section 詳細 + Markdown 編集テキストエリア + プレビューペイン + 画像アップロード）
- [ ] Blade: `resources/views/admin/contents/sections/_partials/markdown-editor.blade.php`
- [ ] Blade: `resources/views/admin/contents/sections/_partials/image-uploader.blade.php`
- [ ] Blade: `resources/views/admin/contents/sections/_partials/image-list.blade.php`
- [ ] Blade: `resources/views/admin/contents/questions/index.blade.php`（フィルタ + 一覧 + ページネーション）
- [ ] Blade: `resources/views/admin/contents/questions/create.blade.php`（新規作成フォーム + options 入力）
- [ ] Blade: `resources/views/admin/contents/questions/show.blade.php`（詳細 + 編集 + 公開ボタン）
- [ ] Blade: `resources/views/admin/contents/questions/_partials/option-fieldset.blade.php`（options 2-6 個、`is_correct` ラジオで唯一性担保）
- [ ] Blade: `resources/views/admin/contents/_partials/status-pill.blade.php`（status バッジ）
- [ ] Blade: `resources/views/admin/contents/_modals/delete-confirm.blade.php`
- [ ] Blade: `resources/views/admin/contents/_modals/publish-confirm.blade.php`
- [ ] Blade: `resources/views/contents/search.blade.php`（受講生向け検索フォーム + 結果リスト + スニペット表示）
- [ ] JavaScript: `resources/js/content-management/section-editor.js`（textarea input → debounce → preview POST → プレビュー描画、`utils/fetch-json.js` 経由）（NFR-content-management-007）
- [ ] JavaScript: `resources/js/content-management/image-uploader.js`（ファイル選択 → multipart POST → 成功時 Markdown 挿入）
- [ ] JavaScript: `resources/js/content-management/reorder.js`（ドラッグ&ドロップで `[{id, order}, ...]` 構築 → reorder PATCH）

## Step 6: テスト

- [ ] tests/Feature/Http/Part/IndexTest.php（admin / coach 担当 / coach 非担当 / student 各ロールの可否、SoftDelete 行除外）
- [ ] tests/Feature/Http/Part/StoreTest.php（正常系 + バリデーション失敗 + 認可漏れ + `status=Draft` 固定 + `order` 自動採番）
- [ ] tests/Feature/Http/Part/UpdateTest.php（正常系 + 認可漏れ + 他資格 Part 非更新）
- [ ] tests/Feature/Http/Part/DestroyTest.php（draft 削除 OK + published 削除拒否 409）
- [ ] tests/Feature/Http/Part/PublishTest.php（draft→published OK + published→published 409）
- [ ] tests/Feature/Http/Part/UnpublishTest.php（published→draft OK + draft→draft 409）
- [ ] tests/Feature/Http/Part/ReorderTest.php（正常系 + ID 不足 / 重複 / 連番違反 422）
- [ ] tests/Feature/Http/Chapter/*（Part と同パターン、5 シナリオ）
- [ ] tests/Feature/Http/Section/*（Part と同パターン + `body` 更新 + preview API 200）
- [ ] tests/Feature/Http/SectionImage/StoreTest.php（正常系 + サイズ超過 422 + 非対応 MIME 422 + Storage に保存確認 + DB に INSERT 確認 + Section 認可）
- [ ] tests/Feature/Http/SectionImage/DestroyTest.php（SoftDelete 確認 + Storage 削除確認）
- [ ] tests/Feature/Http/Question/IndexTest.php（フィルタ network: category / difficulty / status / standalone_only、ロール別認可）
- [ ] tests/Feature/Http/Question/StoreTest.php（正常系 + options 0/1/3 件 正答ケースで `QuestionInvalidOptionsException` 422 + `section_id` certification 不一致で 422 + 認可漏れ）
- [ ] tests/Feature/Http/Question/UpdateTest.php（正常系 + options delete-and-insert 確認 + `certification_id` 不変 + `section_id` 整合）
- [ ] tests/Feature/Http/Question/DestroyTest.php（未使用 SoftDelete OK + mock-exam 参照中で 409）
- [ ] tests/Feature/Http/Question/PublishTest.php（options >=2 + is_correct=1 で OK + 違反で 409 + draft 以外で 409）
- [ ] tests/Feature/Http/ContentSearch/SearchTest.php（受講生 + 登録資格内 + 公開 Section ヒット、未登録資格で空、未公開 Section 除外、keyword 空で空、`title` ヒット / `body` ヒット 両方、スニペット含む、`paginate(20)`）
- [ ] tests/Feature/UseCases/Section/ReorderActionTest.php（同一親内のみ受付、Cross-parent 拒否、ID 重複拒否）
- [ ] tests/Feature/UseCases/Question/StoreActionTest.php（certification 整合検証 + is_correct 検証の境界）
- [ ] tests/Feature/UseCases/SectionImage/StoreActionTest.php（DB 失敗時に Storage が巻き戻されることを fake で確認）
- [ ] tests/Unit/Services/MarkdownRenderingServiceTest.php（`<script>` 除去 + `javascript:` 拒否 + `<a>` の `rel/target` + コードブロック出力 + `extractSnippet` の前後 padding / 該当なし時の挙動）
- [ ] tests/Unit/Policies/PartPolicyTest.php（admin × coach 担当 × coach 非担当 × student 登録 × student 未登録 の全組合せで真偽値網羅）
- [ ] tests/Unit/Policies/ChapterPolicyTest.php / SectionPolicyTest.php / QuestionPolicyTest.php / SectionImagePolicyTest.php

## Step 7: 動作確認 & 整形

- [ ] `sail artisan migrate:fresh --seed` 通過
- [ ] `sail artisan test --filter=Part` / `--filter=Chapter` / `--filter=Section` / `--filter=Question` / `--filter=ContentSearch` / `--filter=MarkdownRenderingService` 通過
- [ ] `sail bin pint --dirty` 整形
- [ ] ブラウザでの主要画面動作確認（コーチアカウントで以下の通しシナリオ）:
  - 担当資格の Part 一覧へアクセス → 「+新規 Part」で Draft 作成 → 編集画面で title を更新 → publish ボタンで公開遷移
  - Part → Chapter → Section と階層を辿り、Section 編集画面で Markdown 入力 → プレビューペインに HTML 反映
  - 画像アップロード UI で `.png` をアップロード → Markdown editor に自動挿入 → プレビューで画像表示
  - Section の reorder（drag & drop で順序入替）→ 一覧の order が更新される
  - Section delete（draft のみ） → SoftDelete + 一覧から消える
  - Question 一覧 → 「+新規」で options 4 件入力（うち 1 件正答）→ Draft 保存 → publish 遷移
  - Question 編集で options を 3 件に変更 → 保存 → DB で options 物理 delete-and-insert を確認
  - Question delete を試み、mock-exam で使用中なら 409 で阻害される（[[mock-exam]] 実装後に確認）
- [ ] ブラウザでの受講生動作確認:
  - 登録資格の Section が `/contents/search?certification_id=...&keyword=...` で検索ヒット
  - 未登録資格を `certification_id` 指定で叩いても空結果（403/404 ではない）
  - Draft Section / Draft Part 配下の Section は検索ヒットしない
- [ ] Markdown XSS 試験: `<script>alert(1)</script>` / `[click](javascript:alert(1))` を Section.body に保存 → プレビュー API / `/contents/search` のスニペット出力で発火しないことを確認
- [ ] 画像アップロード上限試験: 2.1MB の画像で 422 / `.gif` / `.svg` で 422 / `.png` 正常受領
