# T-A-02 mentoring の Controller method を Action 分離

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

## 要件

- 受講生による面談予約 / 当事者によるキャンセル / コーチによる面談メモ記録 の 3 つの Controller メソッドから業務ロジックを Action クラスへ抽出する
- 抽出した Action クラスを `app/UseCases/Meeting/` 配下に「Controller メソッド名 = Action クラス名」の命名で配置する
- Controller メソッドは抽出 Action を受け取って呼ぶだけの薄いラッパーに書き直す(リクエスト受付 / 認可委譲 / レスポンス整形のみ)
- 状態変更を伴う Action はトランザクション境界で囲む(残面談回数の消費、通知発火、Google カレンダー連動を同一境界に含める)
- 抽出した Action それぞれに対し Action 単体テストを新規追加する(正常系 + 例外パス + 副作用)

## スコープ外

- 一覧取得 / 詳細表示 / 空き枠取得 の単純取得系メソッド(`index` / `show` / `fetchAvailability` / `indexAsCoach`)の Action 分離 — 取得系は副作用がなく Controller 内 1〜3 行で完結するためスコープ外
- 自動完了の Schedule Command(`AutoCompleteMeetingAction`)— Schedule Command から呼ばれる Action は既に分離済で Controller リファクタの対象外
- Action 内で呼ぶ協力 Service(`MeetingAvailabilityService` / `CoachMeetingLoadService` / `MeetingQuotaService`)の責務見直し — Service 分離 Task との棲み分け
- 認可ロジックの Policy 抽出 — Policy(`MeetingPolicy`)はすでに存在、変更しない
- 振る舞いを変える変更 — リクエスト・レスポンス・DB 副作用・通知文面すべて同一

## 受け入れ条件

> **件数目安**: Task 1-3 件。振る舞い不変 / 既存テスト全 pass は AC に書かず評価シート ② 横断品質で扱う。

- [ ] 受講生予約 / 当事者キャンセル / コーチメモ upsert の 3 操作の業務ロジックが Controller メソッドから単一責務 Action(`Meeting\StoreAction` / `CancelAction` / `UpsertMemoAction`、Controller メソッド名と一致)に切り出され、各 Controller メソッドは Action 呼出 + 認可委譲 + レスポンス整形のみの薄いラッパー(業務ロジックの if / 計算が 0 行)になっている
- [ ] 状態変更を伴う Action はトランザクション境界で全副作用を囲み、整合性違反(残面談回数 0 / 枠外 / 候補コーチ 0 名 / 状態遷移不可 / 開始時刻超過 等)は Action 内で具象例外(`app/Exceptions/Mentoring/*` + `InsufficientMeetingQuotaException`)を throw する
- [ ] 本チケットの機能に対するテスト (Unit / Feature 等) が実装されている(抽出した各 Action の単体テストを含む)

## 実装方針(参考)

> **粒度**: 業務語彙 + 技術名(変更対象ファイルパス・クラス名・メソッド名)を併記。具体的なコード片 / メソッド完全例は書かない。

### 変更内容

- **変更対象ファイル**:
  - `app/Http/Controllers/MeetingController.php`(`store` / `cancel` / `upsertMemo` の 3 メソッドを薄化)
  - `app/UseCases/Meeting/StoreAction.php` / `CancelAction.php` / `UpsertMemoAction.php`(本チケットで抽出する新規 Action)
  - `tests/Feature/UseCases/Meeting/{Store,Cancel,UpsertMemo}ActionTest.php`(新規、Action 単体テスト)
- **URL 対応**: `store` = `POST /enrollments/{enrollment}/meetings`(担当コーチ自動割当 + 面談回数 -1 消費 + コーチ宛通知 + GCal event 作成)/ `cancel` = `POST /meetings/{meeting}/cancel`(面談回数 +1 返却 + 相手方通知 + GCal event 削除)/ `upsertMemo` = `PUT /coach/meetings/{meeting}/memo`(担当コーチによる面談メモの新規作成 / 更新)
- **変更前**(提供 PJ で受講生が直面する状態): `MeetingController::store` メソッド内に「認可 → 残面談回数チェック(`MeetingQuotaService::remaining`)→ 枠外検証(`MeetingAvailabilityService::validateSlot`)→ 担当コーチ集合取得 + 空きコーチ抽出 → 過去実績最少コーチ選出(`CoachMeetingLoadService`)→ `DB::transaction` で Meeting INSERT + UNIQUE 違反 catch + `MeetingQuotaTransaction` INSERT + Meeting への `meeting_quota_transaction_id` UPDATE → `DB::afterCommit` で GCal `insertEvent` + 通知発火(`NotifyMeetingReservedAction`)」の 60〜80 行を直接展開。`cancel` / `upsertMemo` も同様にベタ書き。`app/UseCases/Meeting/` 配下に `StoreAction` / `CancelAction` / `UpsertMemoAction` が存在しない
- **変更後**(模範解答 PJ の完成形): Controller の各メソッドは Action を受け取り `$action(...)` を呼んでリダイレクト + フラッシュのみ担当(`store` は `$action(enrollment, scheduledAt, topic)` → `meetings.show` へ「面談を予約しました。担当コーチに通知を送信しました。」/ `cancel` は `$this->authorize('cancel', $meeting)` → `$action($meeting, auth ユーザー)` → 「面談をキャンセルしました。面談回数を返却しました。」/ `upsertMemo` は `$action($meeting, body)` → 「面談メモを保存しました。」)。`Meeting\StoreAction` は協力 Service / Action(`MeetingAvailabilityService` / `CoachMeetingLoadService` / `MeetingQuotaService` / `ConsumeQuotaAction` / `NotifyMeetingReservedAction` / `GoogleCalendarService`)を `readonly` DI し、`__invoke` 内で `DB::transaction()` + `DB::afterCommit()`(通知 / GCal)を持つ。`CancelAction` / `UpsertMemoAction` も同パターン
- **対象範囲**: 状態変更系 3 メソッドのみ。取得系(`index` / `show` / `fetchAvailability` / `indexAsCoach`)は副作用なしで Controller 1〜3 行のため対象外、`AutoCompleteMeetingAction` は Schedule Command 経路で対象外
- **採用技術と判断理由**: Action パターン(`__invoke()` 主導の単一責務クラス)/ `DB::transaction()` 境界 + `DB::afterCommit()` での通知 / GCal 遅延実行(付加処理失敗で本予約を巻き戻さない)/ 具象例外 throw(`app/Exceptions/Mentoring/{MeetingNoAvailableCoachException,MeetingStatusTransitionException,MeetingAlreadyStartedException,MeetingOutOfAvailabilityException}` + `MeetingQuota\InsufficientMeetingQuotaException`)。「Controller メソッド名 = Action クラス名」でコード navigation を直感化、認可は Controller / FormRequest・整合性ガードは Action 内、の責務分離(`backend-usecases.md` / `backend-http.md` / `backend-policies.md` 準拠)。Action 参考実装は `app/UseCases/MeetingPack/StoreAction.php` / `app/UseCases/Plan/StoreAction.php` 等の既存パターン
- **テスト観点**: `tests/Feature/Http/Meeting/` の既存 HTTP テスト(認可 / バリデーション / リダイレクト / フラッシュ / DB 副作用 / 通知発火)を改修後も pass させ振る舞い不変を担保 + `tests/Feature/UseCases/Meeting/{Store,Cancel,UpsertMemo}ActionTest` を新規追加し、Action を `app(StoreAction::class)(...)` で直接呼んで正常系 + 例外パス(残数 0 / 枠外 / 候補 0 名 / UNIQUE 違反 / 既キャンセル / 既完了 / 開始時刻超過)+ 副作用(通知発火 / 面談回数遷移 / GCal event 作成・削除の Mockery)+ トランザクション原子性(例外時に Meeting INSERT も Quota Transaction も全ロールバック)を網羅

## 補足

### 想定 Q&A

| 質問 | 回答 |
|---|---|
| Action クラスは「1 Controller method = 1 Action」の対応で良い? | はい。Controller メソッド名(camelCase)と Action クラス名(PascalCase + "Action")を完全一致。`store()` → `StoreAction` / `cancel()` → `CancelAction` / `upsertMemo()` → `UpsertMemoAction` |
| 取得系(`index` / `show` / `fetchAvailability` / `indexAsCoach`)も Action 分離する? | 本チケットの対象外。取得系は副作用がなく Controller 内 1〜3 行で完結するため。模範解答 PJ では Action 化されているが、本リファクタは肥大化している状態変更系に絞る |
| Action のエントリポイントメソッド名は? | `__invoke()` 推奨(1 クラス 1 責務の明示)。Controller からは `$action($args)` で呼ぶ |
| トランザクション境界は Controller / Action どちら? | Action 内。Controller は HTTP 受付に専念し、業務ロジックの境界は Action が責任を持つ |
| Action 内で認可(`$this->authorize`)を呼んで良い? | 呼ばない。認可は Controller の `$this->authorize()` または FormRequest の `authorize()`。Action 内では状態整合性チェック(残数 0 / 枠外 / 既遷移済 / 開始時刻超過)のみ行い、不整合時に具象例外を throw |
| 例外は何を throw する? | `app/Exceptions/Mentoring/` の具象例外(`MeetingNoAvailableCoachException` / `MeetingStatusTransitionException` / `MeetingAlreadyStartedException` 409 系 / `MeetingOutOfAvailabilityException` 422)+ `MeetingQuota\InsufficientMeetingQuotaException`(409)。汎用 `\Exception` 直接 throw は規約違反 |
| 通知発火 / Google カレンダー event 作成はトランザクション内 / 外? | `DB::afterCommit()` で commit 後実行。通知 / GCal 失敗で本予約が巻き戻ると整合性が崩れるため、commit 成功後に付加処理を実行する |
| 既存 HTTP テストは触らないと pass しなくなる? | そのまま pass する設計(HTTP 振る舞いは完全に同一)。Action 分離は内部構造の変更。Action 単体テストは新規追加する |
| Action 単体テストはどこに書く? | `tests/Feature/UseCases/Meeting/{Store,Cancel,UpsertMemo}ActionTest.php`。Action を `app(StoreAction::class)(...)` で直接呼んで業務分岐を網羅 |
| Service(`MeetingAvailabilityService` 等)も分離する必要ある? | 本チケットでは扱わない。Service 分離は別途実施。Action 内では既存 Service を DI で呼ぶ形のままで OK |
