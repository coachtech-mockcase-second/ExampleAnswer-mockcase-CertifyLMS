# S-A-05 Sanctum Cookie 認証 + JS フロント通知表示

## メタ情報

| 項目 | 値 |
|---|---|
| チケット ID | `S-A-05` |
| Feature 連番 | `notification-03` |
| Feature | notification |
| 種別 | Story |
| サブカテゴリ | 既存機能の拡張 |
| 難易度 | Advance |
| 工数 (h) | 12 |
| 依存チケット | `S-B-04` / `S-B-09` |

## 背景・目的

通知ベル UI と通知一覧画面(`/notifications` 静的ページ)はあるが、ベルクリックで一覧画面に遷移するだけで「ちょい見・即既読化」のスナップ動線がない。学習中の受講生は通知の有無を知るためだけに画面遷移が必要で UX が断絶している。また通知を JSON で取得して JS フロントから動的に扱う経路も無く、認証付き通知 API の土台が未整備である。

本チケットは Sanctum Cookie 認証で保護した通知 JSON API(一覧 / 単一既読化 / 全件既読化)を新規構築し、TopBar のベルアイコンにフックして「ベルクリックで API を非同期取得 → 通知ポップオーバーで動的表示」する JS フロントエンドを実装する。ポップオーバーには全件 / 未読タブ + 全件既読ボタン + フッターから全通知ページへの導線を備える。受講生学習体験のスナップアウト改善 + 認証付き通知 API の構築 + 実務で頻出する Sanctum SPA Cookie 認証 / `fetch` + CSRF 二段防御 / Resource クラスでの API レスポンス整形 を扱う。

## ユーザーストーリー

- **受講生(student)として**、TopBar のベルアイコンをクリックしたら通知一覧画面に遷移せず、その場で最新 20 件を確認したい。なぜなら、学習を中断せずに「通知を見るだけ見て学習に戻る」流れを実現したいから。
- **受講生として**、ポップオーバーから 1 件クリックして既読化と該当画面遷移を同時にしたい。なぜなら、二段階の操作(まず一覧で開いてから個別行をクリック)が冗長だから。
- **受講生として**、ポップオーバーで全件既読ボタンを押すと一気に未読バッジが 0 になる体験を期待する。なぜなら、未読が溜まったときの一括処理を素早く済ませたいから。
- **コーチ(coach)として**、同じ通知ポップオーバーを使う。なぜなら、受講生と同じ通知基盤を共有しており、専用 UI を覚えたくないから。
- **管理者(admin)として**、本機能(ポップオーバー UI)は対象外。なぜなら、管理者は通知の受信側ではなく配信側で、別画面で運用情報を確認するから。
- **管理者として**、通知 JSON API への直叩きアクセスが他人の通知を漏らさない構造を期待する。なぜなら、現状の認証なし API はセキュリティ上の負債で、本番運用前に必ず認証を後付けする必要があるから。
- **管理者として**、API への認証は同一オリジンの Cookie ベースで実現しつつ、将来 BE-FE 別オリジン構成に展開できる素地を期待する。なぜなら、実務で BE-FE 別オリジン構成のセキュリティ要件を満たす運用を想定しているから。

## 要件

### 通知 JSON API の構築 + Sanctum Cookie 認証(API 保護)

- 通知 JSON API 3 ルート(一覧 / 単一既読化 / 全件既読化)を `routes/api.php` 配下に新規実装する(JSON Resource による整形 + API FormRequest によるクエリ検証を含む)
- Sanctum stateful 設定の構成(stateful ドメイン定義、Cookie + CSRF 二段防御の有効化)
- 通知 JSON API 3 ルートへ `auth:sanctum` Middleware を適用する
- Sanctum 提供の CSRF Cookie endpoint の公開(初回 GET で XSRF Cookie がブラウザにセットされる状態)
- API は認証ユーザー本人の通知のみを対象にする(認証ユーザー自身の通知のみ取得 / 既読化、他者通知 ID 指定は 403)

### JS フロント通知ポップオーバー

> **実装手段**: 素の JavaScript(`fetch` API + Vite ビルド)で実装する。リアクティブフレームワーク(Vue / React 等)は採用しない。

- TopBar ベルクリックで通知ポップオーバーを開閉(フル画面遷移ボタンあり)
- ポップオーバー上部のタブ切替(タブ識別子は「全件」「未読のみ」のいずれか / 未読タブには未読件数バッジ)
- 1 ページ件数指定(任意 / 1〜50 の整数、ポップオーバー用に少なめの上限)
- 通知行表示(未読ドット / タイトル / プレビュー本文 2 行省略 / 経過時間、未読行は背景うっすら強調)
- 0 件時の空状態表示(「通知はありません。」)
- 行クリックで既読化 API 呼出 → TopBar バッジ -1 → ポップオーバー close → 通知種別に応じた遷移先画面へ遷移(業務通知は該当業務画面、お知らせは通知詳細ページ)
- 全件既読ボタンで全件既読化 API 呼出 → TopBar バッジ 0 化 → 未読タブカウント 0 化 → リスト再フェッチ
- フッター「すべての通知を見る →」リンクで `/notifications` フルページに遷移

### TopBar 通知ベル + 未読件数バッジ

> 未読件数を集計してバッジ初期値を供給する View Composer、およびベル + バッジの静的描画(0 件で非表示 / 100 件以上で「99+」)は提供済みのフロントインフラ。受講生はこれらを構築せず、ベルクリックのポップオーバー表示とバッジの動的増減(JS)を実装する。

- 未読件数バッジの常時表示(未読 0 件で非表示、100 件以上で「99+」固定表示)
- バッジ初期値の供給(ページロード時にサーバ側で算出した未読件数を DOM に初期表示として焼き込む = 提供済みの View Composer が担う)
- 行クリック時の未読バッジ -1 / タブカウント -1
- 全件既読時の未読バッジ非表示 + 未読タブカウント 0

### アクセス制御(機能群共通)

- 受講生 / コーチには通知ポップオーバーを表示
- 管理者には対象外(JS 初期化スキップ、API 一覧は 0 件で返る)
- 認証ユーザー本人の通知のみ API で返り、他者通知 ID 直叩きは 403

## スコープ外

- 管理者(admin)向けの通知ポップオーバー — 管理者は通知の受信側ではないため対象外
- Pusher / Broadcasting によるリアルタイム push 受信 — 別チケット or 将来拡張(本チケットは「ベルクリックで fetch」の同期動作のみ)
- 通知のフィルタ / 検索 / ポップオーバー内ページネーション — 最新 20 件のみ表示、深掘りは `/notifications` フルページに委譲
- 通知の削除 / アーカイブ動線 — 既読化のみ
- 通知種別ごとの細かな UI 出し分け(チャット = チャットアイコン、面談 = カレンダーアイコン等)— 統一ドット + タイトル
- API レスポンスのキャッシュ / 楽観 UI 更新 — 行クリック後は素直に再フェッチ
- BE-FE 別オリジン構成の本格構築 — 実装は同一オリジンで行い、stateful ドメイン設定で別オリジン展開の素地のみ確保する
- Sanctum API トークン認証(PAT)— SPA Cookie 認証のみ
- 通知 JSON API の `routes/web.php` 統合 — `routes/api.php` 配下を維持
- バッジ / ポップオーバーの WebSocket / Polling 受信
- フローティング AI 相談ウィジェットとの UI 統合 — 別 Feature

## 受け入れ条件

- [ ] 未認証クライアントが通知 JSON API(`GET /api/v1/notifications` / 単一既読化 / 全件既読化)に直叩きすると 401、認証済受講生 / コーチは自分の通知一覧 JSON を取得でき、認証済ユーザー A が他者(B)の通知 ID を指定して既読化 API を叩くと 403 が返る
- [ ] 認証済の管理者が通知一覧 API を取得すると、エラーにならず 0 件の JSON が返る(管理者は通知の受信対象外)
- [ ] `/sanctum/csrf-cookie` を GET すると XSRF-TOKEN Cookie がブラウザにセットされ、JS フロントは CSRF Cookie 取得後の POST リクエスト(既読化 / 全件既読化)を CSRF トークン付きで通せる(トークンなしの POST は CSRF 検証失敗)
- [ ] 認証済受講生 / コーチが一覧 API を取得すると、通知種別 / タイトル / プレビュー / 遷移先ルート + パラメータ / 既読化日時 / 作成日時 等の整形フィールドを含む JSON が時系列降順で返る
- [ ] 一覧 API にて、クエリパラメータにバリデーションが行われ、ルール違反時に 422 + JSON 形式の日本語エラーレスポンスが返るか:
  - タブ識別子: 任意 / 「全件」「未読のみ」のいずれか
  - 1 ページ件数: 任意 / 1〜50 の整数(ポップオーバー用に少なめの上限)
- [ ] 受講生 / コーチが TopBar のベルアイコンをクリックすると通知ポップオーバーが開閉する
- [ ] ポップオーバーオープン直後に API がフェッチされ、各通知行(未読ドット / タイトル / プレビュー本文 2 行省略 / 経過時間 / 未読行の背景強調)が表示される。取得結果が 0 件の場合は「通知はありません。」の空状態が表示される
- [ ] ポップオーバー上部のタブを切り替えると、選択タブに応じて API がクエリパラメータを切り替えて再フェッチしリストが更新され、未読タブのラベル右に未読件数バッジが表示される
- [ ] 未読通知行をクリックすると単一既読化 API が呼ばれて TopBar バッジが -1 され、ポップオーバーが閉じてから遷移先ルートに応じた画面(chat ルーム / Q&A スレッド / 面談一覧 / お知らせは通知詳細ページ 等)に遷移する(遷移先ルートが JS 側で未対応のときは `/notifications` フルページにフォールバック)
- [ ] 全件既読ボタンを押下すると全件既読化 API が呼ばれ、TopBar バッジが非表示になり、未読タブカウントが 0 になり、リスト領域が再フェッチされる
- [ ] TopBar に自分宛の未読件数バッジが表示され、未読 0 件で非表示 / 100 件以上で「99+」固定表示になる(ページロード時はサーバ側で算出した値を表示、行クリック / 全件既読化で動的に増減)
- [ ] 本チケットの機能に対するテスト (Unit / Feature 等) が実装されている

## 実装方針(参考)

### インターフェース(必須)

| HTTP | パス | 認可 | 振る舞い |
|---|---|---|---|
| GET | `/sanctum/csrf-cookie` | 認証不要 | Sanctum 自動提供。初回 GET で XSRF Cookie をセット(204 No Content) |
| GET | `/api/v1/notifications?tab={全件\|未読のみ}&per_page={1-50}` | `auth:sanctum` 必須(認証ユーザー本人の通知のみ) | 認証ユーザー本人の通知を時系列降順 + ページネーション meta 付き Resource Collection で返す |
| POST | `/api/v1/notifications/{notification}/read` | `auth:sanctum` 必須(本人通知のみ、他人通知は 403) | 単一既読化、成功時 200 + JSON |
| POST | `/api/v1/notifications/read-all` | `auth:sanctum` 必須(自分宛のみ対象) | 認証ユーザー本人の全未読通知を一括既読化、成功時 200 + 既読化件数 JSON |

**ミドルウェア**: `routes/api.php` の `v1` group 全体に `auth:sanctum` 適用(未通過は 401)。

**既存 Web ルートとの共存**: `routes/web.php` の `/notifications` フルページ(既存)はそのまま維持。本チケットは JSON API を `routes/api.php` 配下に新規実装し、Sanctum 認証 + JS フロントを追加。

**ロール別の UI 表示**: 受講生 / コーチには通知ポップオーバーを表示、管理者は対象外(JS 初期化スキップ、API 一覧は 0 件で返る)。

### データモデル

既存の通知基盤(`notifications` テーブル + `BaseNotification` 抽象基底 + 4 通知種別クラス)を本チケットで再利用。本チケットでテーブル / Model / Enum の新規追加なし。

### コンポーネント

**Controller** (`app/Http/Controllers/Api/V1/`、新規)
- `NotificationController` — `index` / `markAsRead` / `markAllAsRead`

**FormRequest** (`app/Http/Requests/Api/V1/Notification/`、新規)
- `IndexRequest` — タブ識別子 / 1 ページ件数の検証

**Action** (`app/UseCases/Notification/`、API 固有 Action は新規。`MarkAsReadAction` は通知基盤由来を共有)
- `Api\IndexAction` — 認証ユーザー本人の通知をページネーション + タブフィルタで取得
- `Api\MarkAllAsReadAction` — 認証ユーザー本人の全未読通知を一括既読化
- `MarkAsReadAction` — Web / API 共有(既存)

**Resource** (`app/Http/Resources/Api/V1/`、新規)
- `NotificationResource` — 通知データ JSON 平坦化

**View Composer** (`app/View/Composers/`、既存)
- `NotificationBadgeComposer` — TopBar Blade に未読件数を渡す既存の View Composer(1 リクエスト 1 回の `count` クエリで O(1))。受講生は構築せず、バッジ初期値の供給インフラとして利用する

**Policy** (`app/Policies/`、既存)
- `NotificationPolicy::update` — 単一既読化対象が認証ユーザー本人宛か検証

**Blade コンポーネント**(新規)
- `resources/views/notifications/_partials/notification-popover.blade.php` — ポップオーバー HTML 構造 + 通知行テンプレ
- `resources/views/layouts/_partials/topbar.blade.php`(既存) — ベルアイコンに data 属性 + ポップオーバー組込

**JS フロント** (`resources/js/`、新規)
- `notification/notification-popover.js` — ポップオーバー制御(タブ切替 / 行クリック / 全件既読 / バッジ動的更新 / 遷移先 URL 解決)
- `utils/fetch-json.js` — CSRF Cookie 取得管理 + JSON ヘッダ統一 wrapper
- `app.js`(既存) — `DOMContentLoaded` 時に管理者以外でポップオーバー初期化呼出を追加

**Sanctum 認証層**(構成変更)
- `config/sanctum.php` — stateful ドメイン群を環境変数経由で構成
- `config/cors.php` — 同一オリジン運用の最小設定
- `bootstrap/app.php` — api ミドルウェアグループに Sanctum 用 stateful Middleware を追加

**Routes** (`routes/api.php`、新規)
- `v1` group に通知ルート 3 本を新規登録し、Sanctum 認証(`auth:sanctum`)を適用

**環境設定**
- `.env.example` に Sanctum stateful ドメインのサンプル設定を追記

### 異常系

**入力検証**(FormRequest クラス名 + ルール記法):

- 一覧クエリ FormRequest (`Api\V1\Notification\IndexRequest`):
  - `tab`: `nullable` / `string` / `in:全件,未読のみ`
  - `per_page`: `nullable` / `integer` / `min:1` / `max:50`(ポップオーバー用に少なめの上限)
- 単一既読化 / 全件既読化: body なし、パスパラメータの通知 ID は Route Model Binding で解決

**業務例外**:

- 未認証クライアントが通知 JSON API に直叩き → 401(`auth:sanctum` Middleware)
- 認証済ユーザー A が他者(B)の通知 ID を指定して既読化 → 403(`NotificationPolicy::update` 拒否)
- CSRF Cookie / トークンヘッダなしの POST → CSRF 検証失敗(419、`VerifyCsrfToken` Middleware)
- 管理者: API 一覧は常に 0 件(管理者は通知の発火対象外、JS 初期化スキップ)

### 設計判断

- **Sanctum SPA Cookie 認証の採用**: セッション Cookie + CSRF Cookie + CSRF トークンヘッダの二段防御で同一オリジン運用しつつ、stateful ドメイン設定で BE-FE 別オリジン構成への展開可能性を確保。API トークン認証(PAT)は採用しない
- **CSRF Cookie 取得の単発化**: 各 JS ページの初回フェッチ前に 1 度だけ `/sanctum/csrf-cookie` を GET(フラグで多重呼出回避)。レスポンスの Cookie がセッション保持される
- **JS フロントは素の JavaScript + `fetch` API**: Vite ビルドで Vue / React 等リアクティブ FW は採用しない(実務で頻出する「リアクティブ FW なしでの通知 UI 実装」に相当)。バッジカウントは Blade に初期描画 + JS が `data-*` 属性経由で DOM を直接書き換える
- **遷移先 URL 解決の二重管理**: 通知データに含まれる遷移先ルート名 + パラメータ から JS 側で URL 文字列を switch で組み立てる(Laravel ルート名と JS の URL 解決を二重管理 — 新通知種別追加時は両方を更新)
- **TopBar バッジの初期値供給**: 既存の View Composer (`NotificationBadgeComposer`) が Blade に未読件数を渡し、JS は DOM の `data-*` 属性から読み取る(1 リクエスト 1 回の `count` クエリで O(1))。供給は frontend インフラの責務で、本チケットでは構築せず利用する
- **認証付き通知 API の構築**: 通知 JSON API を本チケットで新規実装し `auth:sanctum` で保護。対象ユーザーは認証ユーザー本人に限定し(他者通知 ID 指定は 403)、認証なしの公開 API は設けない
- **テスト観点**: 「Sanctum stateful 通過(認証ユーザーは 200、未認証は 401)」「他人通知 ID への既読化試行で 403」「CSRF Cookie なし POST で 419」「バッジ初期値表示(管理者以外で表示、未読 0 件で非表示)」「JS ポップオーバーの行クリック既読化 → バッジ -1 → 遷移」が本チケット固有の Feature / ブラウザ動作確認観点

## 補足

### 想定 Q&A

| 質問 | 回答 |
|---|---|
| 認証方式は API トークン / Cookie? | Sanctum SPA Cookie 認証(セッション Cookie + CSRF 二段防御)。API トークン認証(PAT)は採用しない |
| なぜ Sanctum Cookie 認証? | 同一オリジンで Cookie ベースの認証を実現しつつ、stateful ドメイン設定により将来の BE-FE 別オリジン構成へ展開できる素地を確保するため |
| Web ベースの認証セッションが既にあるのに Sanctum を別途追加する理由は? | `auth:sanctum` Middleware は API リクエストで「セッション Cookie + CSRF Cookie + CSRF トークンヘッダ」の 3 点セットを検査し、Web セッションを API ルート向けに適応させる。Sanctum なしの API は CSRF 防御が脆弱になる |
| CSRF Cookie はいつ叩く? | 各 JS ページの初回フェッチ前に 1 度だけ(フラグで多重呼出回避)。レスポンスの Cookie がセッション保持される |
| 認可拒否時の HTTP ステータスは 401 / 403? | 未認証は 401(`auth:sanctum` Middleware)/ 認証済だが他人通知 ID 指定は 403(`NotificationPolicy::update` 拒否) |
| ポップオーバーは管理者にも表示する? | 表示しない。管理者は通知の受信側ではないため対象外。受講生 / コーチのみ JS 初期化を実行 |
| ポップオーバーの 1 ページあたり件数は? | 20 件(業界標準)。深掘りは `/notifications` フルページ |
| タブは何種類? | 2 種類(全件 / 未読)。未読タブには未読件数バッジが付く |
| 行クリック後の挙動は? | (1) 単一既読化 API → (2) TopBar バッジ -1 → (3) ポップオーバー close → (4) 遷移先ルートに対応する URL に画面遷移 |
| 行クリック時の遷移先が不明な場合(JS 側未対応)は? | `/notifications` フルページに遷移(汎用フォールバック) |
| 全件既読ボタン押下時の挙動は? | 全件既読化 API → 成功 → TopBar バッジ 0 化 + 未読タブカウント 0 化 → リスト再フェッチ(現在のタブで) |
| 通知 0 件時の表示は? | リスト領域に「通知はありません。」のセンタリング表示。フッターリンクは表示維持 |
| ベルバッジが 100 件以上の表示は? | 「99+」に固定 |
| Pusher / WebSocket リアルタイム push は? | 本チケット範囲外(ベルクリックで fetch する同期動作のみ)。将来拡張余地として broadcast チャネルは Notification 側で既に実装可能な設計 |
| API レスポンスの Resource は? | `App\Http\Resources\Api\V1\NotificationResource`(本チケットで新規作成)で通知データ JSON を平坦化する |
| CORS 設定は必要? | 同一オリジン運用なら最小設定。BE-FE 別オリジン構成時は許可オリジンに FE オリジンを明記する必要があり、README に規約として記載推奨 |
| Sanctum API トークン(PAT)も併用する? | 併用しない(本チケットは SPA Cookie のみ) |
| `routes/web.php` の `/notifications` フルページとの関係は? | 並列で維持。Web 側は Blade + リダイレクト動作(全件閲覧 / フィルタ / ページネーション)、API 側は JSON(ポップオーバー専用、最新 20 件)。Action は共有可能(単一既読化)、一覧 Action は paginate と Web 側の配列返却で別実装 |
| エラー時のフラッシュ表示は? | ポップオーバーは JS フェッチでフラッシュを使わない(リスト維持)。フルページの既読化動作は Web 側でフラッシュ表示 |
| 動画記録の長さの目安は? | 1〜2 分でカバー: ログイン → ベルクリック → ポップオーバー開 → 全件タブ → 未読タブ切替 → 行クリック既読化 + 遷移 → 戻る → 全件既読ボタン → バッジ 0 確認 |
| 旧版の TopBar ベル動作との差は? | 現状ではベルクリックで `/notifications` フルページに直接遷移(JS なし)。本チケットでベルにフックして JS でポップオーバーを開く動作に置き換える。フルページ動線はフッターリンクで継続提供 |
| ベルバッジ初期値(ページロード時)はどう取得? | 既存の View Composer (`NotificationBadgeComposer`) が TopBar Blade に未読件数を渡し、JS は DOM の data 属性をそこから読む(1 リクエスト 1 回の `count` クエリで O(1))。受講生はこの Composer を構築せず利用する |
| TopBar 通知ベル + バッジは現状どう振る舞う? | 現状のベル Blade はクリックで `/notifications` フルページ遷移(通知基盤完成時点の状態)。バッジ表示と JS ポップオーバーは本チケットで後付け |
| 他 Feature の API(決済 / AI 相談 等)との関係は? | 独立。通知 API は notification Feature 専用で、他 Feature の API は本チケットスコープ外。Sanctum 認証基盤(CSRF Cookie + `auth:sanctum`)は本チケットで構築するが、他 Feature の API も将来同じ基盤を流用可能 |
| 通知 JSON API は認証付き? | はい。本チケットで新規実装し Sanctum Cookie 認証(`auth:sanctum`)で保護する。対象は認証ユーザー本人の通知のみで、他者の通知 ID を指定すると 403。認証なしの公開 API は設けない |
