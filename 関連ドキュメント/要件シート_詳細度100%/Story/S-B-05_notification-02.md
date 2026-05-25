# S-B-05 通知 JSON API(認証なし)

## メタ情報

| 項目 | 値 |
|---|---|
| チケット ID | `S-B-05` |
| Feature 連番 | `notification-02` |
| Feature | notification |
| 種別 | Story |
| サブカテゴリ | 新規機能の構築 |
| 難易度 | Basic |
| 工数 (h) | 6 |
| 依存チケット | `S-B-04` |

## 概要

`S-B-04` で実装した通知基盤の上に、指定ユーザーの通知一覧 / 1 件既読化 / 全件既読化を JSON で返す **認証なし JSON API** を新規実装する。`routes/api.php` 配下に v1 名前空間で配置し、ContactForm / BookShelf Basic で経験した「Web/API 分離 + Resource + API FormRequest + テスト」のパターンを資格 LMS ドメインに置き換えて踏襲する。

## 背景・目的

- **現状の問題**: `S-B-04` で通知基盤(DB + メール配信 + Web 一覧)を作っても、外部のフロントエンド / モバイル / 第三者システムから通知を取得・既読化する手段が存在しない。Advance フェーズの JS フロントによる通知ポップオーバー(`S-A-05`)を実装する前提として、まず素の JSON API を Basic 範囲で組み上げる必要がある。
- **達成したい状態**: `routes/api.php` 配下に通知用 JSON API(一覧 / 1 件既読化 / 全件既読化)が存在し、curl / Postman / 任意の HTTP クライアントから JSON 形式で通知を取得・操作できる状態。Web 一覧と同等の振る舞いを認証なしで再現できる。
- **価値・優先度**: 本チケットは Pro 生として **「Web / API の分離設計」「JSON Resource による整形」「Api FormRequest によるクエリ検証」「Web/API ルーティング分離」** を資格 LMS ドメインで再演習する位置づけ。本実装が完成すると `S-A-05` で Sanctum Cookie 認証を後付けして JS フロント表示を組み立てる土台が整う。

> **本 API のセキュリティ位置づけ(必読)**: 本チケットは **意図的に認証なし** で設計されている(BookShelf Basic の「公開 API CRUD (認証なし)」と同じ教材パターン)。第三者が任意ユーザーの通知を閲覧 / 既読化できる構造的脆弱性を含むが、**`S-A-05` で Sanctum Cookie 認証(`auth:sanctum`)を後付けして実用化する** 階段設計。受講生が「認証なしで API を成立させる練習」→「認証を後付けして本番セマンティクスに整える練習」を 2 段階で経験する目的。

## ユーザーストーリー

- **JS フロントエンド開発者として(`S-A-05` の前段)**、curl / fetch で叩ける通知 JSON API が欲しい。なぜなら、後段でモーダル展開 / ポップオーバー UI を組み立てる前にエンドポイントの振る舞い検証を完了させたいから。
- **モバイルアプリ開発者として(将来想定)**、JSON で通知一覧と既読化操作ができる API が欲しい。なぜなら、ネイティブアプリから同じ通知データを参照したいから。
- **受講生(student、API 学習者として)として**、認証なし JSON API CRUD パターン(Web/API 分離 + Resource + API FormRequest + テスト)を資格 LMS ドメインで再演習したい。なぜなら、ContactForm / BookShelf で経験した型を別ドメインで定着させたいから。
- **コーチ / 管理者として**、本 API の振る舞いを画面操作と独立して検証できる経路が欲しい。なぜなら、通知発火と既読化の動作を automated test / 手動 curl で再現可能にしたいから。

## やること

### 通知一覧 API

- **エンドポイント**: `GET /api/v1/notifications`、認証なし
- **クエリパラメータ**: `user_id`(対象ユーザー ID、必須)/ `tab`(`all` / `unread` のいずれか、任意、デフォルト `all`)/ `per_page`(1〜100、任意、デフォルト 20)/ `page`(任意、デフォルト 1)
- **レスポンス**: 200 + JSON(ページネーション付きの Resource Collection、各通知は `id` / `type` / `notification_type` / `title` / `message` / `link_route` / `link_params` / `read_at` / `created_at` を含む)
- **対象ユーザーが存在しない場合**: 404
- **クエリバリデーション失敗時**: 422 + JSON エラーレスポンス
- **並び順**: 通知の作成日時 降順
- **「未読のみ」フィルタ**: `tab=unread` で未読(`read_at = NULL`)のみに絞り込み、`all` または未指定で全件

### 単一通知の既読化 API

- **エンドポイント**: `POST /api/v1/notifications/{notification}/read`、認証なし
- **振る舞い**: パスパラメータの通知 ID で対象を特定し、`read_at = now()` を UPDATE(既読済なら no-op = べき等)
- **レスポンス**: 200 + JSON `{"status": "ok"}`
- **通知が存在しない場合**: 404
- **既読済の場合**: 200 を返す(no-op で副作用なし)

### 全件既読化 API

- **エンドポイント**: `POST /api/v1/notifications/read-all`、認証なし
- **リクエストボディ / クエリパラメータ**: `user_id`(対象ユーザー ID、必須)
- **振る舞い**: 対象ユーザーの全未読通知を一括既読化、`read_at = now()` で UPDATE
- **レスポンス**: 200 + JSON `{"status": "ok", "updated": <更新件数>}`
- **対象ユーザーが存在しない場合**: 404
- **既読化対象 0 件の場合**: 200 + `"updated": 0`(エラーではない)

### 共通の振る舞い

- すべて `routes/api.php` 配下、`api.v1.notifications.*` ルート名(prefix `v1` + name prefix `api.v1.` 規約)
- Web ルート(`routes/web.php` の `notifications.*`)とは完全に独立した別エンドポイント。Web 側の既読化(`S-B-04`)はリダイレクトを返す HTML フロー、API 側は JSON のみを返す
- バリデーションエラー時は Laravel 標準の 422 + `{"message": "...", "errors": {...}}` 形式 JSON
- 404 は Laravel 標準の `{"message": "..."}` 形式 JSON

## やらないこと

- **Sanctum Cookie 認証 / API トークン認証 / CSRF 保護** — `S-A-05` で `auth:sanctum` Middleware 適用と CSRF cookie 取得フロー(`/sanctum/csrf-cookie`)を後付けする
- **CORS 設定 / 別オリジン対応** — `S-A-05` で BE-FE 別オリジン構成を見据えた CORS / Sanctum stateful 設定を扱う(本チケットは同一オリジンで完結)
- **認可(他人通知の閲覧 / 既読化ガード)** — 認証なし API のため Policy 適用なし。`S-A-05` で Sanctum 認証 + `NotificationPolicy` 適用により実用セマンティクスを確立する
- **JS フロントエンド側の通知ポップオーバー / モーダル / バッジ更新** — `S-A-05` で fetch + DOM 操作を実装する
- **Pusher Broadcasting によるリアルタイム push** — `S-A-05` で Pusher を有効化(本チケットの API はポーリング向けの REST のみ)
- **API レートリミット** — MVP 外
- **OpenAPI / Swagger スキーマファイル生成** — MVP 外、必要に応じて将来追加
- **通知の論理削除 / 物理削除 API** — Web 同様、既読化のみ提供
- **通知配信側 API(外部システムから通知を作成する API)** — 通知発火は本 LMS の業務 Action からのみ(`S-B-04` の責務)

## 受け入れ条件

- [ ] **一覧 - 成功レスポンス**: `GET /api/v1/notifications?user_id={存在する user_id}` で 200 + JSON Resource Collection が返る
- [ ] **一覧 - 並び順**: 通知が作成日時 降順で並ぶ
- [ ] **一覧 - ページネーション**: `per_page=10` で 10 件 / ページに区切られ、`page=2` で 2 ページ目を返す。レスポンスにはページネーションのメタ情報(`current_page` / `last_page` / `total` / `per_page` 等)が含まれる
- [ ] **一覧 - tab フィルタ**: `tab=unread` で `read_at = NULL` の通知のみ返る、`tab=all` または未指定で全件返る
- [ ] **一覧 - レスポンスフィールド**: 各通知行に `id` / `type` / `notification_type` / `title` / `message` / `link_route` / `link_params` / `read_at` / `created_at` のキーが含まれる
- [ ] **一覧 - 対象ユーザー不在**: `user_id` が存在しないユーザー ID のとき 404 JSON が返る
- [ ] **一覧 - バリデーション失敗**: `user_id` 未指定 / `tab` が `all`/`unread` 以外 / `per_page` が 0 以下 or 101 以上 のとき 422 + JSON エラーレスポンスが返る
- [ ] **単一既読化 - 成功**: `POST /api/v1/notifications/{未読 notification id}/read` で 200 + `{"status": "ok"}` が返り、対象通知の `read_at` が現在時刻で更新される
- [ ] **単一既読化 - 既読済 no-op**: 既読済通知に対して再度呼んでも 200 + `{"status": "ok"}` が返り、`read_at` が上書きされない(べき等性、※下記 Q&A 参照)
- [ ] **単一既読化 - 通知不在**: 存在しない notification ID のとき 404 JSON が返る
- [ ] **全件既読化 - 成功**: `POST /api/v1/notifications/read-all` + `user_id={存在 user_id}` で 200 + `{"status": "ok", "updated": <件数>}` が返り、対象ユーザーの全未読通知の `read_at` が現在時刻で更新される
- [ ] **全件既読化 - 0 件**: 対象ユーザーの未読通知が 0 件のとき 200 + `{"status": "ok", "updated": 0}` が返る(エラーにはならない)
- [ ] **全件既読化 - 対象不在**: `user_id` が存在しないユーザー ID のとき 404 JSON が返る
- [ ] **API / Web 独立**: 本 API の操作で Web 側(`S-B-04`)の通知一覧の表示状態が同期する(既読化 API → Web 一覧で既読扱い)、逆も同様

## 実装方針

> **参考設計の一例**。受け入れ条件を満たせれば実装手段は問わない。受講生は提供 PJ コード + ヒアリングで自分の設計を組み立てる。

### 主要 URL

| メソッド | パス | 振る舞い |
|---|---|---|
| GET | `/api/v1/notifications?user_id={ulid}&tab={all\|unread}&per_page={1-100}&page={N}` | 通知一覧 JSON、200 + Resource Collection(ページネーション meta 付き) |
| POST | `/api/v1/notifications/{notification}/read` | 単一既読化、200 + `{"status":"ok"}` / 通知 ID 不在で 404 |
| POST | `/api/v1/notifications/read-all`(body or query `user_id={ulid}`) | 一括既読化、200 + `{"status":"ok","updated":N}` / user_id 不在で 404 |

route 名は `api.v1.notifications.index` / `api.v1.notifications.markAsRead` / `api.v1.notifications.markAllAsRead`(`Route::prefix('v1')->name('api.v1.')->group(...)` で生成)。

### データモデル

> **既存テーブル**(`S-B-04` で作成済)。本チケットは新規テーブル追加なし。`notifications` テーブルの読み取り + `read_at` UPDATE のみ。

リソースモデル: Laravel 標準 `Illuminate\Notifications\DatabaseNotification`(本チケットでは Route Model Binding で `{notification}` を自動解決)。`notifiable_id` の検索キーで対象ユーザーの通知を絞り込む。

### バリデーション

`App\Http\Requests\Api\V1\Notification\IndexRequest`(一覧):

| 入力項目 | ルール | 推奨エラーメッセージ例 |
|---|---|---|
| user_id | required / ulid / exists:users,id | 対象ユーザー ID は必須です。<br>対象ユーザーが見つかりません。 |
| tab | nullable / string / in:all,unread | tab は `all` または `unread` を指定してください。 |
| per_page | nullable / integer / min:1 / max:100 | per_page は 1〜100 の整数で指定してください。 |
| page | nullable / integer / min:1 | page は 1 以上の整数で指定してください。 |

`App\Http\Requests\Api\V1\Notification\MarkAllAsReadRequest`(全件既読化):

| 入力項目 | ルール | 推奨エラーメッセージ例 |
|---|---|---|
| user_id | required / ulid / exists:users,id | 対象ユーザー ID は必須です。<br>対象ユーザーが見つかりません。 |

単一既読化はパスパラメータの `{notification}` で Route Model Binding が成立する(失敗時 Laravel 標準 404)、FormRequest は不要。

### 認可設計

**Policy**: 適用しない(認証なし API のため認可ガードがない)。`S-A-05` で `auth:sanctum` Middleware 適用 + `NotificationPolicy::view` / `update` 適用により他人通知の閲覧 / 既読化を 403 で拒否する後付け設計。

`authorize()` メソッドは `return true` で全許可(BookShelf Basic API 流)。

### API 仕様

| エンドポイント | リクエスト | レスポンス(200) | 認証 |
|---|---|---|---|
| GET /api/v1/notifications | query: user_id(ulid)/ tab(string)/ per_page(int)/ page(int) | `{"data": [{...resource}], "links": {...}, "meta": {"current_page":1,"last_page":N,"per_page":20,"total":M}}` | なし |
| POST /api/v1/notifications/{notification}/read | path: notification (ulid) | `{"status":"ok"}` | なし |
| POST /api/v1/notifications/read-all | body / query: user_id(ulid) | `{"status":"ok","updated":N}` | なし |

リソース整形(`App\Http\Resources\Api\V1\NotificationResource`):

```json
{
  "id": "01HXXXX...",
  "type": "App\\Notifications\\Chat\\ChatMessageReceivedNotification",
  "notification_type": "chat_message_received",
  "title": "佐藤太郎 さんから新着メッセージ",
  "message": "今日の宿題について質問があります...",
  "link_route": "chat.show",
  "link_params": {"room": "01HYYYY..."},
  "read_at": null,
  "created_at": "2026-05-25T12:34:56+09:00"
}
```

### テスト観点

| 種別 | 観点 |
|---|---|
| Feature(API) | 一覧の 200 / 422(バリデーション失敗)/ 404(user_id 不在)/ 並び順 / ページネーション meta / tab フィルタ / レスポンスフィールド網羅 / 単一既読化の 200(`read_at` 更新)/ 既読済再呼び出しの no-op + 200 / 単一既読化の 404(通知不在)/ 全件既読化の 200 + `updated` 件数 / 全件既読化の 0 件レスポンス / 全件既読化の 404(user_id 不在) |
| Feature(API/Web 連動) | API 経由既読化後に Web 通知一覧(`S-B-04`)で既読扱いになる連動性 / Web 経由既読化後に API レスポンスで `read_at` が反映される連動性 |
| Unit(Resource) | `NotificationResource::toArray()` が `data` JSON から `notification_type` / `title` / `message` / `link_route` / `link_params` を正しく平坦化する / `data` が空配列のときのフォールバック(`message` を空文字に / `title` を「通知」固定 等) |

### アーキテクチャ判断

> **Basic 範囲制約**: 本チケットは ContactForm / BookShelf Basic の「公開 API CRUD」パターン踏襲。API Controller / FormRequest / Resource は教材範囲内。Action / Service クラスの採用は受講生判断(Controller 内完結も可)。Sanctum 認証 / CSRF / CORS は Advance 範囲(`S-A-05`)で後付け。

- **採用技術**: Laravel 標準 API Resource(`JsonResource`)+ Controller(受講生判断で Action 分割可)+ Api FormRequest + Route Model Binding(`DatabaseNotification` 自動解決)+ ページネーション(`LengthAwarePaginator`)
- **設計判断**:
  1. **Web/API 分離**: `routes/web.php`(`S-B-04` の HTML フロー、`notifications.*`)と `routes/api.php`(本チケット、`api.v1.notifications.*`)で **完全に独立した Controller / Resource / FormRequest** を持つ。Web 側は `App\Http\Controllers\NotificationController`、API 側は `App\Http\Controllers\Api\V1\NotificationController` という namespace 分離(`backend-http.md`「領域別 namespace」に従う Webhooks / Api の特殊カテゴリ扱い)
  2. **Resource クラス**: `App\Http\Resources\Api\V1\NotificationResource` を作り、`DatabaseNotification` の `data` JSON を平坦化して画面表示に必要なフィールドを返す。本 Resource は BookShelf Basic の `BookResource` と同型パターン
  3. **ルート定義**: `Route::prefix('v1')->name('api.v1.')->group(...)` で `api.v1.notifications.index` / `markAsRead` / `markAllAsRead` の 3 ルートを定義。Web 側の既読化 URL は `POST /notifications/{notification}/read`、API 側は `POST /api/v1/notifications/{notification}/read` で **意図的にパスを揃え** て、`S-A-05` で API 側にフロント JS を組み込むときに「Web URL を JS で叩く錯覚」を防ぐ
  4. **既読化の no-op 化**: Web 側(`S-B-04` の `MarkAsReadAction`)と同じ Action を共有してもよい(`if ($notification->read_at !== null) return;`)。API 側 Action として `App\UseCases\Notification\Api\IndexAction` / `Api\MarkAllAsReadAction` を分けるのは「ページネーション per_page を可変にする」「`updated` 件数を返す」等の API 固有要件が出る場合のみ。Basic 受講生は Controller 内で Web 側 Action を流用する実装でも振る舞いが満たせれば OK
  5. **`exists:users,id` バリデーション**: `user_id` 不在を 422 ではなく 404 として返したい場合は、FormRequest で `exists` を入れる代わりに、Controller 内で `User::query()->findOrFail($user_id)` を使う方法もある。本チケットでは「422 か 404 か」は受講生判断(振る舞い目線では「不在ユーザーの一覧を取りに行ったらエラー」が伝われば良い)
  6. **既読化ルートの順序**: `Route::post('notifications/read-all', ...)` を `Route::post('notifications/{notification}/read', ...)` よりも **先に登録** する(`read-all` が `{notification}` の動的セグメントに食われない並び順)
  7. **同期送信 / Queue 不使用**: 本 API は単純な SELECT / UPDATE のみで Mail / 重い処理を伴わないため、Queue 化不要

### 関連ファイルメモ

- `app/Http/Controllers/Api/V1/NotificationController.php`(`index` / `markAsRead` / `markAllAsRead`)
- `app/UseCases/Notification/Api/{Index,MarkAllAsRead}Action.php`(※ 模範解答 PJ では API 固有の Action として分離、Basic 受講生は Controller 内で Web 側 Action 流用も可)
- `app/UseCases/Notification/MarkAsReadAction.php`(Web 側と共有可能)
- `app/Http/Resources/Api/V1/NotificationResource.php`
- `app/Http/Requests/Api/V1/Notification/{Index,MarkAllAsRead}Request.php`(`MarkAllAsRead` は受講生判断で作成、Index のみで完結する設計も可)
- `routes/api.php` の `v1` グループに `Route::get('notifications', ...)` + `Route::post('notifications/read-all', ...)` + `Route::post('notifications/{notification}/read', ...)` を追加
- 既存 `routes/api.php` を **新規プロジェクトの場合は Laravel デフォルトの作成** が必要(Laravel 11+ では `bootstrap/app.php` の `withRouting` で `api: __DIR__.'/../routes/api.php'` を有効化)
- 類似パターン参考: ContactForm の認証なし POST API / BookShelf Basic の公開 CRUD API
- 連携先(参考、変更しない): `app/Notifications/BaseNotification.php`(`S-B-04` 同梱)/ `app/Models/User.php`(`Notifiable` trait)

## 補足

### 想定ヒアリング Q&A

| 質問 | 回答 |
|---|---|
| 認証なしで本当に運用するの? | 本チケットの範囲では認証なし。本質的なセキュリティは `S-A-05` で Sanctum Cookie 認証 + `auth:sanctum` Middleware で確立する。Basic は「API 実装の型を資格 LMS ドメインで再演習する」教材的位置づけ |
| ユーザー特定は URL クエリパラメータ? それともリクエストボディ? | 一覧は GET なので クエリパラメータ(`?user_id=...`)、全件既読化は POST なのでボディ / クエリどちらでも可(`MarkAllAsReadRequest` で受け取れば Laravel が両対応する)。単一既読化はパスパラメータの `{notification}` で対象を特定するため `user_id` 不要 |
| `user_id` を URL 自由指定にすると他人通知が見れる? | 見えます。それが本チケットで意図的に許容しているセキュリティ的トレードオフ。`S-A-05` で `auth:sanctum` + `NotificationPolicy::view` を適用すると、認証ユーザー本人の通知のみ取得 / 既読化に制限される |
| `user_id` 不在時のレスポンスは 422 / 404 のどっち? | 振る舞いベースで「不在ユーザーの一覧を取りに行ったらエラー」が伝われば良い。`exists:users,id` を FormRequest に書けば 422、`findOrFail` で書けば 404。受講生判断 |
| `notification_id` 不在時のレスポンスは? | Route Model Binding(`DatabaseNotification $notification`)で Laravel 標準 404 JSON が自動的に返る |
| `tab=unread` 以外の値を渡したらどうなる? | `in:all,unread` バリデーションで 422 + JSON エラー |
| `per_page` の上限は? | 100。0 以下 or 101 以上で 422 |
| ページネーションのレスポンス形式は? | Laravel 標準の `LengthAwarePaginator` を `JsonResource::collection()` でラップした形式(`data` + `links` + `meta` を含む)。BookShelf Basic 公開 API と同じ |
| 既読済通知に対する単一既読化の振る舞いは? | no-op + 200。`read_at` は上書きしない(既読化日時を最初に既読化した時点に固定する仕様)。再既読化で日時が動くと「最初にいつ読んだか」が消えるため |
| 全件既読化で 0 件のときは? | 200 + `{"status":"ok","updated":0}`。エラーにはしない(べき等性) |
| Web 側(`S-B-04`)と API 側で既読状態は同期する? | 同期する。両者とも同じ `notifications.read_at` カラムを更新するため、片方で既読化したらもう片方でも既読として返る |
| API レートリミットは? | 本チケット範囲外。`throttle` Middleware 適用は MVP 外 |
| CORS はどうする? | 本チケット範囲外。同一オリジン前提で動作確認する。`S-A-05` で BE-FE 別オリジン構成を見据えた CORS / Sanctum stateful 設定を扱う |
| レスポンスの `link_route` を JS フロントでどう使う? | `S-A-05` で `link_route` + `link_params` から JS が遷移先 URL を組み立てて、通知行クリック時に `window.location` を切り替える設計。本チケットではレスポンスにそのまま含める(整形まで) |
| `created_at` のフォーマットは? | ISO 8601(例: `2026-05-25T12:34:56+09:00`)。Resource クラス内で `?->toIso8601String()` を呼ぶ |
| API 用 Controller を `Api/V1/` namespace に置く理由は? | Web 側 Controller(`NotificationController`)との完全分離 + 将来 v2 / v3 が登場したときの段階移行を見据えた配置。`backend-http.md`「領域別 namespace」の Webhooks / Auth と同じ特殊カテゴリ扱い |
| Action を作るべきか、Controller 内完結で OK か? | Basic 受講生判断。Controller 内で `User::findOrFail($user_id)->notifications()->paginate()` を書く実装でも振る舞いを満たす。Action 分割(`Notification\Api\IndexAction` 等)はチャレンジ枠 |
