# T-A-02 面談機能のロジックを Action へ分離

## メタ情報

| 項目 | 値 |
|---|---|
| チケット ID | `T-A-02` |
| Feature 連番 | `mentoring-07` |
| Feature | mentoring |
| 種別 | Task |
| サブカテゴリ | リファクタリング |
| 難易度 | Advance |
| 工数 (h) | 5 |
| 依存チケット | (なし) |

## 概要

mentoring Feature の Controller(`MeetingController`)の各メソッドが抱える業務ロジック / データ取得を、`app/UseCases/Meeting/` 配下の単一責務 Action クラスへ切り出すリファクタリング。状態変更系(予約 / キャンセル / メモ保存)は 1 メソッド数十行に膨らんだ業務ロジックを、取得系(一覧 / コーチ向け一覧 / 詳細 / 空き枠取得)はデータ取得処理を、それぞれ Action に分離し、Controller をリクエスト受付 + 認可委譲 + レスポンス整形だけの薄いラッパーに統一する。「1 Controller メソッド = 1 Action クラス」「Controller は薄く保つ」という本プロジェクトの Clean Architecture 軽量版規約(全 Controller で踏襲済の既存パターン)に揃えることが目的。

## 要件

- 状態変更系メソッド(受講生による面談予約 / 当事者によるキャンセル / コーチによる面談メモ記録)から業務ロジックを Action クラスへ抽出する
- 取得系メソッド(受講生向け一覧 / コーチ向け一覧 / 面談詳細 / 空き枠取得)のデータ取得処理も Action クラスへ抽出し、全 Controller で踏襲済の既存パターン(1 メソッド = 1 Action)に揃える
- 抽出した Action クラスを `app/UseCases/Meeting/` 配下に「Controller メソッド名 = Action クラス名」の命名で配置する
- Controller メソッドは抽出 Action を受け取って呼ぶだけの薄いラッパーに書き直す(リクエスト受付 / 認可委譲 / レスポンス整形のみ、業務ロジック・クエリ組み立ては 0 行)
- 状態変更を伴う Action はトランザクション境界で囲む(残面談回数の消費・通知発火を同一境界に含める。外部カレンダー連携を実装済みなら、その連動も同じ境界に含める)
- 抽出した Action それぞれに対し Action 単体テストを新規追加する(状態変更系は正常系 + 例外パス + 副作用、取得系は取得結果 / フィルタ分岐)

## スコープ外

- 予約フォーム表示メソッド(`create` / `createFallback`)の Action 分離 — 認可 + View 返却のみで業務ロジック・データ取得を持たない純粋な表示メソッドのため、既存パターンでも Action を持たず Controller に残す
- 自動完了の Schedule Command(`AutoCompleteMeetingAction`)— Schedule Command から呼ばれる Action は既に分離済で Controller リファクタの対象外
- Action 内で呼ぶ協力 Service(`MeetingAvailabilityService` / `CoachMeetingLoadService` / `MeetingQuotaService`)の責務見直し — Service 分離 Task との棲み分け
- 認可ロジックの Policy 抽出 — Policy(`MeetingPolicy`)はすでに存在、変更しない
- 振る舞いを変える変更 — リクエスト・レスポンス・DB 副作用・通知文面すべて同一

## 受け入れ条件

- [ ] 受講生予約 / 当事者キャンセル / コーチメモ upsert の状態変更 3 操作の業務ロジックが、Controller メソッド名と一致する単一責務 Action(`Meeting\StoreAction` / `CancelAction` / `UpsertMemoAction`)に切り出され、各 Controller メソッドは Action 呼出 + 認可委譲 + レスポンス整形のみの薄いラッパー(業務ロジックの if / 計算が 0 行)になっている
- [ ] 受講生向け一覧 / コーチ向け一覧 / 面談詳細 / 空き枠取得の取得系 4 メソッドのデータ取得処理が、対応する Action(`Meeting\IndexAction` / `IndexAsCoachAction` / `ShowAction` / `FetchAvailabilityAction`)に切り出され、各 Controller メソッドはクエリ組み立てを持たず Action 呼出 + レスポンス整形のみになっている(予約フォーム表示の `create` / `createFallback` は Action を持たず対象外)
- [ ] 状態変更を伴う Action はトランザクション境界で全副作用を囲み、整合性違反(残面談回数 0 / 枠外 / 候補コーチ 0 名 / 状態遷移不可 / 開始時刻超過 等)は Action 内で具象例外(`app/Exceptions/Mentoring/*` + `InsufficientMeetingQuotaException`)を throw する
- [ ] 本チケットの機能に対するテスト (Unit / Feature 等) が実装されている(抽出した状態変更系 + 取得系の各 Action の単体テストを含む)

## 実装方針(参考)

### 変更内容

- **対象**: `app/Http/Controllers/MeetingController.php`(状態変更系 `store` / `cancel` / `upsertMemo` + 取得系 `index` / `indexAsCoach` / `show` / `fetchAvailability` を薄化、`create` / `createFallback` は対象外)+ 新規抽出 `app/UseCases/Meeting/{Store,Cancel,UpsertMemo}Action.php`(状態変更系)/ `{Index,IndexAsCoach,Show,FetchAvailability}Action.php`(取得系)+ 新規 `tests/Feature/UseCases/Meeting/{Store,Cancel,UpsertMemo,Index,IndexAsCoach,Show,FetchAvailability}ActionTest.php` / 画面・経路: `store` = `POST /enrollments/{enrollment}/meetings`(担当コーチ自動割当 + 面談回数 -1 + コーチ宛通知。外部カレンダー連携を実装済みなら予定作成も含む)/ `cancel` = `POST /meetings/{meeting}/cancel`(面談回数 +1 + 相手方通知。外部カレンダー連携を実装済みなら予定削除も含む)/ `upsertMemo` = `PUT /coach/meetings/{meeting}/memo`(コーチによる面談メモ作成・更新)/ 取得系 = `index`(受講生面談一覧)・`indexAsCoach`(コーチ面談一覧)・`show`(面談詳細)・`fetchAvailability`(空き枠取得 JSON `GET /enrollments/{enrollment}/meetings/availability`)
- **変更前→後**: 状態変更系 3 メソッドの業務ロジック(認可後の残数チェック / 枠外検証 / 空きコーチ抽出 + 過去実績最少コーチ選出 / `DB::transaction` での Meeting・`MeetingQuotaTransaction` INSERT + UNIQUE 違反 catch / `DB::afterCommit` での通知 + 外部カレンダー連携[実装時])が Controller に 60〜80 行ベタ書きで `Meeting\*Action` 未存在 → 各メソッドを `$action(...)` 呼出 + リダイレクト + フラッシュのみに薄化(業務ロジックの if / 計算 0 行)し、`Meeting\StoreAction` 等が協力 Service / Action(`MeetingAvailabilityService` / `CoachMeetingLoadService` / `MeetingQuotaService` / `ConsumeQuotaAction` / `NotifyMeetingReservedAction`、および外部カレンダー連携を実装済みなら連携 Service)を `readonly` DI して `__invoke` 内に `DB::transaction()` + `DB::afterCommit()` を持つ。取得系(`index` / `indexAsCoach` / `show` / `fetchAvailability`)もクエリ組み立てを `Index` / `IndexAsCoach` / `Show` / `FetchAvailabilityAction` へ抽出し、Controller はフィルタ引数を渡して結果を View / JSON 整形するだけにする(副作用なしの薄い Action)。`create` / `createFallback`(認可 + View のみ)と `AutoCompleteMeetingAction`(Schedule Command 経路)は対象外
- **判断理由**: Action パターン(`__invoke()` 単一責務)で「Controller メソッド名 = Action クラス名」とし navigation を直感化、認可は Controller / FormRequest・整合性ガード(具象例外 `app/Exceptions/Mentoring/*` + `MeetingQuota\InsufficientMeetingQuotaException` の throw)は Action 内、の責務分離。通知や外部カレンダー連携を `DB::afterCommit()` 遅延実行とするのは付加処理失敗で本予約を巻き戻さないため。参考実装 `app/UseCases/MeetingPack/StoreAction.php` / `Plan/StoreAction.php`
- **テスト**: 抽出 Action を `app(StoreAction::class)(...)` で直接呼び正常系 + 例外パス(残数 0 / 枠外 / 候補 0 名 / UNIQUE 違反 / 既キャンセル・既完了 / 開始時刻超過)+ 副作用(通知 / 面談回数遷移 / 外部カレンダー連携を実装済みならその Mockery)+ トランザクション原子性(例外時に全ロールバック)を網羅 + 既存 HTTP テスト(`tests/Feature/Http/Meeting/`)の pass(振る舞い不変)。取得系 Action は各 Action を直接呼びフィルタ別(upcoming / past / all、コーチの担当受講生 / 受講登録絞り込み)の取得結果 + Eager Loading を検証(副作用なしのため正常系中心)

## 補足

### 想定 Q&A

| 質問 | 回答 |
|---|---|
| Action クラスは「1 Controller method = 1 Action」の対応で良い? | はい。Controller メソッド名(camelCase)と Action クラス名(PascalCase + "Action")を完全一致。`store()` → `StoreAction` / `cancel()` → `CancelAction` / `upsertMemo()` → `UpsertMemoAction` |
| 取得系(`index` / `indexAsCoach` / `show` / `fetchAvailability`)も Action 分離する? | する。本プロジェクトは全 Controller で「1 メソッド = 1 Action」を踏襲しており、既存パターン準拠のため取得系もデータ取得を対応 Action(`IndexAction` 等)へ切り出す。副作用がないぶん Action は薄くなるが、Controller にクエリ組み立てを残さない |
| 予約フォーム表示(`create` / `createFallback`)も Action 化する? | しない。認可 + View 返却のみで業務ロジック・データ取得を持たないため、既存パターンでも Action を持たず Controller にそのまま残す |
| Action のエントリポイントメソッド名は? | `__invoke()` 推奨(1 クラス 1 責務の明示)。Controller からは `$action($args)` で呼ぶ |
| トランザクション境界は Controller / Action どちら? | Action 内。Controller は HTTP 受付に専念し、業務ロジックの境界は Action が責任を持つ |
| Action 内で認可(`$this->authorize`)を呼んで良い? | 呼ばない。認可は Controller の `$this->authorize()` または FormRequest の `authorize()`。Action 内では状態整合性チェック(残数 0 / 枠外 / 既遷移済 / 開始時刻超過)のみ行い、不整合時に具象例外を throw |
| 例外は何を throw する? | `app/Exceptions/Mentoring/` の具象例外(`MeetingNoAvailableCoachException` / `MeetingStatusTransitionException` / `MeetingAlreadyStartedException` 409 系 / `MeetingOutOfAvailabilityException` 422)+ `MeetingQuota\InsufficientMeetingQuotaException`(409)。汎用 `\Exception` 直接 throw は規約違反 |
| 通知発火や外部カレンダー連携(実装時)はトランザクション内 / 外? | `DB::afterCommit()` で commit 後実行。通知や連携の失敗で本予約が巻き戻ると整合性が崩れるため、commit 成功後に付加処理を実行する |
| 既存 HTTP テストは触らないと pass しなくなる? | そのまま pass する設計(HTTP 振る舞いは完全に同一)。Action 分離は内部構造の変更。Action 単体テストは新規追加する |
| Action 単体テストはどこに書く? | `tests/Feature/UseCases/Meeting/{Store,Cancel,UpsertMemo}ActionTest.php`。Action を `app(StoreAction::class)(...)` で直接呼んで業務分岐を網羅 |
| Service(`MeetingAvailabilityService` 等)も分離する必要ある? | 本チケットでは扱わない。Service 分離は別途実施。Action 内では既存 Service を DI で呼ぶ形のままで OK |
