# S-A-03 Stripe 連携(追加面談購入)

## メタ情報

| 項目 | 値 |
|---|---|
| チケット ID | `S-A-03` |
| Feature 連番 | `meeting-quota-02` |
| Feature | meeting-quota |
| 種別 | Story |
| サブカテゴリ | 新規機能の構築 |
| 難易度 | Advance |
| 工数 (h) | 16 |
| 依存チケット | `S-B-02`(面談パックマスタ管理) |

## 背景・目的

- **現状の問題**: 提供 PJ では受講生の残面談回数は受講プラン契約時に初期付与される分のみで、使い切った後は次の契約更新まで増やせない。残数 0 で予約不可になった受講生は学習リズムを崩しコーチ面談を諦めるしかなく、運営も追加収益機会を取り逃している。
- **達成したい状態**: 受講生が残数 0 になっても自力でその場で追加面談パックを購入でき、決済完了後すぐに予約画面で残数加算が反映される。運営は admin で面談パックを管理するだけで、価格 / 回数の運用を柔軟にコントロールできる。
- **価値・優先度**: 学習継続率の維持 + 追加収益動線の構築 + 外部決済連携(Stripe Checkout / Webhook 冪等性 / 署名検証)という実務で頻出する題材を兼ねる Advance スコープの代表チケット。

## ユーザーストーリー

- **受講生(student)として**、残面談回数が 0 になっても、その場で追加面談を購入してすぐ予約できる状態を期待する。なぜなら、学習リズムを崩したくないから。
- **受講生として**、購入の流れが直感的で、決済画面は信頼できる決済プラットフォームに任されている安心感を期待する。なぜなら、自分のカード情報を学習サービス側に直接渡したくないから。
- **受講生として**、決済完了後すぐに残面談回数が反映され、続けて面談予約に進める導線を期待する。なぜなら、購入してからまた残数が反映されるのを待つ体験はストレスだから。
- **受講生として**、決済が失敗 / 中断した場合は残数が変わらないことを期待する。なぜなら、課金されていないのに残数が増えるのは不安、逆に課金されたのに残数が増えないのも困るから。
- **管理者(admin)として**、決済通知が二重で到着しても残数が二重加算されないことを期待する。なぜなら、決済プロバイダ側の再送で残数会計が崩れたら監査が成立しないから。
- **管理者として**、決済の改ざんを構造的に防げる仕組みを期待する。なぜなら、決済通知を偽装した不正な残数加算を防ぎたいから。
- **コーチ(coach) / 管理者として**、自分の画面に「追加面談を購入」動線が表示されない。なぜなら、購入動線は受講生専用機能で、自分のロールに関係ないから。

## 要件

### 追加面談購入動線(受講生・学習中のみ)

- 公開中の面談パック一覧の閲覧(名前 / 面談回数 / 価格、並び順)
- 面談パックを選択して Stripe の外部決済画面へ遷移(決済処理中の購入記録が保留状態で残る)
- 決済完了画面の表示(残数加算は決済確定後に反映される案内 + ダッシュボードへの導線)
- 決済キャンセル / 中断時はダッシュボードに戻る(購入記録は保留のまま、後で期限切れ通知を受けて失敗扱いになる)

### 購入対象・購入者のガード

- 公開中以外(下書き / アーカイブ)の面談パックを指定した購入を拒否(購入動線に並ばないが URL 改ざんによる購入もブロック)
- 学習中以外のステータス(招待中 / 修了 / 退会)の受講生の購入を拒否

### 決済確定通知(認証なし・署名検証必須)

- 外部決済サービスからのイベント通知を受け取る公開窓口(署名検証で改ざんを検出)
- 決済完了通知で購入記録を成功にし、購入回数分の残面談回数を加算
- 決済失敗 / 期限切れ通知で購入記録を失敗にする(残数加算なし)
- 同一の決済完了通知が複数回届いても残数加算は 1 回のみ(冪等性)
- 対応する購入記録がない通知 / 未対応の通知種別は受信成功として無視

### 共通の振る舞い

- 残数加算が記録された瞬間から受講生の残数集計に反映される(ダッシュボード / 予約画面)
- 購入記録の作成、決済確定時の残数加算は、それぞれ単一トランザクション内で原子的に実行
- 同じ決済識別子で購入記録が重複作成されない構造保証

### 非機能要件

- 決済の改ざん防止(署名検証を必須化)
- 通貨は円のみ、都度購入(自動再課金・サブスクリプションなし)
- 決済額 / 購入回数は購入時点の値をスナップショット保存し、後からマスタを変更しても過去の購入を監査可能にする

## スコープ外

- 面談回数のサブスクリプション(自動再課金)— 都度購入のみ
- 面談回数の有効期限 — 無期限(受講プラン期間内)
- 面談回数の他者への譲渡
- 円以外の通貨対応 — 円のみ
- Stripe 以外の決済プロバイダ(PayPal / Square / Pay.jp 等)
- 返金フロー(受講生主導)— admin が Stripe ダッシュボードから手動操作(返金通知受信で購入記録の状態は更新しうるが、残数返却の自動記録はせず admin の手動判断に委ねる)
- 面談パックの在庫管理(デジタル商品で無限在庫)
- 管理者による面談回数の手動付与 UI — 提供 PJ に既存の付与処理を使うが、本チケットでは購入動線のみを扱う
- 残数ゼロ通知 / 購入リマインダーのプッシュ通知
- 面談パックのまとめ買い割引 / クーポンコード — 単品購入のみ
- 領収書 / 請求書 PDF 自動発行 — Stripe ダッシュボードからの手動操作で代替
- 多段階の承認フロー(法人契約等)

## 受け入れ条件

- [ ] 受講生(学習中)が `/meeting-quota/checkout` で公開中の面談パック一覧(名前 / 面談回数 / 価格、並び順)を閲覧し、面談パックを選択して購入を実行すると Stripe Checkout の外部決済画面に遷移する。このとき保留状態の購入記録が作成される
- [ ] 受講生が Stripe で決済を完了して LMS の決済完了画面に戻ると、購入完了の案内 + ダッシュボードへの導線が表示される(残数加算は決済確定後に反映される旨を含む)
- [ ] 公開中以外(下書き / アーカイブ)の面談パックを指定した購入は 422 で拒否され、購入記録は作成されない
- [ ] 正当な署名付きの決済通知は受信され 200 が返る。署名検証に失敗した通知は 400 が返り、購入記録 / 残数は一切変更されない
- [ ] 決済完了通知の受信時、対応する購入記録が成功状態に遷移(決済確定日時 + 決済識別子を記録)し、同一トランザクション内で購入回数分の残数加算記録が作成される。加算直後から受講生の残数集計(ダッシュボード / 予約画面の数値)に反映される
- [ ] 決済失敗 / 期限切れ通知の受信時、対応する保留状態の購入記録が失敗状態に遷移し、残数加算は行われない
- [ ] 同一の決済完了通知が再送されても残数加算は 1 回のみ(残数会計が崩れない)、同一の決済識別子で購入記録が重複作成されない。決済完了後に失敗通知が逆順で到着しても、成功状態の購入記録は失敗に巻き戻らない
- [ ] 対応する購入記録が存在しない通知 / 未対応の通知種別を受信した場合は 200 を返してスキップする(Stripe 側の再送を止める)
- [ ] コーチ / 管理者 / 学習中以外の受講生(招待中 / 修了 / 退会)が購入の選択画面・購入アクションにアクセスすると 403、購入記録は作成されない
- [ ] 本チケットの機能に対するテスト (Unit / Feature 等) が実装されている

## 実装方針(参考)

> **本セクションは「参考」、受講生ごとに異なる実装を許容**(AC を満たせば実装手段は問わない)。ただし **「(必須)」マーカー付きサブセクション**(インターフェース / データモデル > 初期データ Seeder)は AC・採点・動作確認のベース、ここに記載した内容を正確に実装する。

### インターフェース(必須)

**エンドポイント**:

| HTTP | パス | 認可 | 振る舞い |
|---|---|---|---|
| GET | `/meeting-quota/checkout` | 受講生(学習中)のみ | 公開中の面談パック一覧画面を表示 |
| POST | `/meeting-quota/checkout` | 受講生(学習中)のみ | Stripe Checkout Session を作成 + 保留状態の購入記録を INSERT + 外部リダイレクト(`redirect()->away($session->url)`) |
| GET | `/meeting-quota/success?session_id={CHECKOUT_SESSION_ID}` | 受講生(学習中)のみ | 決済完了画面(`session_id` クエリから対応する購入記録を取得して表示) |
| POST | `/webhooks/stripe` | 認証なし(署名検証のみ) | 署名検証 → Event 配列を `StripeWebhook\HandleAction` へ委譲、200 + JSON `{received: true}`。`type` 欠落等の不正 payload は 400 `{received: false}` |

> Stripe Checkout の `success_url` は `route('meeting-quota.success').'?session_id={CHECKOUT_SESSION_ID}'`(Stripe がプレースホルダを置換)、`cancel_url` は `route('dashboard.index')`。決済キャンセル時は受講生をダッシュボードへ戻す。

**ミドルウェア**: checkout 3 ルートに `auth` + `role:student` + `active-learning`(`meeting-quota` プレフィックス)。Webhook ルートは認証なし + `stripe.signature`(署名検証)+ CSRF 除外。

### データモデル(新規テーブル時のみ)

**エンティティ**:

| エンティティ (Model) | 主要属性 | 関係性 | 制約 |
|---|---|---|---|
| 決済 (`Payment`) | 購入者 / 決済種別(`extra_meeting_quota` 固定) / 対象面談パック / Stripe PaymentIntent ID / Stripe Checkout Session ID / 決済額(円スナップショット) / 購入回数(スナップショット) / 状態 / 決済確定日時 | 購入者 受講者(User)N-1 / 面談パック N-1 / 残数取引(`MeetingQuotaTransaction`)1-N(`related_payment_id` 経由) | 購入者・面談パック FK `restrictOnDelete` / **SoftDelete 採用(会計監査要件)** / Checkout Session ID は UNIQUE(NOT NULL)/ PaymentIntent ID は UNIQUE(nullable、決済成功時にセット) |

> `meeting_quota_transactions` は提供 PJ 既存(初期付与 / 消費 / 返却 / 管理者付与の取引を記録)。本チケットでは新規カラムを追加せず、購入(`Purchased`)取引の INSERT で利用する。

**Enum**:

- 決済状態 (`PaymentStatus`、新規): 処理中 (`Pending`) / 完了 (`Succeeded`) / 失敗 (`Failed`) / 返金 (`Refunded`)。`label()` で「処理中」「完了」「失敗」「返金」
- 残数取引種別 (`MeetingQuotaTransactionType`、提供 PJ 既存): 本チケットでは 購入 (`Purchased`) を加算(正値)で使用。他に 初期付与 (`GrantedInitial`) / 消費 (`Consumed`) / 返却 (`Refunded`) / 管理者付与 (`AdminGrant`)

**インデックス用途**:

- 購入者(`user_id`、ユーザー単位の購入履歴取得)
- 状態 × 決済確定日時(`(status, paid_at)` 複合、状態別の決済一覧)
- Checkout Session ID / PaymentIntent ID(UNIQUE、Webhook 冪等性ガードと決済識別子の重複防止)

**初期データ Seeder(必須)** (`PaymentSeeder`、新規):

- 固定 student(`student@certify-lms.test`): 完了決済 1 件(5 回パック、購入取引連動)+ 保留決済 1 件(1 回パック)+ 管理者付与取引 1 件
- demo 受講生(学習中)× 6: 完了 / 完了 / 保留 / 失敗 / 返金 / 管理者付与 のパターンを循環付与(返金は購入取引 + 返却取引の相殺ペアで投入)
- 動作確認用途: 決済状態 4 種の履歴表示 / 完了決済が残数に反映され保留・失敗は反映されない確認 / 返金の取引相殺
- DatabaseSeeder 順序: `UserSeeder` → `MeetingPackSeeder` → `MentoringSeeder`(消費 / 返却取引)→ 本 Seeder

> 各購入記録は **テスト用のダミー Stripe 識別子**(`cs_test_xxx` / `pi_test_xxx`)で投入される。Webhook 受信の実機確認には Stripe CLI(`stripe listen` + `stripe trigger`)で疑似イベントを送信する(手順は README に記載)。

### コンポーネント

**Controller** (`app/Http/Controllers/`)
- `MeetingQuotaCheckoutController` — 購入動線(select / create / success)。SKU 選択画面 / Checkout Session 発行 / 決済完了画面
- `Webhooks\StripeWebhookController` — Webhook 受信窓口(handle)。署名検証済 Event を Action へ委譲

**FormRequest** (`app/Http/Requests/MeetingQuota/`)
- `CheckoutRequest` — 購入リクエスト(認可 = `purchase-meeting-quota` Gate + 公開中パックの存在検証)

**Action** (`app/UseCases/`、Advance 範囲 = Action 採用)
- `MeetingQuota\CreateCheckoutSessionAction` — 公開中 / 学習中の二重防衛 → Stripe Checkout Session 作成 → 保留決済 INSERT → `checkout_url` 返却
- `MeetingQuota\PurchaseQuotaAction` — 購入回数分の残数加算取引(`Purchased`)INSERT(Webhook 処理から呼ばれる、トランザクション境界は呼出側責務)
- `StripeWebhook\HandleAction` — 3 イベント分岐(completed / expired / payment_failed)+ 冪等性ガード(`lockForUpdate`)

**Middleware** (`app/Http/Middleware/`)
- `VerifyStripeSignature` — HMAC-SHA256 署名検証、成功時に Event 配列を `stripe_event` キーで Request に merge、失敗時は例外

**Policy** (`app/Policies/`)
- `MeetingQuotaPolicy` — `purchase`(受講生 かつ 学習中ステータスのみ)。`purchase-meeting-quota` Gate として登録

**Service** (`app/Services/`、既存)
- `MeetingQuotaService` — 残数集計(`remaining()` = `User.max_meetings + SUM(消費 / 返却 / 購入 / 管理者付与の取引)`)。購入取引が加算され即時反映される

**Model + Enum** (`app/Models/`, `app/Enums/`)
- `Payment` / `PaymentStatus`(新規)、`MeetingQuotaTransaction` / `MeetingQuotaTransactionType`(既存)、`MeetingPack` に `payments()` リレーション追加 / `User` に `payments()` リレーション追加

**View**(提供 PJ 既存、ロック対象)
- `resources/views/meeting-quota/{checkout-select,success}.blade.php`

**Migration / Seeder / Factory**
- `database/migrations/*_create_payments_table.php`(新規、ULID + UNIQUE 制約)
- `database/seeders/PaymentSeeder.php` / `database/factories/PaymentFactory.php`(新規、状態 state: pending / succeeded / failed / refunded)

**例外** (`app/Exceptions/MeetingQuota/`)
- `StripeWebhookSignatureInvalidException`(400)/ `MeetingPackNotPublishedException`(422)/ `UserNotInProgressException`(403、`render()` で HTML は redirect + flash)

**設定 / 登録**
- `config/services.php` の `stripe`(`secret` / `publishable_key` / `webhook_secret`)+ `AppServiceProvider` で `StripeClient` の singleton binding + `AuthServiceProvider` で `purchase-meeting-quota` Gate
- `app/Http/Kernel.php` の `$middlewareAliases` に `'stripe.signature' => VerifyStripeSignature::class` + `app/Http/Middleware/VerifyCsrfToken.php` の `$except` に `webhooks/stripe`(Laravel 10 構成)

**Routes** (`routes/web.php`)
- `meeting-quota.*`(checkout.select / checkout.create / success、`auth` + `role:student` + `active-learning`)+ `webhooks.stripe`(認証なし、`stripe.signature`)

### 異常系

**入力検証**(FormRequest クラス名 + ルール記法):

- 購入 (`MeetingQuota\CheckoutRequest`):
  - `meeting_pack_id`: `required` / `ulid` / `Rule::exists('meeting_packs', 'id')->where('status', 'published')`
- Webhook は FormRequest を使わず、`VerifyStripeSignature` ミドルウェアが `Stripe-Signature` ヘッダ + `config('services.stripe.webhook_secret')` で HMAC-SHA256 検証

**業務例外**(状態ベースガード + HTTP ステータス):

- 公開中以外の面談パック購入 (`MeetingPackNotPublishedException`) → 422
- 学習中以外の受講生の購入 (`UserNotInProgressException`) → 403(HTML 経由は `render()` で前画面へ redirect + flash error に変換)
- Webhook 署名検証失敗 / シークレット未設定 (`StripeWebhookSignatureInvalidException`) → 400

**外部 API / 冪等性**:

- `checkout.session.completed`: `stripe_checkout_session_id` で購入記録を `lockForUpdate` SELECT →(1)未存在なら警告ログ + skip /(2)既に成功なら skip(冪等性ガード)/(3)成功へ UPDATE(`stripe_payment_intent_id` / `paid_at` セット)→(4)`PurchaseQuotaAction` で残数加算
- `payment_intent.payment_failed` / `checkout.session.expired`: `WHERE status=pending` 条件付き UPDATE で、既に成功済の購入記録を失敗へ巻き戻さない(到着順逆転への耐性)
- 対応決済不在 → 警告ログ + 200。未対応イベント種別 → 無視 + 200(Stripe の再送を止める)

### 設計判断

- **Stripe 公式 SDK(`stripe/stripe-php`)採用**: `StripeClient` を `AppServiceProvider::register()` で singleton 登録し Action に constructor injection。Checkout Session は `price_data` 動的生成(都度生成型)で `MeetingPack.price` を `unit_amount`、通貨 `jpy`、`mode=payment` で渡す
- **署名検証を Middleware に分離**: `VerifyStripeSignature` が `Stripe\Webhook::constructEvent` で HMAC-SHA256 検証 → Event を配列化して merge。Controller / Action は検証済 Event を受け取るだけ。検証失敗は 400 で再送を許容し設定不整合を早期発見
- **Webhook 冪等性ガード**: `stripe_checkout_session_id` を `lockForUpdate` で取得し既に成功状態なら処理スキップ。決済完了と失敗の逆順到着には失敗側処理に `WHERE status=pending` を付けて成功済を巻き戻さない
- **二重防衛(購入時の状態再検証)**: FormRequest の公開中検証(`Rule::exists` の `where status=published`)を通過しても `CreateCheckoutSessionAction` 内で `MeetingPack.status` / `User.status` を再検証(`MeetingPackNotPublishedException` / `UserNotInProgressException`)
- **スナップショット保存**: 購入記録の決済額 / 購入回数は面談パックの値コピー(参照ではない)。後から admin が価格 / 回数を変えても過去の購入は購入時点の値で監査可能
- **SoftDelete 採用(会計監査要件)**: `payments` は税務監査 / 返金履歴の整合性のため SoftDelete を採用(マスタ系の `MeetingPack` が物理削除なのとは判断軸が異なる)。返金は `refunded` 状態への遷移を備えるが残数返却の自動記録はせず admin 手動運用に委ねる
- **Webhook は受信成功で 200**: 内部処理(対応決済不在 / 未対応イベント)で例外を出すと Stripe が再送し続けるため、未対応はログのみで 200。署名検証失敗のみ 400
- **CSRF 除外 + 認証なし**: `webhooks/stripe` は外部 POST のため CSRF 除外 + 認証ミドルウェア不適用。署名検証が唯一の正当性保証
- **テスト観点**: Webhook の状態機械(完了 / 失敗 / 期限切れ / 冪等再送 / 逆順到着 / 対応決済不在 / 未対応種別)の網羅 + 署名検証(正規 / 不正 / シークレット未設定)+ 購入時の二重防衛が本チケット固有の観点。`StripeClient` は Mockery でスタブし実 API に通さない

## 補足

### 想定 Q&A

| 質問 | 回答 |
|---|---|
| 購入できるロールは? | 受講生(学習中)のみ。コーチ / 管理者 / 学習中以外の受講生(招待中 / 修了 / 退会)は 403 |
| 認可拒否時の HTTP ステータスは 403 / 404? | 403。学習中以外の受講生は `UserNotInProgressException` 経由(HTML 経由なら前画面へリダイレクト + フラッシュエラーに変換) |
| 公開停止 / 下書きの面談パックの ID を URL に指定したら? | 422(`MeetingPackNotPublishedException`、購入動線に並ばないものの URL 改ざんによる購入をブロック) |
| Stripe Checkout の決済画面は LMS 内で表示する? | 表示しない。Stripe が提供する外部決済画面に `redirect()->away()` で遷移する(信頼性と PCI DSS 対応を Stripe に委譲) |
| 決済完了後の戻り先は? | `/meeting-quota/success?session_id={CHECKOUT_SESSION_ID}`。決済完了画面で「残数加算は決済確定後に反映される」旨を案内 + ダッシュボードへの導線 |
| 決済キャンセル時の戻り先は? | `/dashboard`。購入記録は保留のまま、後で期限切れ通知(`checkout.session.expired`)で失敗へ遷移 |
| 残数加算のタイミングは? | 決済完了通知(`checkout.session.completed`)の受信時。受講生が決済完了画面を見た時点ではまだ反映されていない可能性があるため、案内文に「決済情報の確定後に反映」を明記 |
| Webhook の認証は? | 認証ミドルウェアは適用しない(外部の公開エンドポイント)。代わりに `Stripe-Signature` ヘッダと `STRIPE_WEBHOOK_SECRET` を使った HMAC-SHA256 署名検証を必須化 |
| 署名検証失敗時の HTTP ステータスは? | 400(`StripeWebhookSignatureInvalidException`)。Stripe 側は再送するが、設定不整合の早期発見を優先 |
| 同じ Webhook が再送されたら残数は二重加算される? | されない。`stripe_checkout_session_id` で購入記録を `lockForUpdate` し、既に成功状態なら処理スキップ(冪等性ガード) |
| 決済成功と失敗が逆順で到着したら? | 失敗イベント処理時に `WHERE status=pending` 条件を付けるため、既に成功状態の購入記録は失敗に巻き戻らない |
| 対応する購入記録が LMS にないイベントが来たら? | 警告ログを残して 200 を返す(Stripe 側の再送を止める) |
| 未対応の Stripe イベント種別が来たら? | 無視して 200 を返す(将来対応のためログも残さず、Stripe 側の再送を止める) |
| 返金フローは? | admin が Stripe ダッシュボードから手動操作。返金通知受信で購入記録を `refunded` に更新する経路は本チケットでは扱わない(残数返却の自動記録もしない、admin の手動判断に委ねる) |
| 決済記録の SoftDelete 採用理由は? | 会計監査要件(税務 / 返金履歴の整合性)+ 運用復旧時の参照用 |
| 決済額 / 購入回数は面談パックを参照せずスナップショット? | スナップショット。面談パックの価格 / 回数を後から admin が変えても、過去の購入記録は購入時点の値で監査可能 |
| Stripe SDK のバージョン指定は? | `^14.x` 推奨(`StripeClient` / `Webhook::constructEvent` API が安定)。Wave 0b で確定済なら本チケット範囲外 |
| `.env` に必要な Stripe キーは? | `STRIPE_SECRET_KEY`(API キー)/ `STRIPE_PUBLISHABLE_KEY`(フロント用、本チケットでは Checkout ベースのため未使用)/ `STRIPE_WEBHOOK_SECRET`(Webhook 署名検証用)。Stripe ダッシュボード > Developers > API keys / Webhooks で発行 |
| 通貨は? | 円(`jpy`)のみ。Checkout の `line_items.price_data.currency = 'jpy'` で固定 |
| 決済方法は? | Stripe デフォルト(クレジットカード / Apple Pay / Google Pay 等、Stripe ダッシュボードの設定に従う) |
| 受講生が複数タブで同時に購入を試みたら? | Checkout Session は各回独立して作成され、2 つの購入記録が保留状態で INSERT される。片方だけ完了した場合、もう片方は `checkout.session.expired` 受信で失敗へ遷移 |
| 領収書 / 請求書は? | Stripe ダッシュボードからの手動操作で対応(自動 PDF 発行は本チケットスコープ外) |
| ローカル開発で Webhook を試すには? | `stripe listen --forward-to localhost:8000/webhooks/stripe` + `stripe trigger checkout.session.completed` 等で疑似イベント送信(README に手順を記載) |
| フラッシュ / エラー文言の推奨は? | 公開停止パック購入試行「公開中の面談パックのみ購入できます。」/ 学習中以外購入試行「受講中のユーザーのみ追加面談を購入できます。」/ 署名検証失敗「Stripe Webhook の署名検証に失敗しました。」(適切な日本語であれば文言の細部は採点対象外) |
| 並び順は? | 公開中の面談パックを `sort_order` 昇順 → `created_at` 降順で並べる(admin マスタ画面の表示順と同じ並び順) |
| 1 トランザクションで複数パックを同時購入できる? | できない(単品購入のみ、まとめ買い割引もスコープ外) |
