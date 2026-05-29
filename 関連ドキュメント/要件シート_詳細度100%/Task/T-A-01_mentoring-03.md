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
| 工数 (h) | 6 |
| 依存チケット | `S-A-01`(Google Calendar 連携 — `coach_google_credentials` テーブル + Google API 連携が前提) |

## 概要

受講生(student)の面談予約画面で空き枠を取得する処理が、担当コーチ集合に対して N+1 クエリを発生させている。担当コーチ数 N に応じて DB クエリ数が線形に増える状態を、Eager Loading と一括取得への書き換えで定数クエリに圧縮する。Google Calendar 連携の空き時刻参照を行う外部 API 呼び出しも、未連携コーチに対して無駄に発行しないよう絞り込む。

## 要件

- 面談予約画面の空き枠取得処理(担当コーチ集合に対する空きスロット集計)の DB クエリを **コーチ数に依存しない定数本数** に削減する
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

> **件数目安**: Task 1-3 件(目的 + 該当時の検証項目 + テスト実装)。振る舞い不変 / Before-After 計測値 / 既存テスト全 pass は AC に書かず評価シート ② 横断品質で扱う。

- [ ] 担当コーチが N 名いる資格の空き枠 1 回取得で、コーチ数に比例して DB クエリ本数が増えない(N=1 でも N=10 でも定数本数、目安 5 本以下)
- [ ] Google カレンダー未連携のコーチには空き時刻取得 API を発行しない(連携済 N' 名のときだけ N' 回、連携 0 名なら 0 回)
- [ ] 本チケットの機能に対するテスト (Unit / Feature 等) が実装されている

## 実装方針(参考)

> **粒度**: 業務語彙 + 技術名(変更対象ファイルパス・クラス名・メソッド名)を併記。具体的なコード片 / SQL クエリ / メソッド完全例は書かない。

### 変更内容

- **変更対象ファイル**: `app/Services/MeetingAvailabilityService.php` の `slotsForCertification(Certification $certification, Carbon $date): Collection`(本 Task の中核)。周辺の `app/UseCases/Meeting/FetchAvailabilityAction.php`(Service を呼ぶだけ)/ `app/Http/Controllers/MeetingController.php::fetchAvailability`(薄い)は変更最小。計測対象画面の入口は `GET /enrollments/{enrollment}/meetings/availability?date=YYYY-MM-DD`(JSON)と `GET /enrollments/{enrollment}/meetings/create`(予約フォーム、JS が上記 JSON を叩く)
- **変更前**(提供 PJ で受講生が直面する状態): 担当コーチ集合(`$certification->coaches`)を取得後、各コーチに **for-loop で 1 名ずつ** ①面談可能時間枠(`CoachAvailability`)②同日の既存予約(`Meeting`)③Google 連携情報(`googleCredential` の magic accessor)をクエリ。担当コーチ N 名で **1(coaches)+ 3N(per-coach)** クエリに膨張(N=5 で 16 本)。さらに空き時刻取得 API を未連携コーチにも空打ち(null チェック漏れ)
- **変更後**(模範解答 PJ の完成形): 担当コーチ集合を `coaches()->with('googleCredential')->get()` で Eager Loading(連携情報を 1 本に集約)+ `CoachAvailability::whereIn('coach_id', $coachIds)->where('day_of_week', $dow)->where('is_active', true)->get()` + `Meeting::whereIn('coach_id', $coachIds)->whereBetween('scheduled_at', [...])->whereIn('status', [Reserved, Completed])->get()` で各 1 本に集約。空き時刻取得 API は `$coach->googleCredential !== null` のコーチのみ呼ぶ。PHP 側で `coach_id => booked Set<H:i>` / `coach_id => busy[]` にマップ化し、スロット展開ループ内は in-memory 判定のみ。合計 **DB 4 本固定**(coaches / googleCredential eager / availabilities / meetings)+ 外部 API は連携済コーチ数のみ
- **計測(PR 動作確認に併記)**: 担当コーチ 5 名 + 各 1 枠(09:00-18:00)+ うち 2 名が Google 連携済 + 同日 1 件既存予約、で `GET .../availability?date=...` を 1 回計測。改善前 DB 16 本前後 / 空き時刻取得 API 5 回(未連携 3 名も空打ち)→ 改善後 DB 5 本以下 / 空き時刻取得 API 2 回(連携済のみ)。**「N の増加に対して線形か / 定数に収まるか」が本質的な合否判定**(担当 10 名でも 5 本以下を維持)
- **採用技術と判断理由**: Eloquent `with()` Eager Loading(magic accessor 都度 SELECT の N+1 回避)/ `whereIn('coach_id', $coachIds)` で子テーブル(`coach_availabilities` / `meetings`)を親集合 ID リストで一括取得 / PHP 配列グルーピングでスロット展開を in-memory 化 / 外部 API は Eager Loading 結果で連携状態を判定し必要分だけ発行(レート制限・レスポンスタイム配慮)。既存インデックス(`coach_availabilities.(coach_id, day_of_week)` / `meetings.(coach_id, scheduled_at)`)で `whereIn` も効くためスキーマ追加なし。`app/UseCases/Dashboard/FetchCoachDashboardAction.php` 等の集計系 Action にも同型の `with`/`whereIn` パターンの参考実装がある
- **テスト観点**: `Tests\Unit\Services\MeetingAvailabilityServiceTest` 既存ケース(60 分単位スロット / 既存予約除外 / 非アクティブ枠除外 / 担当コーチ集合 Union / Google busy 反映 / `validateSlot` 枠外例外)を改修後も pass させ振る舞い不変を担保 + 担当コーチ N 名(N=1 / 5 / 10)で `DB::enableQueryLog()` のクエリ件数が N に依存しない定数であることを assert(`#[DataProvider]` 化)+ Google 連携 0 / 1 / 2 名で空き時刻取得 API 呼出回数が一致することを Mockery で assert(モックテスト追加 Task とも整合)

## 補足

### 想定 Q&A

| 質問 | 回答 |
|---|---|
| 担当コーチ数 N に対してクエリ数が何本なら OK? | コーチ数に依存しない定数本数(目安 5 本以下)。N=1 でも N=10 でも本数が同じであることが重要 |
| Google の空き時刻取得 API の呼び出し回数は? | 連携済コーチの数だけ(連携 0 名なら 0 回、2 名なら 2 回)。連携未設定コーチへの空打ちは禁止 |
| 改善前後のクエリ数 / レスポンスタイムはどこに書く? | PR の「動作確認」セクションに Before / After を併記(Debugbar スクショ + `DB::getQueryLog()` の件数 + 計測時間)。動的画面なので動画も推奨 |
| キャッシュは導入する? | しない(本 Task のスコープ外)。クエリ自体の本数削減のみ |
| 計測環境は? | ローカル(Sail)で十分。担当 N=5 / N=10 で線形に伸びないことが確認できれば良い |
| 振る舞い(返却スロット内容)は変えても良い? | いいえ、完全に同一(返却 JSON のスロット開始・終了・予約可能コーチ数の値が改修前後で一致)。クエリ効率の改善のみ |
| インデックス追加 / マイグレーションは必要? | 不要。既存インデックス(`coach_availabilities.(coach_id, day_of_week)` / `meetings.(coach_id, scheduled_at)`)で `whereIn` も効く |
| `MeetingAvailabilityServiceTest` の既存ケースは触って良い? | 既存ケースはそのまま pass させる(振る舞い不変の保証)。性能アサーションは新規テストメソッドとして追加する |
