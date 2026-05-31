# T-A-01 面談予約画面の N+1 解消

## メタ情報

| 項目 | 値 |
|---|---|
| チケット ID | `T-A-01` |
| Feature 連番 | `mentoring-03` |
| Feature | mentoring |
| 種別 | Task |
| サブカテゴリ | パフォーマンス |
| 難易度 | Advance |
| 工数 (h) | 5.5 |
| 依存チケット | `S-A-01`(Google Calendar 連携 — `coach_google_credentials` テーブル + Google API 連携が前提) |

## 概要

受講生(student)の面談予約画面で空き枠を取得する処理が、担当コーチ集合に対して N+1 クエリを発生させている。担当コーチごとに個別クエリが発火する状態を、Eager Loading と一括取得への書き換えで解消する。Google Calendar 連携の空き時刻参照を行う外部 API 呼び出しも、未連携コーチに対して無駄に発行しないよう絞り込む。

## 要件

- 面談予約画面の空き枠取得処理(担当コーチ集合に対する空きスロット集計)の N+1 を解消する(担当コーチ集合とその関連情報を一括取得に変更)
- 担当コーチ集合の Google カレンダー連携情報を、コーチをループで都度参照するのではなく **一括取得** する
- Google Calendar API(空き時刻取得)は **連携済コーチに対してのみ** 呼び出し、連携未設定コーチには空打ちしない
- 空き枠の振る舞い(返却スロット集合・予約可能コーチ数集計)は完全に同一に保つ

## スコープ外

- 空き枠のキャッシュ化 — 本チケットのスコープ外、後続パフォーマンス Task で個別判断
- Google Calendar API への複数カレンダー同時投入(API 仕様 + OAuth scope の都合で 1 リクエスト 1 コーチを維持)
- インデックス追加 / DB スキーマ変更 — 既存インデックスで捌ける範囲のクエリ最適化のみ
- 面談履歴一覧画面 / コーチ宛一覧画面の N+1(別チケットで扱う)
- 振る舞い(返却スロットの内容)を変える変更 — クエリ効率の改善のみ

## 受け入れ条件

- [ ] 担当コーチが複数名いる資格の空き枠取得で、コーチごとに個別クエリが発火せず N+1 が解消されている(コーチ集合・空き枠・既存予約が一括取得される)
  - 確認方法（テスト）: 同梱の `tests/Unit/Services/MeetingAvailabilityQueryCountTest.php::test_slots_query_count_does_not_grow_with_coach_count`(修正前は失敗)が pass する
- [ ] 複数コーチ(Google 連携済 / 未連携が混在)の資格でも空き枠が正しく表示される(未連携コーチは Google を参照せず面談可能時間枠 + 既存予約のみで判定される)
  - 確認方法（テスト）: 同梱の `tests/Unit/Services/MeetingAvailabilityServiceTest.php::test_does_not_call_gcal_for_uncredentialed_coach`(未連携コーチに外部 API を発行しないことを検証、修正前は失敗)が pass する

## 実装方針(参考)

### 変更内容

- **対象**: `app/Services/MeetingAvailabilityService.php` の `slotsForCertification(Certification $certification, Carbon $date): Collection`(本 Task の中核。`app/UseCases/Meeting/FetchAvailabilityAction.php` / `MeetingController::fetchAvailability` は Service を呼ぶだけで変更最小)/ 画面 `GET /enrollments/{enrollment}/meetings/create`(予約フォーム)→ JS が `GET /enrollments/{enrollment}/meetings/availability?date=YYYY-MM-DD`(JSON)を叩く
- **変更前→後**: 担当コーチ集合(`$certification->coaches`)を取得後、各コーチに for-loop で面談可能枠(`CoachAvailability`)/ 同日既存予約(`Meeting`)/ Google 連携情報(`googleCredential` magic accessor)を都度クエリ(N+1)し、未連携コーチにも空き時刻取得 API を空打ち → `coaches()->with('googleCredential')->get()` で Eager Loading + `CoachAvailability` / `Meeting` を `whereIn('coach_id', $coachIds)` で一括取得し、PHP 側でマップ化してスロット展開は in-memory 判定、API は `googleCredential !== null` のコーチのみ呼ぶ
- **判断理由**: `with()` で magic accessor の都度 SELECT を回避、子テーブルは `whereIn` で親集合 ID 一括取得。外部 API は連携状態を判定し必要分だけ発行(レート制限・レスポンスタイム配慮)。既存インデックス(`coach_availabilities.(coach_id, day_of_week)` / `meetings.(coach_id, scheduled_at)`)で `whereIn` が効くためスキーマ追加なし
- **テスト**: 担当コーチ複数名でも個別クエリが発火せず N+1 が再発しないこと + 連携済コーチにのみ空き時刻取得 API が呼ばれることを Mockery で assert + 既存 `Tests\Unit\Services\MeetingAvailabilityServiceTest`(スロット単位 / 既存予約・非アクティブ枠除外 / Google busy 反映 / `validateSlot` 枠外例外)の pass(振る舞い不変)

## 補足

### 想定 Q&A

| 質問 | 回答 |
|---|---|
| Google の空き時刻取得 API の呼び出し対象は? | 連携済コーチのみ(連携 0 名なら呼び出さない)。連携未設定コーチへの空打ちは禁止 |
| キャッシュは導入する? | しない(本 Task のスコープ外)。N+1 の解消のみ |
| 振る舞い(返却スロット内容)は変えても良い? | いいえ、完全に同一(返却 JSON のスロット開始・終了・予約可能コーチ数の値が改修前後で一致)。クエリ効率の改善のみ |
| インデックス追加 / マイグレーションは必要? | 不要。既存インデックス(`coach_availabilities.(coach_id, day_of_week)` / `meetings.(coach_id, scheduled_at)`)で `whereIn` も効く |
| `MeetingAvailabilityServiceTest` の既存ケースは触って良い? | 既存ケースはそのまま pass させる(振る舞い不変の保証)。N+1 解消の検証は新規テストメソッドとして追加する |
