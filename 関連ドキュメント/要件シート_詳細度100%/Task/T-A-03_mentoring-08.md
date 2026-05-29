# T-A-03 Google Calendar 外部連携を Service 分離

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
| 依存チケット | `S-A-01`(Google Calendar 連携の OAuth フロー + API 呼出が実装済の状態が対象) / `T-A-02`(Meeting 系 Action 分離後の Action 内に Google API 呼出が残っている状態) |

## 概要

現状（リファクタ前）、Google Calendar 連携の API 呼び出し(OAuth 認可 URL 発行 / トークン交換 / トークン取消 / 空き時刻取得 / 予定作成 / 予定削除)が、空き枠集計 Service や Meeting 系 Action や連携設定 Controller に散らばっている。これらを **2 つの専用 Service クラス**(API ラッパー)へ集約し、呼び出し側からは Service の高レベルメソッド呼び出しのみで Google 連携が完結するように分離するリファクタリング。

## 要件

- Google Calendar API の **認可フロー**(認可 URL 発行 / トークン交換 / トークン取消 / 共通クライアント生成)を専用 Service クラスに集約する
- Google Calendar API の **Calendar 操作**(空き時刻取得 / 予定作成 / 予定削除 / トークン期限切れ時の自動リフレッシュ)を専用 Service クラスに集約する
- 散在していた API 呼び出し(空き枠集計 Service / 予約 Action / キャンセル Action / 連携設定 Controller)を **すべて Service の高レベルメソッド呼び出しへ置き換え** る
- API 呼出失敗時のフォールバック挙動(空き時刻取得失敗 → 空配列 / 予定作成失敗 → null / 予定削除失敗 → ログのみで継続)を Service 内に集約する
- 抽出した Service それぞれに対し、外部 API への実呼出なしで挙動を検証できるテストを書く

## スコープ外

- Google API の機能拡張(予定編集 / 招待者追加 / 通知設定変更 / カレンダー切替 UI 等)— 構造リファクタのみ
- Google API ライブラリ自体のバージョンアップ — 既存依存のまま
- 認証情報の暗号化保存 — 既存仕様(平文保存、教材として可視性を優先)を維持
- Stripe / Gemini など他外部 API の Service 分離 — 本チケットの対象は Google Calendar 連携のみ
- Google Meet URL の動的生成 — 既存仕様(コーチの固定面談 URL を予定に埋め込む)を維持
- 認可フロー Service と Calendar 操作 Service を 1 クラスに統合する選択肢 — OAuth フローと Calendar 操作の責務が異なるため 2 クラス分離を維持

## 受け入れ条件

> **件数目安**: Task 1-3 件。振る舞い不変 / 既存テスト全 pass は AC に書かず評価シート ② 横断品質で扱う。

- [ ] 認可フロー(認可 URL 発行 / トークン交換 / トークン取消 / 共通クライアント生成)と Calendar 操作(空き時刻取得 / 予定作成 / 予定削除 + トークン期限切れ自動リフレッシュ)がそれぞれ専用 Service(`app/Services/Google/` 配下の 2 クラス)に集約され、Google API ライブラリの直接参照が `app/Services/Google/` 配下以外(空き枠集計 Service / 予約 Action / キャンセル Action / 連携設定 Controller)に存在しない
- [ ] 外部 API 失敗時のフォールバック(空き時刻取得失敗 → 空配列 / 予定作成失敗 → null / 予定削除失敗(既削除含む)→ ログのみで継続)とトークン期限切れ時の自動リフレッシュが Calendar 操作 Service 内に閉じ込められ、利用側に失敗 / 成功を区別する分岐コードがない
- [ ] 本チケットの機能に対するテスト (Unit / Feature 等) が実装されている(抽出した各 Service の単体テストを含む)

## 実装方針(参考)

> **粒度**: 業務語彙 + 技術名(変更対象ファイルパス・クラス名・メソッド名)を併記。具体的なコード片 / メソッド完全例は書かない。

### 変更内容

- **変更対象ファイル**:
  - `app/Services/Google/GoogleOAuthService.php`(新規、認可フロー集約)
  - `app/Services/Google/GoogleCalendarService.php`(新規、Calendar 操作集約)
  - `app/Services/MeetingAvailabilityService.php`(空き時刻取得 API 直接呼出を Service 経由に置換)
  - `app/UseCases/Meeting/StoreAction.php`(予定作成 API 直接呼出を Service 経由に置換)
  - `app/UseCases/Meeting/CancelAction.php`(予定削除 API 直接呼出を Service 経由に置換)
  - `app/Http/Controllers/Settings/CoachGoogleCredentialController.php` + `app/UseCases/CoachGoogleCredential/{FetchAuthUrl,Store,Destroy}Action.php`(認可 URL 発行 / トークン交換 / 取消の Service 経由化)
  - `tests/Unit/Services/Google/GoogleOAuthServiceTest.php` / `GoogleCalendarServiceTest.php`(新規)
- **URL 対応**: 連携開始 = `GET /settings/google-calendar/connect`(Controller method 名は `redirect`)→ `GoogleOAuthService::getAuthUrl` / コールバック = `GET /settings/google-calendar/callback` → `exchangeCode` / 連携解除 = `DELETE /settings/google-calendar` → `revoke` / 空き枠 = `GET /enrollments/{enrollment}/meetings/availability` → `GoogleCalendarService::freebusy` / 予約成立 = `POST /enrollments/{enrollment}/meetings` → `insertEvent` / キャンセル = `POST /meetings/{meeting}/cancel` → `deleteEvent`
- **変更前**(リファクタ前、受講生が直面する状態): `MeetingAvailabilityService::slotsForCertification` 内に `new Google\Client()` → `setAccessToken` → `FreeBusyRequest` 構築 → `freebusy->query` が直接展開 / `Meeting\StoreAction` 内に `Google\Service\Calendar\Event` 構築 → `events->insert` / `Meeting\CancelAction` 内に `events->delete` + 410 例外ハンドリング / `CoachGoogleCredentialController`(または各 Action)内に `createAuthUrl` / `fetchAccessTokenWithAuthCode` / `revokeToken` が展開。さらに **トークン期限切れリフレッシュロジック** と **`Throwable` catch → `Log::warning` → 空配列 / null フォールバック** が複数箇所に重複。`app/Services/Google/` ディレクトリが存在しない
- **変更後**(模範解答 PJ の完成形):
  - `GoogleOAuthService`(`final` 不採用)に `buildClient(): GoogleClient`(client_id / secret / redirect_uri / scopes + `access_type=offline` + `prompt=consent` を集中設定)/ `getAuthUrl(array $state): string`(`state` を JSON エンコード + `createAuthUrl`)/ `exchangeCode(string $code): array`(`fetchAccessTokenWithAuthCode`、エラー時例外)/ `revoke(string $token): bool` を集約
  - `GoogleCalendarService`(`final` 不採用、`GoogleOAuthService` を DI)に `freebusy(CoachGoogleCredential, Carbon, Carbon): array`(busy 区間を `[['start' => Carbon, 'end' => Carbon], ...]` で返す)/ `insertEvent(CoachGoogleCredential, Meeting): ?string`(event_id を返す、失敗時 null)/ `deleteEvent(CoachGoogleCredential, string): void`(410 Gone は成功扱い)を集約。トークン期限切れリフレッシュは **private メソッド** に集約(各メソッド冒頭で `isAccessTokenExpired()` 判定 → `refresh_token` で自動更新)
  - 利用側(`MeetingAvailabilityService` / `Meeting\StoreAction` / `Meeting\CancelAction` / `CoachGoogleCredential/*Action`)から Google API ライブラリの直接参照を排除し、専用 Service の高レベル API のみを呼ぶ
- **採用技術と判断理由**: Service 分離(`backend-services.md`「外部 API を叩く Service」)で低レベル詳細を 2 クラスに閉じ込める。**2 Service 分離の理由** = `GoogleOAuthService` は認可フロー(stateless、コーチ単位の認証情報を持たない)、`GoogleCalendarService` は Calendar 操作(`CoachGoogleCredential` を引数で受けトークン管理を内包)で責務が異なる(Calendar Service が OAuth Service を DI)。**`final` 不採用** = 利用側テストで `$this->mock(GoogleCalendarService::class, ...)` する Mockery 互換性のため。**Interface 不採用** = Google Calendar 連携が他 Feature から呼ばれず過剰抽象を避ける。**`Http::fake` 不採用** = `google/apiclient` が独自 HTTP クライアントを構築するため `Http::fake` でスタブできず、Service 単体テストは Mockery で `Google\Client` をスタブ化。フォールバック(空配列 / null / void)+ トークンリフレッシュを Service 内に集約し、Google Calendar 連携は付加機能(連携なしでも面談予約は成立)という設計判断を Service 構造で表現
- **テスト観点**: 既存 Feature テスト(空き枠取得 / 予約成立 / キャンセル / 連携設定)を改修後も pass させ振る舞い不変を担保 + `tests/Unit/Services/Google/{GoogleOAuthService,GoogleCalendarService}Test` を新規追加(Mockery で `Google\Client` をスタブ化し、`freebusy` 正常 / 401 期限切れ → refresh 成功 → リトライ / refresh 失敗 → 空配列 / `insertEvent` の event_id 返却・失敗 null / `deleteEvent` の 410 Gone 成功扱い等を網羅)+ 利用側テストは `$this->mock(GoogleCalendarService::class, ...)` で Service レベルモック化(モックテスト追加 Task と整合)

## 補足

### 想定 Q&A

| 質問 | 回答 |
|---|---|
| Service は 2 クラス(認可フロー + Calendar 操作)に分けるべき? 1 クラスに統合? | 2 クラス分離。認可フロー(stateless)と Calendar 操作(`CoachGoogleCredential` を引数で受けトークン管理を内包)で責務が異なるため。Calendar 操作 Service が OAuth Service を DI で呼ぶ |
| 配置先は `app/Services/Google/` / `app/Repositories/` どちら? | `app/Services/Google/`。`backend-services.md`「外部 API を叩く Service」+ 模範解答 PJ の既存配置を踏襲。Google Calendar は OAuth フロー + 状態を持つ操作があるため Service 寄り |
| Interface(Contract)は切るべき? | 不要。Google Calendar 連携が他 Feature から呼ばれることがなく、過剰抽象を避ける。Mockery で具象クラスをスタブ化する方針で十分 |
| Service クラスに `final` を付けるべき? | 付けない。利用側テストで `$this->mock(GoogleCalendarService::class, ...)` を使うため Mockery 互換性が必要 |
| トークン期限切れリフレッシュはどこに書く? | Calendar 操作 Service 内の private メソッドに集約。空き時刻取得 / 予定作成 / 予定削除の各メソッド冒頭で期限切れを判定し、`refresh_token` で自動更新。利用側からは見えない |
| 外部 API 呼出失敗時の挙動は? | 「空き時刻取得失敗 → 空配列」「予定作成失敗 → null(予約は成功扱い)」「予定削除失敗(410 Gone 含む)→ ログのみで continue」。すべて Service 内に閉じ込め、利用側は失敗 / 成功を区別しない設計 |
| `Http::fake` を使ってテストする? | 使わない。`google/apiclient` は独自 HTTP クライアントを構築するため `Http::fake` でスタブできない。Service 単体は Mockery で `Google\Client` をスタブ、利用側は `GoogleCalendarService` を Mockery でモックする 2 段構成 |
| 利用側(`Meeting\StoreAction` 等)のテストはどう書く? | `$this->mock(GoogleCalendarService::class, fn ($m) => $m->shouldReceive('insertEvent')->once()->andReturn('event-id'))` パターンで Service をモック化。Google API ライブラリの低レベルモックは Service 単体テストに任せる |
| 既存テスト(`MeetingAvailabilityServiceTest` 等)を触る必要は? | 既存テストの振る舞い検証は維持(改修後も pass)。テスト内で `$this->mock(GoogleCalendarService::class, ...)` を使う形に書き換わる可能性はあるが、テストデータと assert は変えない |
| Google 関連の `config()` 設定は変更する? | しない。`config('services.google.*')` を `.env` 経由で読み込む既存構成を維持し、Service 内で `config()` ヘルパー経由でのみ参照する |
| 連携情報の暗号化保存は導入する? | しない。既存仕様(平文保存、教材として OAuth フローの可視性を優先)を維持。本番運用時は暗号化推奨を README / spec に明記済み |
