# T-A-04 モックを用いたテスト追加(外部 API 連携)

<!--
記述粒度規約: 実装粒度(テーブル名 / カラム名 / クラス名 / SQL 詳細 / Laravel 実装語彙 / URL パス詳細 等)を記載できるのは `## 実装方針` 配下のみ。それ以外のセクション(概要 / 背景・目的 / やること / やらないこと / 補足)は **業務語彙のみ** で記述する。詳細規約は `../../../.claude/rules/ticket-spec.md`「実装粒度の記載範囲」参照。
受け入れ条件は構造記述 / Before/After 計測値ベース(Performance では計測値が振る舞いの代替指標、Refactoring では振る舞い不変 + 構造変更点)。
-->

## メタ情報

| 項目 | 値 |
|---|---|
| チケット ID | `T-A-04` |
| Feature 連番 | `horizontal-04` |
| Feature | 横断(mentoring / ai-chat / meeting-quota の 3 Feature にまたがる) |
| 種別 | Task |
| サブカテゴリ | リファクタリング |
| 難易度 | Advance |
| 工数 (h) | 5.5 |
| 依存チケット | `S-A-01`(Google Calendar 連携) / `S-A-02`(Gemini AI チャット) / `S-A-03`(Stripe 連携) / `T-A-03`(Google Calendar 連携の Service 分離後を対象) |

## 概要

Advance Story で実装した 3 つの外部 API 連携(Google Calendar / Gemini AI / Stripe Webhook)に対し、**外部 API への実呼出をしないテスト** を Mockery / `Http::fake` / HMAC 署名生成ヘルパー の各手法で網羅追加する。CI 環境で外部依存なくテストが完走する状態 + 失敗パス / リトライパス / 署名検証パスを構造的に検証する状態を担保するリファクタリング。

## 背景・目的

- **現状の問題**: `S-A-01` / `S-A-02` / `S-A-03` で実装した外部 API 連携部分には、正常系の Feature テスト(実機動作確認に近いもの)はあるが、**外部 API への実呼出をしないテスト構造** が薄い。例えば Gemini AI チャットの失敗系(HTTP 5xx / 空コンテンツ / 接続例外)、Google Calendar のトークン期限切れ → リフレッシュ → リトライの境界、Stripe Webhook の署名検証(有効 / 無効 / 欠落 / 冪等性)などの **異常系 + 境界系を網羅したテストが不足**。CI で外部 API を実際に叩いてしまうと、API キーの漏洩リスク / レート制限消費 / 不安定なテスト失敗を招く。
- **達成したい状態**: 3 つの外部 API 連携それぞれに対し、適切なモック手法を選択した上で **正常系 + 異常系 + 境界系を網羅したテストファイル** が `tests/` 配下に存在する。CI 上で `php artisan test` を走らせた際、外部 API への実呼出は **0 回**(`Http::preventStrayRequests()` で担保)。各テストファイルは「Service の `final` 不採用判断」「Mockery と `Http::fake` の使い分け」「HMAC 署名ヘルパーの DRY 化」を実コードで示す。
- **価値・優先度**: **「外部 API 連携のテスト戦略」** を扱う中核チケット。AI 丸投げ耐性の高い題材(モック対象の選択 / `final` の判断 / 失敗パスの設計 はコード知識だけでは書けず、設計判断が必要)。`backend-tests.md` の「外部 API 依存テストには `#[Group('external')]` を付ける」「Mockery / `Http::fake` の使い分け」を実装で適用する。

## やること

- **Google Calendar 連携テスト追加**(mentoring): 認可フロー(`GoogleOAuthService`)と Calendar 操作(`GoogleCalendarService`)それぞれの Service 単体テスト + 利用側(空き枠集計 Service / 予約 Action / キャンセル Action / 連携設定 Controller)の Mockery 経由テストを **Mockery でスタブ化** して追加
- **Gemini AI チャットテスト追加**(ai-chat): `GeminiLlmRepository` の Repository 単体テストを **`Http::fake()`** でスタブ化して追加(正常系 / HTTP エラー / 空コンテンツ / リトライ → 成功 / payload 検証 を網羅)
- **Stripe Webhook テスト追加**(meeting-quota): `StripeWebhookController` の Feature テストに **HMAC-SHA256 署名生成ヘルパー** を導入し、有効署名 / 無効署名 / 署名ヘッダ欠落 / 冪等性(同一 event_id の二重配信)を網羅
- **外部 API 実呼出の禁止**: 全テストで `Http::preventStrayRequests()` を `setUp()` または `TestCase` 基底に組み込み、`Http::fake` の対象漏れがあった場合に **テスト失敗で検知** する状態にする
- **`#[Group('external')]` タグ付与**: 外部 API 依存テスト(モックしているが「漏れがあれば実呼出してしまう可能性のある」テスト)に `#[Group('external')]` を付与し、CI で `--exclude-group external` で除外可能にする
- **Service の `final` 判断の集約**: Mockery でモックする Service(`GoogleCalendarService` / `GoogleOAuthService`)から `final` を外す判断を実コードで示す(`T-A-03` 完了時点で既に外れている前提、本チケットでは判断軸の文書化に留める)

## やらないこと

- Google API ライブラリ自体のバージョンアップ / Gemini API のモデル切替 / Stripe SDK のバージョンアップ — テスト構造のリファクタのみ扱う
- 結合テスト(実 Google / 実 Gemini / 実 Stripe を叩くテスト)の追加 — Pre-prod / 別経路で実施(本チケットでは扱わない)
- カバレッジツール(`phpunit-coverage` / Xdebug)の導入 — 別タスク
- Pusher Broadcasting のテスト — 本チケットの対象 3 外部 API には含めない(Pusher は Feature `notification` の Advance スコープだが Story 化されていないため、対応する Story がない)
- 外部 API 連携機能そのものの仕様変更 — テスト追加のみ
- Mock 戦略の統一化(全 Service を Mockery / 全 Repository を Http::fake)を超えた抽象化 — 既存規約(`backend-services.md` / `backend-repositories.md`)の使い分けを **そのまま体現する** ことが目的

## 受け入れ条件

- [ ] **Google Calendar Service 単体テスト**: 認可フロー Service(`GoogleOAuthService`)と Calendar 操作 Service(`GoogleCalendarService`)それぞれに対して `tests/Unit/Services/Google/{GoogleOAuthService,GoogleCalendarService}Test.php` が存在し、正常系 + トークン期限切れ → リフレッシュ → リトライ + 失敗時フォールバック(空配列 / null / Log::warning) + 410 Gone 成功扱い を網羅する
- [ ] **Google Calendar 利用側テスト**: 予約 Action / キャンセル Action / 空き枠集計 Service / 連携設定 Controller のテストで、`$this->mock(GoogleCalendarService::class, fn ($m) => $m->shouldReceive('freebusy|insertEvent|deleteEvent')->...)` パターンによる **Service 経由のモック化** を採用(Google API ライブラリの低レベルモックを利用側のテストに混ぜない)
- [ ] **Gemini AI Repository テスト**: `tests/Unit/Repositories/GeminiLlmRepositoryTest.php` に `Http::fake()` / `Http::fakeSequence()` を用いて 5 ケース以上(正常系 / HTTP 500 / 空コンテンツ / 503 リトライ → 200 成功 / payload 検証)を網羅
- [ ] **Stripe Webhook テスト**: `tests/Feature/Http/Webhooks/StripeWebhookControllerTest.php` に HMAC-SHA256 署名生成ヘルパー(`sign(string $payload, ?int $timestamp = null): string`)を private メソッドで定義し、有効署名 / 無効署名(400)/ 署名ヘッダ欠落(400)/ 冪等性(同一 `checkout_session_id` の二重配信で 1 件のみ INSERT)を網羅
- [ ] **`Http::preventStrayRequests()` の有効化**: 外部 API 依存テスト群で `Http::preventStrayRequests()` が `setUp()` 等で呼ばれており、`Http::fake` の対象漏れ時にテストが失敗する状態
- [ ] **`#[Group('external')]` タグ付与**: 外部 API モックを使うテスト(`GoogleCalendarServiceTest` / `GoogleOAuthServiceTest` / `GeminiLlmRepositoryTest` / `StripeWebhookControllerTest`)にクラスレベルで `#[Group('external')]` が付与され、`--exclude-group external` で除外可能
- [ ] **テスト pass**: `sail bin phpunit` で全テストが pass し、外部 API へのアクセス試行(`Http::assertSent` の追加検証 / API キーへのアクセスログ)が 0 件
- [ ] **PR 動作確認**: PR 説明の「動作確認」セクションに「外部 API への実呼出 0 回」を CI ログまたは `Http::preventStrayRequests()` の動作確認スクショで明示

## 実装方針

> **参考設計の一例**。受け入れ条件を満たせれば実装手段は問わない。受講生は提供 PJ コード + ヒアリングで自分の設計を組み立てる。

### 主要 URL

外部 API 連携機能の利用側 URL(計測 / リファクタ対象画面ではなく、テスト対象の振る舞いが現れる入口):

| メソッド | パス | 対象外部 API |
|---|---|---|
| GET | `/enrollments/{enrollment}/meetings/availability` | Google Calendar `freebusy` |
| POST | `/enrollments/{enrollment}/meetings` | Google Calendar `events.insert` |
| POST | `/meetings/{meeting}/cancel` | Google Calendar `events.delete` |
| GET | `/settings/google-calendar/redirect` | Google OAuth `createAuthUrl` |
| GET | `/settings/google-calendar/callback` | Google OAuth `fetchAccessTokenWithAuthCode` |
| DELETE | `/settings/google-calendar` | Google OAuth `revokeToken` |
| POST | `/ai-chat` 関連 | Gemini `generateContent` |
| POST | `/webhooks/stripe` | Stripe Webhook(自前 HMAC 検証) |

### 変更対象と変更前後の状態

- **変更対象ファイル候補**(新規作成 / 既存テスト拡充):
  - `tests/Unit/Services/Google/GoogleOAuthServiceTest.php`(新規作成、認可フロー Service の単体テスト)
  - `tests/Unit/Services/Google/GoogleCalendarServiceTest.php`(新規作成、Calendar 操作 Service の単体テスト)
  - `tests/Unit/Services/MeetingAvailabilityServiceTest.php`(既存、`$this->mock(GoogleCalendarService::class)` のケース拡充)
  - `tests/Feature/UseCases/Meeting/StoreActionTest.php`(既存、`$this->mock(GoogleCalendarService::class)` のケース拡充)
  - `tests/Feature/UseCases/Meeting/CancelActionTest.php`(既存、`deleteEvent` モックケース追加)
  - `tests/Feature/Http/Settings/CoachGoogleCredentialControllerTest.php`(既存、`$this->mock(GoogleOAuthService::class)` のケース拡充)
  - `tests/Unit/Repositories/GeminiLlmRepositoryTest.php`(新規作成 or 拡充、`Http::fake` / `Http::fakeSequence` 採用)
  - `tests/Feature/Http/Webhooks/StripeWebhookControllerTest.php`(新規作成 or 拡充、HMAC 署名ヘルパー導入)
  - `tests/TestCase.php` または `tests/CreatesApplication.php` 等基底クラス(`Http::preventStrayRequests()` の `setUp()` 統合、必要なら)

- **変更前の状態**(リファクタ前 / 提供 PJ で受講生が直面する状態):
  - 外部 API 連携機能の Feature テストはあるが、**正常系 + 1〜2 ケースのみ** で異常系 / 境界系(リトライ / 期限切れ / 空コンテンツ / 署名検証)が薄い
  - Google API 関連のテストが、利用側(Action / Service)で Google API ライブラリ直接モック(`Mockery::mock(Google\Client::class)`)を試みており、テストが書きづらい状態 or テスト自体が省略されている
  - Gemini Repository のテストファイルが存在しない or 正常系のみ
  - Stripe Webhook のテストに署名生成ヘルパーがなく、有効署名の生成が複雑(または無効署名のテストがない)
  - `Http::preventStrayRequests()` が組み込まれておらず、`Http::fake` の対象漏れがあれば外部 API を実呼出してしまうリスク
  - `#[Group('external')]` タグなし、CI で外部依存テストを除外する手段がない

- **変更後の理想形**(リファクタ後 / 模範解答 PJ の完成形):
  - **Google OAuth Service テスト**: `buildClient` が config 値を読むこと / `getAuthUrl` が `setState(json_encode(...))` の結果を URL に含むこと / `exchangeCode` がエラー時に例外 throw / `revoke` の戻り値 を、Mockery で `Google\Client` をスタブ化して検証
  - **Google Calendar Service テスト**: `freebusy` 正常系(busy 配列を Carbon 配列に変換)/ 期限切れ → refresh 成功 → リトライ / refresh 失敗 → 空配列 / `insertEvent` 正常系(event_id 返却) / API 失敗 → null + Log::warning / `deleteEvent` 正常系(void) / 410 Gone 成功扱い / 他例外 → Log::warning のみ を網羅
  - **利用側テスト(Mockery)**: 予約 Action / キャンセル Action / 空き枠集計 Service / 連携設定 Controller で `$this->mock(GoogleCalendarService::class, fn ($m) => $m->shouldReceive('insertEvent')->once()->with(...)->andReturn('event-id'))` のように **Service レベルでモック化**、Google API ライブラリの低レベル詳細はテストから完全に排除
  - **Gemini Repository テスト**: `Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response([...], 200)])` で正常系 / `Http::fake([... => Http::response(['error' => ...], 500)])` で 500 エラー / `Http::fakeSequence()->push(..., 503)->push(..., 200)` でリトライ → 成功 / `Http::assertSent(fn ($req) => ...)` で payload 構造(systemInstruction / contents / role 変換)検証 / 5 ケース以上
  - **Stripe Webhook テスト**: `private function sign(string $payload, ?int $timestamp = null): string` ヘルパーで `hash_hmac('sha256', $timestamp.'.'.$payload, $secret)` を生成。有効署名 → 200 OK + DB INSERT / 無効署名 → 400 / 署名ヘッダ欠落 → 400 / 冪等性 → 同一 event の 2 回目 POST で DB INSERT 件数が 1 件のまま
  - **`Http::preventStrayRequests()`**: `TestCase` 基底の `setUp()` または各テストファイルの `setUp()` で呼ぶ。`Http::fake` の対象漏れがあれば `RuntimeException`(または明確なエラー)で失敗
  - **`#[Group('external')]`**: 各テストクラスに `#[Group('external')]` を付与、`sail bin phpunit --exclude-group external` で外部 API モックテストを skip 可能

### テスト方針

| 種別 | 観点 |
|---|---|
| Google OAuth Service 単体 | `buildClient` の config 値読み込み / `getAuthUrl` の URL 構造 / `exchangeCode` の正常系 + エラー例外 / `revoke` の真偽値返却 |
| Google Calendar Service 単体 | `freebusy` 正常系 / 期限切れリフレッシュ成功 / 期限切れリフレッシュ失敗 → 空配列 / `insertEvent` 正常系 / `insertEvent` 失敗 → null / `deleteEvent` 正常系 / 410 Gone 成功扱い / 他例外 → Log::warning |
| 利用側(Mockery) | 予約 Action / キャンセル Action / 空き枠集計 Service / 連携設定 Controller で **Service レベルのモック化**、Google API ライブラリの低レベル詳細は混入禁止 |
| Gemini Repository(`Http::fake`)| 正常系 / HTTP 500 → 例外 / 空コンテンツ → 例外 / 503 → リトライ → 200 成功 / payload 検証(`Http::assertSent`)/ 5 ケース以上 |
| Stripe Webhook(HMAC)| 有効署名 → 200 + DB INSERT / 無効署名 → 400 / 署名ヘッダ欠落 → 400 / 冪等性(同一 `checkout_session_id` 二重配信) |
| 外部 API 実呼出禁止 | `Http::preventStrayRequests()` を `setUp()` で有効化、対象漏れがあればテスト失敗 |
| グループ除外 | `#[Group('external')]` 付与で `--exclude-group external` 動作 |

### 採用技術と判断理由

- **採用技術**: Mockery(Service レベルのスタブ化、`$this->mock(Class::class, fn ($m) => $m->shouldReceive(...))`)/ `Http::fake()` / `Http::fakeSequence()` / `Http::assertSent()` / `Http::preventStrayRequests()` / HMAC-SHA256 ヘルパー(自前 `hash_hmac('sha256', ...)`)/ `#[Group('external')]` アトリビュート(PHPUnit 10+)
- **判断理由**:
  1. **Mockery vs `Http::fake` の使い分け**(`backend-services.md` / `backend-repositories.md`):
     - **Service**(`GoogleCalendarService` / `GoogleOAuthService`): `google/apiclient` ライブラリが独自の HTTP クライアントを構築するため `Http::fake` は使えない。Service クラスを Mockery で `final` 不採用にしてスタブ化する
     - **Repository**(`GeminiLlmRepository`): `Illuminate\Support\Facades\Http` を直接使うため `Http::fake` で完全にスタブ化可能。Repository を Mockery する必要はない
     - **Webhook Controller**(`StripeWebhookController`): 受信側なので HMAC 署名生成ヘルパーを自前で書き、リクエストヘッダに付けて送る
  2. **`Http::preventStrayRequests()` の役割**: `Http::fake` の対象 URL に漏れがあった場合、本来なら実際の外部 API を叩いてしまう。`preventStrayRequests()` を有効化すると未モック URL へのリクエストが例外で止まり、テストが失敗するため漏れに気付ける。**API キー漏洩リスク / レート制限消費を防ぐ最終ライン**
  3. **`#[Group('external')]` の意義**: 外部 API モックテストは「モック構造に依存するため、ライブラリのバージョンアップで壊れやすい」「`Http::preventStrayRequests` 失敗時に外部 API を叩く可能性がある」性質を持つ。`#[Group('external')]` で分類しておくと CI で `--exclude-group external` で除外して安全な subset を回せる
  4. **Service レベルでモック化する理由**(利用側のテストで `Google\Client` を直接モックしない):
     - 利用側(Action / Service)のテストは「業務ロジックが正しく Service を呼ぶか」が検証対象。Google API ライブラリの低レベル詳細(`new Event()` / `setSummary` / `setStart` 等)を利用側テストに混ぜると、テストが Service の内部実装に強く結合する
     - Service レベルでモックすれば、利用側テストは `$mock->shouldReceive('insertEvent')->once()->andReturn('event-id')` の 1 行で済む
  5. **HMAC 署名ヘルパー DRY 化**: Stripe Webhook テストでは複数テストメソッドで同じ署名生成ロジックが必要。private メソッド `sign(string $payload, ?int $timestamp = null): string` に集約することで、テスト本体が「何を検証しているか」を読みやすくする
  6. **`#[Group('external')]` を `slow` と分けない**: 外部 API モックテストは実行時間自体は短いため(Mockery / `Http::fake` 共にローカル処理)、`slow` グループとは別概念。「外部 API への実呼出リスク」が分類軸

### 改善対象コードメモ

- 改善対象の主要テストファイル(新規 / 拡充):
  - `tests/Unit/Services/Google/GoogleOAuthServiceTest.php` / `GoogleCalendarServiceTest.php`(新規)
  - `tests/Unit/Repositories/GeminiLlmRepositoryTest.php`(新規 or 拡充)
  - `tests/Feature/Http/Webhooks/StripeWebhookControllerTest.php`(新規 or 拡充)
  - `tests/Unit/Services/MeetingAvailabilityServiceTest.php` / `tests/Feature/UseCases/Meeting/{Store,Cancel}ActionTest.php` / `tests/Feature/Http/Settings/CoachGoogleCredentialControllerTest.php`(既存拡充)
- 既存参考パターン: 他 Feature の Mockery / `Http::fake` テスト(本リポの既存テストファイル群、`tests/Feature/UseCases/Notification/*` 等で `Notification::fake` パターンが多数存在)
- Service の `final` 判断は `T-A-03` で確定済みの前提(`GoogleCalendarService` / `GoogleOAuthService` は `final` 不採用)。本チケットではこの判断軸の文書化(クラス DocBlock のコメント等)も含める
- Mockery のクリーンアップ: `tests/TestCase.php` 等で `Mockery::close()` を `tearDown()` で呼ぶ既存パターンに準拠

## 補足

### 想定ヒアリング Q&A

| 質問 | 回答 |
|---|---|
| 外部 API ごとに使うモック手法は? | **Service(Google Calendar / Google OAuth)**: Mockery(`$this->mock(...)`)。`google/apiclient` ライブラリが独自 HTTP クライアントを使うため `Http::fake` は効かない。**Repository(Gemini)**: `Http::fake` / `Http::fakeSequence`。`Illuminate\Support\Facades\Http` 経由なので完全モック可能。**Webhook(Stripe)**: 受信側なので HMAC-SHA256 署名生成ヘルパーを自前で書く。 |
| Service の `final` は外す? | **外す**(`T-A-03` で確定済)。Mockery でスタブ化するため。Interface を切る選択肢もあるが、本プロジェクト規約では「Feature 横断のみ Interface を採用」のため Google Calendar 連携には Interface 不要。 |
| 利用側(Action / Service)のテストで Google API ライブラリを直接モックする? | **しない**。Service レベル(`GoogleCalendarService` / `GoogleOAuthService`)でモック化する。Google API ライブラリの低レベル詳細(`new Event` / `setSummary` 等)を利用側テストに混ぜない。 |
| `Http::preventStrayRequests()` はどこで呼ぶ? | `tests/TestCase.php` 等基底クラスの `setUp()` で全テスト共通で呼ぶか、外部 API 依存テストの `setUp()` で個別に呼ぶ。`Http::fake` の対象漏れ時にテストが失敗するため、API キー漏洩・レート制限消費を防ぐ最終ライン。 |
| `#[Group('external')]` はどのテストに付ける? | 外部 API モックを使うテスト(`GoogleOAuthServiceTest` / `GoogleCalendarServiceTest` / `GeminiLlmRepositoryTest` / `StripeWebhookControllerTest`)にクラスレベルで付与。CI で `--exclude-group external` を選べる状態にする。 |
| Stripe Webhook の署名生成ヘルパーはどこに書く? | テストクラス内の `private function sign(string $payload, ?int $timestamp = null): string`。`hash_hmac('sha256', $timestamp.'.'.$payload, $secret)` で Stripe 互換の HMAC-SHA256 を生成し、`'t='.$timestamp.',v1='.$signature` 形式のヘッダ値を返す。 |
| Gemini のリトライテストはどう書く? | `Http::fakeSequence()->push(['error' => 'transient'], 503)->push([...], 200)` で 1 回目 503 → 2 回目 200 のシーケンスを構成。Repository 内の手動リトライ(`usleep(100 * 1000)` + ループ)が想定通り動くことを検証。 |
| Gemini で payload 検証はどう書く? | `Http::assertSent(function ($request) { return $request->data()['systemInstruction']['parts'][0]['text'] === 'system here' && ...; })` で送信 payload の構造(`systemInstruction` / `contents` / `role` 変換 = assistant → model)を assert。 |
| 結合テスト(実 Google / 実 Gemini / 実 Stripe を叩く)は書く? | **本チケットでは扱わない**。Pre-prod 環境 / 別経路で実施するもので、ユニット / Feature テストとは責務が異なる。 |
| Pusher Broadcasting のテストも対象? | 対象外。本チケットは Google Calendar / Gemini / Stripe Webhook の 3 外部 API に絞る(対応する Story が存在するため)。Pusher は対応 Story が無いためスコープ外。 |
| カバレッジ目標は? | カバレッジ計測ツールの導入は別タスク。本チケットでは「外部 API 連携機能の異常系 + 境界系を網羅したテストが揃った状態」が完了条件。 |
| Service / Repository / Controller のモック方針が混在しているが、統一すべき? | **統一しない**。Service は Mockery / Repository は `Http::fake` / Webhook は HMAC ヘルパー が、それぞれの **責務と外部 API ライブラリの特性に最適なモック手法** であることを学ぶのが本チケットの主目的。`backend-services.md` / `backend-repositories.md` の規約をそのまま体現する。 |
