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

## 背景・目的

通知基盤(DB + メール配信 + Web 一覧)を構築しても、外部フロントエンド / モバイル / 第三者システムから通知を取得・既読化する手段が存在しない。将来の認証付き JS フロントによる通知ポップオーバーを実装する前提として、まず素の JSON API を Basic 範囲で組み上げる必要がある。

本チケットは `routes/api.php` 配下に通知用 JSON API(一覧 / 単一既読化 / 全件既読化)を **認証なし** で新規実装する。「Web / API の分離設計」「JSON Resource による整形」「API FormRequest によるクエリ検証」「Web / API ルーティング分離」を資格 LMS ドメインで実装する位置づけ。本実装が完成すると、後続で Sanctum Cookie 認証を後付けして JS フロント表示を組み立てる土台が整う。

> **本 API のセキュリティ位置づけ**: 本チケットは **意図的に認証なし** で設計されている。第三者が任意ユーザーの通知を閲覧 / 既読化できる構造的脆弱性を含むが、**後続で Sanctum Cookie 認証を後付けして実用化する** 階段設計。

## ユーザーストーリー

- **受講生(student)として**、自分の通知が JSON 形式で取得できる経路があってほしい。なぜなら、将来 JS フロント / モバイルアプリから非同期で通知一覧を取得 + 既読化できる UX を実現するための土台が必要だから。
- **コーチ(coach)として**、自分の通知が JSON 形式で取得できる経路があってほしい。なぜなら、受講生と同じ通知基盤を共有しており、専用 UI を組み込む際の API が必要だから。
- **管理者(admin)として**、本 API は通知の受信側ロール(受講生 / コーチ)向けで、対象外。なぜなら、管理者は通知の配信側で受信側 API は使わないから。

## 要件

### 通知一覧 API

- 対象ユーザー指定(対象ユーザー ID クエリパラメータ、必須 / 既存ユーザーと整合)による自分宛通知の取得
- 「全件」「未読のみ」のタブ絞り込み(任意 / 範囲外は既定値にフォールバック)
- ページネーション(1 ページ件数指定可、任意 / 1〜100 の整数)
- 通知データ JSON の平坦化(画面表示に必要なフィールドを Resource で整形)

### 単一通知の既読化 API

- パスパラメータの通知 ID で対象を特定し既読化日時を更新
- 既読済通知への再呼出はべき等(no-op + 成功レスポンス)

### 全件既読化 API

- 対象ユーザー指定(クエリ / ボディ)による自分宛全未読通知の一括既読化
- 既読化件数をレスポンスに含める

### 共通の振る舞い

- すべて `routes/api.php` 配下、Web ルートとは完全独立(Web 側はリダイレクト、API 側は JSON のみ)
- バリデーションエラーは Laravel 標準の 422 + JSON 形式
- 通知 ID 不在は 404、対象ユーザー不在は 404 or 422(振る舞いベース)

## スコープ外

- **Sanctum Cookie 認証 / API トークン認証 / CSRF 保護** — 本チケットでは扱わない(認証付きフロント実装時に後付け)
- **CORS 設定 / 別オリジン対応** — 本チケットでは扱わない(本チケットは同一オリジン前提)
- **認可(他人通知の閲覧 / 既読化ガード)** — 認証なし API のため Policy 適用なし。本チケットでは扱わない(認証付きフロント実装時に Sanctum 認証 + Policy 適用により実用セマンティクスを確立する)
- **JS フロント側の通知ポップオーバー / モーダル / バッジ更新** — 本チケットでは扱わない
- **Pusher Broadcasting によるリアルタイム push** — 本チケットでは扱わない
- **API レートリミット** — MVP 外
- **OpenAPI / Swagger スキーマファイル生成** — MVP 外
- **通知の削除 API** — Web 同様、既読化のみ提供
- **通知配信側 API(外部システムから通知を作成する API)** — 通知発火は本 LMS の業務イベントからのみ(通知基盤の責務)

## 受け入れ条件

- [ ] `GET /api/v1/notifications` に存在ユーザーを指定すると、自分宛通知が時系列降順 + ページネーションメタ情報付きの JSON で取得でき、各通知行に通知種別 / タイトル / プレビュー / 遷移先ルート / 既読化日時等の整形済フィールドが含まれる
- [ ] 「全件」「未読のみ」のタブ指定で絞り込みでき、1 ページの件数指定でページ区切りが切り替わる
- [ ] `POST /api/v1/notifications/{notification}/read` で対象通知の既読化日時が現在時刻で更新され、JSON で成功レスポンスが返る(既読済通知への再呼出はべき等で既読化日時を上書きしない)
- [ ] `POST /api/v1/notifications/read-all` で対象ユーザーの全未読通知が一括既読化され、既読化件数を含む JSON が返る(対象 0 件のときは件数 0 を返しエラーにしない)
- [ ] 一覧 / 全件既読化 API にて、リクエストパラメータにバリデーションが行われ、ルール違反時に 422 + JSON 形式の日本語エラーレスポンスが返るか:
  - 対象ユーザー ID (両 API 対象、全件既読化はクエリ / ボディどちらも可): 必須 / 既存ユーザーと整合
  - タブ識別子 (一覧 API): 任意 / 「全件」「未読のみ」のいずれか
  - 1 ページ件数 (一覧 API): 任意 / 1〜100 の整数
  - ページ番号 (一覧 API): 任意 / 1 以上の整数
- [ ] 存在しない通知 ID を指定したリクエストは 404 + JSON エラーレスポンスが返る
- [ ] 本 API による既読化操作の結果は Web 側通知一覧と同期する(API 経由既読化 → Web で既読扱い、逆も同様)
- [ ] 本チケットの機能に対するテスト (Unit / Feature 等) が実装されている

## 実装方針(参考)

> **本セクションは「参考」、受講生ごとに異なる実装を許容**(AC を満たせば実装手段は問わない)。ただし **「(必須)」マーカー付きサブセクション**(インターフェース)は AC・採点・動作確認のベース、ここに記載した内容を正確に実装する。

### インターフェース(必須)

| HTTP | パス | 認可 | 振る舞い |
|---|---|---|---|
| GET | `/api/v1/notifications?user_id={ulid}&tab={全件\|未読のみ}&per_page={1-100}&page={N}` | 認可なし(任意 ID 指定可、構造的脆弱性は認証付きフロント実装時に解消) | 通知一覧 JSON(時系列降順 + ページネーション meta 付き Resource Collection) |
| POST | `/api/v1/notifications/{notification}/read` | 認可なし(任意通知 ID 指定可) | 単一既読化、成功時 200 + 成功 JSON(既読済はべき等で no-op) |
| POST | `/api/v1/notifications/read-all`(body or query `user_id={ulid}`) | 認可なし | 自分宛全未読通知の一括既読化、成功時 200 + 既読化件数を含む JSON |

**ルート規約**: ルート名は `api.v1.notifications.*`(`Route::prefix('v1')->name('api.v1.')` で生成)。Web ルート(`notifications.*`)とは完全独立 + 同じパス階層を意図的に揃え、後続で API 側に JS を組み込むときの整合性を確保。

**ルート登録順序**: `read-all` を `{notification}/read` より先に登録(`read-all` が `{notification}` 動的セグメントに食われない並び順)。

**ミドルウェア**: なし(認証なし API のため)。Laravel 11+ では `bootstrap/app.php` の `withRouting` で `api: __DIR__.'/../routes/api.php'` を有効化。

### データモデル

既存の `notifications` テーブル + `BaseNotification` 抽象基底 + 4 通知種別クラスを本チケットで再利用。本チケットでテーブル / Model / Enum の新規追加なし。

### コンポーネント

**Controller** (`app/Http/Controllers/Api/V1/`)
- `NotificationController` — API 受付 → Action 呼出 → Resource 整形 → JSON 返却(`index` / `markAsRead` / `markAllAsRead`)

**FormRequest** (`app/Http/Requests/Api/V1/Notification/`)
- `IndexRequest` — 対象ユーザー ID / タブ識別子 / ページ番号 / 1 ページ件数の検証(MarkAllAsRead 用は受講生判断、`IndexRequest` の `user_id` ルールを流用する設計も可)

**Action** (`app/UseCases/Notification/`、※ 模範解答 PJ で API 固有 Action を分離、Basic 受講生は Controller 内完結も可)
- `Api\IndexAction` — 対象ユーザー解決 + タブフィルタ + ページネーション
- `Api\MarkAllAsReadAction` — 自分宛未読通知の一括既読化 + 既読化件数返却
- `MarkAsReadAction` — Web / API 共有(既存実装を Resource Binding 経由で同 Action を呼ぶ)

**Resource** (`app/Http/Resources/Api/V1/`)
- `NotificationResource` — 通知データ JSON を平坦化、JS フロントが扱いやすい安定スキーマで返却

**Routes** (`routes/api.php`)
- `v1` group に通知ルート 3 本を登録(`read-all` を `{notification}/read` より先)

**連携先**(本チケットで変更しない、既存提供)
- `app/Notifications/BaseNotification.php` + 4 通知種別クラス / `app/Models/User.php`(`Notifiable` trait) / `notifications` テーブル

### 異常系

**入力検証**(FormRequest クラス名 + ルール記法):

- 一覧クエリ FormRequest (`Api\V1\Notification\IndexRequest`):
  - `user_id`: `required` / `ulid` / `exists:users,id`
  - `tab`: `nullable` / `string` / `in:全件,未読のみ`
  - `per_page`: `nullable` / `integer` / `min:1` / `max:100`
  - `page`: `nullable` / `integer` / `min:1`
- 全件既読化 FormRequest(該当時、`Api\V1\Notification\MarkAllAsReadRequest` 新規作成 or `IndexRequest` の `user_id` ルールを流用):
  - `user_id`: `required` / `ulid` / `exists:users,id`
- 単一既読化: パスパラメータの通知 ID で Route Model Binding が成立(不在時 Laravel 標準 404)、入力検証は不要

**業務例外**:

- 既読済通知への単一既読化: べき等(no-op + 成功レスポンス、`read_at` は上書きしない)
- 全件既読化で対象 0 件: 既読化件数 0 + 成功レスポンス(エラーにしない)
- 対象ユーザー不在: 422(`exists:users,id`) or 404(Controller 内 `findOrFail`)— 受講生判断、振る舞いベースで「不在ユーザーで取得を試みたらエラー」が伝われば良い
- 通知 ID 不在: Route Model Binding により Laravel 標準 404 JSON

### 設計判断

- **Web / API の完全分離**: `routes/api.php` 配下の `Api\V1\` namespace で Controller を切り、Web 側との完全分離 + 将来 v2 / v3 が登場したときの段階移行を見据えた配置
- **Resource クラスでのレスポンス整形**: `Api\V1\NotificationResource` で通知データ JSON を平坦化、JS フロントが扱いやすい安定スキーマを返す(`type` FQCN / 業務識別子 / タイトル / プレビュー / 遷移先ルート + パラメータ / `read_at` / `created_at` を ISO 8601 で整形)。通知データ JSON が空 / キー欠落時はフォールバック値で防御
- **API パスを Web ルートと意図的に揃える**: `/notifications` と `/api/v1/notifications` の対称性で、後続で API 側に JS を組み込むときの整合性を確保
- **べき等性の単一実装**: Web / API 両側で同じ `Notification\MarkAsReadAction` を共有(既存実装)、既読化日時の上書き防止ロジックも 1 箇所に集約
- **構造的脆弱性の意図的許容**: 本チケットは認証なし API のため、第三者が任意の `user_id` / 通知 ID を URL 指定すると他者通知を閲覧 / 既読化できる。これは「Basic で API 構造を組み、Advance で Sanctum 認証を後付けする階段設計」のため意図的に残し、後続で `auth:sanctum` + Policy 適用で実用化する
- **ルート登録順序の意図**: `read-all` を `{notification}/read` より先に登録することで動的セグメントに食われない並び順を確保。順序ミスは 404 / 別レスポンスの原因になる
- **テスト観点**: 「API レスポンスのページネーション meta」「タブ識別子バリデーション(範囲外で 422)」「既読化のべき等性(既読済再呼出で `read_at` 上書きしない)」「全件既読化の 0 件パターン(エラーにせず件数 0 を返す)」が本チケット固有の Feature テスト観点

## 補足

### 想定 Q&A

| 質問 | 回答 |
|---|---|
| 認証なしで本当に運用するの? | 本チケット範囲では認証なし。本質的なセキュリティは後続で Sanctum Cookie 認証 + Policy 適用で確立する。Basic は「実務で頻出する API 実装の型を資格 LMS ドメインで実装する」位置づけ |
| ユーザー特定はクエリパラメータ? ボディ? | 一覧は GET なのでクエリパラメータ、全件既読化は POST なのでボディ / クエリどちらでも可。単一既読化はパスパラメータの通知 ID で対象特定するため対象ユーザー指定不要 |
| 対象ユーザー ID を URL 自由指定にすると他人通知が見れる? | 見える。それが本チケットで意図的に許容しているセキュリティ的トレードオフ。後続で `auth:sanctum` + Policy 適用すると認証ユーザー本人の通知のみ取得 / 既読化に制限される |
| 対象ユーザー不在時のレスポンスは 422 / 404? | 振る舞いベースで「不在ユーザーで取得を試みたらエラー」が伝われば良い。`exists:users,id` バリデーションを通せば 422、Controller 内 `findOrFail` を使えば 404。受講生判断 |
| 通知 ID 不在時のレスポンスは? | Route Model Binding で Laravel 標準 404 JSON が自動的に返る |
| タブ識別子の範囲外を渡したら? | 422 + JSON エラー |
| 1 ページ件数の上限は? | 100。0 以下 or 101 以上は 422 |
| ページネーションのレスポンス形式は? | Laravel 標準の paginated JSON 形式(paginator を Resource Collection でラップした `data` + `links` + `meta` を含む形式) |
| 既読済通知への単一既読化の振る舞いは? | べき等 + 成功レスポンス。既読化日時は上書きしない(「最初にいつ読んだか」を永続化したい仕様) |
| 全件既読化で 0 件のときは? | 既読化件数 0 + 成功レスポンス。エラーにはしない(べき等性) |
| Web 側と API 側で既読状態は同期する? | 同期する。両者とも同じ通知テーブルの既読化日時を更新するため、片方で既読化したらもう片方でも既読として返る |
| API レートリミットは? | 本チケット範囲外。`throttle` Middleware 適用は MVP 外 |
| CORS はどうする? | 本チケット範囲外。同一オリジン前提で動作確認する。BE-FE 別オリジン構成を見据えた CORS / Sanctum stateful 設定は後続で扱う |
| レスポンスの遷移先ルートを JS フロントでどう使う? | 後続で遷移先ルート + パラメータから JS が遷移先 URL を組み立てて、通知行クリック時に画面遷移を実装する。本チケットではレスポンスにそのまま含める(整形まで) |
| 作成日時のフォーマットは? | ISO 8601(例: `2026-05-25T12:34:56+09:00`)。Resource クラス内で日時整形メソッドを呼ぶ |
| ルート登録順(`read-all` を先に)を間違えると? | `read-all` が `{notification}` 動的セグメントに食われ、本来 200 を返すべきリクエストが 404 や別レスポンスになる |
