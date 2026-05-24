# S-B-02 面談パックマスタ管理(admin マスタ CRUD)

## メタ情報

| 項目 | 値 |
|---|---|
| チケット ID | `S-B-02` |
| Feature 連番 | `meeting-quota-01` |
| Feature | meeting-quota |
| 種別 | Story |
| サブカテゴリ | 既存機能の拡張 |
| 難易度 | Basic |
| 工数 (h) | 6 |
| 依存チケット | (なし) |

## 概要

管理者(admin)が追加面談購入用の SKU マスタ(面談パック)を CRUD + 状態遷移(下書き / 公開中 / アーカイブ)できる管理画面を実装する。提供 PJ にはデータ構造 + 初期データ + admin 管理画面の表示部分が既に含まれており、受講生は管理画面のロジック側(認可・バリデーション・ルーティング)を組んで CRUD UI として機能させる。

## 背景・目的

- **現状の問題**: 提供 PJ には面談パックのデータ構造と admin 管理画面の表示部分、初期データが既にあるが、認可・ルーティング・バリデーションのロジック側が欠落しており画面が動作しない(404 になる)。運営側は面談パックの新設・価格改定・終売を直接操作できず、受講生の追加面談購入動線(S-A-04 で別途実装)の前提が立ち上がらない。
- **達成したい状態**: 管理者が面談パック管理画面から面談パックを CRUD でき、下書き → 公開中 → アーカイブの状態遷移で受講生の購入画面に並ぶ SKU を運営側でコントロールできる。公開中の面談パックは削除不可で、過去の購入履歴の整合性を守る。
- **価値・優先度**: 追加面談購入(S-A-04 Stripe 連携)の前提となるマスタ管理画面。本チケットが揃わないと、後続の購入導線の検証ができない。

## ユーザーストーリー

- **管理者(admin)として**、追加面談購入用の面談パックを CRUD したい。なぜなら、運営側で価格 / 回数 / 公開状態を自由にコントロールしたいから。
- **管理者として**、面談パックを下書きから公開へ、または受講生の購入画面から外すためにアーカイブへ遷移させたい。なぜなら、SKU のライフサイクル(新設→販売→終売)を自前でハンドリングしたいから。
- **管理者として**、公開中の面談パックが誤って削除されない仕組みを期待する。なぜなら、購入履歴を持つ SKU を消すと履歴が孤立して監査ができなくなるから。
- **受講生(student) / コーチ(coach)として**、admin 専用の面談パック管理画面に直接アクセスしても 403 が返ることを期待する。なぜなら、運営側のマスタ管理画面に他ロールが入れる構成は事故の元だから。

## やること

### 面談パック

- **一覧**: 管理者のみ可、受講生 / コーチは 403。キーワード検索 + 状態フィルタ + ページネーション付きで表示
- **詳細**: 管理者のみ可、他は 403。面談パックの基本情報 + 直近の購入履歴 + メタ情報を表示
- **新規作成**: 管理者のみ可、他は 403。投稿成功で詳細画面にリダイレクト、初期状態は **下書き** で作成される
- **編集**: 管理者のみ可、他は 403。基本情報を更新できるが、状態は本フォームでは変えられない(状態遷移は別操作)
- **削除**: 管理者のみ可、他は 403。下書き / アーカイブ状態のみ削除可(論理削除)、公開中状態は 409 を返して購入履歴の孤立を防ぐ

### 状態遷移

- **公開する**(下書き → 公開中): 管理者のみ可、他は 403。下書き状態以外で 409
- **アーカイブ**(公開中 → アーカイブ): 管理者のみ可、他は 403。公開中状態以外で 409。アーカイブ後は受講生の購入画面から外れるが、過去の購入履歴は残る
- **下書きへ戻す**(アーカイブ → 下書き): 管理者のみ可、他は 403。アーカイブ状態以外で 409。誤アーカイブの取り戻し / 再販売準備の動線

### 共通の振る舞い

- 受講生 / コーチが管理者専用画面にアクセスすると 403
- 公開中 → 下書きへの **直接戻し** はできない(一度アーカイブを経由する)
- 並び順は公開中の SKU を優先表示

## やらないこと

- 受講生の追加面談購入動線(購入チェックアウト画面 / Stripe Checkout 連携 / Webhook 受信) — `S-A-04` で扱う
- 面談回数履歴(消費 / 返却 / 購入 / 管理者付与 / 初期付与)の表示・記録
- 管理者の面談回数手動付与モーダル(ユーザー詳細画面からの付与)
- 残数集計の実装(残り面談回数の集計)
- ステータス変更履歴の表示
- 並び順をドラッグ&ドロップで操作する UI(並び順は数値入力で十分)

## 受け入れ条件

- [ ] **一覧 - 認可**: 管理者が面談パック管理画面の一覧を開くと一覧画面が表示され、受講生 / コーチが開くと 403
- [ ] **一覧 - 検索 / フィルタ**: SKU 名キーワード(部分一致)と状態(下書き / 公開中 / アーカイブ)で絞り込みでき、ページネーションにフィルタ状態が引き継がれる
- [ ] **新規作成 - リダイレクト + フラッシュ**: 管理者が新規作成成功時、作成された面談パック詳細画面にリダイレクトされ、フラッシュメッセージが表示される
- [ ] **新規作成 - 初期状態**: 新規作成された面談パックの状態が **下書き** で作成される
- [ ] **新規作成 - 認可拒否**: 受講生 / コーチが新規作成フォーム / 新規作成アクションにアクセスすると 403
- [ ] **編集 - リダイレクト + フラッシュ**: 編集成功時、面談パック詳細画面にリダイレクトされ、フラッシュメッセージが表示される
- [ ] **編集 - 状態保持**: 編集フォームで状態(下書き / 公開中 / アーカイブ)は変更されない(状態遷移は別操作でのみ変更)
- [ ] **削除 - 下書き / アーカイブ可**: 管理者が下書き / アーカイブ状態の面談パックを削除すると SoftDelete され、一覧画面にリダイレクト + フラッシュ表示
- [ ] **削除 - 公開中ガード**: 公開中状態の面談パックを削除しようとすると 409 + フラッシュエラーが表示される
- [ ] **状態遷移 - 公開**: 下書き状態の面談パックに対して公開を実行すると公開中に遷移し、詳細画面にリダイレクト + フラッシュ表示。下書き以外で実行すると 409
- [ ] **状態遷移 - アーカイブ**: 公開中状態の面談パックに対してアーカイブを実行するとアーカイブに遷移し、詳細画面にリダイレクト + フラッシュ表示。公開中以外で実行すると 409
- [ ] **状態遷移 - 下書きへ戻す**: アーカイブ状態の面談パックに対して下書きへ戻すを実行すると下書きに遷移し、詳細画面にリダイレクト + フラッシュ表示。アーカイブ以外で実行すると 409
- [ ] **状態遷移 - 認可拒否**: 受講生 / コーチが状態遷移操作にアクセスすると 403

## 実装方針

> **参考設計の一例**。受け入れ条件を満たせれば実装手段は問わない。受講生は提供 PJ コード + ヒアリングで自分の設計を組み立てる。

### 主要 URL

| メソッド | パス | 振る舞い |
|---|---|---|
| GET | `/admin/meeting-packs` | 一覧(キーワード検索 + 状態フィルタ + ページネーション、フィルタ引き継ぎ) |
| GET | `/admin/meeting-packs/create` | 新規作成フォーム表示 |
| POST | `/admin/meeting-packs` | 新規作成、成功時 `/admin/meeting-packs/{plan}` リダイレクト + フラッシュ「面談パックを作成しました。」、`status=draft` で INSERT |
| GET | `/admin/meeting-packs/{plan}` | 詳細(基本情報 + 直近購入履歴 + メタ情報 + 状態遷移ボタン) |
| GET | `/admin/meeting-packs/{plan}/edit` | 編集フォーム表示 |
| PATCH | `/admin/meeting-packs/{plan}` | 更新、成功時 `/admin/meeting-packs/{plan}` リダイレクト + フラッシュ「面談パックを更新しました。」、`status` は変更しない |
| DELETE | `/admin/meeting-packs/{plan}` | 削除(下書き / アーカイブのみ SoftDelete、成功時 `/admin/meeting-packs` リダイレクト + フラッシュ「面談パックを削除しました。」)。公開中なら 409 + フラッシュエラー「公開中の面談パックは削除できません。先に下書きに戻すか、アーカイブしてください。」 |
| POST | `/admin/meeting-packs/{plan}/publish` | 公開(下書き → 公開中)、成功時 詳細画面リダイレクト + フラッシュ「面談パックを公開しました。」。下書き以外なら 409 |
| POST | `/admin/meeting-packs/{plan}/archive` | アーカイブ(公開中 → アーカイブ)、成功時 詳細画面リダイレクト + フラッシュ「面談パックをアーカイブしました。」。公開中以外なら 409 |
| POST | `/admin/meeting-packs/{plan}/unarchive` | 下書きへ戻す(アーカイブ → 下書き)、成功時 詳細画面リダイレクト + フラッシュ「面談パックを下書きへ戻しました。」。アーカイブ以外なら 409 |

### データモデル

> **既存テーブル**(提供 PJ に同梱、変更不要)。受講生は新規にマイグレーション / Model を作成しない。下記カラム構成と Enum を理解した上で Controller / FormRequest を組む。

`meeting_packs`(ULID 主キー + SoftDelete):

| カラム | 型 | NOT NULL | FK / UNIQUE | 補足 |
|---|---|:---:|---|---|
| id | ulid | ✓ | PK | `$table->ulid('id')->primary()` |
| name | varchar(100) | ✓ | | SKU 名 |
| description | text | | | 説明文 |
| meeting_count | unsigned smallint | ✓ | | 面談回数(1〜100) |
| price | unsigned int | ✓ | | 価格(円、0〜1,000,000) |
| stripe_price_id | varchar(255) | | | 事前作成済み Stripe Price ID(任意、本チケット範囲ではフォーム上の任意入力扱い) |
| status | varchar(20) | ✓ | | `MeetingPackStatus` Enum cast(`draft` / `published` / `archived`)、デフォルト `draft` |
| sort_order | unsigned int | ✓ | | 並び順、デフォルト 0 |
| created_by_user_id | ulid | ✓ | users.id, ON DELETE RESTRICT | 作成者 |
| updated_by_user_id | ulid | ✓ | users.id, ON DELETE RESTRICT | 最終更新者 |
| created_at | timestamp | | | `$table->timestamps()` |
| updated_at | timestamp | | | `$table->timestamps()` |
| deleted_at | timestamp | | | `$table->softDeletes()` |

- **インデックス**: `(status, sort_order)` 複合 / `deleted_at`(提供 PJ で既設)
- **Enum / Cast**: `MeetingPackStatus`(Draft / Published / Archived)→ `meeting_packs.status` に cast、`label()` 戻り値「下書き」「公開中」「アーカイブ」
- **リレーション**: User belongsTo (createdBy / updatedBy) / Payment hasMany(`payments` は S-A-04 で作る別テーブル、詳細画面の購入履歴セクションで表示するが本チケットでは空配列フォールバックで OK)
- **Scope**(受講生判断、推奨): `scopePublished` / `scopeOrdered`(`sort_order` ASC → `created_at` DESC)

### バリデーション

`StoreMeetingPackRequest` / `UpdateMeetingPackRequest`:

| 入力項目 | ルール | 推奨エラーメッセージ例 |
|---|---|---|
| name | required / string / max:100 | SKU 名は必須です。<br>SKU 名は 100 文字以内で入力してください。 |
| description | nullable / string / max:2000 | 説明は 2000 文字以内で入力してください。 |
| meeting_count | required / integer / min:1 / max:100 | 面談回数は必須です。<br>面談回数は 1〜100 の範囲で入力してください。 |
| price | required / integer / min:0 / max:1000000 | 価格は必須です。<br>価格は 0〜1,000,000 円の範囲で入力してください。 |
| stripe_price_id | nullable / string / max:255 | Stripe Price ID は 255 文字以内で入力してください。 |
| sort_order | nullable / integer / min:0 | 並び順は 0 以上の整数で入力してください。 |

`IndexMeetingPackRequest`: `keyword` / `status`(Enum、`Rule::enum(MeetingPackStatus::class)`)を任意フィルタとして受け取る。

### 認可設計

**Policy**: `MeetingPackPolicy`

| メソッド | ロール × 判定 |
|---|---|
| viewAny / view / create / update / delete | 管理者(admin): ✅ / コーチ: ❌ / 受講生: ❌ |
| publish / archive / unarchive | 管理者: ✅ / コーチ: ❌ / 受講生: ❌ |

- 削除可否(`delete`)は **admin の真偽判定** のみ Policy 側で行う。「公開中は削除不可」という **状態ベースのガード** は Controller / Action 内で `MeetingPackNotDeletableException`(409) として実装する(認可と整合性チェックの責務分離)
- 状態遷移(publish / archive / unarchive)の遷移元検証も同様に Controller / Action 内で `MeetingPackInvalidTransitionException`(409) を throw

### テスト観点

| 種別 | 観点 |
|---|---|
| Unit | `MeetingPackStatus` Enum(cast / `label()` 日本語) / Model リレーション(createdBy / updatedBy) |
| Feature | 各エンドポイントの認可分岐(管理者 / コーチ / 受講生) / 副作用(DB INSERT・UPDATE・SoftDelete・状態遷移) / フラッシュ表示有無 / リダイレクト先パス / 公開中削除時の 409 + 元画面に戻る + フラッシュエラー / 状態遷移違反時の 409 / 検索・フィルタのページネーション引き継ぎ / 新規作成時の `status=draft` 初期化 |
| Policy | 各メソッド × 各ロールの真偽判定(admin true / coach false / student false 網羅) |

### アーキテクチャ判断

> **Basic 範囲制約**: Service / Action(UseCases) は教材範囲外。本チケットは **Controller 内完結を前提** に詳細を記述する。Action / Service への分離は受講生判断(チャレンジするなら歓迎、振る舞いが受け入れ条件を満たせば OK)。

- **採用技術**: Eloquent + Controller(受講生判断で Action 分割可) + Policy + FormRequest + Blade(提供済み) + `DB::transaction`
- **設計判断**:
  1. **Controller 分離**: 一覧 / 詳細 / 新規 / 編集 / 削除 を `MeetingPackController`、状態遷移(`publish` / `archive` / `unarchive`)を `MeetingPackStatusController` に分けると責務が明確(Single Responsibility)。1 Controller に集約しても可
  2. **状態遷移の検証**: 各遷移メソッド冒頭で「現在状態が遷移元と一致するか」を検証して `MeetingPackInvalidTransitionException`(409、static factory `forPublish()` / `forArchive()` / `forUnarchive()` でメッセージを所有)を throw。メッセージ文字列を Controller から渡さない(例外クラス側が責務を所有)
  3. **削除制約**: `destroy` メソッド内で公開中なら `MeetingPackNotDeletableException`(409)を throw、下書き / アーカイブのみ SoftDelete
  4. **作成 / 更新者の記録**: 新規作成時に `created_by_user_id` / `updated_by_user_id` を `auth()->user()->id` で記録(`fillable` で許可済み)。更新・状態遷移時にも `updated_by_user_id` を更新
  5. **ルート**: `Route::resource('meeting-packs', MeetingPackController::class)` で CRUD 7 ルート + 状態遷移 3 ルートを `admin` プレフィックス + `auth + role:admin` Middleware 配下に配置
  6. **一覧の並び順**: 「公開中優先 → `sort_order` ASC → `created_at` DESC」推奨。`orderByRaw("CASE status WHEN 'published' THEN 1 WHEN 'draft' THEN 2 WHEN 'archived' THEN 3 END")` で実現可(あるいは `sort_order` ASC のみでも振る舞い OK)

### 関連ファイルメモ

- `app/Models/MeetingPack.php` / `app/Enums/MeetingPackStatus.php`
- `app/Http/Controllers/MeetingPackController.php` / `app/Http/Controllers/MeetingPackStatusController.php`
- `app/UseCases/MeetingPack/{Index,Show,Store,Update,Destroy,Publish,Archive,Unarchive}Action.php`(※ 模範解答 PJ で採用、Basic 受講生は Controller 内完結も可)
- `app/Policies/MeetingPackPolicy.php`
- `app/Http/Requests/MeetingPack/{Store,Update,Index}Request.php`
- `app/Exceptions/MeetingQuota/{MeetingPackNotDeletable,MeetingPackInvalidTransition}Exception.php`
- `resources/views/meeting-pack/management/{index,show,create,edit}.blade.php`(提供 PJ 既存、ロック対象)
- `database/migrations/*_create_meeting_packs_table.php`(提供 PJ 既存)
- `database/seeders/MeetingPackSeeder.php`(提供 PJ 既存、状態網羅 published × 3 / draft × 1 / archived × 1)
- `routes/web.php` の admin プレフィックス内に `Route::resource('meeting-packs', ...)` + status route 3 本を追加
- 類似パターン参考: `PlanController` / `PlanStatusController`(`plan-management` Feature の admin マスタ CRUD + 状態遷移、本チケットとほぼ同型)

## 補足

### 想定ヒアリング Q&A

| 質問 | 回答 |
|---|---|
| 面談回数 / 価格の上限・下限は? | 面談回数は 1〜100、価格は 0〜1,000,000(円) |
| 説明 / Stripe 価格 ID / 並び順は必須? | いずれも任意。並び順は省略時 0 |
| 認可は誰がアクセス可能? | 管理者(admin)のみ。受講生 / コーチは面談パック管理画面の全操作で 403 |
| 認可拒否時の HTTP ステータスは 403 / 404? | 403(受講生 / コーチが直接画面を開いても 403 を返す。404 では返さない) |
| 公開中の SKU を削除しようとしたら? | 409 を返す。下書きに戻すかアーカイブしてから削除可 |
| 公開中 → 下書きの直接戻しはできる? | 不可。一度アーカイブを経由し、アーカイブから下書きへ戻す |
| 削除は SoftDelete / 物理削除? | SoftDelete(論理削除)。過去の購入履歴とのデータ参照関係を保つ |
| 公開中の SKU の編集はできる? | 可。基本情報(SKU 名 / 説明 / 面談回数 / 価格 / Stripe 価格 ID / 並び順)は公開後も変更可。受講生の購入画面に即時反映される(キャッシュなし) |
| 一覧の並び順は? | 「公開中優先 → 並び順 昇順 → 作成日時 降順」推奨。「並び順 昇順 → 作成日時 降順」だけでも振る舞い OK |
| 検索のヒット範囲は? | SKU 名 の部分一致のみ(説明 は対象外) |
| ページネーションは何件 / ページ? | 20 件推奨(リスト規模が小さいので 50 でも 100 でも受講生判断で OK) |
| 作成者 ID / 最終更新者 ID は誰の ID? | 作成時は作成した管理者の ID、その後の更新時(編集・状態遷移含む)は更新した管理者の ID |
| 詳細画面の購入履歴は本チケットで実装する? | しない。詳細画面の表示部分は提供 PJ 既存で、購入履歴データが空 / 未作成でも空配列フォールバックで画面破綻なし(S-A-04 で実体が入った時点で表示が活きる) |
| 並び順を編集する UI は必要? | 不要。並び順は数値入力で十分。ドラッグ&ドロップ並び替えは本チケットでは扱わない |
| フラッシュ文言の推奨は? | 作成「面談パックを作成しました。」/ 更新「面談パックを更新しました。」/ 削除「面談パックを削除しました。」/ 公開「面談パックを公開しました。」/ アーカイブ「面談パックをアーカイブしました。」/ 下書きへ戻す「面談パックを下書きへ戻しました。」/ 公開中削除エラー「公開中の面談パックは削除できません。先に下書きに戻すか、アーカイブしてください。」(適切な日本語であれば文言の細部は採点対象外) |
