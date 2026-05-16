# content-management 要件定義

> **v3 改修反映**（2026-05-16）: 旧 `Question` テーブルを廃止、`SectionQuestion`（`section_id` NOT NULL）+ `SectionQuestionOption` に分離。模試問題は [[mock-exam]] が `MockExamQuestion` として独立管理。`difficulty` カラム削除。mock-exam 専用問題管理 UI は本 Feature から撤回。

## 概要

担当資格のコンテンツ階層（**Part → Chapter → Section**）と **Section 紐づき問題（SectionQuestion / SectionQuestionOption）**、**問題カテゴリマスタ（QuestionCategory）**、**教材内画像（SectionImage）** を管理する Feature。コーチは Markdown による Section 本文編集、Section に紐づく選択肢付き問題の作成、画像アップロード、`draft / published` 公開制御、順序入替を行う。受講生向けには教材内 Section の **全文検索** を提供。階層ブラウジングや読了マークは [[learning]] が担う。

**模試問題（`MockExamQuestion`）は本 Feature では扱わない**（[[mock-exam]] が所有、模試マスタの子リソースとして独立管理）。`QuestionCategory` は両系統の共有マスタとして本 Feature が CRUD を所有。

## ロールごとのストーリー

- **受講生（student）**: 自分が登録した資格の公開済 Section を `Section.title` / `Section.body` で全文検索し、該当 Section の閲覧画面（[[learning]] 側）へ遷移する。下書きや未登録資格の教材は検索結果に表示されない。
- **コーチ（coach）**: 自分の担当資格に対して Part / Chapter / Section / SectionQuestion / SectionQuestionOption / QuestionCategory / SectionImage を CRUD する。Markdown 本文に教材内画像を埋め込み、Section 紐づき問題を作成・編集する。問題作成時は担当資格内の `QuestionCategory` から出題分野を選択する。`draft → published` の公開制御を行い、順序入替で表示順を整える。**mock-exam 専用問題は本 Feature では作成しない**（mock-exam の模試詳細画面で MockExamQuestion として作成）。
- **管理者（admin）**: 全資格の上記リソースに対してコーチと同等の操作を行える（運用補助・代行）。

## 受け入れ基準（EARS形式）

### 機能要件 — A. データモデル

- **REQ-content-management-001**: The system shall ULID 主キーと `SoftDeletes` を備えた `parts` / `chapters` / `sections` / `section_questions` / `section_question_options` / `section_images` テーブルを提供する。**旧 `questions` / `question_options` テーブルは廃止**。
- **REQ-content-management-002**: The system shall `parts.certification_id` を `certifications.id` への外部キーとして持ち、Part を資格マスタに紐付ける。
- **REQ-content-management-003**: The system shall `chapters.part_id` / `sections.chapter_id` の外部キーで `Certification → Part → Chapter → Section` の階層を構成する。
- **REQ-content-management-004**: The system shall `section_questions.section_id` を `sections.id` への外部キー（**NOT NULL**、旧 nullable 撤回）として持つ。`certification_id` カラムは持たず、section から辿る（`Section::part::certification`）。
- **REQ-content-management-005**: The system shall `section_question_options.section_question_id` を `section_questions.id` への外部キーとして持ち、各 SectionQuestion に 1 対多で選択肢を紐付ける。`is_correct` boolean カラムで正答フラグを保持する。
- **REQ-content-management-006**: The system shall `section_images.section_id` を `sections.id` への外部キーとして持ち、各 Section に 1 対多で画像メタデータを紐付ける。
- **REQ-content-management-007**: The system shall `Part.status` / `Chapter.status` / `Section.status` / `SectionQuestion.status` に共通 Enum `ContentStatus`（`Draft` / `Published`）を提供し、`label()` メソッドで日本語ラベル（`下書き` / `公開中`）を返す。
- **REQ-content-management-008**: The system shall `SectionQuestion.category_id` を `question_categories.id` への外部キー（必須）として保持する。**`difficulty` カラムは持たない**（v3 撤回）。
- **REQ-content-management-009**: The system shall `parts` / `chapters` / `sections` / `section_questions` に `order`（unsigned integer）カラムを持たせ、`(parent_id, order)` で並び順を一意に決定する。

### 機能要件 — B. 教材階層管理（Part / Chapter / Section CRUD）

- **REQ-content-management-010**: When 認可された coach または admin が `/admin/certifications/{certification}/parts` にアクセスした際, the system shall 当該資格配下の Part 一覧を `order ASC` 順に表示する。
- **REQ-content-management-011**: The system shall Part / Chapter / Section の新規作成時に `status=Draft` 固定で INSERT し、`order` は同一親配下の `MAX(order) + 1` を自動採番する。
- **REQ-content-management-012**: The system shall Part / Chapter / Section の編集時に `title`（required, max 200）/ `description`（nullable, max 1000）を更新する。Section の場合は加えて `body`（required, max 50000 文字、Markdown）を更新する。
- **REQ-content-management-013**: When 認可された coach または admin が削除操作を行った際, the system shall 対象 Entity を SoftDelete し、配下の子 Entity は物理削除せず親の SoftDelete によって不可視化する。
- **REQ-content-management-014**: When 削除対象 Part / Chapter が公開済（`status=Published`）の場合, the system shall `ContentNotDeletableException`（HTTP 409）を throw して削除を拒否する。
- **REQ-content-management-020**: When 認可された coach または admin が `POST /admin/parts/{part}/publish` を呼んだ際, the system shall `Part.status = Draft` であれば `Published` に遷移させる。`Chapter` / `Section` / `SectionQuestion` も同様の規約に従う。
- **REQ-content-management-021**: When 認可された coach または admin が `POST /admin/parts/{part}/unpublish` を呼んだ際, the system shall `Part.status = Published` であれば `Draft` に遷移させる。
- **REQ-content-management-022**: If 親 Entity が `Draft` の場合, the system shall 子 Entity の状態に関わらず受講生向けの公開ビューにおいて当該子 Entity を非公開として扱う（**cascade visibility**）。
- **REQ-content-management-023**: When 認可された coach または admin が `PATCH /admin/parts/{part}/chapters/reorder` 等の reorder エンドポイントを呼んだ際, the system shall ペイロードに含まれる `[{id, order}, ...]` の組合せで `(parent_id, order)` を一斉更新する。
- **REQ-content-management-024**: The system shall reorder ペイロードに対し、(1) 全 id が同一親配下のレコードを参照、(2) order 値の集合が `1..N` の連番、(3) id の重複なし、をバリデーションし違反時は `ContentReorderInvalidException`（HTTP 422）を throw。

### 機能要件 — C. Section 紐づき問題管理（SectionQuestion / SectionQuestionOption）

- **REQ-content-management-030**: When 認可された coach または admin が `/admin/sections/{section}/questions` にアクセスした際, the system shall 当該 Section 配下の SectionQuestion 一覧を `order ASC` / `category_id` / `status` でフィルタ可能な状態で表示する。**「mock-exam 専用問題」タブは存在しない**（v3 撤回）。
- **REQ-content-management-031**: When 認可された coach または admin が SectionQuestion を新規作成した際, the system shall `body` / `explanation` / `category_id` を受け取り、`status=Draft` 固定 / `section_id = URL パラメータ由来` 固定で INSERT する。`category_id` は対象 Certification 配下の `question_categories` から選ばれる必要があり、不整合時は `QuestionCategoryMismatchException`（HTTP 422）を throw する。
- **REQ-content-management-032**: The system shall SectionQuestion 新規作成時に 2 〜 6 個の `section_question_options[]`（`body` + `is_correct`）を同時受信し、SectionQuestion INSERT と同一トランザクションで SectionQuestionOption を一括 INSERT する。
- **REQ-content-management-033**: The system shall SectionQuestion 作成・更新時に `section_question_options` の `is_correct=true` がちょうど 1 件であることを検証し、違反時は `QuestionInvalidOptionsException`（HTTP 422）を throw する。
- **REQ-content-management-034**: When 認可された coach または admin が SectionQuestion を更新した際, the system shall `body` / `explanation` / `category_id` を更新可能とする。`section_id` は変更不可（Section を跨いだ移動は新規作成で対応）。
- **REQ-content-management-035**: When SectionQuestion 更新ペイロードに `section_question_options` が含まれる場合, the system shall 既存 SectionQuestionOption を全削除（物理削除）→ 新規 SectionQuestionOption を一括 INSERT する **delete-and-insert** 方式で同期する。SoftDelete は不採用（SectionQuestion の SoftDelete で履歴的関連付けは保持）。
- **REQ-content-management-036**: When 認可された coach または admin が SectionQuestion の `status` を `Published` に遷移させた際, the system shall `section_question_options` が 2 件以上 AND `is_correct=true` の選択肢が 1 件存在することを確認し、違反時は `QuestionNotPublishableException`（HTTP 409）を throw する。
- **REQ-content-management-037**: When 認可された coach または admin が SectionQuestion を SoftDelete した際, the system shall `SectionQuestionAttempt` / `SectionQuestionAnswer`（[[quiz-answering]] 所有）が SoftDelete 状態の SectionQuestion を `withTrashed()` で参照できるため削除を阻害しない。

### 機能要件 — C2. 問題カテゴリマスタ管理（QuestionCategory CRUD、共有マスタ）

- **REQ-content-management-042**: The system shall ULID 主キー、`certification_id` を `certifications.id` への外部キー（必須）、`name`（required, max 50）、`slug`（required, max 60）、`sort_order`（unsigned integer, default 0）、`description`（nullable, max 500）、`SoftDeletes`、`timestamps` を備えた `question_categories` テーブルを提供する。`(certification_id, slug)` で **資格内 UNIQUE** 制約とする。
- **REQ-content-management-043**: When 認可された coach または admin が `/admin/certifications/{certification}/question-categories` にアクセスした際, the system shall 当該資格配下の `QuestionCategory` 一覧を `sort_order ASC, created_at DESC` 順に表示する。
- **REQ-content-management-044**: When 認可された coach または admin が QuestionCategory を新規作成・更新・削除した際, the system shall FormRequest を通したうえで INSERT / UPDATE / SoftDelete する。
- **REQ-content-management-046**: When 認可された coach または admin が QuestionCategory を削除しようとした際, the system shall **当該カテゴリに紐付く SectionQuestion または MockExamQuestion が 1 件でも存在する場合** は `QuestionCategoryInUseException`（HTTP 409）を throw して削除を拒否し、ゼロ件のみ SoftDelete を実施する。**両系統からの参照を共有マスタとして確認する**。
- **REQ-content-management-047**: The system shall coach に対しては自分の担当資格配下の `QuestionCategory` のみ CRUD 操作を許可し、admin には全資格配下の操作を許可する。
- **REQ-content-management-048**: When 認可された coach または admin が SectionQuestion / MockExamQuestion 作成・更新画面で出題分野を選択する際, the system shall **select ボックスで当該 Certification 配下の `QuestionCategory` のみ** を選択肢として提示する。

### 機能要件 — D. 教材内画像管理（SectionImage）

- **REQ-content-management-050**: When 認可された coach または admin が `POST /admin/sections/{section}/images`（multipart/form-data）で画像をアップロードした際, the system shall ファイルを `Storage::disk('public')->putFileAs('section-images', '{ulid}.{ext}', $file)` 形式で保存し、`section_images` テーブルに `path` / `original_filename` / `mime_type` / `size_bytes` を INSERT、JSON で `{ id, url, alt_placeholder }` を返却する。
- **REQ-content-management-051**: The system shall アップロードされたファイルの MIME と拡張子を `.png` / `.jpg` / `.jpeg` / `.webp` のみに制限し、違反時は HTTP 422 を返す。
- **REQ-content-management-052**: The system shall アップロードされたファイルのサイズを **2MB（2 * 1024 * 1024 bytes）以下** に制限し、違反時は HTTP 422 を返す。
- **REQ-content-management-053**: When coach または admin が `DELETE /admin/section-images/{image}` を呼んだ際, the system shall Storage 上のファイル削除と `section_images` レコードの SoftDelete を単一トランザクションで実行する。
- **REQ-content-management-054**: The system shall アップロード成功レスポンス内の `url` を `/storage/section-images/{ulid}.{ext}` 形式で返し、コーチがそのまま Markdown 内に `![alt](/storage/section-images/{ulid}.{ext})` として貼り付けできる形式とする。

### 機能要件 — E. Markdown 安全レンダリング

- **REQ-content-management-060**: The system shall `App\Services\MarkdownRenderingService` を提供し、`toHtml(string $markdown): string` で Markdown を HTML に変換する。内部実装は `league/commonmark` を利用する。
- **REQ-content-management-061**: The system shall `<img>` / `<a>` / `<code>` / `<pre>` / `<table>` 等の標準タグを許容し、`onclick` / `onerror` / `style` などのイベント属性や任意 CSS を `unallowed_attributes` 設定で除去する。
- **REQ-content-management-062**: The system shall `<a>` タグに対して `safe_links_policy` を適用し、外部リンクには `rel="nofollow noopener noreferrer"` と `target="_blank"` を付与する。
- **REQ-content-management-063**: If Markdown 本文に `<script>` / `<iframe>` / `<object>` / `<embed>` 等の危険タグが含まれていた場合, then the system shall それらをサニタイズして除去または無害化する。
- **REQ-content-management-064**: The system shall `<img>` の `src` 属性が `/storage/section-images/` プレフィックスで始まる URL、または `https://` で始まる外部 URL のみを許容する。

### 機能要件 — F. 受講生向け教材全文検索

- **REQ-content-management-070**: When 受講生が `GET /contents/search?certification_id={id}&keyword={kw}` にアクセスした際, the system shall `keyword` が `Section.title` または `Section.body` に部分一致する Section を抽出する。
- **REQ-content-management-071**: The system shall 検索結果を **(1) 受講生が `enrollments` で登録済の資格内、(2) Section / 親 Chapter / 親 Part がすべて `status=Published`、(3) いずれも `deleted_at IS NULL`** の条件で絞り込む。
- **REQ-content-management-072**: The system shall 検索結果の Section に対して、所属 Part / Chapter / Certification の名称と Section ID を返し、受講生は結果クリックで [[learning]] の Section 閲覧画面へ遷移できる。
- **REQ-content-management-073**: The system shall 検索結果に **マッチした周辺テキストのスニペット**（前後 80 文字 + マッチ箇所をハイライト表示するための区切り情報）を含める。
- **REQ-content-management-074**: The system shall 検索結果を 1 ページあたり 20 件で `paginate(20)` する。
- **REQ-content-management-075**: If `keyword` パラメータが空文字または欠落, then the system shall 結果ゼロ件のレスポンスを返し、検索クエリを発行しない。

### 機能要件 — G. アクセス制御 / 認可

- **REQ-content-management-080**: The system shall `/admin/parts/...` / `/admin/chapters/...` / `/admin/sections/...` / `/admin/section-images/...` / `/admin/section-questions/...` / `/admin/question-categories/...` の各ルートに対し `auth` + `role:admin,coach` Middleware を適用する。
- **REQ-content-management-081**: The system shall `PartPolicy` / `ChapterPolicy` / `SectionPolicy` / `SectionQuestionPolicy` / `SectionImagePolicy` / `QuestionCategoryPolicy` を提供し、admin は全資格、coach は `certification_coach_assignments` で割り当てられた資格配下のリソースのみ全操作可、student は登録資格配下の `status=Published` リソースのみ閲覧可、と判定する。
- **REQ-content-management-082**: When coach が他コーチ担当資格のリソースに書き込み操作を試みた際, the system shall HTTP 403 を返す。
- **REQ-content-management-083**: When student が `/contents/search` に未登録資格の `certification_id` を指定した際, the system shall 検索結果ゼロ件相当のレスポンスを返す。
- **REQ-content-management-084**: When student のログインユーザーが `User.status != UserStatus::InProgress` の場合, the system shall すべての教材閲覧・検索を `EnsureActiveLearning` Middleware（[[auth]] 所有）でブロックする。
- **REQ-content-management-085**: The system shall Section / SectionQuestion の `show` Controller 動作において、Policy `view` が `Draft` 状態を「admin / 担当 coach」に対してのみ true とし、それ以外は HTTP 404 を返す。

### 非機能要件

- **NFR-content-management-001**: The system shall 状態変更を伴う Action（Store / Update / Destroy / Publish / Unpublish / Reorder / SectionImage 操作 / SectionQuestion 操作）を `DB::transaction()` で囲む。
- **NFR-content-management-002**: The system shall N+1 を避けるため `with()` Eager Loading（Part 一覧 → `chapters.sections`、SectionQuestion 一覧 → `options` + `category`）を適用する。
- **NFR-content-management-003**: The system shall 以下 INDEX を migration で定義する: `parts.(certification_id, order)` / `chapters.(part_id, order)` / `sections.(chapter_id, order)` / `section_questions.(section_id, status)` / `section_questions.category_id` / `section_question_options.section_question_id` / `section_images.section_id` / `sections.title` の前方一致用 INDEX / `question_categories.(certification_id, slug)` UNIQUE。
- **NFR-content-management-004**: The system shall ドメイン例外を `app/Exceptions/Content/` 配下に具象クラスとして実装する（`ContentNotDeletableException` / `ContentInvalidTransitionException` / `ContentReorderInvalidException` / `QuestionInvalidOptionsException` / `QuestionNotPublishableException` / `QuestionCategoryMismatchException` / `QuestionCategoryInUseException` / `SectionImageStorageException`）。`QuestionInUseException` / `QuestionCertificationMismatchException` は不要（mock-exam 専用問題管理が本 Feature から消えたため）。
- **NFR-content-management-005**: The system shall SectionImage の Storage 操作と DB 操作が同一トランザクション内で実行される。
- **NFR-content-management-006**: The system shall `views/admin/contents/*` を Wave 0b の共通 Blade コンポーネントに準拠して構築する。
- **NFR-content-management-007**: The system shall Markdown 編集 UI でクライアントサイド JavaScript により、ローカルプレビュー（編集中 Markdown を `/admin/sections/{section}/preview` API へ POST → サーバ側 `MarkdownRenderingService::toHtml` → HTML 返却 → プレビューペイン更新）を提供する。

## スコープ外

- **教材閲覧 UI（PartList / ChapterList / SectionView）** — [[learning]] が提供。本 Feature は Eloquent Model + Policy + `MarkdownRenderingService` を提供するのみ
- **読了マーク・学習進捗集計** — [[learning]] の `SectionProgress` / `ProgressService` が担う
- **模試問題（MockExamQuestion）の CRUD** — [[mock-exam]] が所有（模試マスタの子リソース、本 Feature では扱わない）
- **問題の難易度（difficulty）管理** — v3 撤回、テーブルからカラム削除
- **mock-exam 専用問題管理 UI** — v3 撤回、模試問題は mock-exam Feature が所有
- **動画教材・音声教材** — `product.md` スコープ外
- **Markdown 編集 UI の WYSIWYG / リッチエディタ** — 素のテキストエリア + 保存時サーバプレビュー方式

## 関連 Feature

- **依存先**:
  - [[auth]] — User 認証、`UserRole` Enum、`EnsureActiveLearning` Middleware
  - [[certification-management]] — `Certification` モデルと `certification_coach_assignments`（Part の親、coach 担当判定）
  - [[enrollment]] — `enrollments` テーブル（受講生検索の認可で「自分の登録資格判定」に利用）
- **依存元**:
  - [[learning]] — Part / Chapter / Section モデルと `MarkdownRenderingService` を読み取り利用
  - [[quiz-answering]] — `SectionQuestion` / `SectionQuestionOption` を読み取り利用、`SectionQuestionAnswer` / `SectionQuestionAttempt` で関連を持つ
  - [[mock-exam]] — `QuestionCategory` を読み取り利用（共有マスタ）。`SectionQuestion` は弱点ドリルの出題対象として [[quiz-answering]] 経由で参照される
  - [[dashboard]] — coach ダッシュボードでコンテンツ整備状況の集計に Part / Chapter / Section / SectionQuestion のカウントを利用
