# S-B-03 プラン管理 Admin マスタ UI

## メタ情報

| 項目 | 値 |
|---|---|
| チケット ID | `S-B-03` |
| Feature 連番 | `plan-management-01` |
| Feature | plan-management |
| 種別 | Story |
| サブカテゴリ | 既存機能の拡張 |
| 難易度 | Basic |
| 工数 (h) | 6 |
| 依存チケット | (なし) |

## 概要

管理者(admin)が受講プランのマスタを CRUD + 状態遷移(下書き / 公開中 / アーカイブ)できる管理画面を実装する。提供 PJ にはプランのデータ構造 + 初期データ + 管理画面の表示部分 + 招待時 / プラン延長時にプランを参照する受講生側ロジックがすでに含まれており、受講生は管理画面のロジック側(認可・バリデーション・ルーティング)を組んで CRUD UI として機能させる。

## 背景・目的

- **現状の問題**: 提供 PJ にはプランのデータ構造と管理画面の表示部分、初期データが既にあるが、認可・ルーティング・バリデーションのロジック側が欠落しており画面が動作しない(404 になる)。運営側はプランの新設・受講期間 / 面談回数の改定・終売を直接操作できず、受講生招待時のプラン指定の前提が立ち上がらない。
- **達成したい状態**: 管理者がプラン管理画面からプランを CRUD でき、下書き → 公開中 → アーカイブの状態遷移で招待時に選べるプランを運営側でコントロールできる。公開中・アーカイブ済のプランは削除不可、また受講者が紐づいているプランも削除不可で、招待・受講中ユーザーとの参照整合性を守る。
- **価値・優先度**: プラン受講モデルの前提となるマスタ管理画面。本チケットが揃わないと、招待発行 / プラン延長 / 受講生ダッシュボードのプラン情報パネル など後続フローの検証ができない。

## ユーザーストーリー

- **管理者(admin)として**、受講プランを CRUD したい。なぜなら、運営側で受講期間 / 初期付与面談回数 / 公開状態を自由にコントロールしたいから。
- **管理者として**、プランを下書きから公開へ、または招待画面の選択肢から外すためにアーカイブへ遷移させたい。なぜなら、プランのライフサイクル(新設 → 提供 → 終売)を自前でハンドリングしたいから。
- **管理者として**、受講者が紐づいているプランや、公開中 / アーカイブ済のプランが誤って削除されない仕組みを期待する。なぜなら、受講中ユーザーの参照やプラン履歴が孤立して監査ができなくなるから。
- **受講生(student) / コーチ(coach)として**、admin 専用のプラン管理画面に直接アクセスしても 403 が返ることを期待する。なぜなら、運営側のマスタ管理画面に他ロールが入れる構成は事故の元だから。

## やること

### プラン

- **一覧**: 管理者のみ可、受講生 / コーチは 403。プラン名キーワード検索 + 状態フィルタ + ページネーション付きで表示、各行に受講者数を表示
- **詳細**: 管理者のみ可、他は 403。プランの基本情報 + 紐づく受講者一覧 + メタ情報(作成者 / 最終更新者 / 作成日時)を表示
- **新規作成**: 管理者のみ可、他は 403。作成成功で詳細画面にリダイレクト、初期状態は **下書き** で作成される
- **編集**: 管理者のみ可、他は 403。基本情報を更新できるが、状態は本フォームでは変えられない(状態遷移は別操作)
- **削除**: 管理者のみ可、他は 403。下書き状態かつ受講者が紐づいていないプランのみ物理削除可、それ以外(公開中 / アーカイブ済 / 受講者紐づきあり)は 409 を返して参照整合性を守る

### 状態遷移

- **公開する**(下書き → 公開中): 管理者のみ可、他は 403。下書き状態以外で 409
- **アーカイブ**(公開中 → アーカイブ): 管理者のみ可、他は 403。公開中状態以外で 409。アーカイブ後は招待画面 / プラン延長画面の選択肢から外れるが、受講中ユーザーの参照は維持される
- **下書きへ戻す**(アーカイブ → 下書き): 管理者のみ可、他は 403。アーカイブ状態以外で 409。誤アーカイブの取り戻し / 再提供準備の動線

### 共通の振る舞い

- 受講生 / コーチが管理者専用画面にアクセスすると 403
- 公開中 → 下書きへの **直接戻し** はできない(一度アーカイブを経由する)
- 一覧の並び順は公開中のプランを優先表示

## やらないこと

- **プラン価格情報の保持** — 決済は LMS 外で完結する方針のため、価格カラム自体を持たない(提供 PJ で確定済)
- **受講生招待時のプラン指定 / プラン情報の初期化**(`User.plan_id` / `plan_started_at` / `plan_expires_at` / `max_meetings`)— 招待・オンボーディングフローの責務(`B-B-05` ほか auth / user-management で扱う)
- **プラン延長**(`ExtendCourseAction` を利用した受講期間 + 面談回数の加算)— 提供 PJ 同梱、admin ユーザー詳細画面の延長ボタンから呼び出す(本チケットでは扱わない)
- **期限満了による自動卒業**(`users:graduate-expired` Schedule Command / `GraduateUserAction`)— 提供 PJ 同梱、本チケットでは扱わない
- **プラン履歴の表示**(プラン割当 / 延長 / 期限満了の履歴) — 提供 PJ 同梱の履歴記録ロジックを利用するのは別画面の責務(本チケットの詳細画面では受講者一覧のみ表示)
- **期限切れ判定 / 残日数算出** — 提供 PJ 同梱の判定 Service を別 Feature(受講生ダッシュボード等)が利用する
- **受講生 / コーチ向けのプラン情報表示** — ダッシュボード / プロフィール側の責務
- **プラン延長履歴 / 卒業履歴の Admin 一覧** — MVP 外
- **並び順をドラッグ&ドロップで操作する UI** — 並び順は数値入力で十分

## 受け入れ条件

- [ ] **一覧 - 認可**: 管理者がプラン管理画面の一覧を開くと一覧画面が表示され、受講生 / コーチが開くと 403
- [ ] **一覧 - 検索 / フィルタ**: プラン名キーワード(部分一致)と状態(下書き / 公開中 / アーカイブ)で絞り込みでき、ページネーションにフィルタ状態が引き継がれる
- [ ] **一覧 - 受講者数表示**: 一覧の各行にそのプランを契約中の受講者数が表示される
- [ ] **新規作成 - リダイレクト + フラッシュ**: 管理者が新規作成成功時、作成されたプラン詳細画面にリダイレクトされ、フラッシュメッセージが表示される
- [ ] **新規作成 - 初期状態**: 新規作成されたプランの状態が **下書き** で作成される
- [ ] **新規作成 - 認可拒否**: 受講生 / コーチが新規作成フォーム / 新規作成アクションにアクセスすると 403
- [ ] **編集 - リダイレクト + フラッシュ**: 編集成功時、プラン詳細画面にリダイレクトされ、フラッシュメッセージが表示される
- [ ] **編集 - 状態保持**: 編集フォームで状態(下書き / 公開中 / アーカイブ)は変更されない(状態遷移は別操作でのみ変更)
- [ ] **削除 - 下書き × 受講者なしのみ可**: 管理者が下書き状態かつ受講者が 0 名のプランを削除すると物理削除され、一覧画面にリダイレクト + フラッシュ表示
- [ ] **削除 - 公開中 / アーカイブガード**: 公開中 または アーカイブ状態のプランを削除しようとすると 409 + フラッシュエラーが表示される
- [ ] **削除 - 受講者紐づきガード**: 下書き状態であっても受講者が 1 名以上紐づくプランを削除しようとすると 409 + フラッシュエラーが表示される
- [ ] **状態遷移 - 公開**: 下書き状態のプランに対して公開を実行すると公開中に遷移し、詳細画面にリダイレクト + フラッシュ表示。下書き以外で実行すると 409
- [ ] **状態遷移 - アーカイブ**: 公開中状態のプランに対してアーカイブを実行するとアーカイブに遷移し、詳細画面にリダイレクト + フラッシュ表示。公開中以外で実行すると 409
- [ ] **状態遷移 - 下書きへ戻す**: アーカイブ状態のプランに対して下書きへ戻すを実行すると下書きに遷移し、詳細画面にリダイレクト + フラッシュ表示。アーカイブ以外で実行すると 409
- [ ] **状態遷移 - 認可拒否**: 受講生 / コーチが状態遷移操作にアクセスすると 403

## 実装方針

> **参考設計の一例**。受け入れ条件を満たせれば実装手段は問わない。受講生は提供 PJ コード + ヒアリングで自分の設計を組み立てる。

### 主要 URL

| メソッド | パス | 振る舞い |
|---|---|---|
| GET | `/admin/plans` | 一覧(キーワード検索 + 状態フィルタ + ページネーション、フィルタ引き継ぎ、各行に受講者数) |
| GET | `/admin/plans/create` | 新規作成フォーム表示 |
| POST | `/admin/plans` | 新規作成、成功時 `/admin/plans/{plan}` リダイレクト + フラッシュ「プランを作成しました。」、`status=draft` で INSERT |
| GET | `/admin/plans/{plan}` | 詳細(基本情報 + 紐づく受講者一覧 + メタ情報 + 状態遷移ボタン) |
| GET | `/admin/plans/{plan}/edit` | 編集フォーム表示 |
| PUT | `/admin/plans/{plan}` | 更新、成功時 `/admin/plans/{plan}` リダイレクト + フラッシュ「プランを更新しました。」、`status` は変更しない |
| DELETE | `/admin/plans/{plan}` | 削除(下書き × 受講者なし のみ物理削除、成功時 `/admin/plans` リダイレクト + フラッシュ「プランを削除しました。」)。公開中 / アーカイブなら 409 + フラッシュエラー「このプランは削除できません。下書き状態かつ受講者が紐づいていないプランのみ削除できます。」、受講者紐づきありなら 409 + フラッシュエラー「このプランは受講者が紐づいているため削除できません。」 |
| POST | `/admin/plans/{plan}/publish` | 公開(下書き → 公開中)、成功時 詳細画面リダイレクト + フラッシュ「プランを公開しました。」。下書き以外なら 409 |
| POST | `/admin/plans/{plan}/archive` | アーカイブ(公開中 → アーカイブ)、成功時 詳細画面リダイレクト + フラッシュ「プランをアーカイブしました。」。公開中以外なら 409 |
| POST | `/admin/plans/{plan}/unarchive` | 下書きへ戻す(アーカイブ → 下書き)、成功時 詳細画面リダイレクト + フラッシュ「プランを下書きへ戻しました。」。アーカイブ以外なら 409 |

### データモデル

> **既存テーブル**(提供 PJ に同梱、変更不要)。受講生は新規にマイグレーション / Model を作成しない。下記カラム構成と Enum を理解した上で Controller / FormRequest を組む。

`plans`(ULID 主キー、物理削除 = SoftDelete 不採用):

| カラム | 型 | NOT NULL | FK / UNIQUE | 補足 |
|---|---|:---:|---|---|
| id | ulid | ✓ | PK | `$table->ulid('id')->primary()` |
| name | varchar(100) | ✓ | | プラン名 |
| description | text | | | 説明文 |
| duration_days | unsigned smallint | ✓ | | 受講期間(日、1〜3650) |
| default_meeting_quota | unsigned smallint | ✓ | | 初期付与面談回数(0〜1000) |
| status | varchar(20) | ✓ | | `PlanStatus` Enum cast(`draft` / `published` / `archived`)、デフォルト `draft` |
| sort_order | unsigned int | ✓ | | 並び順、デフォルト 0 |
| created_by_user_id | ulid | ✓ | users.id, ON DELETE RESTRICT | 作成者 |
| updated_by_user_id | ulid | ✓ | users.id, ON DELETE RESTRICT | 最終更新者 |
| created_at | timestamp | | | `$table->timestamps()` |
| updated_at | timestamp | | | `$table->timestamps()` |

- **インデックス**: `(status, sort_order)` 複合(提供 PJ で既設)
- **Enum / Cast**: `PlanStatus`(Draft / Published / Archived)→ `plans.status` に cast、`label()` 戻り値「下書き」「公開中」「アーカイブ」
- **リレーション**: User belongsTo (createdBy / updatedBy) / User hasMany (`plan_id` 外部キーで受講者を引く) / UserPlanLog hasMany(履歴、本チケットでは扱わないが詳細画面の参照整合性のため存在を意識)
- **削除戦略**: 物理削除のみ採用(SoftDelete 不採用、`backend-models.md` の「マスタ系で Draft/Published/Archived の status 列を持つ Entity は SoftDelete 採用しない」規約準拠)
- **Scope**(受講生判断、推奨): `scopePublished` / `scopeOrdered`

### バリデーション

`StoreRequest` / `UpdateRequest`:

| 入力項目 | ルール | 推奨エラーメッセージ例 |
|---|---|---|
| name | required / string / max:100 | プラン名は必須です。<br>プラン名は 100 文字以内で入力してください。 |
| description | nullable / string / max:2000 | 説明は 2000 文字以内で入力してください。 |
| duration_days | required / integer / min:1 / max:3650 | 受講期間(日)は必須です。<br>受講期間(日)は 1〜3650 の範囲で入力してください。 |
| default_meeting_quota | required / integer / min:0 / max:1000 | 初期付与面談回数は必須です。<br>初期付与面談回数は 0〜1000 の範囲で入力してください。 |
| sort_order | nullable / integer / min:0 | 並び順は 0 以上の整数で入力してください。 |

`IndexRequest`: `keyword`(任意 / 文字列 / max:100)/ `status`(任意 / `Rule::enum(PlanStatus::class)`)。

### 認可設計

**Policy**: `PlanPolicy`

| メソッド | ロール × 判定 |
|---|---|
| viewAny / view / create / update / delete | 管理者(admin): ✅ / コーチ: ❌ / 受講生: ❌ |
| publish / archive / unarchive | 管理者: ✅ / コーチ: ❌ / 受講生: ❌ |

- 削除可否(`delete`)は **admin の真偽判定** のみ Policy 側で行う。「下書き状態かつ受講者なしのみ削除可」という **状態ベース・参照ベースのガード** は Controller / Action 内で `PlanNotDeletableException`(409) として実装する(認可と整合性チェックの責務分離、`backend-policies.md` 準拠)
- 状態遷移(publish / archive / unarchive)の遷移元検証も同様に Controller / Action 内で `PlanInvalidTransitionException`(409) を throw

### テスト観点

| 種別 | 観点 |
|---|---|
| Unit | `PlanStatus` Enum(cast / `label()` 日本語) / Model リレーション(createdBy / updatedBy / users) |
| Feature | 各エンドポイントの認可分岐(管理者 / コーチ / 受講生)/ 副作用(INSERT・UPDATE・物理削除・状態遷移)/ フラッシュ表示有無 / リダイレクト先パス / 公開中 / アーカイブ削除時の 409 + 元画面に戻る + フラッシュエラー / 下書き × 受講者紐づきあり 削除時の 409 / 状態遷移違反時の 409 / 検索・フィルタのページネーション引き継ぎ / 新規作成時の `status=draft` 初期化 / 一覧の受講者数表示 |
| Policy | 各メソッド × 各ロールの真偽判定(admin true / coach false / student false 網羅) |

### アーキテクチャ判断

> **Basic 範囲制約**: Service / Action(UseCases) は教材範囲外。本チケットは **Controller 内完結を前提** に詳細を記述する。Action / Service への分離は受講生判断(チャレンジするなら歓迎、振る舞いが受け入れ条件を満たせば OK)。

- **採用技術**: Eloquent + Controller(受講生判断で Action 分割可) + Policy + FormRequest + Blade(提供済み) + `DB::transaction`
- **設計判断**:
  1. **Controller 分離**: 一覧 / 詳細 / 新規 / 編集 / 削除 を `PlanController`、状態遷移(`publish` / `archive` / `unarchive`)を `PlanStatusController` に分けると責務が明確(Single Responsibility)。1 Controller に集約しても可
  2. **状態遷移の検証**: 各遷移メソッド冒頭で「現在状態が遷移元と一致するか」を検証して `PlanInvalidTransitionException`(409) を throw。メッセージ文字列は例外クラス側が所有する(`backend-exceptions.md` 規約: Action から個別文字列を渡さず、`forPublish()` / `forArchive()` / `forUnarchive()` static factory でメッセージのバリエーションを提供)
  3. **削除制約**: `destroy` メソッド内で「下書き以外 → 409」「受講者紐づきあり → 409」の 2 段ガード。下書き状態かつ受講者なしのときのみ物理削除
  4. **作成 / 更新者の記録**: 新規作成時に `created_by_user_id` / `updated_by_user_id` を `auth()->user()->id` で記録(`fillable` で許可済み)。更新・状態遷移時にも `updated_by_user_id` を更新
  5. **ルート**: `Route::resource('plans', PlanController::class)->parameters(['plans' => 'plan'])->names('admin.plans')` で CRUD 7 ルート + 状態遷移 3 ルートを `admin` プレフィックス + `auth + role:admin` Middleware 配下に配置
  6. **一覧の並び順**: 「公開中 → 下書き → アーカイブ」優先 + `sort_order` ASC + `created_at` DESC が UX 上望ましい。MySQL は `FIELD(status, 'published', 'draft', 'archived')`、SQLite は `CASE status WHEN 'published' THEN 1 WHEN 'draft' THEN 2 WHEN 'archived' THEN 3 END` で同等順序(あるいは単純な `sort_order` ASC + `created_at` DESC のみでも振る舞い OK)
  7. **N+1 回避**: 一覧画面では `withCount('users')` で受講者数を 1 クエリ追加で取得(各行ごとに count クエリが走らない)
  8. **詳細画面の受講者一覧**: `$plan->load(['users', 'createdBy', 'updatedBy'])` で Eager Load。受講者数が多い場合の表示制限(直近 N 名・ページネーション等)は受講生判断(初期データで数名想定なので必須ではない)

### 関連ファイルメモ

- `app/Models/Plan.php` / `app/Enums/PlanStatus.php`
- `app/Http/Controllers/PlanController.php` / `app/Http/Controllers/PlanStatusController.php`
- `app/UseCases/Plan/{Index,Show,Store,Update,Destroy,Publish,Archive,Unarchive}Action.php`(※ 模範解答 PJ で採用、Basic 受講生は Controller 内完結も可)
- `app/Policies/PlanPolicy.php`
- `app/Http/Requests/Plan/{Store,Update,Index}Request.php`
- `app/Exceptions/Plan/{PlanNotDeletable,PlanInvalidTransition,PlanNotPublished,UserNotInProgress}Exception.php`(本チケットでは `PlanNotDeletable` / `PlanInvalidTransition` のみ利用、後 2 つは提供 PJ 同梱の他 Action が使用)
- `resources/views/plan/management/{index,show,create,edit}.blade.php`(提供 PJ 既存、ロック対象)
- `database/migrations/*_create_plans_table.php` / `database/migrations/*_add_plan_columns_to_users_table.php` / `database/migrations/*_create_user_plan_logs_table.php`(いずれも提供 PJ 既存)
- `database/seeders/PlanSeeder.php`(提供 PJ 既存、状態網羅 published × 3 / draft × 1 / archived × 1 + 受講生への紐づけ)
- `routes/web.php` の admin プレフィックス内に `Route::resource('plans', ...)` + status route 3 本を追加
- 類似パターン参考: `MeetingPackController` / `MeetingPackStatusController`(`meeting-quota` Feature の admin マスタ CRUD + 状態遷移、本チケットとほぼ同型 — ただし削除戦略は MeetingPack=SoftDelete に対し Plan=物理削除、削除条件も MeetingPack=「下書き / アーカイブ可」に対し Plan=「下書き × 受講者なしのみ可」と異なる)

## 補足

### 想定ヒアリング Q&A

| 質問 | 回答 |
|---|---|
| 受講期間(日) / 初期付与面談回数の上限・下限は? | 受講期間は 1〜3650 日、初期付与面談回数は 0〜1000 回 |
| 説明 / 並び順は必須? | いずれも任意。並び順は省略時 0 |
| プランに価格は持たせる? | 持たない。決済は LMS 外で完結し、LMS 内では `duration_days` + `default_meeting_quota` の組み合わせのみマスタとして管理する |
| 認可は誰がアクセス可能? | 管理者(admin)のみ。受講生 / コーチはプラン管理画面の全操作で 403 |
| 認可拒否時の HTTP ステータスは 403 / 404? | 403(受講生 / コーチが直接画面を開いても 403 を返す。404 では返さない) |
| 公開中 / アーカイブのプランを削除しようとしたら? | 409 を返す。下書き状態のみ削除可 |
| 下書き状態でも受講者が紐づいているプランを削除しようとしたら? | 409 を返す。下書き状態 **かつ** 受講者が 1 名も紐づいていないプランのみ削除可 |
| 公開中 → 下書きの直接戻しはできる? | 不可。一度アーカイブを経由し、アーカイブから下書きへ戻す |
| 削除は SoftDelete / 物理削除? | 物理削除。下書き × 受講者なし の前提を担保しているため、論理削除は採用しない |
| 公開中のプランの編集はできる? | 可。基本情報(プラン名 / 説明 / 受講期間 / 初期付与面談回数 / 並び順)は公開後も変更可。既に受講中のユーザーには影響しない(新規招待時に新しい値で初期化される) |
| 一覧の並び順は? | 「公開中 → 下書き → アーカイブ」優先 + 並び順 昇順 + 作成日時 降順 が UX 上望ましい。「並び順 昇順 → 作成日時 降順」だけでも振る舞い OK |
| 検索のヒット範囲は? | プラン名 の部分一致のみ(説明 は対象外) |
| ページネーションは何件 / ページ? | 20 件推奨(リスト規模が小さいので 50 でも 100 でも受講生判断で OK) |
| 作成者 / 最終更新者 は誰の ID? | 作成時は作成した管理者の ID、その後の更新時(編集・状態遷移含む)は更新した管理者の ID |
| 一覧の受講者数表示は N+1 にならない? | `withCount('users')` で 1 クエリ追加で集計するのが推奨。各行ごとの count クエリが走る実装は避ける |
| 詳細画面の受講者一覧はページネーション必要? | 必須ではない。初期データで数名想定なので全件表示で十分(規模が大きくなったら別チケットで対応) |
| フラッシュ文言の推奨は? | 作成「プランを作成しました。」/ 更新「プランを更新しました。」/ 削除「プランを削除しました。」/ 公開「プランを公開しました。」/ アーカイブ「プランをアーカイブしました。」/ 下書きへ戻す「プランを下書きへ戻しました。」/ 状態違反「下書きのプランのみ公開できます。」「公開中のプランのみアーカイブできます。」「アーカイブ済みのプランのみ下書きへ戻せます。」/ 削除エラー「このプランは削除できません。下書き状態かつ受講者が紐づいていないプランのみ削除できます。」「このプランは受講者が紐づいているため削除できません。」(適切な日本語であれば文言の細部は採点対象外) |
