# S-A-01 Google Calendar 連携（面談予約）

## メタ情報

| 項目 | 値 |
|---|---|
| チケット ID | `S-A-01` |
| Feature 連番 | `mentoring-01` |
| Feature | mentoring |
| 種別 | Story |
| サブカテゴリ | 既存機能の拡張 |
| 難易度 | Advance |
| 工数 (h) | 14 |
| 依存チケット | (なし) |

## 背景・目的

面談機能は「面談可能時間枠のうち既存予約が入っていない時刻」だけで空き判定しているため、コーチが LMS 外の予定(他社案件 / 私用 / 別 LMS の面談 等)で実際は埋まっている時刻でも受講生から予約が成立してしまう。コーチは予約を受けてから「ダブルブッキングなのでキャンセルしてほしい」と運用でカバーしており、受講生体験を損なっている。

本チケットでは、コーチが自分の Google アカウントを LMS と任意連携することで、Google カレンダーに予定が入っている時刻を予約画面で最初から非表示にし、ダブルブッキングを構造的に消す。予約成立時にはコーチのカレンダーへ面談 Event を自動登録し、キャンセル時には連動削除する。連携・解除はコーチ本人がいつでも切り替えられ、未連携コーチは従来通りの空き判定で動く。

コーチ側のスケジュール透明性を OAuth で取り込み、受講生・コーチ双方のダブルブッキングの不快体験を構造的に消す。外部サービスへの OAuth クライアント連携(認可フロー + トークン管理 + API 失敗フォールバック)という、実務で頻出するテーマを扱う。

## ユーザーストーリー

- **コーチ(coach)として**、設定画面から自分の Google アカウントを LMS と連携したい。なぜなら、私用予定とぶつかる時刻を受講生に予約させたくないから。
- **コーチとして**、連携をいつでも解除したい。なぜなら、退職 / 別アカウントへの切替 / 一時的に LMS と Google を切り離したいケースに備えたいから。
- **コーチとして**、面談予約が成立したら自分の Google カレンダーに Event が登録されている状態を期待する。なぜなら、Google カレンダーを日々の予定確認の主軸にしており、LMS の面談を別途確認する手間を省きたいから。
- **コーチとして**、面談がキャンセルされたら Google カレンダーの Event も連動して削除されることを期待する。なぜなら、空いた時刻に他予定を入れる判断を自然にできるようにしたいから。
- **受講生(student)として**、予約画面に表示される空き時刻にはコーチの Google カレンダーで予定がない時刻だけが並んでいることを期待する。なぜなら、予約してから「ダブルブッキング」と言われてキャンセルされる体験を二度としたくないから。
- **管理者(admin)として**、本機能の運用には介入しない。なぜなら、コーチ個人の Google アカウントとの連携であり、管理者がトークン管理する必要はないから。

## 要件

### Google カレンダー連携(コーチのみ)

- 連携開始: 設定画面の面談設定タブから自分の Google アカウントを連携し、認可同意で連携情報が保存される
- 連携解除: いつでも解除可能(LMS 側の連携情報を削除し、Google 側のアクセス権も失効させる)
- 連携状態の表示: 面談設定タブに「未連携 / 連携中(連携日時)」を表示し、対応するボタン(連携 / 解除)を活性化
- 連携は任意で、オンボーディング時点では必須ではなくいつでも切替可能

### 予約画面の空き枠への反映(受講生が見る画面)

- 連携済コーチが Google カレンダーで予定を持つ時刻は、受講生の予約画面の予約可能コーチ数から除外。連携なしコーチは従来通り(面談可能時間枠 + 既存予約のみ)で判定

### コーチの Google カレンダーへの Event 連携(更新先はコーチ本人のカレンダー)

- 予約成立時の Event 作成: 連携済コーチが割り当たった予約成立時に、そのコーチの Google カレンダーへ面談 Event を自動作成(連携なしコーチは作成しない)。Event の場所・説明にはコーチの固定面談 URL を埋め込み、開始〜終了は予約時刻〜60 分後とする
- キャンセル時の Event 削除: Event が紐づく面談のキャンセルで、そのコーチの Google カレンダーの Event も連動削除

### 共通の振る舞い(フォールバック / トークン管理)

- Google API 失敗時のフォールバック: 空き枠取得失敗は busy なし扱い / Event 作成失敗は面談成立扱い / Event 削除失敗はキャンセル成立扱い(いずれも面談予約の根幹機能を壊さない付加機能扱い)
- アクセストークン期限切れ時の自動更新: リフレッシュトークンで自動更新してリトライし、リフレッシュも失敗した場合は当該 API をスキップ(連携なし相当)してコーチに再連携を促す
- 認可コールバックの正当性検証: コールバックに含まれる検証情報がログイン中コーチと一致しなければ拒否(連携のなりすまし防止)。永続トークンが取得できない場合も拒否
- 連携対象はプライマリカレンダー固定

### アクセス制御(機能群共通)

- Google カレンダー連携の操作(連携開始 / 解除)はコーチ本人のみ、受講生 / 管理者はアクセス不可

## スコープ外

- Google Meet URL の自動生成 / 動的取得 — コーチがプロフィールで設定した固定面談 URL を Event の場所 / 説明に焼き込むのみ
- 管理者がコーチの代理で Google 連携を操作する画面 — 連携はコーチ本人のみ
- 連携済コーチの Google カレンダーへの双方向同期(Google 側で Event を編集した内容が LMS に反映される等)— LMS → Google の一方向のみ
- 受講生 / 管理者の Google アカウント連携 — 本チケットはコーチ専用
- 連携カレンダーの選択 UI(コーチが複数カレンダーを持つ場合の選択画面)— プライマリカレンダー固定
- 認証情報の暗号化保存 — 本チケットのスコープ外(本番運用での暗号化は別途検討)、トークンは平文保存(README で本番運用時の暗号化推奨を明記)
- Google API のレート制限超過時のリトライキュー / バックオフ戦略 — 単純な失敗時フォールバックのみ
- 連携状態を管理者画面で一覧表示する機能
- Google 以外のカレンダーサービス(Outlook / Apple カレンダー等)との連携
- 連携前に成立した既存予約を遡って Google カレンダーに同期する機能

## 受け入れ条件

- [ ] 【コーチ・受講生・管理者】Google カレンダーの連携開始
  1. コーチが面談設定タブの連携ボタンを押すと Google の認可画面に遷移し、認可完了後は面談設定タブに戻って「連携中」(連携日時付き) + フラッシュ「Googleカレンダーと連携しました。」が表示される
  2. 受講生 / 管理者は連携ルートにアクセスできない
- [ ] 【コーチ】Google カレンダーの連携解除
  1. コーチが解除ボタンを押すと連携情報が削除され、面談設定タブが「未連携」+ フラッシュ「Googleカレンダー連携を解除しました。」に戻る(Google 側のアクセス権も失効する)
- [ ] 【受講生】空き枠への Google 予定の反映
  1. 受講生の予約画面の空き枠で、連携済コーチは Google カレンダーで予定がある時刻が空き枠から外れる
  2. 連携なしコーチは Google を参照せず面談可能時間枠 + 既存予約のみで判定される
- [ ] 【システム】空き枠取得時の API 失敗フォールバック
  1. 連携済コーチへの Google API 呼び出しが失敗してもエラー画面にならず、当該コーチは予定なし扱い(連携なしと同等)で空き枠が表示される
- [ ] 【システム】予約成立時の Google カレンダー連携
  1. 連携済コーチは面談 Event が作成され、面談に Event 識別子が保存される
  2. 連携なしコーチは Event 作成が走らず、識別子なしで予約が成立する
  3. Event 作成 API が失敗しても予約自体(面談作成 + 面談回数消費 + 担当コーチ宛通知)は成立し、識別子なしになる
- [ ] 【システム】面談キャンセル時の Google カレンダー連携
  1. Event 識別子が紐づく面談は Google カレンダーの Event が連動削除される
  2. 識別子がない / 削除 API が失敗した場合でもキャンセル自体(キャンセル状態への遷移 + 面談回数返却)は成立する
- [ ] 【システム】アクセストークン期限切れ時の自動更新
  1. 連携済コーチのアクセストークンが期限切れの状態で空き枠取得 / Event 作成 / Event 削除が走ると、リフレッシュトークンで自動更新してリトライする
  2. 更新も失敗した場合は当該 API をスキップ(連携なし相当のフォールバック)して画面のフローは継続する
- [ ] 本チケットの機能に対するテスト (Unit / Feature 等) が実装されている

## 実装方針(参考)

### インターフェース(必須)

**エンドポイント**:

| HTTP | パス | 認可 | 振る舞い |
|---|---|---|---|
| GET | `/settings/google-calendar/connect` | コーチのみ(受講生 / 管理者は 403) | 認可 URL を組み立てて Google の認可画面へ 302 redirect。クエリ `redirect_path` で戻り先指定可(既定 `/settings/profile?tab=meeting`)。Controller method 名は `redirect`、route 名は `settings.google-calendar.redirect` |
| GET | `/settings/google-calendar/callback` | コーチのみ | Google からの認可コールバック受信。`code` / `state` を検証し access_token / refresh_token に交換して連携情報を upsert。成功時 `state.redirect_path` 先へ redirect + フラッシュ「Googleカレンダーと連携しました。」 |
| DELETE | `/settings/google-calendar` | コーチのみ | Google 側トークンを revoke 後に連携情報を物理削除。成功時 `/settings/profile?tab=meeting` へ redirect + フラッシュ「Googleカレンダー連携を解除しました。」(連携なしでも冪等に成功扱い) |

> 既存 mentoring の `POST /enrollments/{enrollment}/meetings`(予約成立)/ `POST /meetings/{meeting}/cancel`(キャンセル)/ `GET /enrollments/{enrollment}/meetings/availability`(空き枠取得)に **本チケットの拡張がフックされる**(これら URL 自体は既存)。

**ミドルウェア**: `auth` + `role:coach` を `settings/google-calendar` route group 全体に適用。

### データモデル

**エンティティ**:

| エンティティ (Model) | 主要属性 | 関係性 | 制約 |
|---|---|---|---|
| コーチ Google 連携情報 (`CoachGoogleCredential`) | アクセストークン / リフレッシュトークン / カレンダー ID(既定 `primary`)/ 連携日時(業務語彙) | コーチ(`User`)に 1:1 所属(`User::googleCredential` HasOne / `coach()` BelongsTo) | ULID 主キー / `coach_id` UNIQUE + FK `cascadeOnDelete`(1 コーチ : 1 連携情報)/ SoftDelete 不採用 = 解除時は物理削除 / トークン 2 カラムを Model `$hidden` に追加 |
| 面談 (`Meeting`、既存に列追加) | Google Event 識別子(`google_event_id`、業務語彙) | — | `google_event_id` は varchar(255) nullable(`completed_at` の後に追加)/ Event 作成成功時のみセット、失敗時 / 連携なし時は NULL |

**Enum**: 本チケットで新規 Enum なし。

**インデックス用途**:

- `coach_google_credentials.coach_id` UNIQUE(検索条件は coach_id のみ、1 コーチ 1 連携情報の保証も兼ねる)

**初期データ**:

- 連携済のコーチと未連携のコーチを両方用意する（予約画面での空き枠反映・連携状態の表示・連携解除の動作を確認できる状態にする）

### コンポーネント

**Controller** (`app/Http/Controllers/Settings/`)
- `CoachGoogleCredentialController` — `redirect`(認可 URL へ 302)/ `callback`(code 交換 + 連携情報保存)/ `destroy`(revoke + 物理削除)

**Action** (`app/UseCases/CoachGoogleCredential/`、Advance 範囲)
- `FetchAuthUrlAction` — `state`(`coach_id` + `redirect_path`)を組んで認可 URL を生成
- `StoreAction` — callback で `state.coach_id === auth ユーザー` を検証 → code 交換 → 連携情報を upsert(再連携対応)
- `DestroyAction` — Google 側 revoke + 連携情報の物理削除

**Action 組込み**(既存 mentoring Action に拡張、Advance 範囲)
- `Meeting\StoreAction` — `DB::afterCommit` 内で連携済コーチへ Event 作成 + `meetings.google_event_id` 更新
- `Meeting\CancelAction` — `DB::afterCommit` 内で `google_event_id` ありの面談の Event 削除

**Service** (`app/Services/Google/`、新規、Advance 範囲、`final` 不採用 = Mockery 互換)
- `GoogleOAuthService` — `buildClient` / `getAuthUrl(array $state)` / `exchangeCode(string $code)` / `revoke(string $token)`(認可フロー専用、stateless)
- `GoogleCalendarService` — `freebusy(CoachGoogleCredential, Carbon, Carbon)` / `insertEvent(CoachGoogleCredential, Meeting): ?string` / `deleteEvent(CoachGoogleCredential, string): void` + private `refresh`(`GoogleOAuthService` を DI、トークン自動更新と失敗フォールバックを内包)

**Service 拡張**(既存)
- `MeetingAvailabilityService::slotsForCertification` — 担当コーチ集合のうち連携済コーチにのみ `freebusy` を呼び、busy 区間と重なるスロットから当該コーチを `available_coach_count` から除外

**Policy**
- なし(`role:coach` Middleware でロール確認、連携情報の所有者検証は `StoreAction` 内の `state.coach_id` 照合で実施)

**Model + Enum** (`app/Models/`)
- `CoachGoogleCredential`(新規)/ `Meeting`($fillable に `google_event_id` 追加)/ `User`(`googleCredential` HasOne 追加)

**View**(既存、ロック対象)
- `resources/views/settings/_partials/tab-meeting.blade.php` — 面談設定タブの Google カレンダー連携セクション(連携 / 解除ボタン + 連携状態表示)

**Migration / Seeder**
- `database/migrations/*_create_coach_google_credentials_table.php`
- `database/migrations/*_add_google_event_id_to_meetings_table.php`
- `database/seeders/CoachGoogleCredentialSeeder.php` + `database/factories/CoachGoogleCredentialFactory.php`

**例外** (`app/Exceptions/Mentoring/`)
- `GoogleOAuthException`(400、`stateMismatch()` / `missingRefreshToken()` の static factory)

**Routes** (`routes/web.php`)
- `settings.google-calendar.*`(`redirect` / `callback` / `destroy`、`auth` + `role:coach`)

**設定 / 依存**
- `config/services.php` に `google`(`client_id` / `client_secret` / `redirect_uri` / `scopes`)を追加(`.env` 経由、`scopes = ['calendar.readonly', 'calendar.events']`)
- `composer.json` に `google/apiclient`(Wave 0b で確定済なら本チケット範囲外)

### 異常系

**入力検証**:

- `connect` のクエリ `redirect_path`: 任意、未指定なら `/settings/profile?tab=meeting` を既定値とする(FormRequest は使わず Action 内で処理)
- `callback` の `code` / `state`: FormRequest ではなく `StoreAction` 内で検証

**業務例外**:

- `state.coach_id` がログイン中コーチと不一致 (`GoogleOAuthException::stateMismatch()`) → 400
- 認可コード交換結果に永続トークンが含まれない (`GoogleOAuthException::missingRefreshToken()`) → 400

**外部 API フォールバック**:

- `freebusy` 失敗 → 空配列(当該コーチは busy なし扱い)
- `insertEvent` 失敗 → null(`google_event_id` は NULL のまま、予約は成立)
- `deleteEvent` 失敗 → warning ログのみ(410 Gone = 既削除は成功扱い、キャンセルは成立)
- アクセストークン期限切れ → `refresh_token` で自動更新してリトライ、refresh も失敗なら当該 API をスキップ

### 設計判断

- **OAuth 状態の stateless 引き渡し**: `state` に `coach_id` と `redirect_path` を JSON エンコードして含める(セッションに頼らず認可 URL の再現性を高める)。コールバック時に `state.coach_id === auth()->id()` を必ず照合して連携の取り違え / なりすましを防ぐ
- **Service 二層化(OAuth / Calendar)**: `GoogleOAuthService`(認可 URL / code 交換 / revoke、stateless)と `GoogleCalendarService`(`CoachGoogleCredential` を引数で受け、API 呼出 + トークン自動 refresh を内包)に分離。責務が異なり、テストでもそれぞれ独立に Mockery でスタブできる
- **GCal は付加機能としてのフォールバック設計**: `GoogleCalendarService` の全メソッドは `try/catch` で例外を吸収し、空配列 / null / void で返す。API 失敗で「連携なしコーチでも空き枠は出る / 予約は成立する / キャンセルは成立する」という根幹機能を壊さない
- **外部 API は `DB::afterCommit` で分離**: `Meeting\StoreAction` / `Meeting\CancelAction` の Event 作成 / 削除は DB トランザクション commit 後に実行。Event 操作失敗で `meeting_quota_transactions` の INSERT が ROLLBACK され残数が会計上ずれるのを防ぐ
- **freebusy は 1 コーチ 1 リクエスト**: 複数カレンダーを 1 リクエストで叩く API は OAuth scope の都合で避け、担当コーチ N 名 × freebusy 1 リクエスト / 受講生予約画面ロード 1 回 を上限とする(1 コーチ単位のフォールバックを明示する意図も兼ねる)
- **トークン平文保存**: `access_token` / `refresh_token` は平文保存(`encrypt()` / `decrypt()` 不採用)。OAuth フローの可視性を優先し、本番運用時の暗号化推奨を README に明記
- **Service の `final` 不採用**: `GoogleOAuthService` / `GoogleCalendarService` は Action テスト時に Mockery でスタブする想定のため `final` を付けない
- **設定画面 UI は settings-profile が所有**: 連携 / 解除ボタン + 連携状態表示は設定画面の面談設定タブに埋め込む(本 Feature は OAuth フロー本体 + API ラッパー + DB 操作を所有し、URL `/settings/google-calendar/*` で settings-profile と協調)
- **テスト観点**: 「connect でコーチは 302 / 受講生・管理者は 403」「callback の state 不一致・refresh_token 欠落で 400」「連携済コーチの busy 反映 / 未連携無影響 / API 失敗フォールバック(Mockery で `GoogleCalendarService` をスタブ)」「予約成立時の `google_event_id` セット / 連携なしで NULL」「トークン期限切れ → refresh フロー」が本チケット固有の検証観点

## 補足

### 想定 Q&A

| 質問 | 回答 |
|---|---|
| 連携できるのは誰? | コーチのみ。受講生 / 管理者は連携ボタンが表示されず、連携ルートに直接アクセスしても 403 |
| コーチオンボーディング時に連携必須? | 必須ではない。コーチはいつでも連携 / 解除を切替可能 |
| 1 コーチが複数 Google アカウントを連携できる? | 不可。1 コーチ : 1 連携情報を強制(`coach_id` UNIQUE)。アカウント切替は一度解除してから再連携 |
| 連携するカレンダーは選べる? | 本チケットではプライマリカレンダー固定(`calendar_id = 'primary'`)。複数カレンダー選択 UI はスコープ外 |
| Google API が失敗したら受講生の予約画面はどうなる? | 連携済コーチの busy 取得失敗 → 当該コーチは busy なし扱い(連携なしと同等)で空き枠は引き続き表示。画面はエラーにならない |
| 予約成立時に Event 作成が失敗したら? | 面談予約自体は成立(面談作成 + 面談回数消費 + コーチ宛通知発火)。`google_event_id` は NULL のまま |
| キャンセル時に Event 削除が失敗したら? | 面談キャンセル自体は成立(ステータスがキャンセルに遷移 + 面談回数返却)。Google 側に Event が残る可能性はある |
| アクセストークンの期限は? | Google 仕様で短命(通常 1 時間)。期限切れ時はリフレッシュトークンで自動更新する |
| リフレッシュトークンも失効したら? | 当該 API リクエストはスキップ(連携なし相当のフォールバック)。コーチが再連携するまで GCal 連動は止まる |
| 連携解除時に Google カレンダーの既存 Event は消える? | 消えない(将来の予約からの新規 Event 作成は止まるが、解除前に作られた Event は残る)。面談の Event 識別子も保持し、再連携してキャンセルすれば再び削除リクエストを送れる |
| 認可コールバックで `state` には何を入れる? | `coach_id`(ログイン中コーチ)と `redirect_path`(連携完了後の戻り先)を JSON エンコード。コールバック時にコーチ ID が一致しなければ 400 |
| Google から永続トークンが返らないケースは? | 認可 URL に `prompt=consent` を付与しているため、再連携時も同意画面が出てリフレッシュトークンが返る前提。それでも返らない場合は 400 + フラッシュエラーで再連携を促す |
| Event の件名 / 説明 / 場所には何が入る? | 件名: 受講生名 + 資格名 / 説明: 話題 + 面談 URL の案内 / 場所: コーチの固定面談 URL。文言は `lang/ja/mentoring.php` に集約 |
| 面談 URL はどこから取る? | 予約成立時に面談へスナップショットされたコーチの固定面談 URL を Event に焼き込む(予約後にコーチがプロフィール URL を変えても、その面談の Event 内容は予約時点で固定) |
| Google Meet URL は動的生成する? | しない。コーチが設定した固定面談 URL を Event に焼き込むのみ |
| 連携状態の表示位置は? | 設定画面の面談設定タブ(`/settings/profile?tab=meeting`)に「未連携 / 連携中(連携日時)」と対応ボタンを表示 |
| トークンは暗号化保存する? | 本チケットでは平文保存(OAuth フローの可視性を優先)。本番運用時は暗号化を推奨と README に明記 |
| 1 リクエストで複数コーチのカレンダーをまとめて取れる? | API としては可能だが本チケットでは 1 コーチ 1 リクエストで実装(OAuth scope の都合 + 1 コーチ単位のフォールバックを明示するため) |
| 連携済コーチが LMS の解除を経由せず Google 側で直接アクセス権を剥奪したら? | LMS 側は気づけず、次の API リクエストで失敗(リフレッシュも失敗)。フォールバックで連携なし相当の挙動になり、コーチが LMS 側でも明示的に解除して再連携するのが想定運用 |
| Google API のレート制限超過時は? | 当該 API リクエストは失敗扱い(warning ログ)、自前リトライはしない。受講生予約画面なら busy なしフォールバック / 予約・キャンセル時は Event 操作なしで通過 |
