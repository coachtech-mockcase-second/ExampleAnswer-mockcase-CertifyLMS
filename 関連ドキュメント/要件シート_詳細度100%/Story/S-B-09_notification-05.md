# S-B-09 admin お知らせ配信機能

## メタ情報

| 項目 | 値 |
|---|---|
| チケット ID | `S-B-09` |
| Feature 連番 | `notification-05` |
| Feature | notification(admin お知らせ拡張) |
| 種別 | Story |
| サブカテゴリ | 新規機能の構築 |
| 難易度 | Basic |
| 工数 (h) | 8 |
| 依存チケット | `S-B-04` |

## 概要

管理者(admin)が能動的に受講生集合へ一斉配信できるお知らせ機能を新規実装する。配信対象は「全受講中の受講生」「指定資格に登録した受講生」「指定 1 ユーザー」の 3 種類から選択でき、`S-B-04` で構築した通知基盤(`notifications` テーブル + メール送信)を利用して各受講生にデータベース通知 + メールを発火する。配信は不可逆(再配信 / 編集 / 取消なし)で、配信履歴は admin 専用画面で時系列降順に閲覧可能。

## 背景・目的

- **現状の問題**: `S-B-04` で構築した通知基盤は業務イベント(chat 受信 / Q&A 回答 / 面談予約 / 面談キャンセル)に紐づく **受動的な通知発火** のみで、運営側が能動的に受講生集合へメッセージを届ける手段がない。メンテナンス予告 / 重要更新 / 学習キャンペーン告知 などの運営連絡を chat 個別送信や外部メールツールで代替する状況。
- **達成したい状態**: 管理者がお知らせ作成フォームから「全受講生」「指定資格の受講生」「指定 1 ユーザー」の 3 種類の配信対象を選択し、タイトル + 本文を入力して一斉配信できる。受講生は通常の通知一覧(`/notifications`)で運営お知らせを業務通知と並列に受信でき、メールでも届く。配信履歴は admin が時系列に閲覧でき、配信件数 / 配信時刻 を確認できる。
- **価値・優先度**: 運営からの能動連絡を LMS 内に集約する **運営コミュニケーション基盤**。本機能が揃うと外部メールツールへの依存が減り、お知らせの履歴管理も LMS 内で完結する。

## ユーザーストーリー

- **管理者(admin)として**、全受講中の受講生に対して一斉お知らせを配信したい。なぜなら、メンテナンス予告 / 重要更新 / 学習キャンペーン告知 を運営から直接届けたいから。
- **管理者として**、指定資格に登録した受講生だけに絞ってお知らせ配信したい。なぜなら、資格固有の試験変更 / 教材更新 を該当受講生だけに届けたいから。
- **管理者として**、指定 1 ユーザーだけにお知らせ配信したい。なぜなら、個別のフォロー連絡を通知として残したいから(chat より明示的な「運営連絡」の格付けで残せる)。
- **管理者として**、配信履歴を時系列で閲覧し、各お知らせの配信件数 / 配信時刻を確認したい。なぜなら、配信実績を後から監査できる状態にしたいから。
- **管理者として**、誤配信防止のため再配信 / 編集 / 取消ができない設計を期待する。なぜなら、メール配信は取り消せない不可逆性を持つため、UI 上も同じ不可逆性で運用責任を明確化したいから。
- **受講生(student)として**、運営からのお知らせを通常の通知一覧で業務通知と並列に受信したい。なぜなら、運営連絡を見落としたくないから。

## やること

### お知らせ配信(管理者のみ操作可)

- **配信作成フォーム**: 管理者のみアクセス可、他は 403。タイトル / 本文 / 配信対象タイプ(全受講生 / 資格指定 / ユーザー指定)を入力、配信対象タイプに応じて資格選択 / ユーザー選択フィールドを動的表示
- **配信実行**: 管理者のみ可、他は 403。配信対象集合を解決し、各受講生にデータベース通知 + メール送信 を実行。配信件数(`dispatched_count`)と配信時刻(`dispatched_at`)を記録
- **配信対象の整合性チェック**: 配信対象タイプと指定 FK(資格 ID / ユーザー ID)の組み合わせが不整合な場合 422、指定された資格 / ユーザーが存在しない場合 404
- **受講生 status フィルタ**: 配信対象集合は **`User.status === InProgress`(受講中)** のみに限定、退会済 / 修了済 / 招待中 のユーザーには発火しない
- **配信履歴閲覧**: 管理者のみ可、他は 403。配信履歴一覧(時系列降順、20 件 / ページ、配信件数 / 配信時刻 / 配信対象 表示)+ 配信詳細閲覧

### 配信対象の 3 種類

- **全受講生(`all_students`)**: 受講中(`status === InProgress`)の全受講生に配信、資格 / ユーザー指定なし
- **資格指定(`certification`)**: 指定資格に登録(`enrollments.status === learning`)している受講中の受講生に配信、`target_certification_id` を指定
- **ユーザー指定(`user`)**: 指定 1 受講生のみに配信、`target_user_id` を指定(指定ユーザーが受講生 role でなければ拒否)

### 配信の不可逆性

- **再配信不可**: 同じお知らせを再度配信する動線は持たない(誤って 2 回呼ばないように)
- **編集不可**: 配信後のお知らせ本文 / タイトル / 対象 は変更不可
- **取消不可**: 配信後のお知らせを削除する動線は持たない(メール配信は取り消せないため UI 上も整合)

### 共通の振る舞い

- 配信されたお知らせは `S-B-04` の通知基盤を使って各受講生の `notifications` テーブルに行が INSERT され、通常の通知一覧画面 / TopBar 未読バッジ / メール送信 すべてに乗る
- 通知行クリック時の遷移先は `/notifications` フルページ(お知らせ詳細単独画面ではなく、通知一覧でデータを確認する設計)
- メール件名は「【Certify LMS】<お知らせタイトル>」プレフィックス統一

## やらないこと

- **再配信 / 編集 / 取消** — 配信の不可逆性を仕様として確定
- **コーチ向けの配信** — 配信対象は受講生のみ(運営 → 学習者の連絡経路として位置付け)、コーチ向けは別 Feature(`S-B-04` の業務通知)で間接的に発火
- **配信予約 / スケジュール送信** — MVP 外、配信は即時のみ
- **配信ターゲット内の絞り込み(複数資格 AND / 学習進捗フィルタ等)** — 単純な 3 種類(全 / 資格 / ユーザー)のみ
- **配信ターゲットのプレビュー(配信前に対象件数を表示)** — MVP 外、配信実行時の `dispatched_count` で事後確認
- **お知らせの優先度フラグ / 既読率追跡 / 開封確認** — MVP 外
- **添付ファイル / リッチテキスト本文** — プレーンテキストのみ(本文 5000 文字以内)
- **お知らせ画面の受講生側専用詳細ページ** — 受講生は通常の通知一覧でデータを確認、お知らせ単独画面は持たない
- **配信失敗のリトライ** — 同期送信のためエラー時は管理者が再操作

## Seeder 設計

> `migrate:fresh --seed` 直後に動作確認できるよう、`target_type` 網羅 + 配信時系列のバリエーションを投入する。

**前提**(他 Seeder で投入される想定): 管理者 / 受講生 数名(`InProgress` / `Graduated` 混在)/ 公開資格 数件 / 受講登録 各種

`AnnouncementSeeder`(配信履歴 + 各受講生の通知行を生成):

| レコード分類 | 内容 | 動作確認用途 |
|---|---|---|
| `target_type=all_students` × 1 件 | 「春の学習キャンペーン開始のお知らせ」 / 2 週間前配信 | 全受講生配信の履歴確認 / 受講生通知一覧での受信確認 |
| `target_type=certification` × 1 件 | 「[資格 X] 試験範囲変更のお知らせ」 / 1 週間前配信 | 資格指定配信の履歴確認 / 該当資格受講生のみ受信 |
| `target_type=user` × 1 件 | 「[受講生 A] 個別フォロー」 / 3 日前配信 | ユーザー指定配信の履歴確認 / 該当受講生のみ受信 |
| 配信時の通知行(`notifications` テーブル)| 各お知らせに紐づく受講生通知行を `NotificationSeeder` 経由で INSERT(`type=AnnouncementNotification` / `data.admin_announcement_id` で関連付け) | 受講生通知一覧での運営お知らせ表示確認 |

- **DatabaseSeeder への追加順序**: `UserSeeder` → `CertificationSeeder` → `EnrollmentSeeder` → `AnnouncementSeeder` → `NotificationSeeder`(本 Seeder の後)
- **配信履歴のみ投入**: 本 Seeder では `announcements` テーブルにレコードを INSERT するのみ、通知発火(`Notification::send`)は走らせず、関連通知行は `NotificationSeeder` 側で `type=AnnouncementNotification` として直接 INSERT する(Mail 副作用を発生させない実装)

## 受け入れ条件

- [ ] **配信履歴 - 認可**: 管理者が配信履歴画面を開くと履歴一覧が表示され、受講生 / コーチが開くと 403
- [ ] **配信履歴 - 時系列降順**: 配信履歴が `created_at DESC` で並ぶ
- [ ] **配信履歴 - ページネーション**: 配信履歴が 20 件を超えるとページネーションが表示される
- [ ] **配信履歴 - 配信件数表示**: 各お知らせ行に配信件数(`dispatched_count`)と配信時刻(`dispatched_at`)が表示される
- [ ] **配信作成フォーム - 認可**: 管理者が作成フォームを開くと配信作成画面が表示され、受講生 / コーチが開くと 403
- [ ] **配信作成 - 全受講生対象成功**: 管理者が `target_type=all_students` で配信実行すると、配信成功 + 詳細画面リダイレクト + フラッシュ表示、受講中の全受講生に通知 + メールが発火する
- [ ] **配信作成 - 資格指定対象成功**: 管理者が `target_type=certification` + `target_certification_id` で配信実行すると、指定資格に学習中で登録している受講中の受講生にのみ通知 + メールが発火する
- [ ] **配信作成 - ユーザー指定対象成功**: 管理者が `target_type=user` + `target_user_id` で配信実行すると、指定 1 受講生のみに通知 + メールが発火する
- [ ] **配信作成 - 受講中 status フィルタ**: 受講中(`InProgress`)でない受講生(退会済 / 修了済 / 招待中)には配信されない、`dispatched_count` にも含まれない
- [ ] **配信作成 - 認可拒否**: 受講生 / コーチが配信実行アクションにアクセスすると 403
- [ ] **配信作成 - タイトルバリデーション**: タイトルが空 / 201 文字以上のとき 422 + エラーメッセージが表示される
- [ ] **配信作成 - 本文バリデーション**: 本文が空 / 5001 文字以上のとき 422 + エラーメッセージが表示される
- [ ] **配信作成 - 配信対象タイプ不整合**: `target_type=all_students` で `target_certification_id` / `target_user_id` を含めると 422、`target_type=certification` で `target_certification_id` が空 / `target_user_id` を指定すると 422、`target_type=user` で `target_user_id` が空 / `target_certification_id` を指定すると 422
- [ ] **配信作成 - 配信対象不在**: `target_type=certification` で存在しない / 公開停止資格を指定すると 422 / 404(受講生 PR 判断、振る舞いベース)、`target_type=user` で存在しない / 受講生 role でないユーザーを指定すると 422 / 404
- [ ] **配信作成 - dispatched_count / dispatched_at 確定**: 配信実行成功時、`dispatched_count` に実際の配信件数、`dispatched_at` に現在時刻が記録される
- [ ] **配信詳細 - 認可**: 管理者が配信詳細画面を開くと詳細表示が可能、受講生 / コーチが開くと 403
- [ ] **配信詳細 - 配信内容表示**: タイトル / 本文 / 配信対象タイプ / 配信件数 / 配信時刻 / 配信した管理者の名前 が表示される
- [ ] **不可逆性 - 編集動線なし**: 配信済お知らせの編集 / 削除 / 再配信ボタンが画面上に存在しない
- [ ] **受講生側 - 通知発火**: 配信されたお知らせが受講生の通知一覧画面(`S-B-04` 由来)に表示される
- [ ] **メール送信 - 件名**: メール件名が「【Certify LMS】」プレフィックスで始まる

## 実装方針

> **参考設計の一例**。受け入れ条件を満たせれば実装手段は問わない。受講生は提供 PJ コード + ヒアリングで自分の設計を組み立てる。

### 主要 URL

| メソッド | パス | 振る舞い |
|---|---|---|
| GET | `/admin/announcements` | 配信履歴一覧(時系列降順、20 件 / ページ、配信件数 / 配信時刻 / 配信対象 表示) |
| GET | `/admin/announcements/create` | 配信作成フォーム表示(タイトル / 本文 / 配信対象タイプ / 資格選択 / ユーザー選択) |
| POST | `/admin/announcements` | 配信実行、成功時 `/admin/announcements/{announcement}` リダイレクト + フラッシュ「お知らせを配信しました (N 件)。」、対象受講生に通知 + メール発火 |
| GET | `/admin/announcements/{announcement}` | 配信詳細(タイトル / 本文 / 配信対象 / 配信件数 / 配信時刻 / 配信者) |

> route 名は `admin.announcements.index` / `create` / `store` / `show`(`Route::resource('announcements', AnnouncementController::class)->only(['index','create','store','show'])` で生成、`admin` プレフィックス + `auth + role:admin` Middleware 配下)。

### データモデル

**新規テーブル**: `announcements`(ULID 主キー、SoftDelete 不採用 = 物理削除なし)

| カラム | 型 | NOT NULL | FK / UNIQUE | 補足 |
|---|---|:---:|---|---|
| id | ulid | ✓ | PK | `$table->ulid('id')->primary()` |
| created_by_user_id | ulid | ✓ | users.id, ON DELETE RESTRICT | 配信した管理者の ID |
| title | varchar(200) | ✓ | | お知らせタイトル |
| body | text | ✓ | | お知らせ本文(最大 5000 文字) |
| target_type | varchar(20) | ✓ | | `AnnouncementTargetType` Enum cast(`all_students` / `certification` / `user`) |
| target_certification_id | ulid | | certifications.id, ON DELETE SET NULL | `target_type=certification` 時のみ NOT NULL |
| target_user_id | ulid | | users.id, ON DELETE SET NULL | `target_type=user` 時のみ NOT NULL |
| dispatched_count | unsigned int | ✓ | | 配信件数(配信実行時に確定)、デフォルト 0 |
| dispatched_at | timestamp | | | 配信時刻(配信実行時に確定)、NULL 不可だが初期値 NULL から INSERT 直後に UPDATE |
| created_at | timestamp | | | `$table->timestamps()` |
| updated_at | timestamp | | | `$table->timestamps()` |

- **インデックス**: `(target_type, dispatched_at)` 複合(対象別の配信履歴検索)/ `created_by_user_id`(配信者別履歴)
- **Enum / Cast**: `AnnouncementTargetType`(AllStudents / Certification / User)→ `target_type` に cast、`label()` 戻り値「全受講生」「資格指定」「ユーザー指定」
- **リレーション**: User belongsTo (createdBy、`withTrashed()` で削除済 admin も解決) / Certification belongsTo (targetCertification) / User belongsTo (targetUser、`withTrashed()` で削除済 user も解決)

### バリデーション

`StoreRequest`:

| 入力項目 | ルール | 推奨エラーメッセージ例 |
|---|---|---|
| title | required / string / max:200 | タイトルは必須です。<br>タイトルは 200 文字以内で入力してください。 |
| body | required / string / max:5000 | 本文は必須です。<br>本文は 5000 文字以内で入力してください。 |
| target_type | required / Rule::enum(AnnouncementTargetType::class) | 配信対象は必須です。<br>配信対象が不正です。 |
| target_certification_id | nullable / ulid / required_if:target_type,certification / prohibited_unless:target_type,certification / exists:certifications,id | 資格指定の場合、対象資格を選択してください。<br>全受講生 / ユーザー指定の場合、対象資格を指定しないでください。<br>選択された資格は存在しません。 |
| target_user_id | nullable / ulid / required_if:target_type,user / prohibited_unless:target_type,user / exists:users,id | ユーザー指定の場合、対象ユーザーを選択してください。<br>全受講生 / 資格指定の場合、対象ユーザーを指定しないでください。<br>選択されたユーザーは存在しません。 |

### 認可設計

**Policy**: `AnnouncementPolicy`

| メソッド | ロール × 判定 |
|---|---|
| viewAny / view / create | 管理者: ✅ / コーチ: ❌ / 受講生: ❌ |

- 配信は管理者のみ、配信履歴 / 配信詳細閲覧も管理者のみ
- 受講生は通知一覧画面側(`S-B-04`)で受信したお知らせを閲覧する(本 Feature の `/admin/announcements/*` には到達不要)

### テスト観点

| 種別 | 観点 |
|---|---|
| Unit | `AnnouncementTargetType` Enum(cast / `label()` 日本語)/ `Announcement` Model のリレーション(createdBy / targetCertification / targetUser)/ `AnnouncementNotification` の `toDatabase()` / `toMail()` のキー網羅(`notification_type` / `title` / `message` / `admin_announcement_id` / `link_route` 等) |
| Feature | 配信履歴 / 配信作成 / 配信実行 / 配信詳細 の認可分岐(管理者 / コーチ / 受講生)/ バリデーション失敗時の 422 + エラーメッセージ / `target_type` 別の受講生集合解決(全受講生 / 資格指定 / ユーザー指定)/ 受講中 status フィルタ(退会済 / 修了済 / 招待中 は配信されない)/ `dispatched_count` の正確性 / `Notification::fake()` で通知発火件数の検証 |
| Policy | 各メソッド × 各ロールの真偽判定(admin true / coach false / student false 網羅) |

### アーキテクチャ判断

> **Basic 範囲制約**: 教材外の Action / Service は使わない前提で **Controller 内完結** を基本とする。本チケットは配信ロジック(target_type に応じた受講生集合解決 + 各受講生への通知発火)を Controller 内で書ける(`User::query()->where(...)->each(fn $u => $u->notify(new AnnouncementNotification($announcement)))`)。Action 採用は受講生判断(チャレンジするなら歓迎)。

- **採用技術**: Eloquent + Controller(受講生判断で Action 分割可)+ Policy + FormRequest + `Notifications` channel(`S-B-04` の `BaseNotification` 継承)+ `DB::transaction` + Blade(提供済み)
- **設計判断**:
  1. **配信不可逆性**: Controller には `index` / `create` / `store` / `show` の 4 メソッドのみ(`edit` / `update` / `destroy` を持たない)。Route も `Route::resource(...)->only([...])` で 4 ルートのみ。UI 上にも編集 / 削除ボタンを配置しない
  2. **配信対象解決**: `target_type` の `match` 式で 3 種類の受講生集合クエリを切り替え。全受講生 = `User::role(Student)->status(InProgress)`、資格指定 = 上記 + `whereHas('enrollments', fn ($q) => $q->where('certification_id', $id)->where('status', Learning))`、ユーザー指定 = `User::where('id', $userId)->role(Student)->status(InProgress)`
  3. **通知発火タイミング**: `DB::transaction` 内で `Announcement` INSERT + `dispatched_count` / `dispatched_at` UPDATE までを原子化、通知発火(`$user->notify(...)`)は **`DB::afterCommit()` 内で実行** して、トランザクション ROLLBACK 時にメール副作用が残らないようにする(`S-B-04` と同じ設計判断)
  4. **`AnnouncementNotification` クラス**: `BaseNotification` を継承(`S-B-04` 提供)、`toDatabase()` で `data` JSON にお知らせ本文 / `admin_announcement_id` / `link_route='notifications.index'` を入れる、`toMail()` で件名「【Certify LMS】<タイトル>」+ 本文 + 「お知らせ一覧を開く」ボタン
  5. **受信者 status スキップ**: 配信対象集合解決クエリに `status === InProgress` を含めることで、退会済 / 修了済 / 招待中 を事前除外。`NotifyAnnouncementAction`(模範解答 PJ 採用、Basic 受講生は Controller 内で `each` ループ書き直し可)では受信者 status 再確認の二重防御
  6. **整合性 / 不在チェック**: `StoreRequest` の `required_if` / `prohibited_unless` / `exists` で大半の不整合を 422 で弾く + Controller / Action 内で改めて検査して `AnnouncementInvalidTargetException`(422)/ `AnnouncementTargetNotFoundException`(404)を throw(二重防御)
  7. **クリーンな履歴**: `Announcement` 自体は SoftDelete 不採用、UPDATE もしない(`dispatched_count` / `dispatched_at` のみ INSERT 直後に 1 回更新)。配信履歴は不可逆性を保つ
  8. **配信件数の同期計上**: `Notification::send($users, new AnnouncementNotification(...))` を使ってもよいが、`dispatched_count` を正確に取りたいため `each` でループしながらカウント、または `resolveRecipients()` の Collection サイズ を `count()` で取得して UPDATE する設計が分かりやすい

### 関連ファイルメモ

- `app/Models/Announcement.php` / `app/Enums/AnnouncementTargetType.php`
- `app/Http/Controllers/AnnouncementController.php`(`index` / `create` / `store` / `show`)
- `app/UseCases/Announcement/{Index,Show,Store}Action.php`(※ 模範解答 PJ で採用、Basic 受講生は Controller 内完結も可)
- `app/UseCases/Notification/NotifyAnnouncementAction.php`(※ 模範解答 PJ で発火フック用ラッパー Action、Basic 受講生は Controller / Store Action 内で直接 `$user->notify()` 呼び出しも可)
- `app/Notifications/Announcement/AnnouncementNotification.php`(`BaseNotification` 継承)
- `app/Policies/AnnouncementPolicy.php`
- `app/Http/Requests/Announcement/StoreRequest.php`
- `app/Exceptions/Notification/{AnnouncementInvalidTargetException, AnnouncementTargetNotFoundException}.php`
- `resources/views/announcement/management/{index,create,show}.blade.php`(提供 PJ 既存、ロック対象)+ `_partials/target-fields.blade.php`(`target_type` に応じた資格 / ユーザー選択フィールドの出し分け)
- `database/migrations/*_create_announcements_table.php`
- `database/seeders/AnnouncementSeeder.php`(提供 PJ 既存、`target_type` 3 種網羅)
- `routes/web.php` の admin プレフィックス内に `Route::resource('announcements', AnnouncementController::class)->only(['index','create','store','show'])->parameters(['announcements' => 'announcement'])->names('admin.announcements')` を追加
- 連携先(変更しない、`S-B-04` 提供):
  - `app/Notifications/BaseNotification.php`
  - `app/Http/Controllers/NotificationController.php`(受講生側の受信動線)

## 補足

### 想定ヒアリング Q&A

| 質問 | 回答 |
|---|---|
| 配信対象の種類は? | 3 種類:「全受講生(`all_students`)」「資格指定(`certification`)」「ユーザー指定(`user`)」 |
| コーチ向け配信はできる? | できない。配信対象は受講生のみ |
| 配信対象は受講中(InProgress)のみ? | はい。退会済 / 修了済 / 招待中 のユーザーには配信されず、`dispatched_count` にも含まれない |
| タイトル / 本文の文字数上限は? | タイトルは 200 文字、本文は 5000 文字 |
| 本文に HTML / Markdown は使える? | 使えない。プレーンテキストのみ、リッチテキストは MVP 外 |
| 添付ファイル(画像 / PDF)は付けられる? | 付けられない。プレーンテキスト本文のみ |
| 配信したお知らせは再配信できる? | できない。配信は不可逆、再配信動線なし |
| 配信したお知らせは編集できる? | できない。タイトル / 本文 / 対象 すべて配信後は変更不可 |
| 配信したお知らせは取り消せる? | 取り消せない。メール配信は取り消せないため、UI 上も整合 |
| 配信件数(`dispatched_count`)はどう計上される? | 実際に通知行が INSERT された件数。受講中 status でないユーザーは集合から除外され、件数に含まれない |
| 配信時刻(`dispatched_at`)はどう記録される? | 配信実行時の現在時刻が記録される。INSERT 直後に UPDATE で確定する |
| 配信対象タイプと FK 列の組み合わせ整合性は? | `all_students` の場合は資格 ID / ユーザー ID 両方 NULL、`certification` の場合は資格 ID 必須 / ユーザー ID NULL、`user` の場合はユーザー ID 必須 / 資格 ID NULL。不整合は 422 |
| `certification` 指定で公開停止資格を選んだら? | バリデーション層で `exists:certifications,id` を通る場合は配信成功(資格自体は存在する)。受講生集合解決時に `enrollments.status === learning` で絞り込むため、archived 資格に紐づく受講生も配信対象になりうる(資格の公開状態と受講登録状態は別概念)。詳細な絞り込み要件があれば別チケット |
| `user` 指定で受講生でないユーザー(コーチ / 管理者)を選んだら? | バリデーション層 + 配信集合解決時の `role === Student` チェックで除外される。422 または 配信件数 0 |
| 配信時のメール送信は同期 / 非同期? | 同期送信(Basic 段階は Queue 化なし、`S-B-04` と同じ設計)。配信実行画面が一瞬遅延するが、件数が極端に多い場合(全受講生数百名等)はメール送信が直列に走る点を運用上認識する |
| 受講生はお知らせをどこで見る? | 通常の通知一覧画面(`/notifications`、`S-B-04` 由来)。お知らせ単独画面は持たず、データベース通知 + メールで届く形式 |
| 通知行クリック時の遷移先は? | 通知一覧画面(`notifications.index`)。お知らせ詳細単独画面ではなく、通知一覧で確認する |
| 配信履歴の閲覧範囲は? | 管理者のみ。`/admin/announcements` で時系列降順 + ページネーションで一覧、各行クリックで詳細閲覧 |
| 配信件数 0 件のお知らせを配信できる? | できる(`certification` 指定 / `user` 指定で対象受講生が 0 件の場合)。`dispatched_count=0` で配信履歴に残り、`dispatched_at` も記録される(誤操作の証跡として残す) |
| 配信実行時に対象件数のプレビューは出る? | 出ない。配信実行後の `dispatched_count` で確認するのみ。事前プレビューは MVP 外 |
| `created_by_user_id` の管理者 を退会させたら? | 配信履歴の `createdBy` リレーションは `withTrashed()` で SoftDelete 済管理者も解決可能。FK は ON DELETE RESTRICT のため物理削除はできない |
| メール件名の推奨は? | 「【Certify LMS】<お知らせタイトル>」プレフィックス統一(適切な日本語であれば文言の細部は採点対象外) |
| メール本文の構成は? | `MailMessage` の `->greeting('管理者からのお知らせ')` + `->line($body)` + `->action('お知らせ一覧を開く', route('notifications.index'))` + `->salutation('Certify LMS 運営チーム')` で構成(Mailable クラスは作らない) |
| Pusher Broadcasting で配信時にリアルタイム反映できる? | 本チケットでは扱わない。`S-A-05` で Pusher driver を有効化したときに自動的に broadcast チャネルも発火する設計(`BaseNotification` の `via()` に `broadcast` が含まれているが Basic 段階では no-op) |
