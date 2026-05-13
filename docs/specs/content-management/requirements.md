# content-management 要件定義

## 概要

担当資格のコンテンツ階層（**Part → Chapter → Section**）と**問題（Question / QuestionOption）**、**教材内画像（SectionImage）**を管理する Feature。コーチは Markdown による Section 本文編集、選択肢付き問題の作成、画像アップロード、`draft / published` 公開制御、順序入替を行う。受講生向けには教材内 Section の**全文検索**を提供し、階層ブラウジングや読了マークは [[learning]] が担う（本 Feature は Model + Markdown レンダリングヘルパー + 認可基盤の提供に徹する）。`Question.section_id` は nullable で、Section 紐づき問題と mock-exam 専用問題の両方を本 Feature で CRUD する。

## ロールごとのストーリー

- **受講生（student）**: 自分が登録した資格（[[enrollment]]）の公開済 Section を `Section.title` / `Section.body` で全文検索し、該当 Section の閲覧画面（[[learning]] 側）へ遷移する。下書きや未登録資格の教材は検索結果に表示されない。
- **コーチ（coach）**: 自分の担当資格（[[certification-management]] の `certification_coach_assignments`）に対して Part / Chapter / Section / Question / QuestionOption / SectionImage を CRUD する。Markdown 本文に教材内画像を埋め込み、Section 紐づき問題と mock-exam 専用問題（`section_id = NULL`）の両方を作成・編集する。`draft → published` の公開制御を行い、順序入替で表示順を整える。
- **管理者（admin）**: 全資格の Part / Chapter / Section / Question / QuestionOption / SectionImage に対してコーチと同等の操作を行える（運用補助・代行）。

## 受け入れ基準（EARS形式）

### 機能要件 — A. データモデル

- **REQ-content-management-001**: The system shall ULID 主キーと `SoftDeletes` を備えた `parts` / `chapters` / `sections` / `questions` / `question_options` / `section_images` テーブルを提供する。
- **REQ-content-management-002**: The system shall `parts.certification_id` を `certifications.id` への外部キーとして持ち、Part を資格マスタ（[[certification-management]]）に紐付ける。
- **REQ-content-management-003**: The system shall `chapters.part_id` を `parts.id` への外部キーとして持ち、`sections.chapter_id` を `chapters.id` への外部キーとして持つことで、`Certification → Part → Chapter → Section` の階層を構成する。
- **REQ-content-management-004**: The system shall `questions.certification_id` を `certifications.id` への外部キー（必須）として持ち、`questions.section_id` を `sections.id` への外部キー（**nullable**）として持つ。`section_id = NULL` は mock-exam 専用問題を表す。
- **REQ-content-management-005**: The system shall `question_options.question_id` を `questions.id` への外部キーとして持ち、各 Question に 1 対多で選択肢を紐付ける。`is_correct` boolean カラムで正答フラグを保持する。
- **REQ-content-management-006**: The system shall `section_images.section_id` を `sections.id` への外部キーとして持ち、各 Section に 1 対多で画像メタデータを紐付ける。
- **REQ-content-management-007**: The system shall `Part.status` / `Chapter.status` / `Section.status` / `Question.status` に共通 Enum `ContentStatus`（`Draft` / `Published`）を提供し、`label()` メソッドで日本語ラベル（`下書き` / `公開中`）を返す。
- **REQ-content-management-008**: The system shall `Question.difficulty` に Enum `QuestionDifficulty`（`Easy` / `Medium` / `Hard`）を提供し、`Question.category` は文字列カラム（出題分野タグ、自由記述、最大 50 文字）として保持する。
- **REQ-content-management-009**: The system shall `parts` / `chapters` / `sections` / `questions` に `order`（unsigned integer）カラムを持たせ、`(parent_id, order)` で並び順を一意に決定する。

### 機能要件 — B. 教材階層管理（Part / Chapter / Section CRUD）

- **REQ-content-management-010**: When 認可された coach または admin が `/admin/certifications/{certification}/parts` にアクセスした際, the system shall 当該資格配下の Part 一覧を `order ASC` 順に表示する。
- **REQ-content-management-011**: The system shall Part / Chapter / Section の新規作成時に `status=Draft` 固定で INSERT し、`order` は同一親配下の `MAX(order) + 1` を自動採番する。
- **REQ-content-management-012**: The system shall Part / Chapter / Section の編集時に `title`（required, max 200）/ `description`（nullable, max 1000）を更新する。Section の場合は加えて `body`（required, max 50000 文字）を更新する。
- **REQ-content-management-013**: When 認可された coach または admin が削除操作を行った際, the system shall 対象 Entity を SoftDelete（`deleted_at` セット）し、配下の子 Entity は物理削除せず親の SoftDelete によって不可視化する。
- **REQ-content-management-014**: When 削除対象 Part / Chapter が公開済（`status=Published`）の場合, the system shall `ContentNotDeletableException`（HTTP 409）を throw して削除を拒否し、コーチに「先に非公開化してから削除してください」のメッセージを返す。
- **REQ-content-management-020**: When 認可された coach または admin が `POST /admin/parts/{part}/publish` を呼んだ際, the system shall `Part.status = Draft` であれば `Published` に遷移させ、それ以外なら `ContentInvalidTransitionException`（HTTP 409）を throw する。`Chapter` / `Section` / `Question` も同様の規約に従う。
- **REQ-content-management-021**: When 認可された coach または admin が `POST /admin/parts/{part}/unpublish` を呼んだ際, the system shall `Part.status = Published` であれば `Draft` に遷移させ、それ以外なら `ContentInvalidTransitionException`（HTTP 409）を throw する。`Chapter` / `Section` / `Question` も同様。
- **REQ-content-management-022**: If 親 Entity が `Draft` の場合, the system shall 子 Entity の状態に関わらず受講生向けの公開ビューにおいて当該子 Entity を非公開として扱う（**cascade visibility**）。本 Feature の状態カラム自体は子側を変更しない。
- **REQ-content-management-023**: When 認可された coach または admin が `PATCH /admin/parts/{part}/chapters/reorder` 等の reorder エンドポイントを呼んだ際, the system shall ペイロードに含まれる `[{id, order}, ...]` の組合せで `(parent_id, order)` を一斉更新する。Part の場合は `(certification_id, order)`、Chapter は `(part_id, order)`、Section は `(chapter_id, order)`。
- **REQ-content-management-024**: The system shall reorder ペイロードに対し、(1) 全 id が同一親配下のレコードを参照すること、(2) order 値の集合が `1..N` の連番であること、(3) id の重複が無いこと、をバリデーションし、違反時は `ContentReorderInvalidException`（HTTP 422）を throw する。

### 機能要件 — C. 問題管理（Question / QuestionOption）

- **REQ-content-management-030**: When 認可された coach または admin が `/admin/certifications/{certification}/questions` にアクセスした際, the system shall 当該資格配下の Question 一覧を `category` / `difficulty` / `status` / `section_id IS NULL`（mock-exam 専用問題のみ）でフィルタ可能な状態で表示する。
- **REQ-content-management-031**: When 認可された coach または admin が Question を新規作成した際, the system shall `body` / `explanation` / `category` / `difficulty` / `certification_id` / `section_id`（nullable）を受け取り、`status=Draft` 固定で INSERT する。
- **REQ-content-management-032**: The system shall Question 新規作成時に 2 〜 6 個の `question_options[]`（`body` + `is_correct`）を同時受信し、Question INSERT と同一トランザクションで QuestionOption を一括 INSERT する。
- **REQ-content-management-033**: The system shall Question 作成・更新時に `question_options` の `is_correct=true` がちょうど 1 件であることを検証し、違反時は `QuestionInvalidOptionsException`（HTTP 422）を throw する。
- **REQ-content-management-034**: When 認可された coach または admin が Question を更新した際, the system shall `body` / `explanation` / `category` / `difficulty` / `section_id` を更新可能とし、`certification_id` は変更不可とする。
- **REQ-content-management-035**: When Question 更新ペイロードに `question_options` が含まれる場合, the system shall 既存 QuestionOption を全削除（物理削除）→ 新規 QuestionOption を一括 INSERT する **delete-and-insert** 方式で同期する。QuestionOption は SoftDelete を採用しない（Question の SoftDelete によって履歴的関連付けは保持される）。
- **REQ-content-management-036**: When 認可された coach または admin が Question の `status` を `Published` に遷移させた際, the system shall **`question_options` が 2 件以上 AND `is_correct=true` の選択肢が 1 件存在する**ことを確認し、違反時は `QuestionNotPublishableException`（HTTP 409）を throw する。
- **REQ-content-management-037**: When 認可された coach または admin が Question を SoftDelete した際, the system shall **当該 Question が `mock_exam_questions` 中間テーブル（[[mock-exam]]）から参照されていない** ことを確認し、参照中であれば `QuestionInUseException`（HTTP 409）を throw する。Section 紐づき問題（`section_id IS NOT NULL`）は [[quiz-answering]] からも参照されるが、`answers` / `question_attempts` は SoftDelete 状態の Question を引き続き参照できるため削除を阻害しない。
- **REQ-content-management-040**: The system shall mock-exam 専用問題（`section_id IS NULL`）を Question 一覧の専用フィルタタブ「mock-exam 専用」で抽出可能とする。
- **REQ-content-management-041**: When Question が Section 紐づき問題として作成・更新される場合, the system shall `section_id` が指定された Section の親 Chapter が属する Part の `certification_id` と Question の `certification_id` が一致することを検証し、違反時は `QuestionCertificationMismatchException`（HTTP 422）を throw する。

### 機能要件 — D. 教材内画像管理（SectionImage）

- **REQ-content-management-050**: When 認可された coach または admin が `POST /admin/sections/{section}/images`（multipart/form-data）で画像をアップロードした際, the system shall ファイルを `Storage::disk('public')->putFileAs('section-images', '{ulid}.{ext}', $file)` 形式で保存し、`section_images` テーブルに `path` / `original_filename` / `mime_type` / `size_bytes` を INSERT、JSON で `{ id, url, alt_placeholder }` を返却する。
- **REQ-content-management-051**: The system shall アップロードされたファイルの MIME と拡張子を `.png` / `.jpg` / `.jpeg` / `.webp` のみに制限し、違反時は HTTP 422 を返す。
- **REQ-content-management-052**: The system shall アップロードされたファイルのサイズを **2MB（2 * 1024 * 1024 bytes）以下**に制限し、違反時は HTTP 422 を返す。
- **REQ-content-management-053**: When coach または admin が `DELETE /admin/section-images/{image}` を呼んだ際, the system shall Storage 上のファイル削除と `section_images` レコードの SoftDelete を単一トランザクションで実行する。
- **REQ-content-management-054**: The system shall アップロード成功レスポンス内の `url` を `/storage/section-images/{ulid}.{ext}` 形式（public driver の URL 規約）で返し、コーチがそのまま Markdown 内に `![alt](/storage/section-images/{ulid}.{ext})` として貼り付けできる形式とする。
- **REQ-content-management-055**: The system shall Section と SectionImage の紐付けを `section_images.section_id` で保持し、Section SoftDelete 時に配下 SectionImage を物理削除しない（Section が復元される可能性に備える）。Section の物理削除は本 Feature では提供しない。

### 機能要件 — E. Markdown 安全レンダリング

- **REQ-content-management-060**: The system shall `App\Services\MarkdownRenderingService` を提供し、`toHtml(string $markdown): string` で Markdown を HTML に変換する。内部実装は `league/commonmark` を利用する。
- **REQ-content-management-061**: The system shall `MarkdownRenderingService` で `<img>` / `<a>` / `<code>` / `<pre>` / `<table>` 等の標準タグを許容し、`onclick` / `onerror` / `style` などのイベント属性や任意 CSS を `unallowed_attributes` 設定で除去する。
- **REQ-content-management-062**: The system shall `<a>` タグに対して `safe_links_policy` を適用し、外部リンクには `rel="nofollow noopener noreferrer"` と `target="_blank"` を付与する。
- **REQ-content-management-063**: If Markdown 本文に `<script>` / `<iframe>` / `<object>` / `<embed>` などの危険タグが含まれていた場合, then the system shall それらをサニタイズして除去または無害化する。
- **REQ-content-management-064**: The system shall `<img>` の `src` 属性が `/storage/section-images/` プレフィックスで始まる URL、または `https://` で始まる外部 URL のみを許容する（`javascript:` 等の擬似プロトコルを拒否）。
- **REQ-content-management-065**: The system shall コードブロック（``` で囲まれた範囲）を `<pre><code class="language-{lang}">...</code></pre>` 形式で出力する。シンタックスハイライトのスタイルは Wave 0b 共通 CSS 側の責務とする。

### 機能要件 — F. 受講生向け教材全文検索

- **REQ-content-management-070**: When 受講生が `GET /contents/search?certification_id={id}&keyword={kw}` にアクセスした際, the system shall `keyword` が `Section.title` または `Section.body` に部分一致する Section を抽出する。
- **REQ-content-management-071**: The system shall 検索結果を **(1) 受講生が `enrollments` で登録済（[[enrollment]] により判定）の資格内、(2) Section / 親 Chapter / 親 Part がすべて `status=Published`、(3) いずれも `deleted_at IS NULL`** の条件で絞り込む。
- **REQ-content-management-072**: The system shall 検索結果の Section に対して、所属 Part / Chapter / Certification の名称と Section ID を返し、受講生は結果クリックで [[learning]] の Section 閲覧画面（`/learning/sections/{section}`）へ遷移できる。
- **REQ-content-management-073**: The system shall 検索結果に **マッチした周辺テキストのスニペット**（前後 80 文字 + マッチ箇所をハイライト表示するための区切り情報）を含める。スニペット生成は `MarkdownRenderingService::extractSnippet(string $body, string $keyword, int $padding = 80): string` を新設して担う。
- **REQ-content-management-074**: The system shall 検索結果を 1 ページあたり 20 件で `paginate(20)` し、`total` / `current_page` / `last_page` を含めてレスポンスする。
- **REQ-content-management-075**: If `keyword` パラメータが空文字または欠落, then the system shall 結果ゼロ件のレスポンスを返し、検索クエリを発行しない（バリデーション失敗ではなく仕様）。

### 機能要件 — G. アクセス制御 / 認可

- **REQ-content-management-080**: The system shall `/admin/parts/...` / `/admin/chapters/...` / `/admin/sections/...` / `/admin/section-images/...` / `/admin/questions/...` の各ルートに対し `auth` + `role:admin,coach` Middleware を適用する。
- **REQ-content-management-081**: The system shall `PartPolicy` / `ChapterPolicy` / `SectionPolicy` / `QuestionPolicy` / `SectionImagePolicy` を提供し、admin は全資格に対して全操作可、coach は `certification_coach_assignments` で割り当てられた資格配下のリソースのみ全操作可、student は登録資格配下の `status=Published` リソースのみ閲覧可、と判定する。
- **REQ-content-management-082**: When coach が他コーチ担当資格の Part / Chapter / Section / Question / SectionImage に書き込み操作を試みた際, the system shall HTTP 403 を返す。
- **REQ-content-management-083**: When student が `/contents/search` に未登録資格の `certification_id` を指定した際, the system shall 検索結果ゼロ件相当のレスポンスを返し（情報漏洩を避けるため `certification_id` の存在自体は確認しない）、HTTP 200 で空配列を返す。
- **REQ-content-management-084**: The system shall Section / Question の `show` Controller 動作において、Policy `view` が `Draft` 状態を「admin / 担当 coach」に対してのみ true とし、それ以外は HTTP 404 を返す（Draft の存在自体を隠す）。
- **REQ-content-management-085**: The system shall `Question.section_id` を更新する際、新しい Section の親 Chapter → Part → Certification が現操作者の認可スコープ内であることを Policy で検証し、違反時は HTTP 403 を返す。

### 非機能要件

- **NFR-content-management-001**: The system shall 状態変更を伴う Action（Store / Update / Destroy / Publish / Unpublish / Reorder / SectionImage 操作 / Question 操作）を `DB::transaction()` で囲む。
- **NFR-content-management-002**: The system shall Part / Chapter / Section / Question の管理画面において、N+1 を避けるため `with()` Eager Loading（Part 一覧 → `chapters.sections`、Question 一覧 → `options`）を適用する。
- **NFR-content-management-003**: The system shall 以下のインデックスを `migration` 内で定義する: `parts.(certification_id, order)` / `chapters.(part_id, order)` / `sections.(chapter_id, order)` / `questions.(certification_id, status)` / `questions.section_id` / `questions.category` / `question_options.question_id` / `section_images.section_id` / `sections.title` の前方一致用 INDEX。
- **NFR-content-management-004**: The system shall ドメイン例外を `app/Exceptions/Content/` 配下に具象クラスとして実装する（`ContentNotDeletableException` / `ContentInvalidTransitionException` / `ContentReorderInvalidException` / `QuestionInvalidOptionsException` / `QuestionNotPublishableException` / `QuestionInUseException` / `QuestionCertificationMismatchException` / `SectionImageStorageException`）。
- **NFR-content-management-005**: The system shall SectionImage の Storage 操作（`putFileAs` / `delete`）と DB 操作（INSERT / SoftDelete）が同一トランザクション内で実行され、片方失敗時にロールバックされること。Storage は DB rollback で自動巻き戻しできないため、`DB::afterCommit()` を使って **DB 成功後にファイル削除を実行**する／**新規ファイルアップロード時はトランザクション失敗時に手動で削除を試みる** パターンを採用する。
- **NFR-content-management-006**: The system shall `views/admin/contents/*` を Wave 0b で整備済みの共通 Blade コンポーネント（`<x-button>` / `<x-form.input>` / `<x-form.textarea>` / `<x-modal>` / `<x-alert>` / `<x-card>` / `<x-paginator>`）に準拠して構築する。
- **NFR-content-management-007**: The system shall Markdown 編集 UI（Section 編集画面）でクライアントサイド JavaScript（`resources/js/content-management/section-editor.js`）により、ローカルプレビュー（編集中 Markdown を `/admin/sections/{section}/preview` API へ POST → サーバ側 `MarkdownRenderingService::toHtml` → HTML 返却 → プレビューペイン更新）を提供する。

## スコープ外

- **教材閲覧 UI（PartList / ChapterList / SectionView）** — [[learning]] が提供する。本 Feature は Eloquent Model + Policy + `MarkdownRenderingService` を提供するだけで、受講生向け階層ブラウジング画面は持たない。
- **読了マーク・学習進捗集計** — [[learning]] の `SectionProgress` / `ProgressService` が担う。
- **mock-exam の問題セット組成（`MockExamQuestion` 中間テーブル）** — [[mock-exam]] が所有する。本 Feature は Question 自体の CRUD のみ。
- **mock-exam 結果の弱点ヒートマップ・苦手分野ドリルの問題抽出** — [[mock-exam]] の `WeaknessAnalysisService` と [[quiz-answering]] が担う。
- **章末問題・小テストなどの「Section 単位での問題セット」** — Section 紐づき問題の演習は [[quiz-answering]] が responsibility を持つ。
- **動画教材・音声教材** — `product.md` スコープ外（Section は Markdown 本文 + 画像のみ）。
- **Q&A 用ファイル添付** — [[qa-board]] はテキストのみ（`product.md` スコープ外）。
- **chat 用ファイル添付** — [[chat]] が画像 / PDF 添付を独自に持つ（`ChatAttachment`、本 Feature とは別系統）。
- **Markdown 編集 UI の WYSIWYG / リッチエディタ** — 素のテキストエリア + 保存時サーバプレビュー方式（教育PJスコープを抑える）。
- **タグマスタとしての出題分野管理** — `questions.category` は文字列カラム（自由記述）。マスタ化は将来拡張領域。

## 関連 Feature

- **依存先**（本 Feature が前提とする）:
  - [[auth]] — User 認証、`User` モデル本体、`UserRole` Enum。本 Feature の Policy は `UserRole` で分岐する
  - [[certification-management]] — `Certification` モデルと `certification_coach_assignments` 中間テーブル（Part の親、coach 担当判定）。本 Feature 実装に際して `User::assignedCertifications()` BelongsToMany リレーションを `Certification::coaches()` の逆向きとして [[certification-management]] 側に追加する必要がある（REQ-certification-management-045 で予告済み）
  - [[enrollment]] — `enrollments` テーブル（受講生検索の認可で「自分の登録資格判定」に利用）
- **依存元**（本 Feature を利用する）:
  - [[learning]] — Part / Chapter / Section モデルと `MarkdownRenderingService` を読み取り利用、`SectionProgress` で Section との 1 対多関連を持つ
  - [[quiz-answering]] — Section 紐づき Question と QuestionOption を読み取り利用、`Answer` / `QuestionAttempt` で Question との関連を持つ
  - [[mock-exam]] — Question と QuestionOption を読み取り利用、`MockExamQuestion` 中間テーブルで Question を選定
  - [[dashboard]] — coach ダッシュボードでコンテンツ整備状況の集計に Part / Chapter / Section / Question のカウントを利用する場合がある（オプション）
