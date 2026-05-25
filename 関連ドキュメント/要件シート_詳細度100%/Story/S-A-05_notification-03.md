# S-A-05 Sanctum Cookie 認証追加 + JS フロント通知表示

## メタ情報

| 項目 | 値 |
|---|---|
| チケット ID | `S-A-05` |
| Feature 連番 | `notification-03` |
| Feature | notification |
| 種別 | Story |
| サブカテゴリ | 既存機能の拡張 |
| 難易度 | Advance |
| 工数 (h) | 13.5 |
| 依存チケット | `S-B-05`(通知 JSON API(認証なし)) |

## 概要

提供 PJ で受講生 / コーチが利用している通知機能のうち、認証なしで作られている JSON API(`S-B-05` で実装済)に対して Sanctum Cookie 認証を後付けし、TopBar のベルアイコンにフックして「ベルクリックで API を非同期取得 → 通知ポップオーバーで動的表示」する JS フロントエンドを新規実装する。ポップオーバーには全件 / 未読タブ + 全件既読ボタン + ローディング表示 + フッターから全通知ページへの導線を備える。

## 背景・目的

- **現状の問題**: 提供 PJ では通知ベル UI と通知一覧画面(`/notifications` 静的ページ)はあるが、ベルクリックで一覧画面に遷移するだけで「ちょい見・即既読化」のスナップ動線がない。学習中の受講生は通知の有無を知るためだけに画面遷移が必要で UX が断絶している。さらに通知 JSON API が認証なしで公開されており、他人の通知 ID を直叩きすると個人通知が露出するセキュリティ上のリスクも残っている。
- **達成したい状態**: Stripe / Jira / GitHub と同じ業界標準パターン(ベル横アンカーのポップオーバー)で、画面遷移なしに最新 20 件を確認・既読化できる。バックグラウンドでは Sanctum Cookie 認証で保護された API を JS フェッチが叩き、第三者からの API 直叩きアクセスは 401 で防がれる。本格的な BE-FE 別オリジン構成への参画体験を、同一オリジン Cookie 認証で模擬する。
- **価値・優先度**: 受講生学習体験のスナップアウト改善 + 通知 API のセキュリティ強化 + Pro 生として実務で頻出する Sanctum SPA Cookie 認証 / `fetch` + `credentials: 'include'` / CSRF Cookie / X-XSRF-TOKEN ヘッダ / Resource クラスでの API レスポンス整形 を扱う Advance スコープの代表チケット

## ユーザーストーリー

- **受講生(student)として**、TopBar のベルアイコンをクリックしたら通知一覧画面に遷移せず、その場で最新 20 件を確認したい。なぜなら、学習を中断せずに「通知を見るだけ見て学習に戻る」流れを実現したいから。
- **受講生として**、ポップオーバーから 1 件クリックして既読化と該当画面遷移を同時にしたい。なぜなら、二段階の操作(まず一覧で開いてから個別行をクリック)が冗長だから。
- **受講生として**、ポップオーバーで全件既読ボタンを押すと一気に未読バッジが 0 になる体験を期待する。なぜなら、未読が溜まったときの一括処理を素早く済ませたいから。
- **コーチ(coach)として**、同じ通知ポップオーバーを使う。なぜなら、受講生と同じ通知基盤を共有しており、専用 UI を覚えたくないから。
- **管理者(admin)として**、本機能の対象外。なぜなら、管理者は通知の受信側ではなく配信側で、別画面で運用情報を確認するから。
- **管理者(運用者)として**、通知 JSON API への直叩きアクセスが他人の通知を漏らさない構造を期待する。なぜなら、提供 PJ の認証なし API はセキュリティ上の負債で、本番運用前に必ず認証を後付けする必要があるから。
- **管理者(運用者)として**、API への認証は同一オリジンの Cookie ベースで模擬されるが、将来 BE-FE 別オリジン構成に展開できる素地を期待する。なぜなら、Pro 生として実務で使う Sanctum SPA Cookie 認証のパターンを正しく身につけたいから。

## やること

### Sanctum Cookie 認証の後付け(API 保護)

- **Sanctum stateful 設定**: 環境変数で stateful ドメインを構成し、認証セッション Cookie + CSRF Cookie で API リクエストを認証する状態を作る
- **API endpoint への Sanctum 保護**: 通知 JSON API のルート(`GET /api/v1/notifications` / `POST /api/v1/notifications/{notification}/read` / `POST /api/v1/notifications/read-all`)に Sanctum 認証ミドルウェアを適用。未認証リクエストは 401
- **CSRF Cookie endpoint の公開**: Sanctum が自動提供する `/sanctum/csrf-cookie` を有効化(初回 GET で XSRF-TOKEN Cookie がブラウザにセットされる)
- **JS からの fetch 認証**: JS フロントが `/sanctum/csrf-cookie` を初回 GET で叩いて CSRF Cookie 取得 → 以降のリクエストは `fetch(..., { credentials: 'include' })` + X-CSRF-TOKEN / X-Requested-With ヘッダで認証セッションを保持
- **本人の通知のみアクセス可**: Policy + Action 側の絞り込みで、認証ユーザー自身の通知のみが取得・既読化される。他ユーザーの通知 ID を URL に直接指定すると 403

### JS フロント通知ポップオーバー

- **TopBar ベルクリックでポップオーバー開閉**: ベルアイコンをクリックすると、ベル右下にアンカーされた固定幅 380-420px のポップオーバーが開閉する。ESC キー / 外側クリック / フッターリンク遷移で閉じる
- **タブ切替(全件 / 未読)**: ポップオーバー上部にタブ UI を配置し、選択タブに応じて API クエリパラメータを切替えて再フェッチ。未読タブには「未読件数」バッジが付く
- **ローディング表示**: API フェッチ中はポップオーバー内に円形ローディングスピナーが表示され、応答完了でリスト描画に切り替わる
- **通知行の表示**: 各通知行に種別アイコン or 未読ドット / タイトル / プレビュー本文(2 行省略)/ 経過時間(たった今 / N 分前 / N 時間前 / N 日前 / それ以前は日付)が表示される。未読行は背景色がうっすら強調表示
- **行クリックで既読化 + 遷移**: ポップオーバー内の行をクリックすると、対応する既読化 API を叩いて未読バッジが -1 され、ポップオーバーが閉じてから対応する画面(通知種別ごとの遷移先)に遷移する
- **全件既読ボタン**: ポップオーバー右上の全件既読ボタンを押すと、全件既読化 API を叩いて TopBar バッジ + 未読件数表示を 0 に更新し、リストを再フェッチ
- **フッターから全通知ページへの遷移**: ポップオーバー最下部に「すべての通知を見る →」リンクを配置し、クリックでポップオーバーが閉じてから `/notifications` フルページに遷移
- **0 件時の空状態**: API レスポンスが 0 件の場合、リスト領域に「通知はありません。」を表示

### TopBar バッジの動的更新

- **行クリック時の -1**: ポップオーバー内の未読行を 1 件クリックすると、TopBar の未読バッジ + 未読タブカウントを -1 する
- **全件既読時の 0 化**: 全件既読ボタンを押すと、TopBar バッジが非表示 + 未読タブカウントが 0 になる
- **未読 99 件超の表示**: 未読件数が 99 を超える場合、TopBar バッジは `99+` に固定表示

### 共通の振る舞い

- **動的機能 = 動画必須**: ベルクリック → ポップオーバーオープン → タブ切替 → 行クリック → 既読化 + 遷移 → 戻って全件既読 の動作を動画で記録して PR に添付する
- **モバイル対応**: 画面端から 8px 最小余白を確保、ポップオーバーは最大幅 `100vw - 1rem` で収まる。コンテンツは `max-height: 70vh` で内部スクロール
- **アクセシビリティ**: ベルボタンに `aria-label="通知"`、ポップオーバーに `role="dialog" aria-modal="false" aria-label="通知"`、タブに `role="tablist"` / `role="tab"` + `aria-selected`、ローディングに `role="status" aria-label="読み込み中"`、ESC キーでクローズ + ベルにフォーカス戻し
- **fetch エラー時のフォールバック**: API が 401 / 500 等で失敗した場合、`console.error` でログを残し、ポップオーバーには空状態(または "読み込みに失敗しました" 表示)で続行(画面はクラッシュさせない)

## やらないこと

- 管理者(admin)向けの通知ポップオーバー — 管理者は通知の受信側ではないため対象外
- Pusher / Broadcasting によるリアルタイム push 受信 — 別チケット or 将来拡張(本チケットは「ベルクリックで fetch」の同期動作のみ)
- 通知のフィルタ / 検索 / ページネーション(ポップオーバー内)— 最新 20 件のみ表示、深掘りは `/notifications` フルページに委譲
- 通知の削除 / アーカイブ — 既存仕様(全件保持)を維持
- ポップオーバー内での通知種別アイコンの動的切替 — 業務固有意匠は本チケットスコープ外、基本デザイン(未読ドット + タイトル + プレビュー + 経過時間)で構成
- API レスポンスのキャッシュ / 楽観 UI 更新 — 行クリック後は素直に再フェッチ
- BE-FE 別オリジン構成の本格構築 — 実装は同一オリジンで模擬、`SANCTUM_STATEFUL_DOMAINS` 設定の感覚を養う目的
- Sanctum API トークン認証(PAT)— SPA Cookie 認証のみ
- 通知 JSON API の `routes/web.php` 統合 — `routes/api.php` 配下を維持
- バッジ / ポップオーバーの WebSocket / Polling 受信
- フローティング AI 相談ウィジェット(`S-A-02`)との UI 統合 — 別 Feature
- 通知種別ごとの細かな UI 出し分け(チャット = チャットアイコン、面談 = カレンダーアイコン等) — 統一ドット + タイトル

## Seeder 設計

> 既存の通知データ Seeder(`NotificationSeeder` 等、`S-B-05` / `S-B-04` で投入される `notifications` テーブルのレコード)をそのまま使う。本チケットでは新規 Seeder を追加せず、既存通知レコードをポップオーバーで取得 / 既読化する動作確認に利用する。

**前提**(他 Seeder で投入される想定): 受講生 A / 受講生 B / コーチ X / 管理者 / 各ユーザーに対する通知レコード複数件(未読 / 既読の両方を混在、通知種別もチャット / 面談 / 質問掲示板返信 / お知らせ等を網羅)

> 通知レコード自体は提供 PJ 既存の通知関連 Seeder で投入される。本チケットは新規 Seeder を追加しない。

## 受け入れ条件

- [ ] **API 認証 - 未認証拒否**: 未認証のクライアントが `/api/v1/notifications` / `/api/v1/notifications/{notification}/read` / `/api/v1/notifications/read-all` のいずれかにアクセスすると 401 が返る
- [ ] **API 認証 - 認証ユーザー取得可**: 認証済の受講生 / コーチが `/api/v1/notifications` にアクセスすると 200 + 自分の通知一覧 JSON が返る
- [ ] **API 認証 - 他者通知の既読化拒否**: 受講生 A が受講生 B の通知 ID を URL に指定して `/api/v1/notifications/{notification}/read` を叩くと 403
- [ ] **API レスポンス - 整形**: API レスポンスに各通知の `id` / `type` / `notification_type` / `title` / `message` / `link_route` / `link_params` / `read_at` / `created_at` が含まれる
- [ ] **API レスポンス - タブ全件**: `?tab=all` で全件(既読 / 未読混在)が時系列降順で取得される
- [ ] **API レスポンス - タブ未読**: `?tab=unread` で未読のみ(`read_at = null`)が時系列降順で取得される
- [ ] **API レスポンス - ページネーション**: `?per_page=20` 等で 1 ページあたりの件数指定でき、レスポンス JSON にページネーションメタ情報(`meta.total` 等)が含まれる
- [ ] **CSRF Cookie - 初回取得**: JS フロントが `/sanctum/csrf-cookie` を GET すると `XSRF-TOKEN` Cookie がブラウザにセットされる
- [ ] **CSRF 検証 - 既読化リクエスト**: JS フロントが CSRF Cookie 取得後、`POST /api/v1/notifications/{notification}/read` を `X-CSRF-TOKEN` ヘッダ付きで叩くと 200 が返る。トークンなしだと 419 / 403
- [ ] **ベルクリック - ポップオーバー開閉**: 受講生がログイン後画面で TopBar のベルアイコンをクリックすると、ベル右下にアンカーされた通知ポップオーバーが開く。もう一度クリックで閉じる
- [ ] **ポップオーバー閉じる - ESC キー**: ポップオーバーが開いている状態で ESC キーを押すと閉じ、ベルにフォーカスが戻る
- [ ] **ポップオーバー閉じる - 外側クリック**: ポップオーバーが開いている状態でポップオーバー外をクリックすると閉じる
- [ ] **ポップオーバー初期表示 - ローディング**: ポップオーバーオープン直後に API フェッチ中はローディングスピナーが表示され、応答完了後にリストに切り替わる
- [ ] **ポップオーバー - 通知行表示**: 取得された各通知が、未読ドット(未読時のみ表示)/ タイトル / プレビュー本文(2 行省略)/ 経過時間(たった今 / N 分前 / N 時間前 / N 日前)で表示される
- [ ] **ポップオーバー - 未読背景強調**: 未読状態の行は背景がうっすら強調表示され、既読行と区別される
- [ ] **ポップオーバー - 0 件時の空状態**: 取得結果が 0 件の場合、リスト領域に「通知はありません。」と表示される
- [ ] **タブ切替 - 全件 / 未読**: ポップオーバー上部のタブをクリックすると、選択タブに応じて API がクエリパラメータを切替えて再フェッチし、リストが更新される
- [ ] **タブ切替 - 未読カウント**: 未読タブのラベル右に未読件数バッジが表示され、未読タブ選択時のレスポンス件数を反映する
- [ ] **行クリック - 既読化**: ポップオーバー内の未読行をクリックすると `POST /api/v1/notifications/{notification}/read` が呼ばれ、レスポンス成功後に TopBar バッジが -1 される
- [ ] **行クリック - 該当画面遷移**: 既読化が完了したらポップオーバーが閉じ、通知種別の `link_route` + `link_params` に応じた遷移先 URL(例: `chat.show` → `/chat-rooms/{room}`、`qa-board.show` → `/qa-board/{thread}`、`meetings.show` → `/meetings/{meeting}`)にブラウザが遷移する
- [ ] **行クリック - link_route 不明時**: `link_route` が不明な場合、`/notifications` フルページに遷移する
- [ ] **全件既読 - ボタン押下**: ポップオーバー右上の全件既読ボタンを押すと `POST /api/v1/notifications/read-all` が呼ばれる
- [ ] **全件既読 - バッジ 0 化**: 全件既読リクエスト成功後、TopBar バッジが非表示になり、未読タブカウントが 0 になる。リスト領域が再フェッチされ、既読の通知が表示される
- [ ] **TopBar バッジ - 99 件超え**: 未読件数が 99 を超える場合、TopBar バッジが `99+` で表示される
- [ ] **フッターリンク - 全通知ページへ**: ポップオーバー最下部の「すべての通知を見る →」リンクをクリックすると、ポップオーバーが閉じてから `/notifications` フルページに遷移する
- [ ] **fetch エラー時のフォールバック**: API が 500 等で失敗してもポップオーバー画面はクラッシュせず、空状態(または "読み込みに失敗しました" 表示)で続行する
- [ ] **動的機能の動画記録**: PR の動作確認セクションにベルクリック → ポップオーバー開閉 → タブ切替 → 行クリック既読化 + 遷移 → 戻って全件既読 までの動作を動画で記録

## 実装方針

> **参考設計の一例**。受け入れ条件を満たせれば実装手段は問わない。受講生は提供 PJ コード + ヒアリングで自分の設計を組み立てる。

### 主要 URL

| メソッド | パス | 振る舞い |
|---|---|---|
| GET | `/sanctum/csrf-cookie` | Sanctum が自動提供。初回 GET で `XSRF-TOKEN` Cookie をセットする(204 No Content) |
| GET | `/api/v1/notifications?tab={all\|unread}&per_page={20}` | `auth:sanctum` 必須。認証ユーザーの通知を時系列降順で paginate 取得。`NotificationResource` で整形 |
| POST | `/api/v1/notifications/{notification}/read` | `auth:sanctum` 必須。`NotificationPolicy::update` で本人通知のみ認可。200 + `{status: 'ok'}` |
| POST | `/api/v1/notifications/read-all` | `auth:sanctum` 必須。認証ユーザーの未読通知を一括既読化。200 + `{status: 'ok', updated: N}` |

> 既存 `routes/web.php` の `/notifications` フルページ(`NotificationController::index/markAsRead/markAllAsRead`、提供 PJ で実装済)はそのまま維持。本チケットは JSON API の `routes/api.php` 配下に Sanctum 認証を後付け + JS フロントを新規追加。

### データモデル

**既存テーブル**: `notifications`(Laravel 標準の `DatabaseNotification` スキーマ、提供 PJ で既存、本チケットでカラム変更なし)

| カラム | 型 | NOT NULL | FK / UNIQUE | 補足 |
|---|---|:---:|---|---|
| id | ulid | ✓ | PK | 既存 |
| type | varchar | ✓ | | 既存、通知クラス FQCN |
| notifiable_type | varchar | ✓ | | 既存、通常 `App\Models\User` |
| notifiable_id | ulid | ✓ | | 既存、受信者 |
| data | json | ✓ | | 既存、`notification_type` / `title` / `message` / `link_route` / `link_params` 等を含む |
| read_at | timestamp | | | 既存、既読化時にセット |
| created_at | timestamp | | | 既存 |
| updated_at | timestamp | | | 既存 |

- **Sanctum パーソナルアクセストークン**: `personal_access_tokens` テーブルは本チケットでは使用しない(SPA Cookie 認証のみ採用)
- **本チケットで追加するテーブル**: なし

### バリデーション

`IndexRequest`(`GET /api/v1/notifications`、本チケットで新規):

| 入力項目 | ルール | 推奨エラーメッセージ |
|---|---|---|
| tab | nullable / in:all,unread | タブ指定が不正です。 |
| per_page | nullable / integer / min:1 / max:50 | 1 ページの件数は 1〜50 で指定してください。 |

POST 系(既読化 / 全件既読化)は body なし。`{notification}` パラメータは Route Model Binding で `DatabaseNotification` を引き当て、Policy `update` が認可判定。

### 認可設計

**Policy**: `NotificationPolicy`(提供 PJ で既存、本チケットで `update` メソッドを利用)

| メソッド | ロール × 判定 |
|---|---|
| view | 認証ユーザー かつ `$notification->notifiable_id === $user->id` のみ ✅(他人の通知は ❌) |
| update | 同上(既読化操作で利用) |

- **`$this->authorize('update', $notification)`**: `Api\V1\NotificationController::markAsRead` 内で呼ぶ
- **`auth:sanctum` Middleware**: route group 全体に適用(未認証は 401)
- **本人スコープのフィルタ**: `IndexAction` 内で `$user->notifications()` / `$user->unreadNotifications()` を使うため、認証ユーザー以外の通知は SELECT クエリでも返らない

### API 仕様

| エンドポイント | リクエスト | レスポンス | 認証 |
|---|---|---|---|
| `GET /api/v1/notifications` | `?tab=all\|unread&per_page=20` | 200 + `{data: [NotificationResource, ...], links, meta}`(Laravel paginator JSON 構造) | Sanctum Cookie |
| `POST /api/v1/notifications/{notification}/read` | body なし | 200 + `{status: 'ok'}` / 403(他人) / 404(存在しない) | Sanctum Cookie |
| `POST /api/v1/notifications/read-all` | body なし | 200 + `{status: 'ok', updated: N}` | Sanctum Cookie |
| `GET /sanctum/csrf-cookie` | body なし | 204(`XSRF-TOKEN` Cookie をセット) | なし |

`NotificationResource`(本チケットで新規):

```php
[
  'id' => 'ulid',
  'type' => 'App\\Notifications\\ChatMessageReceivedNotification',
  'notification_type' => 'chat_message_received',
  'title' => '新着メッセージ',
  'message' => 'A さんからのメッセージ',
  'link_route' => 'chat.show',
  'link_params' => ['room' => 'ulid'],
  'read_at' => null,
  'created_at' => '2026-05-25T10:30:00+09:00',
]
```

### テスト観点

| 種別 | 観点 |
|---|---|
| Unit | `Api\IndexAction`(全件タブ / 未読タブ / paginate 件数指定) / `Api\MarkAllAsReadAction`(未読 N 件 → 0 件 / 既読化件数を返す)/ `NotificationResource::toArray`(data 配列キー欠落時の fallback) |
| Feature | `auth:sanctum` 未認証 → 401 / 認証済 → 200 / 他者通知 ID 既読化 → 403 / タブ未読 → 未読のみ返却 / per_page 指定の Validation エラー → 422 / `POST /api/v1/notifications/read-all` で `read_at` 一括更新 / `GET /sanctum/csrf-cookie` → 204 + Cookie セット |
| Browser(任意) | `tests/Browser/NotificationPopoverTest.php` で Dusk(または Playwright MCP)を使ったベル開閉 → タブ切替 → 行クリック → 既読化 + 遷移 → 全件既読 → バッジ 0 化 までを E2E 検証 |
| Manual | 動的機能の動画記録(PR 添付)、Sanctum Cookie 認証フロー(`/sanctum/csrf-cookie` → Cookie 確認 → API リクエスト → 認証成功)を DevTools で確認 |

### アーキテクチャ判断

- **採用技術**: Laravel Sanctum(SPA Cookie 認証) + Resource(`NotificationResource`) + UseCases (Action) + Policy + FormRequest + 素の JavaScript(Vite ビルド) + `fetch` API + Heroicons + CSRF 二段防御(`X-CSRF-TOKEN` ヘッダ + `XSRF-TOKEN` Cookie)
- **設計判断**:
  1. **Sanctum SPA Cookie 認証採用(API トークン不採用)**: 同一オリジン構成で `Sanctum::stateful()` + セッション認証を Cookie 経由で利用。受講生に「BE-FE 別オリジン構成への参画体験」を `SANCTUM_STATEFUL_DOMAINS` 設定の感覚で学ばせる目的で、API トークン認証(PAT)ではなく SPA Cookie 認証を採用(`tech.md` の「Sanctum 採用」方針準拠)
  2. **`auth:sanctum` Middleware の適用範囲**: `routes/api.php` の `prefix('v1')` group 全体に `middleware('auth:sanctum')` を適用。route group 名は `api.v1.*`(`api.v1.notifications.index` / `api.v1.notifications.markAsRead` / `api.v1.notifications.markAllAsRead`)
  3. **CSRF 二段防御**: Sanctum は (a) セッション Cookie で認証 + (b) `X-CSRF-TOKEN` ヘッダで CSRF 検証 を要求。JS フロントは初回 `/sanctum/csrf-cookie` を GET して `XSRF-TOKEN` Cookie を取得 → 以降の `fetch` リクエストに `X-CSRF-TOKEN` ヘッダを Meta タグから読んで付与。`X-Requested-With: XMLHttpRequest` も付与すると 419 回避用に確実
  4. **JS フロントの分離**: `resources/js/notification/notification-popover.js` でポップオーバー制御を分離。`resources/js/utils/fetch-json.js` に CSRF + JSON 共通 wrapper を実装(他 JS Feature でも再利用、`backend-tests.md` の方針)
  5. **`resources/js/app.js` でエントリ初期化**: `app.js` で `initNotificationPopover()` を `DOMContentLoaded` で呼ぶ。Vite ビルドで `resources/js/app.js` を `@vite` 経由でロード(`layouts/app.blade.php` で )
  6. **Resource クラスでレスポンス整形**: `App\Http\Resources\Api\V1\NotificationResource` で `DatabaseNotification` の `data` JSON 連想配列を平坦化。JS が画面表示に必要なフィールドだけを `toArray()` で返す。`@mixin DatabaseNotification` で IDE 補完 + 静的解析サポート
  7. **`final class` 採用**: `Api\IndexAction` / `Api\MarkAllAsReadAction` は `final class`(`backend-services.md` 規約)。`NotificationResource` は `final` 不要(Laravel Resource クラスは慣習で `final` を付けない)
  8. **既存 Web 版 Controller との分離**: `NotificationController`(`routes/web.php`、`/notifications` フルページ)は Blade + リダイレクト動作を維持し、`Api\V1\NotificationController`(`routes/api.php`、JSON API)を別 Controller として新設。同名の Action(`MarkAsReadAction` 等)は共有可能だが、`IndexAction` は paginate + tab フィルタ用に `Api\IndexAction` を新設(レスポンス型が `LengthAwarePaginator` で異なる)
  9. **CSRF Cookie 取得タイミング**: JS の `ensureCsrfCookie()` 関数を 1 回だけ呼ぶフラグ(`csrfCookieFetched`)で管理。複数のフェッチが同時走行しても初回 1 回のみ叩く
  10. **ポップオーバーの a11y**: `role="dialog"` + `aria-modal="false"`(モーダルでないため false)+ `aria-label="通知"`。ESC キーで close + `triggerEl.focus()` でベルにフォーカス戻し。タブは `role="tablist"` / `role="tab"` + `aria-selected` で WCAG 2.1 AA 準拠
  11. **fetch エラー時のフォールバック**: 401 で `console.error` ログのみ(再ログインを促すリダイレクトは出さない、`/sanctum/csrf-cookie` の再取得は別途トラブルシュート)/ 500 で空状態表示 / Network エラーで `catch` ブロック内で `renderItems([])` を呼んで画面継続
  12. **TopBar バッジの DOM 更新**: バッジカウントは `<x-badge>` ベースの Blade で初期描画 → JS が `data-notification-popover-badge` / `data-notification-popover-unread-count` を直接書き換える(リアクティブ FW 不採用、`frontend-javascript.md` 「素の JS」方針準拠)
  13. **遷移先 URL 解決の Switch**: `link_route` 値(例: `chat.show` / `qa-board.show` / `meetings.show` / `certificates.download`)に応じて JS 側で URL 文字列を組み立てる Switch 文(`resolveTargetUrl`)。Laravel のルート名と JS の URL 解決を二重管理するため、新通知種別追加時は両方を更新する責務(規約として明示)

### 関連ファイルメモ

- `app/Http/Controllers/Api/V1/NotificationController.php`(新規、JSON API 用 `index` / `markAsRead` / `markAllAsRead`)
- `app/Http/Requests/Api/V1/Notification/IndexRequest.php`(新規、`tab` / `per_page` バリデーション)
- `app/UseCases/Notification/Api/IndexAction.php`(新規、paginate + tab フィルタ)
- `app/UseCases/Notification/Api/MarkAllAsReadAction.php`(新規、`unreadNotifications()->update(['read_at' => now()])`)
- `app/UseCases/Notification/MarkAsReadAction.php`(提供 PJ で既存、Web / API 共有)
- `app/Http/Resources/Api/V1/NotificationResource.php`(新規、`data` JSON 平坦化)
- `app/Policies/NotificationPolicy.php`(提供 PJ で既存、`update` メソッドを利用)
- `app/Models/User.php`(提供 PJ で既存、`HasApiTokens` Trait は SPA Cookie 認証では不要だが追加しても害なし)
- `config/sanctum.php`(`stateful` ドメインを `.env` 経由で構成)
- `config/cors.php`(`supports_credentials => true` + `paths` に `api/*` + `sanctum/csrf-cookie` を含める、同一オリジン運用なら `allowed_origins` は LMS URL のみ)
- `bootstrap/app.php` or `app/Http/Kernel.php` の `api` Middleware に `EnsureFrontendRequestsAreStateful::class` を追加(Sanctum SPA 用)
- `resources/views/notifications/_partials/notification-popover.blade.php`(新規、ポップオーバーの HTML 構造 + `<template>` で行テンプレ)
- `resources/views/layouts/_partials/topbar.blade.php`(既存に `<x-notification-popover>` を組み込み + ベルアイコンに `data-notification-popover-trigger` 付与)
- `resources/js/notification/notification-popover.js`(新規、ポップオーバー制御 JS)
- `resources/js/utils/fetch-json.js`(新規、CSRF + JSON 共通 wrapper)
- `resources/js/app.js`(既存に `initNotificationPopover()` 呼び出しを追加)
- `routes/api.php` の `prefix('v1')->middleware('auth:sanctum')->name('api.v1.')->group(...)` に notification 3 ルートを追加
- `.env.example` に `SANCTUM_STATEFUL_DOMAINS=localhost,localhost:8000,127.0.0.1,127.0.0.1:8000` のサンプル設定を追加(同一オリジン運用)

## 補足

### 想定ヒアリング Q&A

| 質問 | 回答 |
|---|---|
| 認証方式は API トークン / Cookie? | Sanctum SPA Cookie 認証(セッション Cookie + CSRF 二段防御)。API トークン認証(PAT)は採用しない |
| なぜ Sanctum Cookie 認証? | BE-FE 別オリジン構成への参画体験を、同一オリジンで Cookie ベースで模擬するため。`SANCTUM_STATEFUL_DOMAINS` の感覚を養う意図 |
| Web ベースの認証セッションが既にあるのに、Sanctum を別途追加する理由は? | `auth:sanctum` Middleware は API リクエストで「セッション Cookie + CSRF Cookie + `X-CSRF-TOKEN` ヘッダ」の 3 点セットを検査し、Web セッションを API ルート向けに適応させる。Sanctum なしの API は `auth` ミドルウェアでもセッション認証は通るが、JS フェッチからの CSRF 防御が脆弱になる |
| JS から `fetch` するときの認証ヘッダは? | `credentials: 'include'` でセッション Cookie を送信 + `X-CSRF-TOKEN` ヘッダ(Meta タグから読む)+ `X-Requested-With: XMLHttpRequest` を付与 |
| `/sanctum/csrf-cookie` はいつ叩く? | 各 JS ページの初回フェッチ前に 1 度だけ(`ensureCsrfCookie()` フラグで多重呼出回避)。レスポンスの Cookie がセッション保持される |
| 認可拒否時の HTTP ステータスは 401 / 403? | 未認証は 401(`auth:sanctum` ミドルウェア)/ 認証済だが他人の通知 ID 指定は 403(`NotificationPolicy::update` Policy 拒否) |
| ポップオーバーは管理者にも表示する? | 表示しない。管理者は通知の受信側ではないため対象外。受講生 / コーチのみ |
| ポップオーバーの位置 / サイズは? | ベルアイコンの右下にアンカー、固定幅 380-420px(モバイルでは `max-width: 100vw - 1rem`)、`max-height: 70vh` で内部スクロール |
| ポップオーバーの 1 ページあたり件数は? | 20 件(Stripe / Jira / GitHub 業界標準)。深掘りは `/notifications` フルページ |
| タブは何種類? | 2 種類(全件 / 未読)。未読タブには未読件数バッジが付く |
| 行クリック後の挙動は? | (1) `POST /api/v1/notifications/{id}/read` で既読化 → (2) TopBar バッジ -1 → (3) ポップオーバー close → (4) `link_route` に対応する URL に `window.location.href` で遷移 |
| 行クリックの遷移先が不明な場合(`link_route` が JS 側未対応)は? | `/notifications` フルページに遷移(汎用フォールバック) |
| 全件既読ボタン押下時の挙動は? | `POST /api/v1/notifications/read-all` → 200 → TopBar バッジ 0 化 + 未読タブカウント 0 化 → リスト再フェッチ(現在のタブで) |
| ローディングインジケータの種類は? | 円形スピナー(`animate-spin` の Tailwind ユーティリティ + `border-2 border-ink-200 border-t-primary-600`)。`role="status"` + `aria-label="読み込み中"` で a11y |
| 通知 0 件時の表示は? | リスト領域に「通知はありません。」(`text-xs text-ink-500` のセンタリング)。フッターリンクは表示維持 |
| ベルバッジが 100 件以上の表示は? | `99+` に固定(Stripe / Jira / GitHub 準拠) |
| Pusher / WebSocket リアルタイム push は? | 本チケットスコープ外(ベルクリックで fetch する同期動作のみ)。将来拡張余地として `broadcastOn` メソッドは Notification クラス側に既に実装 |
| API レスポンスの Resource は? | `App\Http\Resources\Api\V1\NotificationResource` で `data` JSON を平坦化。`id` / `type` / `notification_type` / `title` / `message` / `link_route` / `link_params` / `read_at` / `created_at` を返す |
| CORS 設定は必要? | 同一オリジン運用なら最小設定。`config/cors.php` で `supports_credentials => true` + `paths` に `api/*` + `sanctum/csrf-cookie` を含める。BE-FE 別オリジン構成時は `allowed_origins` に FE オリジンを明記する必要(将来拡張時の規約として README に記載推奨) |
| Sanctum API トークン(PAT)も併用する? | 併用しない(本チケットは SPA Cookie のみ)。`HasApiTokens` Trait の追加は害なしだが、PAT 用 UI は実装しない |
| `routes/web.php` の `/notifications` フルページとの関係は? | 並列で維持。`/notifications` は Blade + リダイレクト動作(全件閲覧 / フィルタ / ページネーション)、`/api/v1/notifications` は JSON API(ポップオーバー専用、最新 20 件)。Action は共有可能(`MarkAsReadAction`)、`IndexAction` は paginate vs 旧 array で別実装 |
| エラー時のフラッシュ表示は? | ポップオーバーは JS フェッチでフラッシュを使わない(`console.error` ログのみ + 空状態 / リスト維持)。フルページの既読化動作はフラッシュ表示(提供 PJ 既存) |
| 動画記録の長さの目安は? | 1〜2 分でカバー: ログイン → ベルクリック → ポップオーバー開 → 全件タブ → 未読タブ切替 → 行クリック既読化 + 遷移 → 戻る → 全件既読ボタン → バッジ 0 確認 |
| WCAG / a11y 要件は? | `role="dialog"` + `aria-modal="false"` / タブの `role="tab"` + `aria-selected` / ローディングの `role="status"` + `aria-label` / ESC キーで close + ベルフォーカス戻し / 通知行のキーボード操作(Tab / Enter)対応 |
| 旧版(提供 PJ)の TopBar ベル動作との差は? | 提供 PJ ではベルクリックで `/notifications` フルページに直接遷移(JS なし)。本チケットでベルにフックして JS でポップオーバーを開く動作に置き換える。フルページ動線はフッターリンクで継続提供 |
| 401 が返ったときの JS フロントの挙動は? | `console.error` ログ + 空状態表示(再ログイン誘導は出さない)。`/sanctum/csrf-cookie` の再取得 or Cookie 確認は別途トラブルシュートの責任 |
| ベルバッジ初期値(ページロード時)はどう取得? | `NotificationBadgeComposer`(提供 PJ 既存)で View Composer 経由で `<x-badge>` に未読件数を渡す。JS は DOM の `data-notification-popover-badge` をそこから読む |
| 同 Feature 別チケットとの関係(`S-A-04` Stripe / `S-A-02` AI 等)は? | 独立。`/api/v1/notifications` は通知 Feature 専用で、他 Feature の API は本チケットスコープ外。Sanctum 認証基盤(`/sanctum/csrf-cookie` / `auth:sanctum`)は本チケットで構築するが、他 Feature の API も将来同じ基盤を流用可能 |
