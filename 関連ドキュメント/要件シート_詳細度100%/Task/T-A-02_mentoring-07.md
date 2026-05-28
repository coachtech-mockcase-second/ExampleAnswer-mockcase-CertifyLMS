# T-A-02 mentoring の Controller method を Action 分離

<!--
記述粒度規約: 実装粒度(テーブル名 / カラム名 / クラス名 / SQL 詳細 / Laravel 実装語彙 / URL パス詳細 等)を記載できるのは `## 実装方針` 配下のみ。それ以外のセクション(概要 / 背景・目的 / やること / やらないこと / 補足)は **業務語彙のみ** で記述する。詳細規約は `../../../.claude/rules/ticket-spec.md`「実装粒度の記載範囲」参照。
受け入れ条件は構造記述 / Before/After 計測値ベース(Performance では計測値が振る舞いの代替指標、Refactoring では振る舞い不変 + 構造変更点)。
-->

## メタ情報

| 項目 | 値 |
|---|---|
| チケット ID | `T-A-02` |
| Feature 連番 | `mentoring-07` |
| Feature | mentoring |
| 種別 | Task |
| サブカテゴリ | リファクタリング |
| 難易度 | Advance |
| 工数 (h) | 4 |
| 依存チケット | `S-A-01`(Google Calendar 連携で `MeetingController` 内の業務ロジックがさらに肥大化した状態が対象) |

## 概要

mentoring Feature の Controller(`MeetingController`)で 1 メソッドあたり数十行に膨らんだ業務ロジックを、`app/UseCases/Meeting/` 配下の単一責務 Action クラスに切り出すリファクタリング。「1 Controller メソッド = 1 Action クラス」「Controller は薄く保つ」を本プロジェクト規約に揃え、`backend-http.md` + `backend-usecases.md` の Clean Architecture 軽量版規約に準拠させる。

## 背景・目的

- **現状の問題**: 受講生による面談予約 / 当事者によるキャンセル / コーチによる面談メモ記録 の 3 操作が、Controller メソッド内に「残面談回数チェック → 枠検証 → 候補コーチ抽出 → 自動割当 → 予約 INSERT → 面談回数消費 → 通知発火 → Google カレンダーイベント作成」のように 60〜80 行の手続きをベタ書きしている。Controller が「リクエスト受付 + 業務ロジック + レスポンス整形」を兼ね、テスト容易性 / 単一責務 / 再利用性のすべてが損なわれた状態。`S-A-01`(Google カレンダー連携)実装後はさらに行数が増え、コードリーディングが急速に困難化する。
- **達成したい状態**: Controller メソッドは「リクエスト受付 → 認可委譲 → Action 呼出 → レスポンス整形」のみを担い、業務ロジックは `app/UseCases/Meeting/{Store,Cancel,UpsertMemo}Action.php` の `__invoke()` に集約される。トランザクション境界 / 例外 throw / 副作用(通知 / 外部 API)が Action 内に閉じ込められ、Action 単体の Feature テストで業務分岐を網羅検証できる。
- **価値・優先度**: **Clean Architecture 軽量版** を実装で示す中核チケット。`backend-usecases.md` の「Controller メソッド名 = Action クラス名」「1 業務操作 = 1 Action」「データ整合性チェックは Action 内」「DB::transaction() で囲む」を既存コードを題材に再構築する。

## やること

- 受講生による面談予約 / 当事者によるキャンセル / コーチによる面談メモ記録 の 3 つの Controller メソッドから業務ロジックを **Action クラスへ抽出** する
- 抽出した Action クラスを `app/UseCases/Meeting/` 配下に **「Controller メソッド名 = Action クラス名」** の命名で配置する
- Controller メソッドは **抽出 Action を DI で受け取り `__invoke()` で呼ぶだけの薄いラッパー** に書き直す(リクエスト受付 / 認可委譲 / レスポンス整形のみ)
- 状態変更を伴う Action は **`DB::transaction()` で囲む**(残面談回数の消費・返却、通知発火、Google カレンダー連動は同一トランザクション境界に含める)
- 抽出した Action それぞれに対し **Action 単体の Feature テスト** を `tests/Feature/UseCases/Meeting/` 配下に新規追加する(正常系 + 例外パス + 副作用)
- 既存の Controller 経由 Feature テスト(`MeetingControllerTest` 相当)が改修後も pass し、HTTP 振る舞い(認可 / バリデーション / リダイレクト / フラッシュ)が変わっていないことを担保する

## やらないこと

- 一覧取得 / 詳細表示 / 空き枠取得 の単純取得系メソッド(`index` / `show` / `fetchAvailability` / `indexAsCoach`)の Action 分離 — 取得系は副作用がなく、Controller 内 1〜3 行で完結するため本チケットのスコープ外。模範解答 PJ では取得系 Action も存在するが、リファクタチケットとしては「肥大化している状態変更系のみ」に絞る
- 自動完了の Schedule Command(`AutoCompleteMeetingAction`)— Schedule Command から呼ばれる Action は既に Command ハンドラで分離されており、Controller リファクタの対象外
- Action 内で呼ぶ協力 Service(`MeetingAvailabilityService` / `CoachMeetingLoadService` / `MeetingQuotaService`)の分離 — `T-A-03` の Service 分離 と棲み分け、Service 自体の責務見直しは別チケット
- 認可ロジックの Policy 抽出 — Policy はすでに存在(`MeetingPolicy`)、変更しない
- 振る舞いを変える変更 — リクエスト・レスポンス・DB 副作用・通知文面 すべて同一

## 受け入れ条件

- [ ] **Action ファイル新規作成**: `app/UseCases/Meeting/` 配下に **受講生予約 / 当事者キャンセル / コーチメモ upsert の 3 つの Action クラス** が存在し、各クラスがそれぞれ `__invoke()` を公開している
- [ ] **Action クラス名の規約**: 各 Action クラス名が **対応する Controller メソッド名と一致**(`store()` → `StoreAction` / `cancel()` → `CancelAction` / `upsertMemo()` → `UpsertMemoAction`、`backend-usecases.md` 規約準拠)
- [ ] **Controller の薄化**: 該当 3 メソッドのメソッド本体が **5 行以下**(Action DI 受け取り + 認可 + `__invoke()` 呼出 + レスポンス整形のみ、業務ロジックの if/計算は 0 行)
- [ ] **トランザクション境界**: 状態変更を伴う Action(予約 / キャンセル / メモ upsert)は **`DB::transaction()` で全副作用を囲む**(DB 更新 / 関連レコード INSERT / 例外時のロールバック)
- [ ] **データ整合性ガード**: 例外パス(残面談回数 0 / 枠外 / 開始時刻超過 / 既キャンセル / 既完了 等)は **Action 内で具象例外を throw**(`InsufficientMeetingQuotaException` / `MeetingOutOfAvailabilityException` / `MeetingNoAvailableCoachException` / `MeetingStatusTransitionException` / `MeetingAlreadyStartedException` 等、`app/Exceptions/Mentoring/` 配下)
- [ ] **Action 単体テスト新規追加**: `tests/Feature/UseCases/Meeting/{Store,Cancel,UpsertMemo}ActionTest.php` に 各 Action の正常系 + 例外パス + 副作用(通知発火 / 面談回数遷移)を網羅したテストが存在する
- [ ] **既存 Feature テスト pass**: Controller 経由の既存 Feature テスト(`tests/Feature/Http/Meeting/` 配下)が改修後も全件 pass
- [ ] **振る舞い不変**: HTTP リクエスト・レスポンス(ステータスコード / リダイレクト先 / フラッシュ表示)・DB 副作用・通知発火 が改修前後で完全に一致

## 実装方針

> **参考設計の一例**。受け入れ条件を満たせれば実装手段は問わない。受講生は提供 PJ コード + ヒアリングで自分の設計を組み立てる。

### 主要 URL

| メソッド | パス | 振る舞い | 対応 Action(リファクタ後) |
|---|---|---|---|
| POST | `/enrollments/{enrollment}/meetings` | 受講生による面談予約。担当コーチ自動割当 + 面談回数 -1 消費 + コーチ宛通知 + GCal event 作成 | `App\UseCases\Meeting\StoreAction` |
| POST | `/meetings/{meeting}/cancel` | 当事者(受講生 or コーチ)によるキャンセル。面談回数 +1 返却 + 相手方通知 + GCal event 削除 | `App\UseCases\Meeting\CancelAction` |
| PUT | `/coach/meetings/{meeting}/memo` | 担当コーチによる面談メモの新規作成 / 更新(upsert)。`reserved` / `completed` どちらの状態でも可 | `App\UseCases\Meeting\UpsertMemoAction` |

### 変更対象と変更前後の状態

- **変更対象ファイル候補**:
  - `app/Http/Controllers/MeetingController.php`(該当 3 メソッドを薄化)
  - `app/UseCases/Meeting/StoreAction.php`(新規作成、本チケットで抽出)
  - `app/UseCases/Meeting/CancelAction.php`(新規作成)
  - `app/UseCases/Meeting/UpsertMemoAction.php`(新規作成)
  - `tests/Feature/UseCases/Meeting/{Store,Cancel,UpsertMemo}ActionTest.php`(新規作成、Action 単体テスト)

- **変更前の状態**(リファクタ前 / 提供 PJ で受講生が直面する状態):
  - `MeetingController::store` メソッド内に直接以下が展開:
    - 認可(`$this->authorize('create', Meeting::class)`)
    - 残面談回数チェック(`MeetingQuotaService::remaining()` 呼出)
    - 枠外検証(`MeetingAvailabilityService::validateSlot()` 呼出 or 直接クエリ)
    - 担当コーチ集合の取得 + 当該時刻の空きコーチ抽出
    - 過去 30 日 completed 数最少コーチ選出(`CoachMeetingLoadService` 呼出 or 直接 SQL)
    - `DB::transaction()` + Meeting INSERT + UNIQUE 違反 catch + `MeetingQuotaTransaction` INSERT + Meeting への `meeting_quota_transaction_id` UPDATE
    - `DB::afterCommit()` での Google `insertEvent` 呼出 + 通知発火(`NotifyMeetingReservedAction` 呼出)
    - 60〜80 行の手続きベタ書き
  - `cancel` / `upsertMemo` も同様に Controller 内ベタ書き
  - `app/UseCases/Meeting/` 配下に StoreAction / CancelAction / UpsertMemoAction が **存在しない**

- **変更後の理想形**(リファクタ後 / 模範解答 PJ の完成形):
  - `MeetingController::store(Enrollment $enrollment, StoreRequest $request, StoreAction $action): RedirectResponse` のシグネチャで `StoreAction` を DI 受け取り、`$action($enrollment, $scheduledAt, $topic)` を呼ぶだけ。リダイレクト + フラッシュのみ Controller が担当
  - `StoreAction` は constructor で `MeetingAvailabilityService` / `CoachMeetingLoadService` / `MeetingQuotaService` / `ConsumeQuotaAction` / `NotifyMeetingReservedAction` / `GoogleCalendarService` を `readonly` DI
  - `__invoke(Enrollment $enrollment, Carbon $scheduledAt, string $topic): Meeting` を公開
  - 内部で `DB::transaction()` で全副作用を囲み、`DB::afterCommit()` で通知発火 / GCal event 作成を遅延実行
  - 例外パス(残数 0 / 枠外 / 候補 0 名 / UNIQUE 違反)は具象例外 throw
  - `CancelAction` / `UpsertMemoAction` も同パターン

### 変更対象 Controller メソッドの理想シグネチャ(参考)

```php
// Before (Controller 内に業務ロジックがベタ書きされている、推定 60-80 行)
public function store(Enrollment $enrollment, StoreRequest $request): RedirectResponse
{
    $this->authorize('create', Meeting::class);
    // ... 残面談回数チェック / 枠外検証 / 候補コーチ抽出 / 自動割当 /
    //     DB::transaction で Meeting INSERT + Quota 消費 + 通知 + GCal event 作成 ...
}

// After (Action 分離後、3-5 行に薄化)
public function store(Enrollment $enrollment, StoreRequest $request, StoreAction $action): RedirectResponse
{
    $scheduledAt = Carbon::parse($request->validated('scheduled_at'));
    $meeting = $action($enrollment, $scheduledAt, $request->validated('topic'));

    return redirect()
        ->route('meetings.show', $meeting)
        ->with('success', '面談を予約しました。担当コーチに通知を送信しました。');
}
```

### テスト方針

| 種別 | 観点 |
|---|---|
| 振る舞い不変 | `tests/Feature/Http/Meeting/MeetingControllerTest`(あるいは `tests/Feature/Http/Meeting/StoreTest` 等)既存の HTTP テスト(認可 / バリデーション / リダイレクト / フラッシュ / DB 副作用 / 通知発火)が改修後も全件 pass |
| 構造(Action 単体) | `tests/Feature/UseCases/Meeting/StoreActionTest.php` を新規追加し、Action を `app(StoreAction::class)(...)` で直接呼んで以下を網羅: 正常系(自動コーチ割当 + 面談回数消費 + 通知発火)/ 残数 0 例外 / 枠外例外 / 候補 0 名例外 / UNIQUE 違反 race condition 例外 / GCal 連携済コーチへの event 作成 / 連携未設定コーチへの event 不発火 |
| 構造(キャンセル Action) | `tests/Feature/UseCases/Meeting/CancelActionTest.php`: 正常系(状態遷移 + 面談回数返却 + 相手方通知)/ 既キャンセル例外 / 既完了例外 / 開始時刻超過例外 / GCal event 削除呼出 |
| 構造(メモ Action) | `tests/Feature/UseCases/Meeting/UpsertMemoActionTest.php`: 新規作成 / 既存更新(upsert) / `reserved` 状態で作成可 / `completed` 状態で作成可 / `canceled` 状態で状態遷移例外 |
| トランザクション原子性 | Action 内で例外発生時に **DB が全ロールバック**(Meeting INSERT も Quota Transaction も巻き戻る)を `assertDatabaseCount`(0) で検証 |

### 採用技術と判断理由

- **採用技術**: Action パターン(`{Controller method}Action.php` の単一責務クラス、`__invoke()` 主導)/ `DB::transaction()` 境界 / `DB::afterCommit()` 通知発火 / 具象例外 throw(`app/Exceptions/Mentoring/` 配下)/ Constructor Injection
- **判断理由**:
  1. **「1 Controller method = 1 Action」規約**(`backend-usecases.md`): Controller メソッド名 と Action クラス名("Action" 接尾辞を除いた部分)を完全一致させることで、コード navigation が直感的になる(`store()` → `StoreAction`、迷いゼロ)
  2. **Controller を薄く保つ**(`backend-http.md`): Controller メソッド内に if 文 / 計算が増えたら Action に移すのが本プロジェクト規約。リクエスト受付 / 認可委譲 / Action 呼出 / レスポンス整形 の 4 責務に限定
  3. **データ整合性ガードは Action 内**(`backend-policies.md`): 認可(Policy)は Controller / FormRequest で実施し、状態整合性チェック(残数 0 / 枠外 / 既状態遷移済 / 開始時刻超過)は Action 内で具象例外 throw する責務分離
  4. **トランザクション境界の明示**: 「Meeting INSERT + Quota Transaction INSERT + Meeting への transaction_id 更新」を **単一 `DB::transaction()`** で囲むことで、途中失敗時の部分書込を防ぐ。通知 / GCal event 作成は `DB::afterCommit()` で commit 後実行(失敗しても本予約は成立、付加機能扱い)
  5. **Action 単体テストの容易性**: Action を直接 `app(StoreAction::class)(...)` で呼べる構造にすることで、HTTP 文脈なしで業務分岐を網羅検証できる。複雑な例外パス(race condition / 候補 0 名)も Mockery + Notification::fake で完結
  6. **取得系を対象外にする判断**: `index` / `show` / `fetchAvailability` は副作用がなく Controller 内 1-3 行で済むため、リファクタの優先度が低い(模範解答 PJ では Action 化されているが、本リファクタチケットでは「肥大化している状態変更系」に絞る)

### 改善対象コードメモ

- 改善対象の主要ファイル: `app/Http/Controllers/MeetingController.php`(`store` / `cancel` / `upsertMemo` の 3 メソッド)
- 抽出先: `app/UseCases/Meeting/StoreAction.php` / `CancelAction.php` / `UpsertMemoAction.php`(新規作成)
- Action の参考実装例として、他 Feature(`app/UseCases/MeetingPack/StoreAction.php` / `app/UseCases/Plan/StoreAction.php` 等)に既存パターンが多数あり、受講生がこれを `Read` して倣う流れ
- 例外クラス配置先: `app/Exceptions/Mentoring/{MeetingNoAvailableCoachException,MeetingStatusTransitionException,MeetingAlreadyStartedException,MeetingOutOfAvailabilityException}.php` / `app/Exceptions/MeetingQuota/InsufficientMeetingQuotaException.php`(既存例外、Action から throw)
- `Meeting\StoreAction` が Google カレンダー連携で `GoogleCalendarService::insertEvent()` を呼ぶ部分は `T-A-03`(Service 分離)とコンテキスト連携。本チケットは「Controller → Action 分離」のみが対象、Service 分離は `T-A-03` で別途実施

## 補足

### 想定ヒアリング Q&A

| 質問 | 回答 |
|---|---|
| Action クラスは「1 Controller method = 1 Action」の対応で良い? | はい、Controller メソッド名(camelCase)と Action クラス名(PascalCase + "Action")を **完全一致** させる。`store()` → `StoreAction` / `cancel()` → `CancelAction` / `upsertMemo()` → `UpsertMemoAction`。 |
| 取得系(`index` / `show` / `fetchAvailability` / `indexAsCoach`)も Action 分離する? | **本チケットの対象外**。取得系は副作用がなく Controller 内 1-3 行で完結するため、リファクタの優先度が低い。模範解答 PJ では Action 化されているが、本リファクタでは状態変更系(`store` / `cancel` / `upsertMemo`)に絞る。 |
| Action のエントリポイントメソッド名は `__invoke()` で良い? | はい、規約上 **`__invoke()` 推奨**(1 クラス 1 責務の明示)。Controller からは `$action($args)` で呼ぶ。 |
| `DB::transaction()` は Controller / Action のどちらで囲む? | **Action 内**。Controller は HTTP 受付に専念し、業務ロジックの境界(トランザクション)は Action が責任を持つ。 |
| Action 内で `$this->authorize(...)` を呼んで良い? | **呼ばない**。認可(Policy)は Controller の `$this->authorize()` または FormRequest の `authorize()` で実施。Action 内では **状態整合性チェック**(残数 0 / 枠外 / 既遷移済 / 開始時刻超過)のみ行い、不整合時は具象例外を throw する。 |
| 例外は何を throw する? | `app/Exceptions/Mentoring/` 配下の具象例外(`MeetingNoAvailableCoachException` 409 / `MeetingStatusTransitionException` 409 / `MeetingAlreadyStartedException` 409 / `MeetingOutOfAvailabilityException` 422)+ `app/Exceptions/MeetingQuota/InsufficientMeetingQuotaException` 409。汎用 `\Exception` 直接 throw は規約違反。 |
| 通知発火 / Google カレンダーイベント作成はトランザクション内 / 外? | **`DB::afterCommit()` で commit 後実行**。通知失敗 / GCal 失敗で本予約が巻き戻ると整合性が崩れるため、commit が成功してから付加処理を実行する。 |
| 既存 HTTP テスト(`MeetingControllerTest`)は触らないと pass しなくなる? | **そのまま pass する設計**(HTTP 振る舞いが完全に同一)。Action 分離は内部構造の変更で、Controller シグネチャと HTTP レスポンスは変わらない。Action 単体テストは **新規追加** する。 |
| Action 単体テストはどこに書く? | `tests/Feature/UseCases/Meeting/{Store,Cancel,UpsertMemo}ActionTest.php`(`backend-tests.md`「Feature(UseCases) — Action 単体の業務ロジックテスト」配置規約準拠)。Action を `app(StoreAction::class)(...)` で直接呼んで業務分岐を網羅検証。 |
| Service(`MeetingAvailabilityService` 等)も分離する必要ある? | **本チケットでは扱わない**。Service 分離は `T-A-03`(Google Calendar 連携を Service 分離)で別途実施。Action 内では既存 Service を DI で呼び出す形のままで OK。 |
