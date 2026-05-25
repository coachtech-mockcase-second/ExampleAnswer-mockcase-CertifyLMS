# S-B-04 通知基盤(Laravel Notification、DB + Mail)

## メタ情報

| 項目 | 値 |
|---|---|
| チケット ID | `S-B-04` |
| Feature 連番 | `notification-01` |
| Feature | notification |
| 種別 | Story |
| サブカテゴリ | 新規機能の構築 |
| 難易度 | Basic |
| 工数 (h) | 12 |
| 依存チケット | (なし) |

## 概要

受講生 / コーチ宛の通知を一元的に受け取る通知基盤を新規実装する。Laravel Notification を採用し、データベース + メールの 2 チャネル固定で配信、TopBar の通知ベル + 未読件数バッジ + 通知一覧画面 + 既読化動線を提供する。chat メッセージ受信 / Q&A 回答受信 / 面談予約 / 面談キャンセル の 4 種類の業務イベントを発火点として、既存の各 Feature から本通知基盤を呼び出して通知を配信する。

## 背景・目的

- **現状の問題**: 提供 PJ には chat / Q&A / 面談予約 / 面談キャンセル の各業務操作が完成しているが、当事者(やり取りの相手方 / 担当コーチ等)に対する通知配信が無く、ログインしてその画面を開かなければ新着 / 状態変更に気付けない。受講生は学習中に他作業へ移動できず、コーチは複数受講生の対応漏れリスクがある。
- **達成したい状態**: 業務イベントが発生したらメールで即時配信し、ログイン後は TopBar の通知ベル(未読件数バッジ)と通知一覧画面で時系列に既読 / 未読を確認できる。既読化動線(行クリック既読化 + 一括既読化)で受信者が能動的に整理できる。
- **価値・優先度**: 通知基盤は本 LMS の運用品質を底上げするインフラ機能。本チケットが揃わないと、受講生 / コーチが受動的に画面更新を待つ運用になり、Pro 生として現場で通用する LMS の体裁が整わない。

## ユーザーストーリー

- **受講生(student)として**、自分宛の chat 新着メッセージ / Q&A 回答 / 面談キャンセル通知 を一覧で確認したい。なぜなら、複数の Feature を横断して未対応事項を取りこぼしたくないから。
- **コーチ(coach)として**、自分宛の chat 新着メッセージ / 担当受講生の面談予約 / 面談キャンセル通知 を一覧で確認したい。なぜなら、複数受講生を効率的にフォローしたいから。
- **受講生 / コーチとして**、未読通知数が TopBar 通知ベルに数字バッジで常時見えていてほしい。なぜなら、画面遷移しなくても未対応の有無を把握したいから。
- **受講生 / コーチとして**、通知行をクリックしたら自動的に既読化されて該当画面に遷移してほしい。なぜなら、既読化忘れで未読件数が永遠に減らない事態を避けたいから。
- **受講生 / コーチとして**、一括「全件既読にする」ボタンが欲しい。なぜなら、過去の未読が大量に残っているとき個別クリックでは追いつかないから。
- **管理者(admin)として**、自分宛の通知は受け取らない設計を期待する。なぜなら、運用情報は管理画面で集約確認するので通知ベルが鳴り続けると逆にノイズになるから。

## やること

### 通知一覧 / 既読化(全受信者ロール共通)

- **一覧表示**: 認証済受講生 / コーチが通知一覧画面を開くと自分宛の通知が時系列降順で表示される、管理者は通知一覧画面を開いても自分宛通知が 1 件も存在しないため空表示
- **タブ切替**: 「全件」「未読のみ」の 2 タブで絞り込み、URL クエリパラメータでタブ状態が保持されページネーションにも引き継がれる
- **行クリック既読化 + リンク遷移**: 未読通知行をクリックすると既読化され、通知種別に応じた業務画面(chat ルーム / Q&A スレッド / 面談詳細)へリダイレクトされる
- **行クリック - 既読済**: 既読済通知行をクリックしても再既読化(no-op)で済み、業務画面へリダイレクトされる
- **全件既読化**: 「全件既読にする」フォーム POST で自分宛の全未読通知を一括既読化、未読件数が 0 になる
- **認可**: 他人宛の通知の既読化 / 閲覧アクションにアクセスすると 403

### TopBar 通知ベル + 未読件数バッジ

- **未読件数表示**: TopBar 通知ベルに未読件数バッジ(`<x-badge variant="danger" size="sm">`)を重ね、未読 0 件のときはバッジ非表示
- **99+ 表示**: 未読件数が 100 件以上のときはバッジ表示を「99+」に固定
- **ベルクリック遷移**: ベルアイコンをクリックすると通知一覧画面に遷移する(本チケットでは JS によるドロップダウン展開は対象外。サイドバーの「通知」項目からも同じ画面に遷移可)

### chat 新着メッセージ通知

- **発火タイミング**: chat ルームで新規メッセージが投稿された際、送信者を除く全 chat ルームメンバーに通知を配信
- **配信チャネル**:
  - 受講生 → コーチ送信: ルーム内全コーチに DB + メール
  - コーチ → 受講生送信: 受講生に DB + メール
  - コーチ → 他コーチ送信: 他コーチに DB のみ(連絡過剰防止のため Mail を抑制)
- **配信スキップ**: 受信者の利用状態が「受講中」でない(退会済 / 修了済 / 招待中)場合は配信スキップ
- **遷移先**: 通知行クリックで該当 chat ルーム画面

### Q&A 回答受信通知

- **発火タイミング**: Q&A 掲示板で質問スレッドに新規回答が投稿された際、スレッド投稿者(受講生)に通知を配信
- **配信チャネル**: DB + メール
- **自己回答スキップ**: 回答者 = スレッド投稿者の場合は配信しない(スレッド投稿者本人による自己回答 / 補足記述に対しては通知を発火させない)
- **配信スキップ**: スレッド投稿者の利用状態が「受講中」でない場合は配信スキップ
- **遷移先**: 通知行クリックで該当 Q&A スレッド詳細画面

### 面談予約通知

- **発火タイミング**: 受講生が面談を予約した際、担当コーチに通知を配信(受講生宛は予約 UI で即時確認できるため発火しない)
- **配信チャネル**: DB + メール、件名 / 本文に予約日時 / 受講生名 / 資格名 を含める
- **配信スキップ**: 担当コーチの利用状態が「受講中」でない場合は配信スキップ
- **遷移先**: 通知行クリックでコーチの担当面談一覧画面

### 面談キャンセル通知

- **発火タイミング**: 当事者(受講生 or 担当コーチ)が予約済面談をキャンセルした際、相手方に通知を配信(自己通知はしない)
- **配信チャネル**: DB + メール、本文にキャンセル実行者の役割(受講生 / コーチ)を含める
- **配信スキップ**: 相手方の利用状態が「受講中」でない場合は配信スキップ
- **遷移先**: 通知行クリックで面談一覧画面(受講生は受講生向け / コーチはコーチ向け)

### 共通の振る舞い

- 管理者(admin)宛通知は発火しない(全 4 種で受信対象から admin は除外)
- 通知 1 行は ULID 主キー(時系列ソート + 主キー再利用)、Laravel 標準の `notifications` テーブルに格納される
- 通知行データ(`data` JSON)に共通キー(通知種別 / タイトル / プレビュー / 遷移先ルート名 + パラメータ)を含めて、業務 Model を再 fetch せず一覧画面が描画できる(N+1 回避)
- メール件名は「【Certify LMS】」プレフィックスで統一

## やらないこと

- **JSON API**(`/api/v1/notifications` 認証なし API) — `S-B-05` で扱う
- **Sanctum Cookie 認証 + JS フロント通知ポップオーバー / リアルタイム push** — `S-A-05` で扱う(Pusher Broadcasting + JS fetch + モーダル展開)
- **管理者からのお知らせ配信機能**(Announcement Entity / 配信フォーム / 一斉配信) — `S-B-09` で扱う
- **面談リマインダー通知**(前日 18:00 / 1 時間前のリマインダー Schedule Command + `MeetingReminderNotification`)— mentoring Feature 拡張の責務として本チケットでは扱わない(通知基盤の発火フックを利用する側として将来別チケットで扱う)
- **管理者宛通知** — 設計上 admin は通知の発火対象から除外、運用情報は管理画面で集約確認
- **通知種別 × チャネルごとの ON/OFF 設定 UI** — MVP では全通知が DB + メール固定送信
- **通知の論理削除 / 物理削除 UI** — 既読化のみ提供、削除動線は持たない
- **モバイルプッシュ通知** — 教育 PJ スコープ外
- **メール配信の Queue 非同期化** — 本チケットでは同期送信で実装(Queue Worker 運用は別チケット 工夫として位置づけ)

## Seeder 設計

> `migrate:fresh --seed` 直後に動作確認できるよう、通知種別と既読 / 未読を網羅したシナリオを投入する。

**前提**(他 Seeder で投入される想定): 固定受講生(`student@certify-lms.test`)/ 固定コーチ(`coach@certify-lms.test`)/ デモ受講生 × 数名 / デモコーチ × 数名 / chat ルーム × 複数 / Q&A スレッド + 回答 × 複数 / 面談(予約済 / キャンセル済)× 複数

`NotificationSeeder`(`notifications` テーブルに直接 INSERT、Notification 発火副作用は走らせない):

| レコード分類 | 内容 | 動作確認用途 |
|---|---|---|
| 固定受講生 × 全種別 | chat 受信 / Q&A 回答受信 / 面談キャンセル の 3 種類を最低 1 件ずつ、既読 / 未読 を半々に振る | 通知一覧の全種別表示確認 / バッジ件数確認 / 行クリック既読化 + 遷移確認 |
| 固定コーチ × 全種別 | chat 受信 / 面談予約 / 面談キャンセル の 3 種類を最低 1 件ずつ、既読 / 未読 を半々に振る | 同上(コーチ視点) |
| デモ受講生 × 数件 | chat / Q&A 回答 の混在通知を各受講生に投入、既読 / 未読 を交互に | 認可分岐確認(他受講生宛通知の閲覧 403 / 既読化 403) |
| デモコーチ × 数件 | 面談予約 / chat の混在通知を各コーチに投入、既読 / 未読 を交互に | コーチ別の認可分岐確認 |

- **既読 / 未読の振り方**: `read_at` を null と過去日付(`created_at + 2 時間`)で交互に設定し、未読バッジ件数 / 未読タブのフィルタ動作を担保する
- **作成日時の散らし**: 1 日前 / 2 日前 / 3 日前 ... と日付を散らしてページネーション(20 件 / ページ)の動作を確認できるようにする
- **DatabaseSeeder への追加順序**: 既存ドメインデータ Seeder(`UserSeeder` / `EnrollmentSeeder` / `MentoringSeeder` / `ChatSeeder` / `QaBoardSeeder`)の **後**(本 Seeder は既存ドメインデータを参照して通知行を生成する)

## 受け入れ条件

- [ ] **一覧 - 認可**: 認証済受講生 / コーチが通知一覧画面を開くと自分宛の通知が時系列降順で表示される
- [ ] **一覧 - 未認証**: 未認証ユーザーが通知一覧画面にアクセスするとログイン画面にリダイレクトされる
- [ ] **一覧 - タブ切替**: 「全件」「未読のみ」タブで絞り込みでき、選択中タブはページネーション遷移後も保持される
- [ ] **一覧 - ページネーション**: 通知が 20 件を超えるとページネーションが表示され、次ページに遷移してもタブ状態が引き継がれる
- [ ] **TopBar - 未読バッジ表示**: 未読通知が 1 件以上あるとき TopBar 通知ベルに未読件数バッジが表示され、未読 0 件のときはバッジ非表示
- [ ] **TopBar - 99+ 表示**: 未読件数が 100 件以上のときバッジ表示が「99+」に固定される
- [ ] **行クリック既読化 + 遷移**: 未読通知行をクリックすると既読化され、通知種別に応じた業務画面(chat ルーム / Q&A スレッド / 面談一覧)へリダイレクトされる
- [ ] **行クリック - 認可拒否**: 他人宛通知の既読化 URL を直叩きすると 403
- [ ] **全件既読化**: 「全件既読にする」ボタンを押すと自分宛の全未読通知が一括既読化され、通知一覧画面にリダイレクト + フラッシュ表示、未読件数が 0 になる
- [ ] **chat 通知 - 受講生送信**: 受講生がメッセージ送信時、ルーム内コーチ全員に DB + メール通知が発火する
- [ ] **chat 通知 - コーチ送信 (受講生宛)**: コーチが受講生宛にメッセージ送信時、受講生に DB + メール通知が発火する
- [ ] **chat 通知 - コーチ間**: コーチが他コーチ宛にメッセージ送信時、他コーチに DB 通知のみが発火する(メールは送信されない)
- [ ] **chat 通知 - 送信者除外**: 送信者本人には自分の送信に対して通知が発火しない
- [ ] **Q&A 通知 - 回答受信**: スレッド投稿者以外が回答投稿時、投稿者に DB + メール通知が発火する
- [ ] **Q&A 通知 - 自己回答スキップ**: 投稿者本人が自己回答した場合、通知が発火しない
- [ ] **面談予約通知**: 受講生が面談を予約した際、担当コーチに DB + メール通知が発火する(受講生本人には発火しない)
- [ ] **面談キャンセル通知 - 受講生キャンセル**: 受講生がキャンセルした際、担当コーチに DB + メール通知が発火する
- [ ] **面談キャンセル通知 - コーチキャンセル**: コーチがキャンセルした際、受講生に DB + メール通知が発火する
- [ ] **受信者状態スキップ**: 受信者の利用状態が「受講中」でない(退会済 / 修了済 / 招待中)場合、その受信者への通知配信はスキップされる
- [ ] **管理者除外**: 管理者は本 4 種いずれの通知も受信しない(自分宛通知が `notifications` に INSERT されない)

## 実装方針

> **参考設計の一例**。受け入れ条件を満たせれば実装手段は問わない。受講生は提供 PJ コード + ヒアリングで自分の設計を組み立てる。

### 主要 URL

| メソッド | パス | 振る舞い |
|---|---|---|
| GET | `/notifications` | 通知一覧(時系列降順 + タブ「全件 / 未読のみ」+ 20 件 / ページ ページネーション、未読件数を画面上部に表示) |
| POST | `/notifications/{notification}/read` | 単一通知の既読化、成功時に通知行の `data.link_route` に従って業務画面へリダイレクト。他人宛なら 403 |
| POST | `/notifications/read-all` | 自分宛全未読通知の一括既読化、成功時 `/notifications` リダイレクト + フラッシュ「すべての通知を既読にしました。」 |

> TopBar 通知ベル(`<x-icon name="bell" />` + 未読バッジ)は **`<a href="{{ route('notifications.index') }}">`** のリンクとして配置し、Basic 範囲では JS によるポップオーバー展開は持たない(`S-A-05` で JS フロント拡張)。

### データモデル

> **既存テーブル**(Laravel 標準 `notifications` テーブル、本チケットの Migration として新規作成)。
> **メモ**: `notifications` テーブル自体は Laravel が用意するが、本チケットの Migration として `php artisan notifications:table` 相当 + ULID 化 + 複合 INDEX を整える。

`notifications`(Laravel 標準 + ULID 拡張):

| カラム | 型 | NOT NULL | FK / UNIQUE | 補足 |
|---|---|:---:|---|---|
| id | ulid | ✓ | PK | `$table->ulid('id')->primary()`、`BaseNotification::__construct` で事前確定 |
| type | string | ✓ | | Notification クラスの FQCN(例: `App\Notifications\Chat\ChatMessageReceivedNotification`) |
| notifiable_type | string | ✓ | morph | `App\Models\User` 固定 |
| notifiable_id | ulid | ✓ | morph | 受信者 User の ID(`$table->ulidMorphs('notifiable')` で ULID 対応 morph を生成) |
| data | text | ✓ | | JSON 文字列。共通キー(`notification_type` / `title` / `message` / `link_route` / `link_params`)+ 種別固有キー |
| read_at | timestamp | | | 既読化日時、未読時 NULL |
| created_at | timestamp | | | `$table->timestamps()` |
| updated_at | timestamp | | | `$table->timestamps()` |

- **インデックス**: `(notifiable_type, notifiable_id, read_at)` 複合(自分宛 + 未読絞り込みクエリの高速化)/ `created_at`(時系列ソート)
- **`data` JSON の共通キー**: `notification_type`(種別の業務識別子、文字列) / `title`(通知タイトル) / `message`(プレビュー 100 字) / `link_route`(遷移先ルート名) / `link_params`(遷移パラメータ配列)
- **`data` JSON の種別固有キー**:
  - chat: `chat_room_id` / `chat_message_id` / `sender_user_id` / `sender_name` / `sender_role` / `body_preview`
  - Q&A: `qa_thread_id` / `qa_reply_id` / `replier_user_id` / `replier_name` / `thread_title` / `body_preview`
  - 面談予約: `meeting_id` / `enrollment_id` / `coach_user_id` / `student_user_id` / `student_name` / `scheduled_at` / `topic`
  - 面談キャンセル: 上記 + `actor_user_id` / `actor_role`
- **N+1 回避**: 通知一覧描画時、業務 Model を再 fetch せず `data` JSON 内のキャッシュ値(`sender_name` / `thread_title` 等)を直接表示する

### バリデーション

`IndexRequest`(通知一覧クエリ):

| 入力項目 | ルール | 推奨エラーメッセージ例 |
|---|---|---|
| tab | nullable / string / in:all,unread | (バリデーション失敗時の表示は不要、デフォルト `all` にフォールバック推奨) |
| page | nullable / integer / min:1 | (同上) |

既読化(単一 / 全件)はパス変数 / 認証ユーザーのみで完結するため FormRequest は最小限。

### 認可設計

**Policy**: `NotificationPolicy`

| メソッド | 判定 |
|---|---|
| view | `$notification->notifiable_id === $user->id` (自分宛のみ ✅、他人宛 ❌、ロール無関係) |
| update | 同上(既読化アクション用) |

- 通知一覧画面 / 全件既読化エンドポイントはルート Middleware `auth` のみで保護(ロール制約なし、自分宛通知は `auth()->user()->notifications()` で自動絞り込みされるため)
- 単一既読化エンドポイントは Controller 内 `$this->authorize('update', $notification)` を呼ぶ

### テスト観点

| 種別 | 観点 |
|---|---|
| Unit | 各 Notification クラスの `via()`(chat 通知のコーチ間 mail 抑制を含む)/ `toDatabase()` / `toMail()` の戻り値検証(タイトル / プレビュー / `data` JSON の共通キー網羅)/ `BaseNotification::__construct` の ULID 事前確定 |
| Feature(Web)| 通知一覧画面の認可分岐(受講生 / コーチ / 未認証)/ タブ切替動作(全件 / 未読のみ)/ ページネーション + タブ状態引き継ぎ / 行クリック既読化 + 遷移先パス / 全件既読化フォーム POST / 他人宛通知 URL 直叩きで 403 / TopBar バッジ件数表示(未読 0 / 99+ 含む)|
| Feature(発火)| chat メッセージ送信時に正しい受信者集合に通知が dispatch される(`Notification::fake()` でテスト)/ コーチ間の Mail 抑制 / 送信者除外 / 受信者「受講中」でない場合の配信スキップ / Q&A 自己回答スキップ / 面談予約はコーチのみ受信 / 面談キャンセルは相手方のみ受信 / 管理者は全種別で受信しない |
| Policy | `NotificationPolicy::view` / `update` で自分宛 / 他人宛の真偽判定 |

### アーキテクチャ判断

> **Basic 範囲制約**: 本チケットは通知基盤の構築を扱う。Laravel Notification 自体は CLAUDE.md「Basic 拡張範囲」に含まれており、Notification クラスの実装 + DB + Mail channel は Basic 受講生が扱える前提とする。ただし以下 2 点は構造上 Basic 範囲を逸脱する箇所があり、受講生実装方針は柔軟運用とする:
> - **既存 Action からの発火フック**: 模範解答 PJ では `chat StoreMessageAction` / `qa-board QaReply\StoreAction` / `mentoring Meeting\StoreAction` / `Meeting\CancelAction` 内で `DB::afterCommit()` フックを使って通知ラッパー Action を呼ぶ(※ Action 内呼び出し = Basic 範囲外)。Basic 受講生が **対応する Controller method 内で直接 `$user->notify(new XxxNotification(...))` を呼ぶ実装** をしている場合は、振る舞いが受け入れ条件を満たす限り OK。
> - **通知ラッパー Action**(`NotifyChatMessageReceivedAction` 等): Action 採用は受講生判断(チャレンジするなら歓迎)。Controller 内で受信者集合を解決して直接 `$user->notify()` を呼ぶ実装も OK。

- **採用技術**: Laravel Notification(`Illuminate\Notifications\Notification` 継承)+ DB(`database` channel)+ Mail(`mail` channel)+ Controller(受講生判断で Action 分割可)+ Policy + FormRequest + Blade(提供済み)+ View Composer
- **設計判断**:
  1. **基底クラス**: `App\Notifications\BaseNotification`(`abstract class`、`Illuminate\Notifications\Notification` 継承)を提供し、コンストラクタで ULID 事前確定(`$this->id = (string) Str::ulid()`)+ `via()` を `['database', 'mail']` 固定の 2 チャネル既定として実装する。各通知クラスは本基底を継承し、`toDatabase()` / `toMail()` を実装する(`broadcast` チャネルは S-A-05 で追加するため本チケットでは含めない)
  2. **chat 通知の Mail 抑制**: `ChatMessageReceivedNotification` クラスにフラグ `mailEnabled: bool` を渡せるコンストラクタを持たせ、`via()` 内で false なら `database` のみ返すパターン。判断ロジック(コーチ間 false / その他 true)は呼出側(発火フック)で行い、Notification クラス側はフラグの反映のみ担当する
  3. **`data` JSON のキャッシュ戦略**: 受信者の業務 Model(`ChatMessage` / `QaReply` / `Meeting`)を `toDatabase()` 内で参照して関連エンティティ名(`sender_name` / `thread_title` / `student_name` / `certification_name`)を解決し、`data` JSON にキャッシュする。通知一覧画面では `data` JSON のみ参照して描画し、関連 Model の eager load を不要にする(NFR 準拠)
  4. **受信者「受講中」スキップ**: 通知発火点(ラッパー Action or Controller)で受信者の `User.status` を確認し、`InProgress` 以外(`Withdrawn` / `Graduated` / `Invited`)はスキップ。基底 Notification クラス側ではガードしない(発火側の責務)
  5. **発火フックの配置**: 模範解答 PJ は `DB::afterCommit()` を使って業務トランザクション内では発火しない設計(業務 UPDATE のロールバックで通知だけ残る事故防止)。Basic 受講生実装も `DB::afterCommit()` の利用を推奨するが、業務 UPDATE 直後に同期送信でも振る舞い OK(Mail / DB の同期送信で遅延を許容する Basic 前提)
  6. **TopBar 通知ベル + 未読件数バッジ**: `NotificationBadgeComposer`(`app/View/Composers/`)を作り、TopBar Blade で `$notificationBadge` 変数を受けて 1 リクエスト 1 回の `count` クエリで未読件数を取得する。`AppServiceProvider::boot()` で `View::composer('layouts._partials.topbar', NotificationBadgeComposer::class)` で登録
  7. **行クリック既読化 + 遷移**: 一覧画面の各通知行は `<form method="POST" action="/notifications/{id}/read">` でラップし、JS を使わずに既読化 + 遷移を実現。Controller 側は `data.link_route` を `Route::has()` で安全に解決して `redirect()->route()` を返す
  8. **メール件名のプレフィックス統一**: 全 4 種で `subject('【Certify LMS】...')` でプレフィックスを統一(NFR 準拠)
  9. **Mailable は作らない**: `Illuminate\Notifications\Messages\MailMessage` の組立(`->subject` / `->greeting` / `->line` / `->action`)で完結し、専用 Mailable クラスは作らない(教材スコープを Notification の範囲に絞る)

### 関連ファイルメモ

- `app/Notifications/BaseNotification.php`(抽象基底)
- `app/Notifications/Chat/ChatMessageReceivedNotification.php`
- `app/Notifications/QaBoard/QaReplyReceivedNotification.php`
- `app/Notifications/Mentoring/MeetingReservedNotification.php` / `MeetingCanceledNotification.php`
- `app/Http/Controllers/NotificationController.php`(`index` / `markAsRead` / `markAllAsRead`)
- `app/UseCases/Notification/{Index,MarkAsRead,MarkAllAsRead}Action.php`(※ 模範解答 PJ で採用、Basic 受講生は Controller 内完結も可)
- `app/UseCases/Notification/Notify{ChatMessageReceived,QaReplyReceived,MeetingReserved,MeetingCanceled}Action.php`(※ 模範解答 PJ では発火フック用ラッパー Action として配置、Basic 受講生は対応 Controller 内で直接 `$user->notify()` 呼び出しも可)
- `app/Policies/NotificationPolicy.php`
- `app/Http/Requests/Notification/IndexRequest.php`
- `app/View/Composers/NotificationBadgeComposer.php`
- `resources/views/notifications/index.blade.php`(提供 PJ 既存、ロック対象)+ `_partials/notification-row.blade.php`
- `database/migrations/*_create_notifications_table.php`(提供 PJ 同梱想定、または受講生が新規生成、ULID 主キー + 複合 INDEX を含める)
- `database/seeders/NotificationSeeder.php`(提供 PJ 既存、種別網羅 + 既読 / 未読 半々)
- `routes/web.php` の認証済グループ内に `Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index')` + `Route::post('notifications/{notification}/read', ...)->name('notifications.markAsRead')` + `Route::post('notifications/read-all', ...)->name('notifications.markAllAsRead')` を追加
- 発火フック側(本チケットで発火呼び出しを追加する既存ファイル):
  - `app/UseCases/Chat/StoreMessageAction.php`(※ Action 内、Basic 受講生は `ChatMessageController` 内で代替可)
  - `app/UseCases/QaReply/StoreAction.php`(※ Action 内、同上)
  - `app/UseCases/Meeting/{Store,Cancel}Action.php`(※ Action 内、同上)

## 補足

### 想定ヒアリング Q&A

| 質問 | 回答 |
|---|---|
| 通知配信のチャネルは? | データベース + メールの 2 チャネル固定。受信者種別 / 通知種別ごとの ON/OFF 設定 UI は持たない |
| chat 通知のコーチ間メール抑制とは? | コーチ → 他コーチ送信のときだけメールを送らず、データベース通知のみ発火する(コーチ複数体制で同一受講生をフォローしている際の連絡過剰防止)。コーチ → 受講生 / 受講生 → コーチ は通常通り DB + メール |
| Q&A の自己回答(投稿者本人が回答)で通知は飛ぶ? | 飛ばない。回答者 = スレッド投稿者の場合は配信をスキップする |
| 面談予約通知は受講生にも飛ぶ? | 飛ばない。受講生は予約 UI で即時確認できるため、コーチ宛のみ発火 |
| 面談キャンセル通知は誰宛? | キャンセル実行者の **相手方**。受講生キャンセル → コーチ宛 / コーチキャンセル → 受講生宛(自己通知はしない) |
| 受信者が退会済 / 修了済の場合は? | 配信スキップ。利用状態が「受講中」(`InProgress`)のユーザーのみに配信する。発火点側で受信者の状態を確認してから `notify()` を呼ぶ |
| 管理者宛通知は? | 一切発火しない。本 4 種のいずれも管理者は受信対象から外す |
| 未読件数バッジの 99+ 表示は? | 100 件以上のとき「99+」固定。実件数は通知一覧画面で確認 |
| 行クリック時の既読化と遷移はどちら先? | DB UPDATE で `read_at = now()` を先に確定してから業務画面へリダイレクト。`data.link_route` を `Route::has()` で確認してから `redirect()->route()`、ルート未登録時は通知一覧に戻る |
| 全件既読化の対象範囲は? | 自分宛 × 未読 のみ。他人宛は対象外(`auth()->user()->unreadNotifications()->update(['read_at' => now()])` で完結) |
| 通知の削除動線は? | 持たない。既読化のみ。削除は将来要件 |
| メール件名の推奨は? | 「【Certify LMS】<通知タイトル>」プレフィックス統一(`chat` 受信通知例: 「【Certify LMS】◯◯ さんから新着メッセージ」、面談予約例: 「【Certify LMS】◯◯ さんから面談予約が入りました」) |
| メール本文の構成は? | `MailMessage` の `->greeting(...)` + `->line(...)` × 数行 + `->action('業務画面を開く', $url)` + `->salutation('Certify LMS 運営チーム')` で構成(Mailable クラスは作らない) |
| ベルクリック時のドロップダウン展開は本チケット範囲? | 範囲外。Basic では「ベルクリック = 通知一覧画面遷移」の純 Laravel パターン。`S-A-05` で Sanctum + JS + モーダル展開を後付け |
| 発火タイミングを業務トランザクション内で書く? | 推奨は `DB::afterCommit()` フックでトランザクション確定後に発火する。同期送信のためメール失敗で業務 UPDATE がロールバックされる事故を避ける |
| 発火点を Controller 内に書いて Action は使わない実装でも OK? | OK。受講生が Basic 範囲で Action を採用しない場合、対応する Controller method 内で受信者集合を解決して `$user->notify(new XxxNotification(...))` を直接呼ぶ実装で振る舞いが満たせれば良い |
| 通知ベル / 未読件数バッジは全ロールに表示する? | 認証済の全ロール(受講生 / コーチ / 管理者)で表示。ただし管理者は自分宛通知が 0 件のためバッジは常に非表示になる |
| 同一通知の重複防止(同じ chat メッセージで 2 回送る等)は? | 本チケット範囲外(冪等性は MVP 外)。面談リマインダー通知の重複防止(`(meeting_id, window)` の組合せで JSON path 検査)は別チケット範囲 |
