# T-A-04 モックを用いたテスト追加(外部 API 連携)

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

3 つの外部 API 連携機能(コーチの Google Calendar 連携による面談予約 / Gemini を用いた AI チャット / Stripe Webhook 受信による追加面談購入)には正常系のテストはあるが、外部 API へ実際に接続せずに失敗系・境界系を検証するテスト構造が薄い(Gemini の HTTP エラー / 空応答、Google Calendar のトークン期限切れ → リフレッシュ → リトライ、Stripe Webhook の署名検証や冪等性 等)。CI で外部 API を実際に叩くと、API キー漏洩・レート制限消費・不安定なテスト失敗を招く。

本チケットでは、3 連携それぞれに適切なモック手法(Service のスタブ化 / HTTP クライアントの fake / 受信 Webhook の署名生成ヘルパー)を選択し、正常系 + 異常系 + 境界系を網羅するテストを追加する。テスト実行時に外部 API への実通信が一切発生しない状態を担保し、外部依存テストを分離実行できるようにする。**外部 API ごとに最適なモック手法が異なり、1 つの手法で統一できない**ことを実コードで扱うのが本チケットの主眼で、実務で頻出する外部連携のテスト戦略(モック対象の選択 / 失敗パスの設計)を養う。

## 要件

### Google Calendar 連携のテスト追加

- 認可フロー(認可 URL 発行 / 認可コード交換 / revoke)と Calendar 操作(空き時間取得 / イベント作成 / イベント削除)それぞれの単体テスト
- トークン期限切れ → リフレッシュ → リトライ、リフレッシュ失敗時のフォールバック(空配列 / null)、削除済みイベントの成功扱い、を検証
- 利用側(空き枠集計 / 予約 / キャンセル / 連携設定画面)は、外部ライブラリの低レベル詳細ではなく Calendar 操作のまとまり単位でスタブ化してテスト

### Gemini AI チャットのテスト追加

- 正常系 / HTTP エラー / 空応答 / 一時エラーからのリトライ成功 / 送信内容(プロンプト構造)の検証

### Stripe Webhook のテスト追加

- 署名生成ヘルパーを用意し、有効署名 / 無効署名 / 署名欠落 / 同一イベント二重配信(冪等性)を検証

### 外部 API 実呼出の防止

- テスト実行時に未モックの外部通信が発生したらテスト失敗となる仕組み
- 外部 API 依存テストをまとめて分離実行(除外)できるグループ指定

## スコープ外

- 外部ライブラリ / API モデル / SDK のバージョンアップ・切替(テスト構造のリファクタのみ扱う)
- 実際の外部サービス(本物の Google / Gemini / Stripe)を叩く結合テストの追加(別経路で実施)
- カバレッジ計測ツールの導入(別タスク)
- Pusher Broadcasting のテスト(本チケットの対象 3 連携に含めない)
- 外部連携機能そのものの仕様変更(テスト追加のみ)
- 3 手法を 1 つに統一する抽象化(各手法の使い分けを体現することが目的)

## 受け入れ条件

- [ ] 3 つの外部 API 連携(Google Calendar 連携 / Gemini AI / Stripe Webhook)それぞれに、外部 API へ実接続しないモックテストが追加され、正常系に加えて異常系・境界系(HTTP エラー / 一時エラーからのリトライ / トークン期限切れ・リフレッシュ / 空応答 / 署名の有効・無効・欠落 / 二重配信の冪等性 等)を検証している
- [ ] 利用側(面談予約 / キャンセル / 空き枠集計 / 連携設定)のテストが、外部ライブラリの低レベル呼出ではなく外部連携のまとまり単位でスタブ化されている
- [ ] テスト実行時に外部 API への実通信が発生せず(未モック通信は遮断される)、外部 API 依存テストをグループ指定で分離実行できる

## 実装方針(参考)

> **粒度**: 業務語彙 + 技術名(変更対象ファイルパス・クラス名・メソッド名)を併記。具体的なコード片は書かない。「どのテストを新設し / どれを拡充するか」が機械的に判断できる粒度にする。

### 変更内容

- **変更対象ファイル**:
  - 新規: `tests/Unit/Services/Google/GoogleOAuthServiceTest.php`(認可フロー Service `App\Services\Google\GoogleOAuthService` の単体テスト) / `tests/Unit/Services/Google/GoogleCalendarServiceTest.php`(Calendar 操作 Service `App\Services\Google\GoogleCalendarService` の単体テスト) — いずれも Mockery で `Google\Client` をスタブ化
  - 既存拡充: `tests/Unit/Services/MeetingAvailabilityServiceTest.php` / `tests/Feature/UseCases/Meeting/StoreActionTest.php` / `tests/Feature/UseCases/Meeting/CancelActionTest.php` / `tests/Feature/Http/Settings/CoachGoogleCredentialControllerTest.php` — `$this->mock(GoogleCalendarService::class, ...)` / `$this->mock(GoogleOAuthService::class, ...)` の Service レベルモック化(確立済、境界ケースを拡充)
  - 既存(参照): `tests/Unit/Repositories/GeminiLlmRepositoryTest.php`(`Http::fake` / `Http::fakeSequence` / `Http::assertSent`、正常系 / HTTP 500 / 空応答 / 503 → 200 リトライ / payload 検証の 5 ケース)/ `tests/Feature/Http/Webhooks/StripeWebhookControllerTest.php`(`private function sign()` HMAC-SHA256 ヘルパー + 有効署名 / 無効署名 400 / 署名欠落 400 / 冪等性)
  - 基盤: `tests/TestCase.php`(または各外部依存テストの `setUp()`)に `Http::preventStrayRequests()` を組込み、各外部 API モックテストクラスに `#[Group('external')]` を付与
- **変更前**(提供 PJ で受講生が直面する状態): 外部 API 連携機能には正常系中心のテストはあるが、Google 連携 Service 自体の単体テスト(`tests/Unit/Services/Google/`)が無く、`Http::preventStrayRequests()` も `#[Group('external')]` も未組込み(未モック通信があれば外部 API を実呼出してしまうリスク + CI で外部依存テストを除外する手段がない)
- **変更後**(理想形): 上記 3 系統のモックテストが揃い、Google 連携 Service の単体テストが新設され、`Http::preventStrayRequests()` で未モック通信が遮断され、`#[Group('external')]` で `--exclude-group external` の分離実行が可能
- **採用技術と判断理由**: モック手法を外部 API ライブラリの特性で使い分ける。**Service(Google Calendar / OAuth)= Mockery**: `google/apiclient` が独自 HTTP クライアントを内部構築するため `Http::fake` が効かない → Service クラスの `final` を外して(別の Refactoring Task で対応済)Mockery でスタブ化。**Repository(Gemini)= `Http::fake`**: `Illuminate\Support\Facades\Http` 経由なので完全にスタブ可能、Mockery は不要。**Webhook(Stripe)= 署名生成ヘルパー**: 受信側なので HMAC-SHA256 署名(`hash_hmac('sha256', $timestamp.'.'.$payload, $secret)`)を自前生成してヘッダに付与。利用側のテストで `Google\Client` を直接モックしないのは、業務ロジックが「正しく Service を呼ぶか」を検証対象とし、ライブラリ低レベル詳細との結合を避けるため。`Http::preventStrayRequests()` は未モック URL への通信を例外で止める最終ライン(API キー漏洩 / レート制限消費の防止)、`#[Group('external')]` はモック構造依存でライブラリ更新に弱いテストを CI で分離実行可能にする分類軸(実行時間ではなく「外部 API への実呼出リスク」で分類)
- **テスト観点**: Gemini のリトライは `Http::fakeSequence()->push(..., 503)->push(..., 200)` で 1 回目失敗 → 2 回目成功を構成 / payload 検証は `Http::assertSent()` で `systemInstruction`・`contents`・role 変換(assistant → model)を assert / Stripe 冪等性は同一イベントの 2 回 POST で関連テーブルの INSERT が 1 件のままを確認 / Google Calendar は freebusy 正常系・期限切れリフレッシュ成功と失敗・イベント作成成功と失敗(null 返却)・削除の 410 Gone 成功扱いを網羅

## 補足

### 想定 Q&A

| 質問 | 回答 |
|---|---|
| 外部 API ごとに使うモック手法は? | Service(Google Calendar / Google OAuth)= Mockery(`google/apiclient` が独自 HTTP クライアントを使い `Http::fake` が効かないため)/ Repository(Gemini)= `Http::fake` / `Http::fakeSequence`(`Http` Facade 経由で完全モック可)/ Webhook(Stripe)= 受信側なので HMAC-SHA256 署名生成ヘルパーを自前で用意 |
| Service の `final` は外すのか? | 外す(別の Refactoring Task で対応済の前提)。Mockery はファイナルクラスをモックできないため。Interface を切る選択肢もあるが、本プロジェクトは「Interface は Feature 横断時のみ」の方針のため Google 連携には Interface 不要 |
| 利用側(予約 / キャンセル等)のテストで外部ライブラリを直接モックする? | しない。Calendar 操作 Service のまとまり単位でスタブ化する。ライブラリの低レベル詳細(イベント組み立て等)を利用側テストに混ぜると、テストが内部実装に強く結合する |
| 外部 API への実通信を防ぐ仕組みはどこに書く? | テスト基底クラスの `setUp()` で全テスト共通に呼ぶか、外部 API 依存テストの `setUp()` で個別に呼ぶ。未モック通信があればテスト失敗となり、API キー漏洩・レート制限消費を防ぐ最終ラインになる |
| 外部 API 依存テストの分離実行はどう実現する? | 外部 API モックを使うテストクラスにグループ指定を付け、CI で当該グループを除外実行できるようにする。モック構造はライブラリ更新で壊れやすく、未モック時に実通信するリスクがあるため分類しておく |
| Stripe の署名生成ヘルパーは何を生成する? | `t={timestamp},v1={signature}` 形式のヘッダ値。`signature` は `hash_hmac('sha256', {timestamp}.'.'.{payload}, {webhook_secret})` で生成し、Stripe 互換の HMAC-SHA256 署名を再現する。複数テストで使うため private メソッドに集約して DRY 化 |
| Gemini のリトライはどう書くか? | `Http::fakeSequence()->push([...], 503)->push([...], 200)` で 1 回目 503 → 2 回目 200 のシーケンスを構成し、Repository 内の手動リトライ(5xx のみ最大 2 回、429 はリトライせず即時失敗)が想定どおり動くことを検証する |
