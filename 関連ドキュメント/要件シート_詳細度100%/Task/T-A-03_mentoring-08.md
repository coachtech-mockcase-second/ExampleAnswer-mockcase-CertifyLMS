# T-A-03 Google Calendar 外部連携を Service 分離

<!--
記述粒度規約: 実装粒度(テーブル名 / カラム名 / クラス名 / SQL 詳細 / Laravel 実装語彙 / URL パス詳細 等)を記載できるのは `## 実装方針` 配下のみ。それ以外のセクション(概要 / 背景・目的 / やること / やらないこと / 補足)は **業務語彙のみ** で記述する。詳細規約は `../../../.claude/rules/ticket-spec.md`「実装粒度の記載範囲」参照。
受け入れ条件は構造記述 / Before/After 計測値ベース(Performance では計測値が振る舞いの代替指標、Refactoring では振る舞い不変 + 構造変更点)。
-->

## メタ情報

| 項目 | 値 |
|---|---|
| チケット ID | `T-A-03` |
| Feature 連番 | `mentoring-08` |
| Feature | mentoring |
| 種別 | Task |
| サブカテゴリ | リファクタリング |
| 難易度 | Advance |
| 工数 (h) | 4 |
| 依存チケット | `S-A-01`(Google Calendar 連携の OAuth フロー + API 呼出が実装済の状態が対象) / `T-A-02`(Meeting 系 Action 分離後の Action 内に Google API 呼出が残っている状態)|

## 概要

`S-A-01` 完了時点では Google Calendar 連携の API 呼び出し(OAuth 認可 URL 発行 / トークン交換 / トークン取消 / 空き時刻取得 / 予定作成 / 予定削除)が、`MeetingAvailabilityService` や `Meeting` 系 Action や `CoachGoogleCredentialController` に散らばっている。これらを **2 つの専用 Service クラス**(API ラッパー)へ集約し、呼び出し側からは Service の高レベルメソッド呼び出しのみで Google 連携が完結するように分離するリファクタリング。

## 背景・目的

- **現状の問題**: Google API クライアントの組み立て(クライアント ID / シークレット / リダイレクト URI / scope 設定)、OAuth 認可 URL 生成、認可コードのトークン交換、トークン取消、トークン期限切れ時のリフレッシュ、`freebusy` リクエスト構築、予定作成 / 削除 API 呼出が、利用側(空き枠集計 Service / 予約 Action / キャンセル Action / 連携設定 Controller)に **直接書き散らかっている**。利用側が Google API ライブラリの低レベル詳細(`Google\Client` インスタンス生成 / `Google\Service\Calendar` 構築 / `Google\Service\Calendar\Event` 構築 / DateTime オブジェクト変換 / Throwable 例外ハンドリング / Log 出力)を知らないと改修不能で、テストもしづらい。トークン期限切れ時のリフレッシュロジックも複数箇所に重複している。
- **達成したい状態**: Google Calendar API の呼び出しを **2 つの専用 Service クラス**(認可フロー専用 + Calendar API 専用)に集約する。利用側からは「`freebusy($credential, $start, $end): array`」「`insertEvent($credential, $meeting): ?string`」「`deleteEvent($credential, $eventId): void`」「`buildClient(): Client`」「`getAuthUrl(array $state): string`」「`exchangeCode(string $code): array`」「`revoke(string $token): bool`」のような高レベル API のみを呼ぶ。トークン期限切れリフレッシュ / 例外 → 空配列フォールバック / Log 出力は Service 内に閉じ込められ、利用側は本質的なドメインロジックだけを書ける。
- **価値・優先度**: **「外部 API の境界設計」** を扱う題材。`backend-services.md` の「外部 API を叩く Service は専用クラスに切り出す」「Mockery でテストできるよう `final` を外す判断軸」を実コードで適用する。S-A-01 で書いた散在コードを「ライブラリ依存を 1 箇所に閉じ込めるリファクタ」で整理する流れは、実務プロジェクトでの外部 SaaS 連携の典型パターン。

## やること

- Google Calendar API の **認可フロー**(認可 URL 発行 / トークン交換 / トークン取消 / 共通クライアント生成)を専用 Service クラスに集約する
- Google Calendar API の **Calendar 操作**(空き時刻取得 / 予定作成 / 予定削除 / トークン期限切れ時の自動リフレッシュ)を専用 Service クラスに集約する
- 散在していた API 呼び出し(空き枠集計 Service / 予約 Action / キャンセル Action / 連携設定 Controller)を **すべて Service の高レベルメソッド呼び出しへ置き換え** る
- API 呼出失敗時のフォールバック挙動(空き時刻取得失敗 → 空配列返却で予約画面を壊さない、予定作成失敗 → null 返却で予約自体は成功扱い、予定削除失敗 → warning ログのみで継続)を Service 内に集約する
- 抽出した Service それぞれに対し **Mockery / Http::fake / Service 単体差し替え** でテストを書き、外部 API への実呼出なしで挙動を検証できるようにする
- 既存の Feature テストが改修後も pass し、利用側の振る舞い(空き枠返却 / 予約成立 / キャンセル完了 / 連携状態遷移)が変わっていないことを担保する

## やらないこと

- Google API の機能拡張(`event` 編集 / 招待者追加 / 通知設定変更 / `calendar_id` 切替 UI 等)— 既存機能の構造リファクタのみ扱う
- Google API ライブラリ自体のバージョンアップ(`google/apiclient` のメジャー更新)— 既存依存のまま
- 認証情報の暗号化保存(`encrypt()` / `decrypt()`)— 既存仕様(プレーンテキスト保存、教材として可視性を優先)を維持
- Stripe / Gemini など他外部 API の Service 分離 — 別 Feature の責務、本チケットの対象は Google Calendar 連携のみ
- Google Meet URL の動的生成 — 既存仕様(コーチの固定面談 URL を予定の `location` / `description` に埋め込む)を維持
- 認可フロー Service と Calendar 操作 Service を 1 クラスに統合する選択肢 — `S-A-01` で 2 クラス分離 = OAuth フローと Calendar 操作の責務が明確に異なるため分離維持。再統合はしない

## 受け入れ条件

- [ ] **Service ファイル新規作成(認可フロー)**: 共通クライアント生成 / OAuth 認可 URL 発行 / 認可コードのトークン交換 / トークン取消 の 4 操作を集約した Service クラスが `app/Services/Google/` 配下に存在する
- [ ] **Service ファイル新規作成(Calendar 操作)**: 空き時刻取得 / 予定作成 / 予定削除 + トークン期限切れ時の自動リフレッシュ の操作を集約した Service クラスが `app/Services/Google/` 配下に存在する
- [ ] **API 呼出の集約**: Google API ライブラリ(`Google\Client` / `Google\Service\Calendar` / `Google\Service\Calendar\Event` / `Google\Service\Calendar\FreeBusyRequest` 等)の参照が **`app/Services/Google/` 配下の 2 ファイル以外には存在しない**(grep で確認)
- [ ] **呼出側の置き換え**: 空き枠集計 Service / 予約 Action / キャンセル Action / 連携設定 Controller のすべてで、Google API ライブラリの直接参照が消え、専用 Service の高レベルメソッド呼出に置き換わっている
- [ ] **フォールバック挙動の保持**: 外部 API 呼出失敗時の挙動(空き時刻取得失敗 → 空配列フォールバック / 予定作成失敗 → null 返却 / 予定削除失敗 → warning ログのみで継続)が Service 内に閉じ込められ、利用側からは失敗 / 成功を区別する必要がない
- [ ] **トークンリフレッシュの集約**: トークン期限切れ時の `refresh_token` による自動更新ロジックが Calendar 操作 Service 内のみに存在し、利用側に分岐コードがない
- [ ] **Service 単体テスト追加**: 認可フロー Service と Calendar 操作 Service それぞれに対し `tests/Unit/Services/Google/` 配下に単体テストを追加(`Http::fake` または Mockery で `Google\Client` をスタブ化)
- [ ] **既存テスト pass**: 既存の Feature テスト(空き枠取得 / 予約成立 / キャンセル / 連携設定)が改修後も全件 pass
- [ ] **振る舞い不変**: HTTP リクエスト・レスポンス / DB 副作用 / Google API への発行回数 / フラッシュ表示 が改修前後で完全に一致

## 実装方針

> **参考設計の一例**。受け入れ条件を満たせれば実装手段は問わない。受講生は提供 PJ コード + ヒアリングで自分の設計を組み立てる。

### 主要 URL

| メソッド | パス | 振る舞い | 利用する Service(リファクタ後)|
|---|---|---|---|
| GET | `/settings/google-calendar/redirect` | OAuth 認可 URL へ 302 redirect | `App\Services\Google\GoogleOAuthService::getAuthUrl` |
| GET | `/settings/google-calendar/callback` | Google からの callback、`code` をトークンに交換して DB 保存 | `App\Services\Google\GoogleOAuthService::exchangeCode` |
| DELETE | `/settings/google-calendar` | 連携解除(トークン取消 + DB 行削除) | `App\Services\Google\GoogleOAuthService::revoke` |
| GET | `/enrollments/{enrollment}/meetings/availability?date=...` | 空き枠 JSON 返却(連携済コーチは GCal `freebusy` を参照) | `App\Services\Google\GoogleCalendarService::freebusy` |
| POST | `/enrollments/{enrollment}/meetings` | 予約成立後、連携済コーチに予定作成 | `App\Services\Google\GoogleCalendarService::insertEvent` |
| POST | `/meetings/{meeting}/cancel` | キャンセル後、連携済コーチの予定を削除 | `App\Services\Google\GoogleCalendarService::deleteEvent` |

### 変更対象と変更前後の状態

- **変更対象ファイル候補**:
  - `app/Services/Google/GoogleOAuthService.php`(新規作成、認可フロー集約)
  - `app/Services/Google/GoogleCalendarService.php`(新規作成、Calendar 操作集約)
  - `app/Services/MeetingAvailabilityService.php`(`freebusy` API 直接呼出を Service 経由に置換)
  - `app/UseCases/Meeting/StoreAction.php`(`insertEvent` API 直接呼出を Service 経由に置換)
  - `app/UseCases/Meeting/CancelAction.php`(`deleteEvent` API 直接呼出を Service 経由に置換)
  - `app/Http/Controllers/Settings/CoachGoogleCredentialController.php`(認可 URL 発行 / トークン交換 / 取消の Service 経由化、ただし本 Controller は既に薄く Action 経由のため変更最小)
  - `app/UseCases/CoachGoogleCredential/FetchAuthUrlAction.php` / `StoreAction.php` / `DestroyAction.php`(Service の高レベル API を呼ぶラッパーに薄化)
  - `tests/Unit/Services/Google/GoogleOAuthServiceTest.php` / `GoogleCalendarServiceTest.php`(新規作成)

- **変更前の状態**(リファクタ前 / 提供 PJ で受講生が直面する状態、`S-A-01` 完了直後を想定):
  - `MeetingAvailabilityService::slotsForCertification` 内に `new Google\Client()` → `setClientId(config(...))` → `setAccessToken(...)` → `new FreeBusyRequest` → `freebusy->query` の手続きが直接展開
  - `Meeting\StoreAction::__invoke` 内に `new Google\Service\Calendar\Event()` → `setSummary(...)` → `setDescription(...)` → `setStart(...)` → `setEnd(...)` → `events->insert(...)` の手続きが展開
  - `Meeting\CancelAction::__invoke` 内に `new Google\Client()` → `setAccessToken(...)` → `events->delete(...)` + `410 Gone` 例外ハンドリング が展開
  - `CoachGoogleCredentialController::redirect` 内に `new Google\Client()` → `setScopes(...)` → `setState(json_encode(...))` → `createAuthUrl()` が展開
  - `CoachGoogleCredentialController::callback` 内に `new Google\Client()` → `fetchAccessTokenWithAuthCode($code)` + エラーハンドリング が展開
  - `CoachGoogleCredentialController::destroy` 内に `new Google\Client()` → `revokeToken(...)` が展開
  - **トークン期限切れリフレッシュロジックが `MeetingAvailabilityService` / `Meeting\StoreAction` / `Meeting\CancelAction` の 3 箇所に重複**
  - **`Throwable` catch して `Log::warning` + 空配列 / null 返却 のフォールバックが各所に重複**
  - `app/Services/Google/` ディレクトリが **存在しない**

- **変更後の理想形**(リファクタ後 / 模範解答 PJ の完成形):
  - `app/Services/Google/GoogleOAuthService.php` を新設:
    - `buildClient(): GoogleClient` — クライアント ID / シークレット / リダイレクト URI / scope / `access_type=offline` / `prompt=consent` を集中設定して `Google\Client` を返す
    - `getAuthUrl(array $state): string` — `$state` を JSON エンコードして `setState` した上で `createAuthUrl` を返す
    - `exchangeCode(string $code): array` — `fetchAccessTokenWithAuthCode($code)` を呼び、エラーがあれば例外 throw、`access_token` / `refresh_token` / `expires_in` を含む配列を返す
    - `revoke(string $token): bool` — `revokeToken` の結果を bool で返す
  - `app/Services/Google/GoogleCalendarService.php` を新設(`GoogleOAuthService` を DI):
    - `freebusy(CoachGoogleCredential $credential, Carbon $start, Carbon $end): array` — `freebusy.query` を呼び、busy 配列を `[['start' => Carbon, 'end' => Carbon], ...]` で返す。トークン期限切れ時は内部で `refresh_token` でリフレッシュし credential を UPDATE。失敗時は空配列 + `Log::warning`
    - `insertEvent(CoachGoogleCredential $credential, Meeting $meeting): ?string` — Event を組み立てて `events->insert`、成功時に `event_id` を返す。失敗時は null + `Log::warning`(予約自体は成功扱い)
    - `deleteEvent(CoachGoogleCredential $credential, string $eventId): void` — `events->delete` を呼ぶ。410 Gone(既削除済)は成功扱い、他例外は `Log::warning` のみで continue
  - `MeetingAvailabilityService` / `Meeting\StoreAction` / `Meeting\CancelAction` / `CoachGoogleCredential/*Action` から Google API ライブラリの直接参照を排除し、`GoogleCalendarService` / `GoogleOAuthService` の高レベル API のみを呼ぶ形に置換
  - トークン期限切れリフレッシュは `GoogleCalendarService` 内 private メソッド `refresh()` に集約(他 Service / Action からは見えない)

### テスト方針

| 種別 | 観点 |
|---|---|
| 振る舞い不変 | 既存 Feature テスト(`tests/Feature/Http/Settings/CoachGoogleCredentialControllerTest` / `tests/Unit/Services/MeetingAvailabilityServiceTest` / `tests/Feature/UseCases/Meeting/{Store,Cancel}ActionTest` 等)が改修後も全件 pass |
| 構造(認可フロー Service 単体) | `tests/Unit/Services/Google/GoogleOAuthServiceTest.php` を新規追加。`buildClient` が config から値を読んで `Google\Client` を返すこと / `getAuthUrl` が `setState(json_encode(...))` の結果を含む URL を返すこと / `exchangeCode` がエラー時に例外 throw すること / `revoke` の戻り値を真偽値で返すこと を検証 |
| 構造(Calendar 操作 Service 単体) | `tests/Unit/Services/Google/GoogleCalendarServiceTest.php` を新規追加。Mockery で `Google\Client` / `Google\Service\Calendar` をスタブ化し以下を網羅: `freebusy` の正常系(busy 配列を Carbon 配列に変換)/ 401 期限切れ → refresh 成功 → リトライ / 401 + refresh 失敗 → 空配列フォールバック / `insertEvent` の正常系(event_id 返却) / `insertEvent` 失敗 → null + Log::warning / `deleteEvent` の正常系(void) / 410 Gone → 成功扱い / その他例外 → Log::warning のみ |
| 構造(呼出側) | `Meeting\StoreAction` / `Meeting\CancelAction` / `MeetingAvailabilityService` のテストは `$this->mock(GoogleCalendarService::class, fn ($m) => $m->shouldReceive('insertEvent')->...)` パターンで Service をモック化(Google API ライブラリを直接モックしない)|
| Service の `final` 不採用 | 利用側のテストで Mockery で mock するため、`GoogleCalendarService` / `GoogleOAuthService` は `final class` を **外す**(`backend-services.md`「Mockery でテストする Service は final 不採用可」方針) |

### 採用技術と判断理由

- **採用技術**: Service 分離 / Constructor Injection / Mockery によるテスト時スタブ / `Http::fake` 不採用(`google/apiclient` は内部で `Guzzle` を使うが、Service レベルで Mockery 化する方が利用側のテストが書きやすい)/ Log Facade による警告ログ集約
- **判断理由**:
  1. **責務分離**(`backend-services.md`「外部 API 依存の切り離し」): Google API ライブラリの低レベル詳細(`Google\Client` 構築 / `Event` オブジェクト構築 / DateTime 変換 / 例外ハンドリング)を 2 つの Service に閉じ込めることで、利用側はドメインロジックに集中できる
  2. **2 Service 分離の理由**: `GoogleOAuthService` は **認可フロー(stateless、コーチ単位の認証情報を持たない)**、`GoogleCalendarService` は **Calendar 操作(`CoachGoogleCredential` を引数で受けてトークン管理を内包)** という責務の違いがあるため、1 クラスに統合せず分離する。Calendar 操作 Service は OAuth Service を DI で呼ぶ(共通クライアント生成のため)
  3. **`final class` を外す判断**: 利用側の Feature テスト(`Meeting\StoreActionTest` 等)で `$this->mock(GoogleCalendarService::class, fn ($m) => $m->shouldReceive(...))` を使うため、Mockery 互換性のために `final` 不採用(`backend-services.md` 規約準拠)。Interface(`Contract`)を切る選択肢もあるが、Google Calendar 連携が他 Feature から呼ばれることはなく、過剰抽象を避けるため Interface は切らない
  4. **`Http::fake` を採用しない理由**: `google/apiclient` ライブラリは内部で独自の HTTP クライアントを構築するため、Laravel の `Http::fake` ではスタブ化できない。Service 単体テストでは Mockery で `Google\Client` をスタブ化し、利用側テストでは `GoogleCalendarService` を Mockery でモックする 2 段構成にする
  5. **トークンリフレッシュの集約**: `access_token` 期限切れ時の `refresh_token` による自動更新は Calendar 操作 Service 内の private メソッドに集約(`freebusy` / `insertEvent` / `deleteEvent` の各メソッド冒頭で `isAccessTokenExpired()` 判定 → 自動 refresh)。これにより利用側はトークン管理から完全に解放される
  6. **失敗時フォールバックの集約**: 外部 API は不安定なため、全メソッドで `Throwable` catch → `Log::warning` → 空配列 / null / void で返す形を Service 内に閉じ込める。Google Calendar 連携は **付加機能**(連携なしでも面談予約自体は成立する)という設計判断を Service 構造で表現

### 改善対象コードメモ

- 改善対象の主要ファイル: `app/Services/MeetingAvailabilityService.php`(`freebusy` 呼出を含む空き枠集計) / `app/UseCases/Meeting/StoreAction.php`(`insertEvent` 呼出) / `app/UseCases/Meeting/CancelAction.php`(`deleteEvent` 呼出) / `app/Http/Controllers/Settings/CoachGoogleCredentialController.php`(認可 URL 発行 / コールバック / 取消) / `app/UseCases/CoachGoogleCredential/FetchAuthUrlAction.php` / `StoreAction.php` / `DestroyAction.php`
- 抽出先: `app/Services/Google/GoogleOAuthService.php` / `app/Services/Google/GoogleCalendarService.php`(新規作成)
- 既存パターン参考: `app/Repositories/GeminiLlmRepository.php`(`Http::fake` でテストする外部 API ラッパー、Repository パターン採用)/ 本チケットは **Service として配置**(`backend-services.md`「外部 API を叩く Service」+ 模範解答 PJ の既存配置を踏襲。Repository ではなく Service とする判断は OAuth フローを含むため Service 寄り)
- `config('services.google.client_id')` / `client_secret` / `redirect_uri` / `scopes` を `.env` 経由で読み込む構成は既存維持。Service 内で `config()` ヘルパー経由でのみ参照する

## 補足

### 想定ヒアリング Q&A

| 質問 | 回答 |
|---|---|
| Service は 2 クラス(認可フロー + Calendar 操作)に分けるべき? それとも 1 クラスに統合? | **2 クラス分離**。認可フロー(stateless、コーチ単位の認証情報を持たない)と Calendar 操作(`CoachGoogleCredential` を引数で受けてトークン管理を内包)で責務が明確に異なるため。Calendar 操作 Service が OAuth Service を DI で呼ぶ構成にする。 |
| 配置先は `app/Services/Google/` / `app/Repositories/` どちら? | `app/Services/Google/` を採用。`backend-services.md`「外部 API を叩く Service」+ 模範解答 PJ の既存配置を踏襲。`app/Repositories/` は Gemini など「データ取得が主目的の外部 API」向けで、Google Calendar は OAuth フロー + 状態を持つ操作(予定作成・削除)があるため Service 寄り。 |
| Interface(`Contract`)は切るべき? | **不要**。Google Calendar 連携が他 Feature から呼ばれることがなく、過剰抽象を避けるため。Mockery で具象クラスをスタブ化する方針で十分。 |
| Service クラスに `final` を付けるべき? | **付けない**。利用側のテストで `$this->mock(GoogleCalendarService::class, ...)` を使うため、Mockery 互換性のために `final` 不採用(`backend-services.md` 規約準拠)。 |
| トークン期限切れリフレッシュはどこに書く? | Calendar 操作 Service 内の **private メソッド** に集約。`freebusy` / `insertEvent` / `deleteEvent` の各メソッド冒頭で `isAccessTokenExpired()` を判定し、期限切れなら `refresh_token` で自動更新。利用側からは見えない。 |
| 外部 API 呼出失敗時の挙動は? | 「空き時刻取得失敗 → 空配列フォールバック」「予定作成失敗 → null 返却(予約自体は成功扱い)」「予定削除失敗(410 Gone 含む)→ warning ログのみで continue」。すべて Service 内に閉じ込め、利用側は失敗 / 成功を区別する必要がない設計。 |
| `Http::fake` を使ってテストする? | **使わない**。`google/apiclient` ライブラリは独自の HTTP クライアントを構築するため `Http::fake` ではスタブ化できない。Service 単体テストでは Mockery で `Google\Client` をスタブ化し、利用側テストでは `GoogleCalendarService` を Mockery でモックする 2 段構成。 |
| 利用側(`Meeting\StoreAction` 等)のテストはどう書く? | `$this->mock(GoogleCalendarService::class, fn ($m) => $m->shouldReceive('insertEvent')->once()->andReturn('event-id'))` パターンで Service をモック化。Google API ライブラリの低レベルモックは Service 単体テストに任せる。`T-A-04`(モックテスト追加)とも整合。 |
| 既存テスト(`MeetingAvailabilityServiceTest` 等)を触る必要は? | 既存テストの **振る舞い検証は維持**(改修後も pass)。ただし、テスト内で `$this->mock(GoogleCalendarService::class, ...)` を使う形に書き換わる可能性がある(Service 経由になったため)。テストデータと assert は変えない。 |
| Google API 関連の `config()` 設定は変更する? | しない。`config('services.google.client_id')` / `client_secret` / `redirect_uri` / `scopes` を `.env` 経由で読み込む既存構成を維持し、Service 内で `config()` ヘルパー経由でのみ参照する。 |
| 連携情報の暗号化保存(`encrypt()` / `decrypt()`)は導入する? | **しない**。既存仕様(プレーンテキスト保存、教材として OAuth フローの可視性を優先)を維持。本番運用時は `encrypt()`/`decrypt()` 又は Vault の導入を推奨と spec に明記済み。 |
