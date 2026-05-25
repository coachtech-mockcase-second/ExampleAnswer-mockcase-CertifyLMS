# T-A-01 面談予約画面の N+1 解消

<!--
記述粒度規約: 実装粒度(テーブル名 / カラム名 / クラス名 / SQL 詳細 / Laravel 実装語彙 / URL パス詳細 等)を記載できるのは `## 実装方針` 配下のみ。それ以外のセクション(概要 / 背景・目的 / やること / やらないこと / 補足)は **業務語彙のみ** で記述する。詳細規約は `../../../.claude/rules/ticket-spec.md`「実装粒度の記載範囲」参照。
受け入れ条件は構造記述 / Before/After 計測値ベース(Performance では計測値が振る舞いの代替指標、Refactoring では振る舞い不変 + 構造変更点)。
-->

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

## 背景・目的

- **現状の問題**: 面談予約画面の空き枠 JSON 取得で、資格の担当コーチ集合(`Certification.coaches`)を 1 件ずつループしながら、各コーチごとに「面談可能時間枠」「同日の既存予約」「Google カレンダー連携情報」を都度クエリしている。担当コーチが 5 名の資格では、空き枠 1 回の取得に DB クエリが 10 本以上発行され、コーチ数の増加でレスポンスタイムが線形に悪化する。Advance フェーズで Google Calendar 連携(`S-A-01`)が入ると、未連携コーチにも freebusy API を空打ちしてしまい外部 API の無駄な呼び出しが増える二次問題もある。
- **達成したい状態**: 担当コーチ集合に対する基礎データ取得を **定数本数の DB クエリ**(コーチ数 N に依存しない本数)に圧縮し、Google Calendar API は **連携済コーチ N' 名のみ** に呼び出しを限定する(N' ≤ N、連携未設定コーチには発行しない)。空き枠の振る舞い(返却スロット集合・コーチ数集計)は完全に同一に保つ。
- **価値・優先度**: Advance チケットとして「N+1 検出 → Eager Loading / 一括取得」「外部 API 呼び出しの境界設計」を組み合わせる典型題材。受講生が `Laravel Debugbar` / `DB::enableQueryLog()` での実測 → Eager Loading 適用 → 再計測 のサイクルを体感する。

## やること

- 面談予約画面の空き枠取得処理(担当コーチ集合に対する空きスロット集計)の DB クエリを **コーチ数に依存しない定数本数** に削減する
- 担当コーチ集合の Google カレンダー連携情報を、コーチをループで都度参照するのではなく **一括 Eager Loading** で取得する
- Google Calendar API(freebusy 取得)は **連携済コーチに対してのみ** 呼び出し、連携未設定コーチには空打ちしない
- 改善前後で **DB クエリ数 / レスポンスタイム** を計測し、PR の「動作確認」セクションに Before / After を併記する
- 既存の受け入れ条件(空きスロットの一致 / 既存予約除外 / 非アクティブ枠除外 / 担当コーチ集合の Union / Google カレンダー busy 反映)が崩れていないことを既存テストで担保する

## やらないこと

- 空き枠 1 件 1 件のキャッシュ化(`Cache::remember`)— 本チケットのスコープ外、後続パフォーマンス Task で個別判断
- Google Calendar API の `freebusy.query` への複数カレンダー同時投入(API 仕様上の制限 + OAuth scope の都合で 1 リクエスト 1 コーチを維持)
- インデックス追加 / DB スキーマ変更 — 既存インデックスで十分捌ける範囲のクエリ最適化のみ扱う
- 面談履歴一覧画面 / コーチ宛一覧画面の N+1(これらは別 Task `T-B-02` / 別チケットで扱う)
- 振る舞い(返却スロットの内容)を変える変更 — あくまでクエリ効率の改善のみ

## 受け入れ条件

- [ ] **DB クエリ数の削減**: 担当コーチが 5 名の資格における空き枠 1 回の取得で、改善後 **5 本以下** に収まる(担当コーチが何名でも本数が線形に増えないことを Debugbar / `DB::enableQueryLog()` のスクリーンショットで PR に提示)
- [ ] **外部 API 呼び出しの境界**: Google カレンダー未連携のコーチに対しては `freebusy` API を発行しない(連携済コーチ 0 名のとき 0 回、N' 名のとき N' 回)
- [ ] **振る舞い不変**: `MeetingAvailabilityServiceTest` 既存ケース(60 分単位スロット / 既存予約除外 / 非アクティブ枠除外 / 担当コーチ集合の Union / Google カレンダー busy 反映 / `validateSlot` 枠外例外)が全件 pass
- [ ] **クエリ数アサート**: 担当コーチ N 名 + 各コーチに面談可能時間枠を持たせた状況で空き枠 1 回取得し、`DB::getQueryLog()` のクエリ件数が **N に依存しない定数** であることを Unit テストで assert
- [ ] **PR 動作確認セクション**: 同条件(担当コーチ 5 名、Google 連携 2 名、1 日 1 枠 09:00-18:00)での Before / After 計測値(DB クエリ数 / レスポンスタイム / freebusy API 発行回数)を PR に併記

## 実装方針

> **参考設計の一例**。受け入れ条件を満たせれば実装手段は問わない。受講生は提供 PJ コード + ヒアリングで自分の設計を組み立てる。

### 主要 URL

| メソッド | パス | 振る舞い |
|---|---|---|
| GET | `/enrollments/{enrollment}/meetings/availability?date=YYYY-MM-DD` | 指定 Enrollment の担当コーチ集合の空き枠を JSON 返却(60 分単位、`{slot_start, slot_end, available_coach_count}` の配列)。本 Task の **計測対象画面**。 |
| GET | `/enrollments/{enrollment}/meetings/create` | 予約フォーム画面(空き枠取得は JS から上記 JSON を呼ぶ)。同経路で空き枠取得が走る入口。 |

### 変更対象と変更前後の状態

- **変更対象ファイル候補**: `app/Services/MeetingAvailabilityService.php::slotsForCertification(Certification $certification, Carbon $date): Collection`
  - 周辺: `app/UseCases/Meeting/FetchAvailabilityAction.php`(Service を呼ぶだけなので変更最小)
  - `app/Http/Controllers/MeetingController.php::fetchAvailability`(Controller 自体は薄く変更なし)

- **変更前の状態**(リファクタ前 / 提供 PJ で受講生が直面する状態):
  - 担当コーチ集合を `$certification->coaches` で取得後、各コーチに対して **for-loop で 1 名ずつ** 次のクエリを発行する:
    - `CoachAvailability::where('coach_id', $coach->id)->where('day_of_week', $dow)->where('is_active', true)->get()` — コーチごと 1 本
    - `Meeting::where('coach_id', $coach->id)->whereBetween('scheduled_at', [$dayStart, $dayEnd])->whereIn('status', [Reserved, Completed])->get()` — コーチごと 1 本
    - `$coach->googleCredential`(magic accessor で都度 SELECT) — コーチごと 1 本
  - 担当コーチ N 名の場合、合計 **1(coaches) + 3N(per-coach 取得)** クエリ。N=5 で 16 本、N=10 で 31 本に膨張。
  - Google `freebusy` API は連携未設定コーチに対しても呼び出され(`null` チェック漏れ)、外部 API の空打ち + 例外ハンドリング負荷が発生。

- **変更後の理想形**(リファクタ後 / 模範解答 PJ の完成形):
  - 担当コーチ集合を取得する際に `with('googleCredential')` で Eager Loading(`belongsTo` でもこの方向なら `hasOne` 1 本に集約可能、N+1 解消の典型パターン)
  - `CoachAvailability` を `whereIn('coach_id', $coachIds)->where('day_of_week', $dow)->where('is_active', true)->get()` で **1 本に集約**
  - `Meeting` を `whereIn('coach_id', $coachIds)->whereBetween('scheduled_at', [$dayStart, $dayEnd])->whereIn('status', [...])->get()` で **1 本に集約**
  - Google `freebusy` API は `$coach->googleCredential !== null` のコーチのみループ呼び出し(コーチ N' ≤ N 名のみ発行)
  - PHP 側で `coach_id => Set<H:i>` や `coach_id => busy[]` のマップ化を行い、スロット展開時に `in_array` / 区間重なり判定のみで集計
  - 合計 DB クエリ **4 本固定**(coaches with googleCredential / coaches 取得 / CoachAvailability `whereIn` / Meeting `whereIn`)+ Google API は連携済コーチ数のみ

### 計測指標と目標値

> **計測条件を統一**: 担当コーチ 5 名 + 各コーチ 1 枠(月曜 09:00-18:00) + うち 2 名が Google 連携済 + 同日に 1 件既存予約あり、で 1 回の空き枠取得(`GET /enrollments/{enrollment}/meetings/availability?date=2026-06-01`)を計測。

| 項目 | 内容 |
|---|---|
| 計測手法 | Laravel Debugbar の「Database」タブ + `DB::enableQueryLog()` / `DB::getQueryLog()` を Controller 側に仕込む方法 / `microtime(true)` 差分でレスポンスタイム計測 |
| 計測指標 | (1) DB クエリ数 / (2) レスポンスタイム(JSON 返却完了まで、ミリ秒) / (3) Google `freebusy` API 発行回数(`Log::info` で記録 or `Http::fake()` の呼出カウント) |
| 改善前(Before) | DB クエリ 16 本前後(担当コーチ 5 名 × 3 種 + base 1)/ レスポンスタイム 数百 ms / `freebusy` 発行 5 回(連携未設定 3 名分も含めて空打ち) |
| 改善後(After 目標) | DB クエリ **5 本以下**(担当コーチ何名でも線形にならない)/ レスポンスタイム 100ms 以下を目安 / `freebusy` 発行 **2 回**(連携済 2 名のみ) |

> 上記 Before の絶対値は計測環境で前後する。**「コーチ数 N の増加に対して線形に増えるか / 定数に収まるか」が本質的な合否判定**。担当コーチ 10 名でも改善後は 5 本以下を維持できることを補助計測として確認すると良い。

### テスト方針

| 種別 | 観点 |
|---|---|
| 振る舞い不変 | `Tests\Unit\Services\MeetingAvailabilityServiceTest` の既存ケース(60 分単位スロット / 既存予約除外 / 非アクティブ枠除外 / 担当コーチ集合 Union / Google カレンダー busy 反映 / `validateSlot` 枠外例外)が改修後も全件 pass |
| 性能(クエリ数) | 担当コーチ 5 名 + 各コーチ 1 枠 + Google 連携 0 名のシナリオで `DB::enableQueryLog()` を仕込み、`DB::getQueryLog()` の件数が **5 本以下** であることを `assertLessThanOrEqual` で assert する Unit テストを追加 |
| 性能(線形性) | 担当コーチ N 名(N=1 / N=5 / N=10)で同じテストを走らせ、**クエリ数が N に対して定数(線形に増えない)** ことを assert(複数 N に対する `#[DataProvider]` 化)|
| 外部 API 境界 | Google 連携済コーチ 0 名 / 1 名 / 2 名で `GoogleCalendarService::freebusy` の `shouldReceive` 回数が一致することを `Mockery::shouldReceive('freebusy')->times($expected)` で assert(これは `T-A-04` の「モックテスト追加」とも整合) |

### 採用技術と判断理由

- **採用技術**: Eloquent `with()` Eager Loading / `whereIn()` での一括取得 / PHP 配列によるグルーピング(`groupBy` / `pluck`)/ `Carbon` でスロット展開
- **判断理由**:
  1. **`with('googleCredential')` で Lazy Load 回避**: コーチ 1 名 1 名で magic accessor を叩く形は典型的 N+1。`hasOne('googleCredential')` を 1 本に圧縮するのが Eloquent の標準パターン
  2. **`whereIn('coach_id', $coachIds)` で per-coach クエリを集約**: 子テーブル(`coach_availabilities` / `meetings`)を「親集合の id リスト」で一括取得する基本パターン。担当コーチ N 名 → 子テーブル 1 本に圧縮できる
  3. **PHP 側マップ化**: `coach_id => Set<H:i>` / `coach_id => busy[]` で索引化し、スロット展開ループ内では DB / API を呼ばずに in-memory 判定。担当コーチ N 名 × 1 日 9 枠でも追加クエリ 0 本
  4. **外部 API 呼び出しの境界**: Google `freebusy` API は連携未設定コーチに発行しても確実に空配列が返るが、外部 API レート制限 / レスポンスタイム の観点で空打ちは避けたい。連携状態を Eager Loading 結果で判定し、必要分だけ発行する
  5. **インデックスは既存維持**: `coach_availabilities.(coach_id, day_of_week)` 複合 / `meetings.(coach_id, scheduled_at)` UNIQUE は既存。`whereIn` でも既存インデックスがそのまま効くため、スキーマ追加なしで完結

### 改善対象コードメモ

- 改善対象の主要ファイル: `app/Services/MeetingAvailabilityService.php`(`slotsForCertification` メソッド本体)
- 周辺の `with`/`whereIn` の Eager Loading パターンは `app/UseCases/Dashboard/FetchCoachDashboardAction.php` 等の集計系 Action にも適用例があり、受講生が既存パターンを `Read` して倣う流れを想定
- 計測時の Debugbar 確認画面 / `DB::enableQueryLog()` の使い方は受講生のヒアリングで触れる(具体的なログ出力位置・スクショの撮り方はコーチが個別指南)

## 補足

### 想定ヒアリング Q&A

| 質問 | 回答 |
|---|---|
| 担当コーチ数 N に対してクエリ数が何本になっていれば OK? | コーチ数に依存しない **定数本数**(目安: 5 本以下)。N=1 でも N=10 でも本数が同じであることが重要。 |
| Google `freebusy` API の呼び出し回数は? | **連携済コーチの数だけ**(連携 0 名なら 0 回、2 名なら 2 回)。連携未設定コーチへの空打ちは禁止。 |
| 改善前後のクエリ数 / レスポンスタイムは PR のどこに書く? | 「動作確認」セクションに Before / After を併記(Debugbar スクショ + `DB::getQueryLog()` の `count()` + `microtime` 差分)。動的画面なので動画も推奨。 |
| キャッシュ(`Cache::remember`)は導入する? | しない(本 Task のスコープ外)。クエリ自体の本数削減のみ扱う。 |
| 計測環境はローカル(Sail) / 本番想定どちらで? | ローカル(Sail)で十分。担当コーチ N=5 / N=10 で線形に伸びないことが確認できれば良い。 |
| 既存の `with(...)` / `whereIn(...)` パターンは他 Feature にもあるが、参考にして良い? | はい、`app/UseCases/Dashboard/FetchCoachDashboardAction.php` 等の集計系 Action で類似パターンが採用されている。倣って構わない。 |
| 振る舞い(返却スロット内容)は変えても良い? | いいえ、**完全に同一**(返却 JSON の `slot_start` / `slot_end` / `available_coach_count` の値が改修前後で一致)。あくまでクエリ効率の改善のみ。 |
| インデックス追加 / マイグレーション追加は必要? | 不要。既存インデックス(`coach_availabilities.(coach_id, day_of_week)` / `meetings.(coach_id, scheduled_at)`)で `whereIn` も効く。スキーマ変更はしない。 |
| `MeetingAvailabilityServiceTest` の既存ケースは触っても良い? | 既存ケースは **そのまま pass させる**(振る舞い不変の保証)。性能アサーションは新規テストメソッドとして追加する。 |
