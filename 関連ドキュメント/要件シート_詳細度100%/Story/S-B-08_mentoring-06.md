# S-B-08 コーチ用 受講生メモ(EnrollmentNote)編集

## メタ情報

| 項目 | 値 |
|---|---|
| チケット ID | `S-B-08` |
| Feature 連番 | `mentoring-06` |
| Feature | mentoring(+ enrollment / user-management) |
| 種別 | Story |
| サブカテゴリ | 新規機能の構築 |
| 難易度 | Basic |
| 工数 (h) | 6 |
| 依存チケット | (なし) |

## 概要

コーチが担当資格に登録した受講生 1 人ずつに対して、面談以外の日々の観察・メモ・申し送り事項を時系列に残す機能を新規実装する。新規テーブル `enrollment_notes` を追加し、Enrollment 詳細画面にメモ一覧 + 追加フォーム、編集は専用ページで実現。コーチは自分が作成したメモのみ編集 / 削除可、管理者は越境して全コーチノートを編集 / 削除可、受講生本人にはメモ一覧・操作いずれもアクセス禁止(閲覧含む)。

## 背景・目的

- **現状の問題**: 提供 PJ のコーチは面談予約・面談中の議事メモ(面談に紐づく)は記録できるが、面談以外の日常観察(「最近 chat の応答が遅れている」「Q&A で躓いた論点」「次回面談で確認したい事項」等)を構造化して残す場所がない。複数受講生を持つコーチは私的なメモ帳や別ツールに分散管理することになり、引き継ぎや管理者監督が困難。
- **達成したい状態**: コーチが担当資格に登録した受講生の Enrollment 単位でメモを CRUD でき、時系列に履歴を残せる。複数コーチ体制下では各コーチが自分のメモのみ編集 / 削除でき、管理者は全コーチのメモを越境管理できる。受講生本人にはメモが完全に見えない(コーチ → 管理者の業務的記録の性質を保つ)。
- **価値・優先度**: 担当コーチが受講生の状況を継続観察するための **業務記録基盤**。本機能が整うとコーチ間の知見共有が促され、担当変更時の引き継ぎも円滑になる。新規エンティティの CRUD + ロール × 当事者の二重認可判定として Policy の典型実装が揃う構成。

## ユーザーストーリー

- **コーチ(coach)として**、担当資格に登録した受講生の Enrollment 単位でメモを追加・編集・削除したい。なぜなら、面談以外の日常観察を構造化して残し、フォローの質を高めたいから。
- **コーチとして**、他コーチが書いたメモも閲覧したい。なぜなら、複数コーチ体制での申し送り事項を相互に把握したいから。介入(編集 / 削除)はせず、自分のメモのみ自分で管理する。
- **管理者(admin)として**、任意の受講生に対する全コーチのメモを閲覧・編集・削除したい。なぜなら、運用上の不適切記述の是正やコーチ離任時のメモ管理を一元的に扱いたいから。
- **管理者として**、自分自身もコーチと同じフォーマットでメモを残せることを期待する。なぜなら、運営観察も同じ場所に記録した方が一貫しているから。
- **受講生(student)として**、自分の Enrollment 配下にコーチ / 管理者が残したメモが自分には一切表示されないことを期待する。なぜなら、メモは業務記録であり、受講生に見えると素直な観察ができなくなるから。

## やること

### メモ(コーチ + 管理者のみ操作可、受講生は閲覧含めすべて 403)

- **追加**: 担当資格に登録した受講生の Enrollment に対してコーチが本文を入力 → 追加成功で元の画面(Enrollment 詳細)にリダイレクト + フラッシュ表示。作成者(`coach_user_id`)に操作者の ID を記録
- **追加(管理者)**: 管理者は任意の Enrollment に対してメモ追加可、作成者には管理者の ID が記録される
- **編集(コーチ)**: コーチは **自分が作成したメモのみ** 専用編集ページで本文更新可、他コーチが作成したメモへの編集アクションは 403
- **編集(管理者)**: 管理者は **任意のコーチ / 管理者が作成したメモ** を編集可(越境)
- **削除(コーチ)**: コーチは自分が作成したメモのみ物理削除可、他コーチのメモは 403
- **削除(管理者)**: 管理者は任意のメモを物理削除可(越境)
- **作成者の不変性**: 編集時に `coach_user_id`(作成者)は変更しない(履歴的に意味があるため)

### 閲覧

- **コーチ**: 担当資格(`certification_coach_assignments` 経由)に登録した受講生の Enrollment 詳細画面でメモ一覧(時系列降順)を閲覧可、自分のメモには編集 / 削除ボタンが表示され、他コーチのメモには表示されない
- **管理者**: 任意受講生の Enrollment 詳細画面でメモ一覧を閲覧可、全メモに編集 / 削除ボタンが表示される
- **受講生本人**: 自分の Enrollment 詳細画面にメモ一覧自体が表示されない(タブ / セクション自体が非表示、認可で 403)
- **第三者**: 担当外コーチ / 他受講生は親 Enrollment 詳細画面自体が 403 / 404 のためそもそもメモ一覧にアクセスできない

### 共通の振る舞い

- 親 Enrollment が SoftDelete されている場合、メモ一覧から除外される(Enrollment cascade 削除に追従)
- Enrollment が `passed` / `failed` 状態でもメモ CRUD は可(状態判定は本機能の責務外)
- メモは物理削除のみ(履歴は保持しない)

## やらないこと

- **受講生本人がメモを閲覧する動線** — 業務記録の性質を保つため、受講生は閲覧含め全 403。設計上タブ / セクションが受講生画面で描画されない
- **メモの検索 UI**(本文部分一致検索等) — 時系列降順閲覧のみ、検索動線なし
- **コーチ間のノート個別非公開フラグ** — 全コーチ + 管理者は他コーチのメモを常に閲覧可、個別 coach 非公開モードは持たない
- **メモへのコメント / リアクション機能** — 単純なメモ本文 1 行のみ、対話機能なし
- **メモの達成マーク / ステータス管理** — `EnrollmentGoal`(`S-B-06`)と異なり、メモはフラットな本文のみ(状態なし)
- **メモの SoftDelete + 復元 UI** — 物理削除のみ(誤削除リスクは UI 側の確認ダイアログで対応)
- **添付ファイル(画像 / PDF)** — 本文テキストのみ、ファイルアップロードなし
- **コーチ → 受講生宛のメッセージ** — chat Feature の責務、本機能は内部記録専用
- **コーチ離任時のメモ自動委譲 / 移管** — 管理者が手動で `coach_user_id` を書き換える運用は採用しない(履歴の連続性を重視、運用上必要なら管理者が新たにメモを追加して言及)

## Seeder 設計

> `migrate:fresh --seed` 直後に動作確認できるよう、シナリオに紐付けたレコード単位で具体化する。

**前提**(他 Seeder で投入される想定): 受講生 A〜D / コーチ X(資格 X 担当)/ コーチ Y(資格 Y 担当)/ 管理者 / 公開資格 X, Y(資格 X はコーチ X、Y はコーチ Y が担当)/ Enrollment 数件

`EnrollmentNoteSeeder`(各 Enrollment 配下に複数メモを投入):

| レコード分類 | 内容 | 動作確認用途 |
|---|---|---|
| 受講生 A × 資格 X Enrollment | コーチ X が作成したメモ × 2(直近 / 数日前)/ コーチ X 自身が編集できる動作確認 / 削除動作確認 |
| 受講生 A × 資格 X Enrollment | 管理者が作成した越境メモ × 1 | 管理者越境 CRUD 確認 |
| 受講生 B × 資格 X Enrollment | コーチ X が作成したメモ × 1 | 複数受講生間のメモ分離確認 |
| 受講生 C × 資格 Y Enrollment | コーチ Y が作成したメモ × 1 | コーチ X からの担当外資格メモ閲覧で 403 確認 |

- **DatabaseSeeder への追加順序**: `UserSeeder` → `CertificationSeeder` → `EnrollmentSeeder`(`certification_coach_assignments` 含む)→ `EnrollmentNoteSeeder`

## 受け入れ条件

- [ ] **追加 - コーチ成功**: 担当資格に登録した受講生の Enrollment に対してコーチがメモ追加すると物理 INSERT され、Enrollment 詳細画面にリダイレクト(back)+ フラッシュ表示
- [ ] **追加 - 作成者記録**: 新規メモの作成者(`coach_user_id`)に操作したコーチ / 管理者の ID が記録される
- [ ] **追加 - 管理者越境**: 管理者は任意の Enrollment に対してメモ追加可、作成者には管理者の ID が記録される
- [ ] **追加 - 担当外コーチ拒否**: コーチが担当していない資格の Enrollment に対してメモ追加を試みると 403
- [ ] **追加 - 受講生拒否**: 受講生が自分または他人の Enrollment に対してメモ追加を試みると 403
- [ ] **編集 - 専用ページ**: 自分が作成したメモの編集リンクからコーチが編集専用ページに遷移し、本文を更新できる
- [ ] **編集 - リダイレクト + フラッシュ**: 編集成功時、親 Enrollment 詳細画面にリダイレクト + フラッシュ表示
- [ ] **編集 - 他コーチノート拒否**: コーチが他コーチが作成したメモの編集フォームまたは更新アクションにアクセスすると 403
- [ ] **編集 - 管理者越境**: 管理者は任意のコーチ / 管理者が作成したメモを編集可
- [ ] **編集 - 受講生拒否**: 受講生がメモ編集フォームまたは更新アクションにアクセスすると 403
- [ ] **編集 - 作成者不変**: 編集後も作成者(`coach_user_id`)は変更されない
- [ ] **削除 - コーチ本人成功**: コーチが自分が作成したメモを削除すると物理削除され、元の画面にリダイレクト(back)+ フラッシュ表示
- [ ] **削除 - 他コーチノート拒否**: コーチが他コーチが作成したメモの削除アクションにアクセスすると 403
- [ ] **削除 - 管理者越境**: 管理者は任意のメモを物理削除可
- [ ] **削除 - 受講生拒否**: 受講生がメモ削除アクションにアクセスすると 403
- [ ] **一覧表示 - コーチ閲覧**: 担当資格に登録した受講生の Enrollment 詳細画面でコーチがメモ一覧(時系列降順)を閲覧でき、自分のメモには編集 / 削除ボタンが表示される
- [ ] **一覧表示 - 他コーチノート**: コーチが他コーチが作成したメモを閲覧でき(全 5 件の一覧に含まれる)、編集 / 削除ボタンは表示されない
- [ ] **一覧表示 - 管理者閲覧**: 管理者が任意 Enrollment 詳細画面でメモ一覧を閲覧でき、全メモに編集 / 削除ボタンが表示される
- [ ] **一覧表示 - 受講生非表示**: 受講生が自分の Enrollment 詳細画面を開いた際、メモ一覧のセクション / タブ自体が表示されない
- [ ] **本文文字数 - バリデーション**: 本文が空 / 2001 文字以上のとき 422 + 入力値とエラーメッセージが表示される

## 実装方針

> **参考設計の一例**。受け入れ条件を満たせれば実装手段は問わない。受講生は提供 PJ コード + ヒアリングで自分の設計を組み立てる。

### 主要 URL

| メソッド | パス | 振る舞い |
|---|---|---|
| POST | `/enrollments/{enrollment}/notes` | メモ追加、成功時 `back()`(`/enrollments/{enrollment}` または管理者の場合は `/admin/users/{user}` 等の元画面)リダイレクト + フラッシュ「メモを追加しました。」 |
| GET | `/enrollment-notes/{note}/edit` | 編集フォーム表示(自分が作成したメモのみ閲覧可、管理者は越境可、他コーチは 403) |
| PATCH | `/enrollment-notes/{note}` | メモ更新、成功時 `/enrollments/{enrollment}` リダイレクト + フラッシュ「メモを更新しました。」、`coach_user_id` は変更しない |
| DELETE | `/enrollment-notes/{note}` | 物理削除、成功時 `back()` リダイレクト + フラッシュ「メモを削除しました。」 |

> **route 名**: `enrollments.notes.store` / `enrollment-notes.edit` / `enrollment-notes.update` / `enrollment-notes.destroy`(`auth` Middleware 配下の認証済ルート、ロール Middleware は不要 = Policy が当事者判定で完結)。
> **GET 一覧 / 詳細単独画面はない**: 一覧は親 Enrollment 詳細画面(`enrollments.show`)の partial として表示される、個別メモの詳細単独画面も持たない。

### データモデル

**新規テーブル**: `enrollment_notes`(ULID 主キー、SoftDelete 不採用 = 物理削除)

| カラム | 型 | NOT NULL | FK / UNIQUE | 補足 |
|---|---|:---:|---|---|
| id | ulid | ✓ | PK | `$table->ulid('id')->primary()` |
| enrollment_id | ulid | ✓ | enrollments.id, ON DELETE CASCADE | `$table->foreignUlid('enrollment_id')->constrained()->cascadeOnDelete()`(親 Enrollment 削除で配下メモも自動削除) |
| coach_user_id | ulid | ✓ | users.id, ON DELETE RESTRICT | 作成者(coach or admin)、ON DELETE RESTRICT で作成者 User を退会させようとしても残メモが阻む(SoftDelete で対応) |
| body | varchar(2000) | ✓ | | メモ本文 |
| created_at | timestamp | | | `$table->timestamps()` |
| updated_at | timestamp | | | `$table->timestamps()` |

- **インデックス**: `(enrollment_id, created_at)` 複合(Enrollment 配下の時系列降順一覧の高速化)/ `coach_user_id`(作成者別検索の高速化)
- **リレーション**: Enrollment 1-N EnrollmentNote(`Enrollment::notes()` で `hasMany`)/ User 1-N EnrollmentNote(`coach_user_id` 経由、`author()` リレーション)
- **削除戦略**: 物理削除のみ採用(SoftDelete 不採用、`backend-models.md`「進捗・履歴・累計集計テーブルは SoftDelete 採用しない」規約準拠)

### バリデーション

`StoreRequest` / `UpdateRequest`(同一ルール):

| 入力項目 | ルール | 推奨エラーメッセージ例 |
|---|---|---|
| body | required / string / max:2000 | メモ本文は必須です。<br>メモ本文は 2000 文字以内で入力してください。 |

### 認可設計

**Policy**: `EnrollmentNotePolicy`

| メソッド | ロール × 判定 |
|---|---|
| viewAny(Enrollment) | 担当コーチ(`certification_coach_assignments` 経由)/ 管理者 / その他 (受講生含む) ❌ |
| view(EnrollmentNote) | 同上(親 Enrollment アクセス権) |
| create(Enrollment) | 担当コーチ / 管理者 / その他 (受講生含む) ❌ |
| update(EnrollmentNote) | 自身が作成したコーチ / 管理者 / その他 ❌ |
| delete(EnrollmentNote) | 同上 |

- **担当コーチ判定**: `$enrollment->certification->coaches->contains('id', $user->id)`(`certification_coach_assignments` 中間テーブル経由)
- **コーチ間越境拒否**: `update` / `delete` で「`role === Coach && coach_user_id === user.id`」の二重判定(自身が作成したノートのみ)
- **管理者越境**: 全ロール許可で先頭判定(`if ($user->role === Admin) return true;`)
- **受講生拒否**: 全メソッドで `role === Student` は false で返す(明示判定 or `default false` パターン)
- **Blade での出し分け**: 一覧表示画面で `@can('viewAny', [EnrollmentNote::class, $enrollment])` でメモセクション自体の表示制御、`@can('create', [EnrollmentNote::class, $enrollment])` でフォーム表示制御、`@can('update', $note)` / `@can('delete', $note)` で各操作ボタンの表示制御

### テスト観点

| 種別 | 観点 |
|---|---|
| Unit | `EnrollmentNote` Model のリレーション(`enrollment` BelongsTo / `author` BelongsTo to User via `coach_user_id`) |
| Feature | 各エンドポイントの認可分岐(担当コーチ / 担当外コーチ / 管理者 / 受講生本人 / 他受講生)/ 副作用(INSERT・UPDATE・物理削除)/ 作成者の不変性(編集時 `coach_user_id` が変わらない)/ フラッシュ表示有無 / リダイレクト先パス / Enrollment 詳細画面でのメモ一覧表示 + 操作ボタンの認可別出し分け / 受講生に対してはセクション自体が表示されない |
| Policy | 各メソッド × 各ロール × 当事者判定の真偽(担当コーチ × 自作成 true / 担当コーチ × 他コーチ作成 false (update/delete) / 担当外コーチ false / 管理者 true 全パターン / 受講生 false 全パターン) |

### アーキテクチャ判断

> **Basic 範囲制約**: 教材外の Action / Service は使わない前提で **Controller 内完結** を基本とする。Action 採用は受講生判断(チャレンジするなら歓迎、振る舞いが受け入れ条件を満たせば OK)。

- **採用技術**: Eloquent + Controller(受講生判断で Action 分割可)+ Policy + FormRequest + Blade(提供済み)+ Route Model Binding
- **設計判断**:
  1. **画面構成**: メモ一覧 + 追加フォームは親 `enrollments.show` 画面に partial(`_partials/note-list.blade.php` + `_partials/note-form.blade.php` 等)として埋め込み、編集は専用ページ(`enrollment-notes.edit`)へ遷移する純 Laravel パターン(`frontend-blade.md`「Basic チケットの Blade(JS なし)」規約準拠)
  2. **ルート命名**: `enrollments.notes.store`(親 Enrollment のネスト URL `/enrollments/{enrollment}/notes` で POST)+ `enrollment-notes.{edit,update,destroy}`(個別メモは ID 解決可能なため `/enrollment-notes/{note}` のフラット URL でルーティング)
  3. **作成者の保持**: `coach_user_id` は INSERT 時に `auth()->user()->id` で設定し、UPDATE 時には変更しない(編集 Action の `validated` に `coach_user_id` を含めない、または `fillable` で許可しても呼出側で渡さない)。これにより「誰がこのメモを書いたか」の履歴的整合性を保つ
  4. **Policy 内の DRY**: コーチ担当資格判定 + 管理者越境判定 + 作成者本人判定 を private ヘルパーメソッド(`canAccessEnrollmentForNotes` / `canModify`)に集約。可読性 + 重複排除
  5. **管理者の操作者記録**: 管理者がメモを追加すると `coach_user_id = admin.id` で記録される(カラム名は `coach_user_id` だが実体は「作成者の User ID」)。カラム名を `author_user_id` にリネームする選択肢もあるが、本テーブル定義は `coach_user_id` のまま運用する(受講生 PR の Blade / Action / Migration が一貫していれば OK)
  6. **物理削除**: SoftDelete 不採用。誤削除リスクは UI 側の `onsubmit="return confirm()"`(HTML 標準 confirm)でカバー
  7. **受講生への完全非表示**: Blade の一覧表示画面で `@can('viewAny', [EnrollmentNote::class, $enrollment])` でセクション自体を出し分け。受講生画面では `note-list.blade.php` の `@if` ブロックが false になり、メモ一覧の見出し / フォーム / 行すべてが非表示。URL を直叩きしてもエンドポイントは 403 が返る二重防御

### 関連ファイルメモ

- `app/Models/EnrollmentNote.php` / `app/Models/Enrollment.php`(後者は既存、`notes()` リレーションを定義 — 既存 PJ で定義済か要確認)
- `app/Http/Controllers/EnrollmentNoteController.php`(`store` / `edit` / `update` / `destroy`)
- `app/UseCases/EnrollmentNote/{Store,Update,Destroy}Action.php`(※ 模範解答 PJ で採用、Basic 受講生は Controller 内完結も可)
- `app/Policies/EnrollmentNotePolicy.php`
- `app/Http/Requests/EnrollmentNote/{Store,Update}Request.php`
- `resources/views/enrollment-note/edit.blade.php` + `resources/views/enrollment-note/_list.blade.php`(提供 PJ 既存、ロック対象)
- `database/migrations/*_create_enrollment_notes_table.php`
- `database/seeders/EnrollmentNoteSeeder.php`(提供 PJ 既存、複数コーチ × 複数受講生 + 管理者越境メモ 投入)
- `routes/web.php` の認証済グループ内に `Route::post('enrollments/{enrollment}/notes', ...)` + `Route::get('enrollment-notes/{note}/edit', ...)` + `Route::patch('enrollment-notes/{note}', ...)` + `Route::delete('enrollment-notes/{note}', ...)` を追加
- 類似パターン参考: `EnrollmentGoal`(`S-B-06`)— 同じ「Enrollment 配下の子エンティティ」だが、受講生本人 CRUD(`S-B-06`)/ コーチ + 管理者 CRUD(本チケット)で役割対称。Policy の責務委譲 / 二重認可判定パターンは両者で共通

## 補足

### 想定ヒアリング Q&A

| 質問 | 回答 |
|---|---|
| メモ本文の文字数上限は? | 2000 文字 |
| 1 Enrollment あたりのメモ数上限は? | 上限なし。コーチが自由に書き残す |
| 受講生本人にメモは見える? | 見えない。閲覧含めすべて 403。Blade 一覧表示画面でもセクション自体が出ない |
| 担当外コーチがメモを書ける? | 書けない。Policy で `certification_coach_assignments` 経由の担当判定があり、担当外資格の Enrollment に対しては 403 |
| 担当外コーチがメモを閲覧できる? | できない。viewAny も担当コーチ判定があり、Enrollment 詳細画面自体が 403 |
| 他コーチが作成したメモを編集できる? | できない。コーチは自分が作成したメモのみ編集 / 削除可。他コーチのメモを編集できるのは管理者のみ(越境) |
| 管理者は誰のメモでも編集 / 削除できる? | できる。管理者は越境可、全コーチ / 管理者が作成したメモを編集 / 削除可 |
| 管理者がメモを追加すると作成者は誰になる? | 操作した管理者の ID が `coach_user_id` カラムに記録される(カラム名は `coach_user_id` だが、実体は「作成者の User ID」) |
| 編集すると作成者(`coach_user_id`)は変わる? | 変わらない。「誰がこのメモを書いたか」の履歴的整合性を保つため、編集時に作成者は不変 |
| メモは SoftDelete / 物理削除? | 物理削除。誤削除リスクは UI の確認ダイアログで対応 |
| 親 Enrollment が削除されたらメモはどうなる? | 連動して物理削除される(FK ON DELETE CASCADE)。SoftDelete された Enrollment のメモは一覧から除外される |
| `coach_user_id` の User を退会させたら? | ON DELETE RESTRICT のため作成者 User の物理削除ができない(SoftDelete でしか退会できない)。SoftDelete された User の作成メモは表示できる(`withTrashed()` で作成者名を解決) |
| `passed` / `failed` Enrollment にもメモ追加できる? | できる。Enrollment の状態判定は本機能の責務外(コーチが修了済 / 失敗済の受講生に対しても申し送りメモを残せるよう許容) |
| メモの並び順は? | 時系列降順(`created_at DESC`)。最新メモが一覧の上に来る |
| 検索 / 絞り込み UI はある? | ない。時系列降順の閲覧のみ。検索動線は別チケット要件 |
| メモへのコメント / リアクション機能は? | ない。単純な本文のみ、対話機能なし |
| 添付ファイル(画像 / PDF)は付けられる? | ない。テキストのみ |
| コーチ離任時に作成済メモはどうなる? | 残る。管理者が必要に応じて手動で個別削除 / 編集する運用。自動的な `coach_user_id` 書き換え / 委譲は提供しない |
| 編集はインライン編集 / モーダル / 専用ページ どれ? | 専用ページ遷移(Basic 段階は JS なし、`S-B-06` と同じ純 Laravel パターン) |
| 削除確認ダイアログは? | HTML 標準 `onsubmit="return confirm('本当に削除しますか?')"` で十分(JS ファイル不要) |
| 受講生にメモが見えないことをどう保証する? | 二重防御: ① Blade で `@can('viewAny', [EnrollmentNote::class, $enrollment])` でセクション非表示、② エンドポイント直叩きでも `EnrollmentNotePolicy` が 403 を返す |
| フラッシュ文言の推奨は? | 追加「メモを追加しました。」/ 編集「メモを更新しました。」/ 削除「メモを削除しました。」(適切な日本語であれば文言の細部は採点対象外) |
