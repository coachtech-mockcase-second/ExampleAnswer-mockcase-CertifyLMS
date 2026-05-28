# S-B-06 個人目標(EnrollmentGoal)CRUD

## メタ情報

| 項目 | 値 |
|---|---|
| チケット ID | `S-B-06` |
| Feature 連番 | `enrollment-03` |
| Feature | enrollment |
| 種別 | Story |
| サブカテゴリ | 新規機能の構築 |
| 難易度 | Basic |
| 工数 (h) | 8 |
| 依存チケット | (なし) |

## 概要

受講生が資格ごとの受講登録(Enrollment)配下に個人目標を自由に立て、達成状況をマークできる新規機能を実装する。受講生本人のみ CRUD 可、担当コーチ / 管理者は受講生詳細画面で閲覧のみ可。新規テーブル `enrollment_goals` を追加し、Enrollment 詳細画面に目標一覧 + 追加フォーム、編集は専用ページ、削除 / 達成マーク / 達成解除はフォーム POST で実現する。

## 背景・目的

- **現状の問題**: 提供 PJ の受講生は資格ごとに学習を進められるが、「いつまでに何を達成したいか」を LMS 内に記録する手段がない。受講生はモチベーション維持のために自分で目標を立て進捗を可視化したいが、その動線が無く、コーチ面談や Q&A 等の人手介入が必要な場面以外は受講生個人の頭の中に閉じてしまう。
- **達成したい状態**: 受講生が資格ごとに目標(例「8 月末までに過去問 5 年分を 1 周」「Section X を今週中に修了」)を CRUD でき、達成時にワンクリックで達成マークを付けられる。Enrollment 詳細画面に目標一覧 + 達成 / 未達成の視覚的区別が表示され、コーチ / 管理者も受講生の自主的目標を覗き見れる(介入はせず閲覧のみ)。
- **価値・優先度**: 受講生の自走力を高める **モチベーション維持機能**。担当コーチも目標を介して受講生の現在地を把握でき、面談や chat での声かけの参考になる。新規エンティティの CRUD として「Eloquent + Policy + FormRequest + Blade」のフル組み立てが揃う構成。

## ユーザーストーリー

- **受講生(student)として**、受講中の資格ごとに目標を CRUD したい。なぜなら、自分のペースで学習計画を立てて進捗を管理したいから。
- **受講生として**、達成した目標にワンクリックで達成マークを付けたい。なぜなら、達成感を可視化してモチベーションを維持したいから。
- **受講生として**、誤って達成マークしてしまった目標を取り消したい。なぜなら、間違って押した場合の補正動線が欲しいから。
- **コーチ(coach)として**、担当資格に登録した受講生の個人目標を閲覧したい。なぜなら、面談や chat で目標達成状況を踏まえて声をかけたいから。介入はしない(編集 / 削除 / 達成マークは行わない)。
- **管理者(admin)として**、任意受講生の個人目標を閲覧したい。なぜなら、運用上の受講生フォロー状況を把握したいから。介入はしない。

## やること

### 個人目標(受講生本人のみ操作可)

- **追加**: 受講生本人のみ可、他は 403。Enrollment 詳細画面のフォームから目標(タイトル + 詳細(任意) + 目標期日(任意))を入力 → 追加成功で Enrollment 詳細画面にリダイレクト + フラッシュ表示
- **編集**: 受講生本人のみ可、他は 403。専用編集ページに遷移してタイトル / 詳細 / 目標期日 を更新できる(達成マーク状態は本フォームでは変更しない、専用エンドポイントから)
- **削除**: 受講生本人のみ可、他は 403。物理削除(履歴は保持しない)、削除後は Enrollment 詳細画面にリダイレクト + フラッシュ表示
- **達成マーク**: 受講生本人のみ可、他は 403。未達成の目標を「達成済」に切替(達成日時を現在時刻でセット)、達成済の目標に対しては時刻のみ書き換える(べき等)
- **達成解除**: 受講生本人のみ可、他は 403。達成済の目標を「未達成」に戻す(達成日時を NULL に戻す)、未達成の目標に対してもエラーにせずべき等

### 閲覧

- **受講生本人**: 自分の Enrollment 詳細画面で自分の目標一覧を時系列降順 + 未達成優先で表示
- **コーチ**: 担当資格(`certification_coach_assignments` 経由)に登録した受講生の Enrollment 詳細画面で目標一覧を閲覧可、CRUD / 達成マークボタンは表示されない(認可で 403)
- **管理者**: 全 Enrollment の目標一覧を閲覧可、CRUD / 達成マークボタンは表示されない(同上)
- **第三者**: 担当外コーチ / 他受講生は親 Enrollment 詳細画面自体が 403 / 404 のためそもそも目標一覧にアクセスできない

### 共通の振る舞い

- 親 Enrollment が SoftDelete されている場合、目標一覧から除外される(Enrollment cascade 削除に追従)
- Enrollment が `passed` / `failed` 状態でも目標 CRUD は可(状態判定は本機能の責務外、Enrollment 側でブロックされる場合は Middleware で 403)
- 受講停止状態(修了済 / 退会済 / 招待中)の受講生は親 Enrollment アクセス時点で 403 のため目標操作も到達不可

## やらないこと

- **目標テンプレートのマスタ管理**(`product.md` 明示) — 受講生個別の自由入力のみ
- **資格非紐づきの総合目標**(複数資格をまたぐ生活習慣等) — 個人目標は Enrollment 単位(資格紐づき)のみ
- **達成時の自動通知**(コーチ / 管理者宛 push)— 受講生の自己マークで完結
- **目標達成統計 / KPI ダッシュボード** — 別 Feature(dashboard)の責務、本チケットは個別 CRUD のみ
- **目標のリマインダー(目標期日前のメール / プッシュ通知)** — MVP 外
- **コーチ / 管理者による目標へのコメント / レビュー機能** — 閲覧専用、介入動線なし
- **目標の重要度 / 優先度フラグ** — フィールド最小化、視覚区別は達成 / 未達成のみ
- **目標 SoftDelete + 復元 UI** — 物理削除のみ(誤削除リスクは UI 側の確認ダイアログで対応)
- **目標履歴(達成 → 解除 → 達成の遷移ログ)** — `achieved_at` カラム 1 つでの状態管理のみ、履歴は持たない
- **チェックリスト形式のサブタスク** — 1 目標 = 1 行のフラット構造のみ

## Seeder 設計

> `migrate:fresh --seed` 直後に動作確認できるよう、シナリオに紐付けたレコード単位で具体化する。

**前提**(他 Seeder で投入される想定): 固定受講生(`student@certify-lms.test`)/ デモ受講生 × 数名 / 各受講生は 1〜複数の Enrollment(基礎ターム / 実践ターム の混在)を保有

`EnrollmentGoalSeeder`(各 Enrollment 配下に複数目標を投入):

| レコード分類 | 内容 | 動作確認用途 |
|---|---|---|
| 固定受講生 × 基礎ターム Enrollment | 未達成 × 3(目標期日: 今日 / 1 週間後 / 期日なし)/ 達成済 × 2(2 日前 / 5 日前に達成) | 自分の目標一覧表示確認 / 達成マーク + 達成解除動作 / 編集 + 削除動作 |
| 固定受講生 × 実践ターム Enrollment | 未達成 × 2 / 達成済 × 1 | 異なる Enrollment 間の目標分離確認(別 Enrollment 目標が混入しない) |
| デモ受講生 × 数件 | 各 1〜3 件、未達成 / 達成済を混在 | 認可分岐確認(他受講生目標の閲覧 / CRUD で 403)/ コーチ閲覧確認(担当資格に登録した受講生の目標が見える) |

- **DatabaseSeeder への追加順序**: `UserSeeder` → `CertificationSeeder` → `EnrollmentSeeder` → `EnrollmentGoalSeeder`

## 受け入れ条件

- [ ] **追加 - リダイレクト + フラッシュ**: 受講生が自分の Enrollment 詳細画面で目標追加フォーム送信成功時、その Enrollment 詳細画面にリダイレクトされ、フラッシュメッセージが表示される
- [ ] **追加 - 初期状態**: 新規追加された目標の達成状態が **未達成** で作成される(達成日時 NULL)
- [ ] **追加 - 認可拒否**: コーチ / 管理者 / 他受講生が目標追加アクションにアクセスすると 403
- [ ] **編集 - 専用ページ**: 受講生本人が目標編集リンクをクリックすると編集専用ページに遷移し、タイトル / 詳細 / 目標期日 を更新できる
- [ ] **編集 - リダイレクト + フラッシュ**: 編集成功時、親 Enrollment 詳細画面にリダイレクトされ、フラッシュメッセージが表示される
- [ ] **編集 - 認可拒否**: 他受講生 / コーチ / 管理者が編集フォーム表示または更新アクションにアクセスすると 403
- [ ] **編集 - 達成状態保持**: 編集フォームで達成 / 未達成の状態は変更されない(達成マーク / 解除は別操作でのみ変更)
- [ ] **削除 - 物理削除**: 受講生本人が削除すると物理削除され、Enrollment 詳細画面にリダイレクト + フラッシュ表示
- [ ] **削除 - 認可拒否**: 他受講生 / コーチ / 管理者が削除アクションにアクセスすると 403
- [ ] **達成マーク - 受講生本人**: 受講生本人が未達成目標に達成マークを実行すると達成済に切替、Enrollment 詳細画面にリダイレクト + フラッシュ表示
- [ ] **達成マーク - べき等**: 既に達成済の目標に再度達成マークを実行してもエラーにならず、達成日時のみ書き換わる(成功扱い)
- [ ] **達成マーク - 認可拒否**: 他受講生 / コーチ / 管理者が達成マークアクションにアクセスすると 403
- [ ] **達成解除 - 受講生本人**: 受講生本人が達成済目標の達成解除を実行すると未達成に戻り、Enrollment 詳細画面にリダイレクト + フラッシュ表示
- [ ] **達成解除 - べき等**: 既に未達成の目標に再度達成解除を実行してもエラーにならず、達成日時が NULL のまま維持される(成功扱い)
- [ ] **達成解除 - 認可拒否**: 他受講生 / コーチ / 管理者が達成解除アクションにアクセスすると 403
- [ ] **一覧表示 - 受講生本人**: 受講生本人の Enrollment 詳細画面で自分の目標が一覧表示され、未達成 / 達成済 の視覚的区別がある
- [ ] **一覧表示 - コーチ閲覧**: 担当資格に登録した受講生の Enrollment 詳細画面でコーチが目標一覧を閲覧でき、CRUD / 達成マーク操作ボタンは表示されない
- [ ] **一覧表示 - 管理者閲覧**: 管理者が任意受講生の Enrollment 詳細画面で目標一覧を閲覧でき、CRUD / 達成マーク操作ボタンは表示されない
- [ ] **一覧表示 - 他受講生不可**: 他受講生が他人の Enrollment 詳細画面にアクセスすると Enrollment 側の認可で 403(目標一覧にも到達不可)

## 実装方針

> **参考設計の一例**。受け入れ条件を満たせれば実装手段は問わない。受講生は提供 PJ コード + ヒアリングで自分の設計を組み立てる。

### 主要 URL

| メソッド | パス | 振る舞い |
|---|---|---|
| POST | `/enrollments/{enrollment}/goals` | 目標追加、成功時 `/enrollments/{enrollment}` リダイレクト + フラッシュ「目標を追加しました。」、`achieved_at = NULL` で INSERT |
| GET | `/enrollment-goals/{goal}/edit` | 編集フォーム表示(受講生本人のみ、他は 403) |
| PATCH | `/enrollment-goals/{goal}` | 編集更新、成功時 `/enrollments/{enrollment}` リダイレクト + フラッシュ「目標を更新しました。」、`achieved_at` は変更しない |
| DELETE | `/enrollment-goals/{goal}` | 物理削除、成功時 `/enrollments/{enrollment}` リダイレクト + フラッシュ「目標を削除しました。」 |
| POST | `/enrollment-goals/{goal}/achieve` | 達成マーク、`achieved_at = now()` で UPDATE、成功時 `/enrollments/{enrollment}` リダイレクト + フラッシュ「目標を達成済にしました。」、既達成でもべき等 (時刻書き換え) |
| DELETE | `/enrollment-goals/{goal}/achieve` | 達成解除、`achieved_at = NULL` で UPDATE、成功時 `/enrollments/{enrollment}` リダイレクト + フラッシュ「目標の達成マークを取り消しました。」、未達成でもべき等 |

> **route 名**: `enrollments.goals.store` / `enrollment-goals.edit` / `enrollment-goals.update` / `enrollment-goals.destroy` / `enrollment-goals.markAchieved` / `enrollment-goals.unmarkAchieved`(`auth` Middleware 配下の認証済ルート、ロール Middleware は不要 = Policy が当事者判定で完結)。
> **GET 一覧 / 詳細単独画面はない**: 一覧は親 Enrollment 詳細画面(`enrollments.show`)の partial として表示される、個別目標の詳細単独画面も持たない。

### データモデル

**新規テーブル**: `enrollment_goals`(ULID 主キー、SoftDelete 不採用 = 物理削除)

| カラム | 型 | NOT NULL | FK / UNIQUE | 補足 |
|---|---|:---:|---|---|
| id | ulid | ✓ | PK | `$table->ulid('id')->primary()` |
| enrollment_id | ulid | ✓ | enrollments.id, ON DELETE CASCADE | `$table->foreignUlid('enrollment_id')->constrained()->cascadeOnDelete()`(親 Enrollment 削除で配下目標も自動削除) |
| title | varchar(100) | ✓ | | 目標タイトル |
| description | varchar(1000) | | | 詳細(任意) |
| target_date | date | | | 目標期日(任意) |
| achieved_at | timestamp | | | 達成日時(NULL = 未達成、datetime = 達成済) |
| created_at | timestamp | | | `$table->timestamps()` |
| updated_at | timestamp | | | `$table->timestamps()` |

- **インデックス**: `(enrollment_id, achieved_at)` 複合(Enrollment 配下の目標一覧 + 達成 / 未達成フィルタの高速化)
- **Cast**: `target_date` → `date`、`achieved_at` → `datetime`
- **リレーション**: Enrollment 1-N EnrollmentGoal(`Enrollment::goals()` で `hasMany`)
- **ヘルパーメソッド**(受講生判断、推奨): `isAchieved(): bool` を Model に生やすと Blade 内で `@if ($goal->isAchieved())` で簡潔に判定可
- **削除戦略**: 物理削除のみ採用(SoftDelete 不採用、`backend-models.md` の「進捗・履歴・累計集計テーブルは SoftDelete 採用しない」規約準拠)

### バリデーション

`StoreRequest` / `UpdateRequest`(同一ルール):

| 入力項目 | ルール | 推奨エラーメッセージ例 |
|---|---|---|
| title | required / string / max:100 | 目標は必須です。<br>目標は 100 文字以内で入力してください。 |
| description | nullable / string / max:1000 | 詳細は 1000 文字以内で入力してください。 |
| target_date | nullable / date | 目標期日は有効な日付で入力してください。 |

`achieved_at` は本フォームでは受け取らない(専用エンドポイント `POST /enrollment-goals/{goal}/achieve` / `DELETE /enrollment-goals/{goal}/achieve` 経由のみで更新)。

達成マーク / 達成解除は入力項目なしなので FormRequest 不要(Controller method で完結)。

### 認可設計

**Policy**: `EnrollmentGoalPolicy`

| メソッド | ロール × 判定 |
|---|---|
| viewAny(Enrollment) | 親 Enrollment へのアクセス権がある全員(受講生本人 / 担当コーチ / 管理者) = `EnrollmentPolicy::view` に委譲 |
| view(EnrollmentGoal) | 親 Enrollment へのアクセス権がある全員(同上) |
| create(Enrollment) | 受講生本人のみ ✅(`role === Student && enrollment.user_id === user.id`)、他は ❌ |
| update | 受講生本人のみ ✅(`role === Student && goal.enrollment.user_id === user.id`)、他は ❌ |
| delete | 同上 |
| markAchieved / unmarkAchieved | 同上 |

- **責務委譲**: `view` / `viewAny` は親 `EnrollmentPolicy::view` に委譲(目標固有の閲覧スコープを持たない、Enrollment 配下にいるなら見える)。`create` / `update` / `delete` / `markAchieved` / `unmarkAchieved` は受講生本人判定を本 Policy 内で完結
- **コーチ / 管理者の CRUD 拒否**: `update` / `delete` 等で `$user->role !== UserRole::Student` を冒頭判定して false 返却(受講生本人以外は介入不可)
- **Blade での出し分け**: 一覧表示画面(`enrollments.show`)内で `@can('create', [EnrollmentGoal::class, $enrollment])` で「目標追加フォーム」の表示制御、`@can('update', $goal)` / `@can('delete', $goal)` / `@can('markAchieved', $goal)` で各操作ボタンの表示制御

### テスト観点

| 種別 | 観点 |
|---|---|
| Unit | `EnrollmentGoal` Model のリレーション(`enrollment` BelongsTo)/ `isAchieved()` ヘルパーの真偽判定 / Cast(`target_date` → date, `achieved_at` → datetime) |
| Feature | 各エンドポイントの認可分岐(受講生本人 / 他受講生 / 担当コーチ / 担当外コーチ / 管理者)/ 副作用(INSERT・UPDATE・物理削除・`achieved_at` セット / クリア)/ フラッシュ表示有無 / リダイレクト先パス / 達成マーク / 達成解除のべき等性 / 編集フォームでの `achieved_at` 不変 / Enrollment 詳細画面での目標一覧表示 + CRUD ボタンの認可別出し分け |
| Policy | 各メソッド × 各ロールの真偽判定(受講生本人 true / 他受講生 false / 担当コーチ view のみ true / その他 false / 管理者 view のみ true / その他 false の網羅) |

### アーキテクチャ判断

> **Basic 範囲制約**: 教材外の Action / Service は使わない前提で **Controller 内完結** を基本とする。Action 採用は受講生判断(チャレンジするなら歓迎、振る舞いが受け入れ条件を満たせば OK)。

- **採用技術**: Eloquent + Controller(受講生判断で Action 分割可) + Policy + FormRequest + Blade(提供済み) + Route Model Binding
- **設計判断**:
  1. **画面構成**: 目標一覧 + 追加フォームは親 `enrollments.show` 画面に partial(`_partials/goal-list.blade.php` + `_partials/goal-form.blade.php` 等)として埋め込み、編集は専用ページ(`enrollment-goals.edit`)へ遷移する純 Laravel パターン(`frontend-blade.md`「Basic チケットの Blade(JS なし)」規約準拠 — JS によるインライン編集や Modal 展開は使わない)
  2. **ルート命名**: `enrollments.goals.store`(親 Enrollment のネスト URL `/enrollments/{enrollment}/goals` で POST)+ `enrollment-goals.{edit,update,destroy,markAchieved,unmarkAchieved}`(個別目標は ID 解決可能なため `/enrollment-goals/{goal}` のフラット URL でルーティング)
  3. **HTTP メソッド選択**: 達成マーク = `POST /enrollment-goals/{goal}/achieve`、達成解除 = `DELETE /enrollment-goals/{goal}/achieve` という REST 風の対称設計。状態切替の業務操作を「POST = 達成扱いリソースを作る / DELETE = 達成扱いリソースを消す」とメタファー的に表現する
  4. **べき等性の担保**: 達成マーク Action 内で「既達成なら何もしない」分岐は持たず、`update(['achieved_at' => now()])` を無条件実行(時刻のみ書き換え)。達成解除も `update(['achieved_at' => null])` を無条件実行。重複呼び出しでもエラーにならず副作用が冪等
  5. **Policy ロジックの責務委譲**: `view` / `viewAny` は `EnrollmentPolicy::view` に委譲して目標 固有の閲覧スコープ判定を持たない(親 Enrollment にアクセスできる権限なら目標も見える、責務の重複を排除)
  6. **物理削除**: SoftDelete 不採用。誤削除リスクは UI 側の `onsubmit="return confirm()"`(HTML 標準 confirm)でカバー(`frontend-blade.md` Basic 規約準拠 — JS ファイル不要、純 HTML)
  7. **状態整合性の心配なし**: `passed` / `failed` Enrollment であっても目標 CRUD は許容(目標は進捗管理ツール、状態判定は本機能の責務外)。Enrollment 側で「修了済資格は目標追加不可」のような制約が必要なら別チケットで `Middleware` / `Policy` 拡張する。本チケットでは Enrollment 状態は問わない

### 関連ファイルメモ

- `app/Models/EnrollmentGoal.php` / `app/Models/Enrollment.php`(後者は既存、`goals()` リレーションを定義 — 既存 PJ で定義済か要確認)
- `app/Http/Controllers/EnrollmentGoalController.php`(`store` / `edit` / `update` / `destroy` / `markAchieved` / `unmarkAchieved`)
- `app/UseCases/EnrollmentGoal/{Store,Update,Destroy,MarkAchieved,UnmarkAchieved}Action.php`(※ 模範解答 PJ で採用、Basic 受講生は Controller 内完結も可)
- `app/Policies/EnrollmentGoalPolicy.php`
- `app/Http/Requests/EnrollmentGoal/{Store,Update}Request.php`
- `resources/views/enrollment-goal/edit.blade.php` + `resources/views/enrollment/_partials/goal-list.blade.php` + `_partials/goal-form.blade.php`(提供 PJ 既存、ロック対象)
- `database/migrations/*_create_enrollment_goals_table.php`
- `database/seeders/EnrollmentGoalSeeder.php`(提供 PJ 既存、未達成 / 達成済 混在 + Enrollment 別バリエーション)
- `routes/web.php` の認証済グループ内に `Route::post('enrollments/{enrollment}/goals', ...)` + `Route::get('enrollment-goals/{goal}/edit', ...)` + `Route::patch('enrollment-goals/{goal}', ...)` + `Route::delete('enrollment-goals/{goal}', ...)` + `Route::post('enrollment-goals/{goal}/achieve', ...)` + `Route::delete('enrollment-goals/{goal}/achieve', ...)` を追加
- 類似パターン参考: 既存 PJ で同様の「Enrollment 配下の子エンティティ」は本チケットが初出だが、`EnrollmentNote`(`S-B-08`)とは姉妹関係(目標 = 受講生本人 CRUD / ノート = コーチ + 管理者 CRUD)。Policy の責務委譲パターン(親 Policy への `view` 委譲)は両者で共通

## 補足

### 想定ヒアリング Q&A

| 質問 | 回答 |
|---|---|
| 目標タイトル / 詳細の文字数上限は? | タイトルは 100 文字、詳細は 1000 文字 |
| 目標期日は必須? | 任意。期日を設定しない目標(継続的習慣等)も許容する |
| 過去日を目標期日に設定できる? | 可能(バリデーションで未来日制約は付けない)。受講生が過去の目標を後から記録したいケースを許容する |
| 1 Enrollment あたりの目標数上限は? | 上限なし。受講生が自由に立てる |
| 受講生以外(コーチ / 管理者)が目標を追加できる? | できない。介入動線なし。コーチ / 管理者は閲覧専用 |
| 達成マークしたらコーチに通知が飛ぶ? | 飛ばない。MVP では受講生の自己マークで完結、通知連携なし |
| 達成済目標を再編集できる? | 可能。タイトル / 詳細 / 目標期日 は達成後も更新可。達成日時(`achieved_at`)は専用エンドポイントでのみ操作 |
| 達成解除すると達成日時はどうなる? | NULL に戻る。「いつ達成したか」の履歴は保持しない(MVP 外、別チケット要件) |
| 目標を削除すると履歴は残る? | 残らない。物理削除のみ。誤削除リスクは UI の確認ダイアログで対応 |
| 親 Enrollment が削除されたら目標はどうなる? | 連動して物理削除される(FK ON DELETE CASCADE)。SoftDelete された Enrollment の目標は一覧から除外される |
| 受講生が修了済 / 退会済の場合は目標 CRUD できる? | 親 Enrollment へのアクセス時点でブロックされる(プラン機能ロック Middleware)。本機能側では追加判定不要 |
| 達成マーク URL を直接叩いて他受講生の目標を達成にできる? | できない。Policy で受講生本人判定があり、他受講生 / 担当外コーチ / 管理者 は 403 |
| 編集はインライン編集 / モーダル / 専用ページ どれ? | 専用ページ遷移(Basic 段階は JS なし)。BookShelf / ContactForm の Basic パターン踏襲 |
| 削除確認ダイアログは? | HTML 標準 `onsubmit="return confirm('本当に削除しますか?')"` で十分(JS ファイル不要)。Basic 範囲で完結 |
| コーチ / 管理者の目標一覧表示でも CRUD ボタンが見える? | 見えない。`@can('update', $goal)` 等で出し分けし、認可がない場合はそもそもボタンを描画しない(認可拒否 URL を直叩きしても 403) |
| 編集フォームに「達成 / 未達成」チェックボックスを付けてよい? | 付けない。達成マーク / 解除は専用エンドポイントでのみ操作(UI の責務分離、編集 = タイトル等の内容更新 / 達成マーク = ステータス操作 の意味的区別) |
| フラッシュ文言の推奨は? | 追加「目標を追加しました。」/ 編集「目標を更新しました。」/ 削除「目標を削除しました。」/ 達成「目標を達成済にしました。」/ 達成解除「目標の達成マークを取り消しました。」(適切な日本語であれば文言の細部は採点対象外) |
| 一覧の並び順は? | 「未達成優先 + 目標期日昇順 + 作成日時降順」 が UX 上望ましい。「作成日時降順」だけでも振る舞い OK。受講生が自分で見て分かれば良い |
