# S-A-01 Google Calendar 連携(面談予約)

## メタ情報

| 項目 | 値 |
|---|---|
| チケット ID | `S-A-01` |
| Feature 連番 | `mentoring-01` |
| Feature | mentoring |
| 種別 | Story |
| サブカテゴリ | 既存機能の拡張 |
| 難易度 | Advance |
| 工数 (h) | 16.5 |
| 依存チケット | (なし) |

## 概要

コーチが自分の Google アカウントを LMS と OAuth 連携することで、面談予約の空き枠から「コーチの Google カレンダー上で予定が入っている時刻」を自動除外し、予約成立時にコーチの Google カレンダーに面談 Event を自動作成、キャンセル時に Event を自動削除する仕組みを追加する。連携はコーチごとに任意で、未連携のコーチは従来通り面談可能時間枠と既存予約だけで空き判定される。

## 背景・目的

- **現状の問題**: 提供 PJ の面談機能は「面談可能時間枠 ∩ 既存予約なし」のみで空き判定しているため、コーチが LMS 外の予定(他社案件 / 私用 / 別 LMS の面談 等)で実際は埋まっている時刻でも、受講生から予約成立してしまう。コーチは予約を受けてから「ダブルブッキングなのでキャンセルしてほしい」と運用でカバーしており、受講生体験を損なっている。
- **達成したい状態**: 連携を有効にしたコーチについては、Google カレンダーの予定とぶつかる時刻は予約画面で最初から非表示になり、ダブルブッキングが発生しない。予約成立時にコーチのカレンダーへ Event が自動登録され、コーチは自分の手帳でも面談を一元管理できる。連携解除も任意でいつでも可能。
- **価値・優先度**: コーチ側のスケジュール透明性を OAuth で取り込み、受講生・コーチ双方のダブルブッキング不快体験を構造的に消す。本機能は受講生体験の中核改善であり、外部 OAuth 連携を扱う Advance スコープの代表チケット。

## ユーザーストーリー

- **コーチ(coach)として**、設定画面から自分の Google アカウントを LMS と連携したい。なぜなら、私用予定とぶつかる時刻を受講生に予約させたくないから。
- **コーチとして**、連携をいつでも解除したい。なぜなら、退職 / 別アカウントへの切替 / 一時的に LMS と Google を切り離したいケースに備えたいから。
- **コーチとして**、面談予約が成立したら自分の Google カレンダーに Event が登録されている状態を期待する。なぜなら、Google カレンダーを日々の予定確認の主軸にしており、LMS の面談を別途確認する手間を省きたいから。
- **コーチとして**、面談がキャンセルされたら Google カレンダーの Event も連動して削除されることを期待する。なぜなら、空いた時刻に他予定を入れる判断を自然にできるようにしたいから。
- **受講生(student)として**、予約画面に表示される空き時刻にはコーチの Google カレンダーで予定がない時刻だけが並んでいることを期待する。なぜなら、予約してから「ダブルブッキング」と言われてキャンセルされる体験を二度としたくないから。
- **管理者(admin)として**、本機能の運用には介入しない。なぜなら、コーチ個人の Google アカウントとの連携であり、管理者がトークン管理する必要はないから。

## やること

### Google カレンダー連携(コーチ)

- **連携開始**: コーチのみ可、受講生 / 管理者は 403。設定画面の面談設定タブにある連携ボタンから Google の認可画面に遷移し、コーチが自分の Google アカウントで同意することで LMS にアクセス用の認可情報が保存される
- **連携解除**: コーチのみ可、受講生 / 管理者は 403。連携状態のコーチが解除ボタンを押すと、LMS 側の認可情報が削除され、Google 側でも LMS のアクセス権が失効する
- **連携状態の表示**: 設定画面の面談設定タブに「未連携 / 連携中(連携日時付き)」のいずれかが表示される。連携中の場合は解除ボタンが活性化し、未連携の場合は連携ボタンが活性化する
- **コーチオンボーディング後の任意連携**: コーチオンボーディング時点では連携必須ではなく、いつでも連携 / 解除を切り替えられる

### 面談予約への影響(受講生体験)

- **空き枠の Google カレンダー反映**: 受講生が予約画面で空き枠を表示する際、Google カレンダー連携済のコーチについては、コーチの Google カレンダー上で予定が入っている時刻はその時刻の予約可能コーチ数から除外される。連携していないコーチは従来通りの集計
- **予約成立時の Event 自動作成**: 受講生が予約を成立させた直後、選出されたコーチが Google カレンダー連携済の場合、コーチの Google カレンダーに面談 Event が自動作成される(成功時に Event ID が面談に紐づけられて保存される)。連携なしのコーチが選出された場合は Event 作成自体が走らない
- **キャンセル時の Event 自動削除**: 予約がキャンセルされた際、その面談に紐づく Google カレンダー Event ID があれば、Google カレンダーの Event も連動して削除される

### 共通の振る舞い

- **Google API 失敗時のフォールバック**: 空き枠取得時に Google API が失敗 / タイムアウトしても、面談予約画面自体は壊れずに表示される(Google カレンダー側の busy 情報は空として扱う = 連携なしと同等にフォールバック)。Event 作成失敗時も面談予約自体は成立扱い、Event 削除失敗時も面談キャンセル自体は成立扱い
- **アクセストークン期限切れの自動更新**: Google の短命なアクセストークンが切れた状態で API を叩いた場合、リフレッシュトークンを使って自動的にトークンを更新してリトライする。リフレッシュトークンまで無効化されている場合は、当該リクエストは失敗扱いとし、コーチには再連携を促す
- **認可コールバックの検証**: Google からの認可コールバックを受けた際、認証情報のすり替え防止のため、コールバックに含まれる検証情報と現在ログイン中のコーチ ID が一致することを確認する。一致しなければ 400 で拒否する
- **トークン保存形式**: アクセストークン / リフレッシュトークンは認証情報レコードに直接保存する(教材として OAuth フローの可視性を優先、本番運用時は暗号化推奨を補足で明記)

## やらないこと

- Google Meet URL の自動生成 / 動的取得 — コーチがプロフィールで設定した固定面談 URL(`users.meeting_url`)を Event の場所 / 説明に焼き込むのみ
- 管理者がコーチの代理で Google 連携を操作する画面 — 連携はコーチ本人のみ
- 連携済コーチの Google カレンダーへの双方向同期(Google 側で Event を編集した内容が LMS に反映される等)— LMS → Google の一方向のみ
- 受講生 / 管理者の Google アカウント連携 — 本チケットはコーチ専用
- 連携 calendar の選択 UI(コーチが複数カレンダーを持つ場合の選択画面)— `primary` カレンダー固定
- 認証情報の暗号化保存 — 教材スコープ外、トークンは平文保存(README で本番運用時の暗号化推奨を明記)
- Google API のレート制限超過時のリトライキュー / バックオフ戦略 — 単純な失敗時フォールバックのみ
- 連携状態を admin 管理画面で一覧表示する機能
- Google 以外のカレンダーサービス(Outlook / Apple カレンダー等)との連携
- 連携前に成立した既存予約を遡って Google カレンダーに同期する機能

## Seeder 設計

> `migrate:fresh --seed` 直後に動作確認できるよう、シナリオに紐付けたレコード単位で具体化する。

**前提**(他 Seeder で投入される想定): 受講生 A / コーチ X(資格 X 担当) / コーチ Y(資格 X 担当) / 管理者 / 公開資格 X / コーチ X の `meeting_url` / コーチ Y の `meeting_url` / 既存 `meetings` 数件(`reserved` / `completed` 含む)

`CoachGoogleCredentialSeeder`(`DatabaseSeeder` で `UserSeeder` の後に実行):

| レコード | 内容 | 動作確認用途 |
|---|---|---|
| credential_1 | コーチ X 用 / `calendar_id = 'primary'` / `connected_at` = 現在時刻 / `access_token` `refresh_token` = ダミー文字列(`'ya29.fake_access_xxxxx'` / `'1//fake_refresh_xxxxx'`、Factory の `fake()` ベース)| 既に連携済コーチが存在する状態で受講生予約画面を確認(GCal API は実呼び出ししない / Factory ベースのダミートークンで `freebusy` 呼び出しが起きると Service 側のフォールバックで空 busy 扱いになる挙動を確認)/ 設定画面の連携中表示 + 解除ボタン活性 / 解除実行後 DB から行が消えること |

> Seeder で投入したトークンは **テスト・教材用のダミー値で、実際の Google API には通らない**。GCal の `freebusy` / `events.insert` / `events.delete` を実機確認する場合はコーチがブラウザから自分の Google アカウントで連携し直す必要がある(教材手順書で別途案内)。連携なしコーチ(コーチ Y)も併設することで、ダミートークン経由のフォールバックと未連携経由のフォールバックの両ルートを動作確認できる。

- **DatabaseSeeder への追加順序**: `UserSeeder` → `CoachGoogleCredentialSeeder` → 既存の `CertificationCoachAssignmentSeeder` / `CoachAvailabilitySeeder` 等の後

## 受け入れ条件

- [ ] **連携開始 - 認可**: コーチが設定画面の面談設定タブから連携ボタンを押下すると Google の認可画面に遷移する。受講生 / 管理者がコーチ専用ルートに直接アクセスすると 403
- [ ] **連携完了 - 認可情報保存**: コーチが Google で認可を完了して LMS に戻ると、コーチ ID に紐づく認可情報が保存され、設定画面の面談設定タブに「連携中(連携日時付き)」が表示される
- [ ] **連携完了 - リダイレクト + フラッシュ**: 認可完了時、設定画面の面談設定タブにリダイレクトされ、フラッシュメッセージが表示される
- [ ] **連携解除 - 認可情報削除**: コーチが解除ボタンを押すと、コーチ ID に紐づく認可情報が DB から削除され、設定画面の面談設定タブに「未連携」が表示される
- [ ] **連携解除 - リダイレクト + フラッシュ**: 解除成功時、設定画面の面談設定タブにリダイレクトされ、フラッシュメッセージが表示される
- [ ] **連携解除 - Google 側のアクセス失効**: 解除実行時に Google 側に対してもアクセス失効リクエストが送信される(Google 側のアプリ連携一覧から LMS が消える、実機確認)
- [ ] **コールバック検証 - すり替え防止**: Google コールバックに含まれる検証情報と現在ログイン中のコーチ ID が一致しない場合、400 が返り認可情報は保存されない
- [ ] **コールバック検証 - リフレッシュトークン欠落**: Google から永続トークン(リフレッシュトークン)が返らない場合、400 が返り認可情報は保存されない(再連携の案内をフラッシュエラーで表示)
- [ ] **空き枠表示 - 連携済コーチ反映**: 連携済コーチが Google カレンダー上で予定を持つ時刻は、受講生の予約画面の空き枠から外れる(連携なしコーチがその時刻に枠を持っていれば、その時刻の予約可能コーチ数からは連携済コーチの 1 名分だけが減じられる)
- [ ] **空き枠表示 - 連携なしコーチ無影響**: 連携なしコーチについては Google カレンダーが参照されず、面談可能時間枠と既存予約のみで空き判定される(本ストーリー導入前と同じ挙動)
- [ ] **空き枠表示 - Google API 失敗時フォールバック**: 連携済コーチへの Google API 呼び出しが失敗してもエラー画面にならず、当該コーチについては busy なし扱い(連携なしと同等)で空き枠が表示される
- [ ] **予約成立 - Event 自動作成**: 連携済コーチが選出された予約が成立すると、コーチの Google カレンダーに面談 Event が作成され、Event ID が面談レコードに保存される
- [ ] **予約成立 - Event 内容**: 作成された Event の件名に受講生名と資格名、説明に話題と面談 URL、場所に面談 URL、開始時刻 / 終了時刻が面談の予約時刻と完了予定時刻(開始 + 60 分)に一致している
- [ ] **予約成立 - 連携なしコーチ無影響**: 連携なしコーチが選出された場合は Event 作成リクエスト自体が走らず、面談レコードの Event ID は NULL のまま予約成立する
- [ ] **予約成立 - Event 作成失敗時フォールバック**: Event 作成 API が失敗しても面談予約自体は成立扱い(面談レコードは作成され面談回数も消費される / 担当コーチ宛通知も発火 / Event ID は NULL のまま)
- [ ] **キャンセル - Event 自動削除**: 面談レコードに Event ID が紐づいている面談がキャンセルされると、Google カレンダーの Event が連動して削除される
- [ ] **キャンセル - Event ID なし無影響**: 面談レコードに Event ID が紐づいていない面談がキャンセルされても削除リクエストは走らず、キャンセル自体は成立する
- [ ] **キャンセル - Event 削除失敗時フォールバック**: Event 削除 API が失敗しても面談キャンセル自体は成立扱い(面談ステータスはキャンセルに遷移し、面談回数も返却される)
- [ ] **トークン自動更新**: 連携済コーチのアクセストークンが期限切れの状態で空き枠取得 / Event 作成 / Event 削除のいずれかが走った場合、リフレッシュトークンを使って自動更新が試行され、更新成功後に元の API リクエストが完了する
- [ ] **リフレッシュ失敗時フォールバック**: リフレッシュトークン自体が無効化されていてトークン更新も失敗した場合、当該 API リクエストはスキップされ(空き枠取得なら busy なし扱い / Event 作成・削除なら何もしない)、画面側のフローは継続する

## 実装方針

> **参考設計の一例**。受け入れ条件を満たせれば実装手段は問わない。受講生は提供 PJ コード + ヒアリングで自分の設計を組み立てる。

### 主要 URL

| メソッド | パス | 振る舞い |
|---|---|---|
| GET | `/settings/google-calendar/connect` | コーチのみ可、認可 URL を組み立てて Google の認可画面に 302 redirect。クエリ `redirect_path` で連携完了後の戻り先を指定可(デフォルト `/settings/profile?tab=meeting`) |
| GET | `/settings/google-calendar/callback` | Google からの認可コールバックを受信。クエリ `code` `state` を検証し、`code` を access_token / refresh_token に交換して `coach_google_credentials` に upsert。成功時 `state.redirect_path` 先に リダイレクト + フラッシュ「Googleカレンダーと連携しました。」 |
| DELETE | `/settings/google-calendar` | コーチのみ可、Google 側のトークンを revoke 後 `coach_google_credentials` 行を物理削除。成功時 `/settings/profile?tab=meeting` にリダイレクト + フラッシュ「Googleカレンダー連携を解除しました。」 |

> 既存 mentoring の `POST /enrollments/{enrollment}/meetings`(予約成立)/ `POST /meetings/{meeting}/cancel`(キャンセル)/ `GET /enrollments/{enrollment}/meetings/availability`(空き枠取得)に **本チケットの拡張がフックされる**。これら自体は提供 PJ で既存の URL として完成済。

### データモデル

**新規テーブル**: `coach_google_credentials`(ULID 主キー、SoftDelete 不採用 = 解除時は物理削除)

| カラム | 型 | NOT NULL | FK / UNIQUE | 補足 |
|---|---|:---:|---|---|
| id | ulid | ✓ | PK | `$table->ulid('id')->primary()` |
| coach_id | ulid | ✓ | users.id UNIQUE, ON DELETE CASCADE | `$table->foreignUlid('coach_id')->unique()->constrained('users')->cascadeOnDelete()`、1 コーチ : 1 認証情報 |
| access_token | varchar(2048) | ✓ | | Google API リクエストヘッダーに付与する短命トークン |
| refresh_token | varchar(512) | ✓ | | access_token 失効時に再発行するための長命トークン |
| calendar_id | varchar(255) | ✓ | | デフォルト `primary`、将来の任意カレンダー切替の余地 |
| connected_at | timestamp | ✓ | | 連携完了時刻、UI 表示用 |
| created_at | timestamp | | | `$table->timestamps()` |
| updated_at | timestamp | | | `$table->timestamps()` |

- **インデックス**: `coach_id` UNIQUE のみ(検索条件は coach_id だけ)
- **リレーション**: `CoachGoogleCredential::coach(): BelongsTo<User>` / `User::googleCredential(): HasOne<CoachGoogleCredential>`(`'coach_id'` を FK 指定)
- **`$hidden`**: `['access_token', 'refresh_token']` を Model `$hidden` に追加(toArray / JSON 出力にトークンを漏らさない)
- **削除戦略**: 物理削除のみ(SoftDelete 不採用、解除 = LMS 側で完全消失が要件)

**既存テーブル変更**: `meetings` テーブルに `google_event_id` カラムを追加

| カラム | 型 | NOT NULL | FK / UNIQUE | 補足 |
|---|---|:---:|---|---|
| google_event_id | varchar(255) | | | `$table->string('google_event_id', 255)->nullable()->after('completed_at')`。Event 作成成功時のみセット、失敗時 / 連携なし時は NULL |

### バリデーション

本チケットでは FormRequest ベースの入力検証は最小限。`/settings/google-calendar/connect` のクエリ `redirect_path` は連携完了後の戻り先パスとして `Request::query('redirect_path')` で受け取り、未指定なら `/settings/profile?tab=meeting` を既定値とする。

OAuth コールバック(`/settings/google-calendar/callback`)は Google からの正当な引数を期待するが、改ざんを想定して以下を Action 内で検証する:

| 項目 | 検証内容 | 失敗時の挙動 |
|---|---|---|
| `code` クエリ | 空文字でないこと | `GoogleOAuthException::stateMismatch()`(400) |
| `state` クエリ | JSON デコード可能 / `coach_id` キー存在 / `coach_id` が現在ログイン中のコーチ ID と一致 | `GoogleOAuthException::stateMismatch()`(400)推奨メッセージ「Google 認証情報の検証に失敗しました。再度連携をお試しください。」 |
| 認可コード交換結果 | `refresh_token` キーが含まれていること(初回連携時は必ず返る、`prompt=consent` を OAuth URL に付与して再連携時も強制発行) | `GoogleOAuthException::missingRefreshToken()`(400)推奨メッセージ「Google から永続トークンを取得できませんでした。Google アカウントの権限を取り消してから再度連携してください。」 |

### 認可設計

**Policy**: `CoachGoogleCredentialPolicy`(本チケットで新設、または Route Middleware `role:coach` で十分なら Policy 不要)

| メソッド | ロール × 判定 |
|---|---|
| connect / callback / destroy | コーチ(coach)のみ ✅(`role:coach` Middleware で route group に適用)/ 受講生 / 管理者は 403 |

- **連携情報の所有者検証**: コールバックの `state.coach_id === auth()->id()` で他人の連携を奪うリスクをガード(Policy ではなく Action 内で具象例外 throw)
- **解除時の対象特定**: ログイン中コーチに紐づく credential を `auth()->user()->googleCredential` で取得し、存在すれば削除、なければ 404 ではなく成功扱い(冪等性)

### API 仕様 (該当しない)

本チケットは画面遷移ベースで、JSON API endpoint は追加しない。

### テスト観点

| 種別 | 観点 |
|---|---|
| Unit | `CoachGoogleCredential` Model リレーション(coach)/ `GoogleCalendarService::freebusy/insertEvent/deleteEvent` の Mockery でスタブした `Google\Client` を経由した成功・失敗フォールバック / トークン期限切れ時の refresh フロー |
| Feature | `/settings/google-calendar/connect`(コーチ → 302 redirect / 受講生・管理者 → 403)/ `/settings/google-calendar/callback`(正常 code 交換 → DB に行追加 + リダイレクト + フラッシュ / `state.coach_id` 不一致 → 400 / refresh_token 欠落 → 400) / `/settings/google-calendar`(DELETE で行削除 + リダイレクト + フラッシュ) / 既存 `MeetingControllerTest::store` に GCal 連携済コーチが選出された場合の `google_event_id` セット検証(Mockery で `GoogleCalendarService::insertEvent` をスタブ)/ 既存 `MeetingControllerTest::cancel` に `google_event_id` ありの面談キャンセルで `GoogleCalendarService::deleteEvent` 呼び出し検証 |
| Service | `MeetingAvailabilityService::slotsForCertification` のテストで GCal 連携済コーチの busy 反映 / 未連携コーチ無影響 / API 失敗フォールバックを Mockery で網羅 |

### アーキテクチャ判断

- **採用技術**: `google/apiclient` パッケージ(`Google\Client` + `Google\Service\Calendar`) + `App\Services\Google\GoogleOAuthService`(認可 URL 発行 / code 交換 / revoke) + `App\Services\Google\GoogleCalendarService`(freebusy / insertEvent / deleteEvent + トークン自動 refresh) + `App\UseCases\CoachGoogleCredential\{FetchAuthUrl,Store,Destroy}Action` + `App\Http\Controllers\Settings\CoachGoogleCredentialController` + `App\Exceptions\Mentoring\GoogleOAuthException`(400)
- **設計判断**:
  1. **OAuth 状態の引き渡し**: `state` パラメータに `coach_id` と `redirect_path` を JSON エンコードして含める(セッションに頼らず stateless にし、認可 URL の再現性を高める)。コールバック時に `state.coach_id === auth()->id()` を必ず検証して連携の取り違えを防ぐ
  2. **Service 二層化**: `GoogleOAuthService`(認可 URL / code 交換 / revoke のみ)と `GoogleCalendarService`(API リクエスト + トークン自動 refresh)に分離。OAuth フロー責務とカレンダー操作責務を別 Service にすることで、テストでもそれぞれ独立に Mockery でスタブ可
  3. **`GoogleCalendarService` の例外吸収方針**: `freebusy` 失敗時は空配列 / `insertEvent` 失敗時は null / `deleteEvent` 失敗時は何もせず warning ログのみ。GCal は **付加機能** であり、API 失敗で面談予約画面の根幹機能(連携なしコーチでも空き枠は出る / 予約は成立する)を壊さない設計。同じ理由で `GoogleCalendarService` のメソッドは `try { ... } catch (\Throwable $e) { Log::warning(...) }` で包む
  4. **トークン自動 refresh**: `Google\Client::isAccessTokenExpired()` で期限切れを検知し、`fetchAccessTokenWithRefreshToken($credential->refresh_token)` で更新。成功時に `coach_google_credentials.access_token` を `update()` で永続化。refresh 自体も失敗したら警告ログのみで上位はフォールバック
  5. **`Meeting\StoreAction` への組込み**: GCal Event 作成は `DB::afterCommit` 内で実行する(DB トランザクション commit 後に外部 API を叩く設計)。Event 作成失敗で `meeting_quota_transactions` の INSERT が ROLLBACK されると残数が会計上ずれるため、`afterCommit` で外部 API を分離して内部の DB 操作を保護する
  6. **`Meeting\CancelAction` への組込み**: 同様に GCal Event 削除も `DB::afterCommit` 内で実行。`meetings.google_event_id` が NULL なら何もしない(未連携 / Event 作成失敗時の互換性)
  7. **`MeetingAvailabilityService::slotsForCertification` への組込み**: 担当コーチ集合のうち `coach->googleCredential` を持つコーチに対してのみ `GoogleCalendarService::freebusy` を 1 コーチずつ呼び、各スロットで busy 区間と重なるなら当該コーチを `available_coach_count` から除外する。「N コーチ × 1 freebusy API 呼び出し」で 1 受講生予約画面ロードの API コストを上限化(複数 calendar を 1 リクエストで叩く API は OAuth scope の都合で避ける、`NFR-mentoring-012`)
  8. **トークン平文保存(教材判断)**: `access_token` / `refresh_token` は DB に平文で保存する(`encrypt()` / `decrypt()` は採用しない)。教材として OAuth フローの可視性を優先、`README` で本番運用時の暗号化推奨を明記する(`NFR-mentoring-013` 準拠)
  9. **`final` を Service で外す**: `GoogleCalendarService` / `GoogleOAuthService` は Action テスト時に Mockery でスタブする想定なので `final` を付けない(`backend-services.md` 「Mockery でテストする Service は final 不採用可」方針)
  10. **設定画面 UI の責務分担**: 連携ボタン / 解除ボタン / 連携状態表示は `settings-profile` Feature が所有する設定画面の面談設定タブ(`/settings/profile?tab=meeting`)に埋め込まれる。本 Feature は OAuth フロー本体 + API ラッパー + DB 操作を所有(`mentoring` Feature 内に Controller を配置するが、URL は `/settings/google-calendar/*` で settings-profile と協調)

### 関連ファイルメモ

- `app/Models/CoachGoogleCredential.php`(新規 Model + Factory)
- `app/Models/User.php` に `googleCredential(): HasOne<CoachGoogleCredential>` リレーションを追加
- `app/Models/Meeting.php` の `$fillable` に `google_event_id` を追加
- `app/Services/Google/GoogleOAuthService.php`(新規、認可 URL / code 交換 / revoke)
- `app/Services/Google/GoogleCalendarService.php`(新規、freebusy / insertEvent / deleteEvent + 自動 refresh)
- `app/UseCases/CoachGoogleCredential/{FetchAuthUrl,Store,Destroy}Action.php`(新規)
- `app/Http/Controllers/Settings/CoachGoogleCredentialController.php`(新規)
- `app/Exceptions/Mentoring/GoogleOAuthException.php`(新規、400)
- `app/Services/MeetingAvailabilityService.php` の `slotsForCertification()` を GCal 統合版に拡張(担当コーチ集合に対する freebusy 呼出ループ + busy 重なり判定)
- `app/UseCases/Meeting/StoreAction.php` の `DB::afterCommit` 内に GCal Event 作成 + `meetings.google_event_id` 更新を追加
- `app/UseCases/Meeting/CancelAction.php` の `DB::afterCommit` 内に GCal Event 削除を追加
- `config/services.php` に `'google' => ['client_id', 'client_secret', 'redirect_uri', 'scopes']` 設定を追加(`.env` から読込、`scopes = ['calendar.readonly', 'calendar.events']`)
- `database/migrations/*_create_coach_google_credentials_table.php`(新規、ULID 主キー + coach_id UNIQUE + token カラム)
- `database/migrations/*_add_google_event_id_to_meetings_table.php`(新規、`google_event_id` カラム追加)
- `database/factories/CoachGoogleCredentialFactory.php`(新規、ダミートークン文字列で Seeder / テスト共用)
- `database/seeders/CoachGoogleCredentialSeeder.php`(新規、コーチ X 用 1 件のダミー連携を投入)
- `resources/views/settings/_partials/tab-meeting.blade.php` に Google カレンダー連携セクションを埋め込み(提供 PJ で既存)
- `routes/web.php` に `Route::middleware(['auth', 'role:coach'])->prefix('settings/google-calendar')->name('settings.google-calendar.')->group(...)` を追加
- `composer.json` に `"google/apiclient": "^2.x"` を追加(Wave 0b で確定済の場合は本チケット範囲外、未追加なら本チケットで追加)

## 補足

### 想定ヒアリング Q&A

| 質問 | 回答 |
|---|---|
| 連携できるのは誰? | コーチのみ。受講生 / 管理者は連携ボタンが表示されず、コーチ専用ルートに直接アクセスしても 403 |
| コーチオンボーディング時に連携必須? | 必須ではない。コーチはオンボーディング後にいつでも連携 / 解除を切替え可能 |
| 1 コーチが複数 Google アカウントを連携できる? | 不可。1 コーチ : 1 認証情報を強制(`coach_id` UNIQUE 制約)。アカウント切替は一度解除してから再連携 |
| 連携するカレンダーは選べる? | 本チケットではプライマリカレンダー固定(`calendar_id = 'primary'`)。複数カレンダーから選ぶ UI はスコープ外 |
| Google API が失敗したら受講生の予約画面はどうなる? | 連携済コーチについて busy 取得失敗 → 当該コーチの busy なし扱い(連携なしと同等)で空き枠は引き続き表示される。画面はエラーにならない |
| 予約成立時に Event 作成が失敗したら? | 面談予約自体は成立扱い(面談レコードは作成され、面談回数も消費される、コーチ宛通知も発火)。面談レコードの Event ID は NULL のまま |
| キャンセル時に Event 削除が失敗したら? | 面談キャンセル自体は成立扱い(面談ステータスはキャンセルに遷移し、面談回数も返却される)。Google 側に Event が残る可能性はある |
| アクセストークンの期限は? | Google 仕様で短命(通常 1 時間)。期限切れ時は LMS 側でリフレッシュトークンを使って自動更新する |
| リフレッシュトークンも失効したら? | 当該 API リクエストはスキップされる(連携なしと同等のフォールバック挙動)。コーチが再連携するまで GCal 連動は止まる |
| 連携解除時に Google カレンダーの既存 Event は消える? | 消えない(将来の予約からの新規 Event 作成は止まるが、解除前に作られた既存 Event は残る)。面談レコードの Event ID も保持し、再連携してキャンセルすれば再び削除リクエストを送れる挙動を維持 |
| 認可コールバックで `state` に何を入れる? | `coach_id`(現在ログイン中のコーチ ID)と `redirect_path`(連携完了後の戻り先パス)を JSON エンコード。コールバック時にコーチ ID が一致しなければ 400 で拒否 |
| Google から `refresh_token` が返らないケースは? | `prompt=consent` を OAuth URL に付与しているため、再連携時も強制的に同意画面が出てリフレッシュトークンが返る前提。それでも返らない場合は 400 + フラッシュエラーで再連携を促す |
| Event の件名 / 説明 / 場所には何が入る? | 件名: `「{受講生名} と {資格名} の面談」` / 説明: 話題 + 面談 URL の案内文 / 場所: コーチの固定面談 URL(`users.meeting_url`)。文字列は `lang/ja/mentoring.php` に集約 |
| Google Meet URL は動的生成する? | しない。コーチがプロフィールで設定した固定面談 URL(`users.meeting_url`)を Event に焼き込むのみ |
| 連携状態の表示位置は? | 設定画面の面談設定タブ(`/settings/profile?tab=meeting`)に「未連携」または「連携中(連携日時)」が表示され、対応するボタン(連携 / 解除)が活性化する |
| トークンは暗号化保存する? | 本チケットでは平文保存(教材として OAuth フローの可視性を優先)。本番運用時は暗号化を推奨と README に明記 |
| 1 リクエストで複数コーチのカレンダーをまとめて取れる? | API としては可能だが、本チケットでは 1 コーチ 1 リクエストで実装する(OAuth scope の都合 + 1 コーチ単位のフォールバックを明示するため)。担当コーチ N 名 × freebusy 1 リクエスト / 受講生予約画面ロード 1 回 が上限 |
| 連携済コーチが LMS の連携解除を経由せず Google 側で直接アクセス権を剥奪したら? | LMS 側は気づけず、次に API リクエストを送ると失敗する(リフレッシュトークン更新も失敗)。フォールバックで連携なしと同等の挙動になり、コーチが LMS 側でも明示的に解除して再連携することが想定運用 |
| Google API のレート制限超過時の挙動は? | 当該 API リクエストは失敗扱い(warning ログ)、リトライは自前では行わない。受講生予約画面なら busy なしフォールバック / 予約成立 / キャンセル時は Event 操作なしで通過 |
