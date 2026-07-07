# S-B-09 面談リマインダー通知（前日・1時間前）

## メタ情報

| 項目 | 値 |
|---|---|
| チケット ID | `S-B-09` |
| Feature 連番 | `notification-04` |
| Feature | notification(面談リマインダー拡張) |
| 種別 | Story |
| サブカテゴリ | 新規機能の構築 |
| 難易度 | Basic |
| 工数 (h) | 5 |
| 依存チケット | `S-B-04` |

## 背景・目的

通知基盤(chat / Q&A / 面談予約・キャンセル の 4 業務イベント)は構築済みだが、予約済み面談に対する **事前リマインダー** が存在しない。受講生 / コーチは面談時刻の確認のため UI を都度開く必要があり、特に予約日から面談日までが離れているケースで参加忘れ・遅刻のリスクが高い。

本チケットは予約済み面談に対して **前日 18:00** と **開始 1 時間前** の 2 タイミングで自動リマインダー通知を配信する Schedule Command を新規実装する。既存の通知基盤(DB + メール)をそのまま利用し、配信対象は予約済み面談の当事者(受講生 + 担当コーチ両方)。Schedule の重複起動 / 手動再実行でも同一の面談・同一の配信タイミングの組み合わせで二重配信しない冪等性を確保する。Schedule Command + 業界標準的なリマインダーパターンを資格 LMS ドメインで実装する位置づけ。

## ユーザーストーリー

- **受講生(student)として**、予約済み面談の前日にリマインダー通知を受け取りたい。なぜなら、当日朝に「明日面談あったっけ?」と UI を確認する手間を省きたいから。
- **受講生として**、開始 1 時間前にも直前リマインダー通知を受け取りたい。なぜなら、別作業中に面談時刻を忘れて遅刻する事態を避けたいから。
- **コーチ(coach)として**、自分が担当する予約済み面談の前日 / 1 時間前にリマインダー通知を受け取りたい。なぜなら、複数受講生を抱えており次の面談を見落とさず準備したいから。
- **受講生 / コーチとして**、同じ面談に対して重複リマインダーが届かないことを期待する。なぜなら、Schedule の二重起動や運用ミスでメールが連発するとノイズになるから。

## 要件

### リマインダー配信タイミング(2 種類)

- **前日リマインド** (`--window=eve`): 翌日(00:00-23:59)に予約されている面談 → **前日 18:00** に配信
- **1 時間前リマインド** (`--window=one_hour_before`): 開始 55-65 分後に予約されている面談 → **5 分間隔** で巡回(5 分粒度の精度を許容)

### 配信対象

- 予約済みの面談のみ(キャンセル済み / 完了済みは対象外)
- 当該面談の **受講生 + 担当コーチ 両方** に配信(自己通知制限なし、両当事者が対象)
- 受信者の利用状態が「受講中」でない場合は配信スキップ

### 冪等性(重複配信防止)

- 同一の面談・同一の配信タイミングで既にリマインダー通知が存在する場合は配信スキップ
- Schedule の二重起動 + 手動再実行 + 5 分間隔走査の重複範囲 すべてに耐える

### Schedule 登録

- リマインダー配信コマンド `sail artisan notifications:send-meeting-reminders` を配信タイミング(`--window=eve` = 前日 / `--window=one_hour_before` = 1 時間前)で切替起動
- 前日リマインド: 毎日 18:00 に実行
- 1 時間前リマインド: 5 分間隔で実行
- 両方とも同時起動防止設定を付与

### 共通の振る舞い

- 通知チャネル: データベース通知 + メール 両方(既定チャネル踏襲)
- メール件名は「【Certify LMS】」プレフィックスで統一

## スコープ外

- **Queue 化 / 非同期配信** — `QUEUE_CONNECTION=sync` 維持、別途 Queue 専用チケット(Advance)で扱う
- **管理者宛リマインダー** — 管理者は面談の当事者ではないため対象外
- **リマインダー設定の ON/OFF スイッチ** — 全ユーザー一律配信、ユーザー設定は持たない
- **受講生によるカスタムリマインダー時刻** — 前日 18:00 / 1 時間前 の 2 タイミング固定
- **配信失敗時の自動リトライ** — 同期送信のため失敗時は次回 Schedule での再試行に任せる(未配信なら冪等性検査をすり抜けて再配信される)
- **リアルタイム push 配信(Pusher 等)** — 本チケットでは DB 通知 + メールのみ
- **モバイルプッシュ通知** — 教育 PJ スコープ外
- **リマインダー精度の向上**(1 分粒度) — 5 分粒度を許容

## 受け入れ条件

- [ ] 【システム】前日リマインダーの発火
  1. `sail artisan notifications:send-meeting-reminders --window=eve` を実行すると、翌日(00:00-23:59)に予約されている面談の当事者(受講生 + 担当コーチ)に DB + メール通知が発火する
- [ ] 【システム】1 時間前リマインダーの発火
  1. `sail artisan notifications:send-meeting-reminders --window=one_hour_before` を実行した時刻を基準に、開始 55-65 分後に予約されている面談の当事者に DB + メール通知が発火する
- [ ] 【システム】リマインダーの重複防止
  1. 同じ面談・同じ配信タイミングで既にリマインダー通知が存在する場合、再実行しても通知は重複して作られない
- [ ] 【システム】対象外面談の除外
  1. 予約済み以外の面談(キャンセル済み / 完了済み)はリマインダー対象外で、当事者に通知が発火しない
- [ ] 本チケットの機能に対するテスト (Unit / Feature 等) が実装されている

## 実装方針(参考)

### インターフェース(必須)

本チケットは HTTP エンドポイントを持たず、Schedule で起動する Artisan コマンドが機能トリガとなる。

**Artisan コマンド**:

| コマンド | オプション | 動作 |
|---|---|---|
| `sail artisan notifications:send-meeting-reminders` | `--window=eve` | 翌日(`now` の翌日 00:00..23:59)に予約されている面談の当事者(受講生 + 担当コーチ)にリマインダー配信 |
| `sail artisan notifications:send-meeting-reminders` | `--window=one_hour_before` | 開始 55-65 分後(`now+55min`..`now+65min`)に予約されている面談の当事者にリマインダー配信 |

**実行タイミング(Schedule 登録、`app/Console/Kernel.php` の `schedule()` メソッドに追記)**:

```php
$schedule->command('notifications:send-meeting-reminders --window=eve')
    ->dailyAt('18:00')
    ->withoutOverlapping(5);

$schedule->command('notifications:send-meeting-reminders --window=one_hour_before')
    ->everyFiveMinutes()
    ->withoutOverlapping(5);
```

**返り値**: 正常時 `SUCCESS` (`0`)、不正な `--window` 値で `INVALID` (`2`)。

**動作確認**: ローカルでは Cron を動かさず artisan コマンドを手動実行 (`sail artisan notifications:send-meeting-reminders --window=eve` 等)。`Meeting` Seeder データを操作して翌日 / 1 時間後の予約を作成して動作確認。

### データモデル

新規テーブルは追加しない(`notifications` テーブルは既存の通知基盤を流用、`Meeting` は mentoring Feature 既存)。

**Enum**(新規):

- 面談リマインダーウィンドウ (`MeetingReminderWindow`): 前日 (`Eve`, `'eve'`) / 1 時間前 (`OneHourBefore`, `'one_hour_before'`)
  - `label()` メソッド: `Eve` → 「前日リマインド」/ `OneHourBefore` → 「1時間前リマインド」

**通知データ JSON の構造**(`MeetingReminderNotification::toDatabase`):

- 共通: `notification_type=meeting_reminder` / `title`(タイミングラベル + 日時) / `message`(資格名 + 相談内容) / `link_route=meetings.show` / `link_params={meeting:面談 ID}`
- 種別固有メタ: `meeting_id` / `enrollment_id` / `coach_user_id` / `student_user_id` / `scheduled_at`(ISO 8601) / `topic` / `window`(`eve` or `one_hour_before`)

**冪等性検査クエリ**: `notifications` テーブルの `type = MeetingReminderNotification::class` + `data->meeting_id = {面談 ID}` + `data->window = {タイミング}` で既存検査。

**Seeder**: 新規不要(リマインダーは Schedule で動的発火するため、初期データ投入の必要なし)。

### コンポーネント

**Console / Command** (`app/Console/Commands/Notification/`、新規)
- `SendMeetingRemindersCommand` — `--window` オプション解析 + 配信対象クエリ組立 + `chunkById(100)` で巡回 + Action 呼出 + 処理件数のログ出力

**Action** (`app/UseCases/Notification/`、新規、※ Advance 範囲、Basic 受講生は Command 内完結も可)
- `NotifyMeetingReminderAction` — 受信者集合解決(`student` + `coach`) + 利用状態フィルタ(`UserStatus::InProgress`) + 同一 `(meeting_id, window)` 重複検査(`DatabaseNotification` の JSON path クエリ) + `Notification::send()` 実行

**Notification** (`app/Notifications/Mentoring/`、新規)
- `MeetingReminderNotification` — `BaseNotification` 継承、`Meeting` + `MeetingReminderWindow` をコンストラクタで受け取る、`toDatabase` で通知データ JSON 組立、`toMail` で件名 + 日時 + 資格 + 相談内容 + 面談 URL ボタンの Mail 組立(DB + Mail 既定チャネル)

**Model + Enum** (`app/Models/`, `app/Enums/`)
- `MeetingReminderWindow`(新規、`Eve` / `OneHourBefore`)

**Schedule 登録** (`app/Console/Kernel.php`、既存ファイルに追記)
- `schedule()` メソッド — 2 種類の Cron 登録 + `withoutOverlapping(5)`

**連携先**(本チケットで変更しない、既存提供)
- `app/Notifications/BaseNotification.php`(抽象基底)/ `app/Models/User.php`(`Notifiable` trait)/ `app/Models/Meeting.php`(mentoring Feature 既存)/ `app/Enums/MeetingStatus.php` / `app/Enums/UserStatus.php`
- Laravel 標準 `Notification` モデル / `notifications` テーブル(既存の通知基盤)

### 異常系

**Command 引数検証**:

- `--window`: `eve` / `one_hour_before` のいずれか → `MeetingReminderWindow::tryFrom` で検証、不正値は `error()` 出力 + `INVALID` 終了コードで終了

**業務例外**(状態ベースガード):

- 同一 `(meeting_id, window)` で通知が既存 → 配信スキップ(`NotifyMeetingReminderAction` 内で `DatabaseNotification` の JSON path クエリで重複検査)
- 受信者の利用状態が `UserStatus::InProgress` でない → 配信スキップ(`student` / `coach` 個別判定)
- キャンセル済み / 完了済み面談 → Command の `buildTargetQuery` で `status = Reserved` フィルタにより除外
- `student` / `coach` が NULL(`Meeting` の関連未確立)→ 該当ユーザー分の配信をスキップして処理継続

**Schedule 重複起動**: `withoutOverlapping(5)` で同時起動を防止 + 冪等性検査で二重防御。

### 設計判断

- **冪等性の二重防御**: ① `Schedule::withoutOverlapping(5)` で Cron の二重起動を防止、② `NotifyMeetingReminderAction` の `alreadyDispatched()` で `notifications` テーブル JSON path クエリ重複検査。両方で守ることで Schedule タイミングのずれ / 手動再実行 / 5 分巡回の重複範囲のいずれでも二重配信を防ぐ
- **Queue 化なし**: `QUEUE_CONNECTION=sync` 維持。Mail 送信は Schedule Command 内で同期実行。配信件数が少数(数十件以下)のため許容、数百件規模で Queue 化が必要になった段階で別チケット(Advance)で対応
- **5 分粒度の許容**: 1 時間前リマインドを厳密に「1 時間前」にすると 1 分間隔の Schedule が必要だが、Cron 負荷増 + 実用上の過剰さから 5 分粒度(55-65 分前範囲)で妥協
- **`chunkById(100)`**: 大量予約があってもメモリ展開を抑える(`get()` でなく `chunkById` を採用)。大量データ処理のさらなるメモリ最適化は別 Task で扱う
- **受講生 + コーチ 両当事者配信**: chat / Q&A 等の通知と異なり「自己通知制限なし」(リマインダーは当事者全員に届ける性質)
- **テスト観点**: 配信対象クエリの時間境界(翌日 00:00-23:59 / 開始 55-65 分後)+ 同一 `(meeting_id, window)` 再実行時の冪等性 + 受講中以外 / 予約済み以外の除外が本チケット固有の観点

## 補足

### 想定 Q&A

| 質問 | 回答 |
|---|---|
| リマインダーのタイミングは? | 前日 18:00 + 開始 1 時間前 の 2 種類 |
| 配信対象は誰? | 予約済み面談の **当事者全員**(受講生 + 担当コーチ両方)。自己通知制限なし |
| 管理者にもリマインダーは飛ぶ? | 飛ばない。管理者は面談の当事者ではないため対象外 |
| 受講中以外のユーザーには配信される? | されない。受講中のユーザーのみが対象(退会済 / 修了済 / 招待中 は除外) |
| キャンセル済み / 完了済み面談にもリマインダーは飛ぶ? | 飛ばない。予約済みの面談のみが Schedule Command の対象クエリに含まれる |
| 同じ面談に同じタイミングで 2 回リマインダーが飛ぶことは? | ない。面談 ID と配信タイミングの組合せで既存通知の存在を JSON path クエリで検査し、既存ならスキップ |
| Schedule の二重起動でも問題ない? | 問題ない。`withoutOverlapping(5)` で同時起動を防止 + JSON path クエリの重複検査で冪等性確保 |
| ローカル環境ではどう動作確認する? | artisan コマンドを手動実行(`sail artisan notifications:send-meeting-reminders --window=eve` 等)。Cron 登録自体は本番運用前提でテストしない |
| 不正な配信タイミングを渡すとどうなる? | エラーメッセージが表示され、`INVALID` 終了コードで終了する |
| 1 時間前リマインダーの精度は? | 5 分粒度(5 分間隔の Schedule で 55-65 分前範囲を巡回)。厳密 1 時間前ではない |
| 5 分粒度を選んだ理由は? | 1 分間隔の Cron 負荷を避けつつ、実用上十分な精度を確保。本番運用では業界標準的な妥協ライン |
| 配信失敗時のリトライは? | 自動リトライなし。同期送信のため Mail 失敗時は次回 Schedule 起動で再試行(冪等性検査は同一タイミングで既配信のため発火しない、未配信ならスキップ条件を通って再配信される) |
| メール件名の推奨は? | 「【Certify LMS】前日リマインド」「【Certify LMS】1時間前リマインド」プレフィックス(細部は採点対象外) |
| メール本文の構成は? | 日時 / 資格 / 相談内容 + 面談 URL ボタン。専用 Mailable クラスは作らず Notification の Mail 組立 API (`toMail`) のみで完結 |
| 通知データ JSON にどんなメタを保存する? | `meeting_id` / `enrollment_id` / `coach_user_id` / `student_user_id` / `scheduled_at` / `topic` / `window` / `link_route` / `link_params` 等(発火時スナップショット) |
| 受講生 / コーチが通知行をクリックしたら? | `link_route = meetings.show` + `link_params = {meeting: ID}` から面談詳細画面に遷移(通知一覧の行クリック動線をそのまま利用) |
| リマインダー設定の OFF スイッチは? | 提供しない。全ユーザー一律配信 |
| 受講生がリマインダー時刻をカスタマイズできる? | できない。前日 18:00 / 1 時間前の 2 タイミング固定 |
| Queue 化は必要? | 本チケット範囲では不要(`QUEUE_CONNECTION=sync` 維持)。Queue 専用チケット(Advance)で別途扱う |
| リアルタイム配信(Pusher 等)は? | 本チケット範囲外。基底クラス `BaseNotification` は `toBroadcast` メソッドを持つが、本チケットでは `toDatabase` + `toMail` のみ実装 |
| `chunkById(100)` を使う理由は? | 大量予約があってもメモリ展開を抑えるため。さらなる大量データ処理のメモリ最適化は別 Task で扱う |
| Schedule の `dailyAt('18:00')` を選んだ理由は? | 業界標準的なリマインダー配信タイミング。日中の業務時間内に配信して「明日の予定」として認識される時間帯を選択 |
