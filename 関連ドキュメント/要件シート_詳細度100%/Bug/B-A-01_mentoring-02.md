# B-A-01 面談予約の並行リクエストで面談回数が二重消費される

## メタ情報

| 項目 | 値 |
|---|---|
| チケット ID | `B-A-01` |
| Feature 連番 | `mentoring-02` |
| Feature | mentoring(連携: meeting-quota) |
| 種別 | Bug |
| サブカテゴリ | 並行性(TOCTOU / 排他ロック漏れ) |
| 難易度 | Advance |
| 工数 (h) | 6 |
| 依存チケット | (なし) |

## 概要

残面談回数が 1 件しかない受講生(student)が、ほぼ同時に 2 つの面談予約リクエストを送ると、本来 1 件しか成立してはいけないのに **2 件とも予約が成立** してしまう。結果として面談回数の残数が **マイナス**(消費 2 件 + 初期付与 1 件 = -1)になり、会計上の不整合が発生する。

## 再現手順

**前提**: 受講生でログイン済み。残面談回数が **ちょうど 1 件**(初期付与 1 + 消費 0 + 返却 0 + 購入 0 + 管理者付与 0)。担当コーチが少なくとも 2 名おり、同一の予約可能時刻枠を持っている。

1. 同一の受講生として、別々の面談時刻スロット(または別タブ)で予約フォームを開く
2. 同じ瞬間に近いタイミングで両方の予約を確定する(並行リクエストの送信。Sail 環境では並行 HTTP クライアントや並行テストで再現可能)
3. → 両方のリクエストがレスポンス上「面談を予約しました」と完了表示される
4. 面談履歴を開くと、本来残数 1 件で 1 件しか取れないはずなのに 2 件とも予約済みになっている
5. 残数を確認すると、初期付与 1 + 消費 -2 = **残数 -1** となっており、マイナス値が露出している

## 期待する結果

並行リクエストでも、残数 1 件の受講生が同時に 2 件の面談予約を成立させることはできない。**1 件目だけが成立し、2 件目は 409(残数不足)で拒否される**。残数は最終的に 0 となり、マイナスにはならない。

## 実際の結果

残数チェックと面談回数消費の取引記録 INSERT の間に他リクエストが割り込むため、両方のリクエストが「残数 1 件」を読み取った直後にそれぞれ消費の取引記録を INSERT してしまう。結果として 2 件とも予約が成立し、残数がマイナスになる(TOCTOU バグ)。

## 受け入れ条件

- [ ] 残数 1 件の受講生が並行 2 リクエストで予約を試みた場合、1 件のみ予約成立し、もう 1 件は 409(残数不足エラー)で拒否される
- [ ] 並行 2 リクエストが成立した側 / 拒否された側のどちらでも、消費の取引記録は最大 1 件しか作られず、最終的な残数が 0 を下回らない
- [ ] 単発の正常な予約 / 残数 0 での予約拒否 / 既存のキャンセル → 返却 などの単発系挙動は従来どおり維持されている
- [ ] 本チケットの機能に対するテスト (Unit / Feature 等) が実装されている

## 実装方針(参考)

### 原因

- **主要ファイル**: `app/UseCases/MeetingQuota/ConsumeQuotaAction.php` の `__invoke()`(面談回数消費の取引記録 INSERT)。面談予約エンドポイント `POST /enrollments/{enrollment}/meetings` を並行に多重に叩いた状況で発生する
- **関連ファイル**:
  - `app/UseCases/Meeting/StoreAction.php` — 面談予約の総合 Action。`DB::transaction` 内で「残数チェック → コーチ選出 → 面談(`Meeting`)作成 → `ConsumeQuotaAction` 呼出」の順に動く
  - `app/Services/MeetingQuotaService.php::remaining(User $user): int` — 残数を 1 クエリで集計するステートレス Service(`User.max_meetings + SUM(MeetingQuotaTransaction.amount WHERE type IN [consumed, refunded, purchased, admin_grant])`、`granted_initial` は `max_meetings` と二重計上を避けるため集計から除外)
  - `app/Models/MeetingQuotaTransaction.php` — 面談回数の付与・消費・返却・購入の取引記録(INSERT only、`MeetingQuotaTransactionType::Consumed` / `amount = -1` / `related_meeting_id` を持つ)
  - `app/Exceptions/MeetingQuota/InsufficientMeetingQuotaException.php`(409)— 残数不足時に throw
- **仕込み内容**: `ConsumeQuotaAction::__invoke` の `DB::transaction` 冒頭にある「同一受講生の同時消費を直列化するための排他ロック 1 行」(`User::query()->whereKey($user->id)->lockForUpdate()->first();`)を削除する。これにより、同一受講生に対する残数集計 SELECT(`remaining()`)と消費 INSERT(`MeetingQuotaTransaction::create`)の間に並行リクエストが割り込み可能になり、両方が「残数 1」を読んでから INSERT する TOCTOU が発生する
- **修正範囲**: 「残数チェック → 消費取引記録 INSERT」のクリティカルセクションが並行リクエストで割り込まれないよう、**同一受講生(User 行)への排他ロック**(`lockForUpdate`)を `DB::transaction` 冒頭で取得する形に戻す(Action 内 1 行)。`MeetingController::store` → `Meeting\StoreAction` → `ConsumeQuotaAction` と辿り、「残数集計の SELECT と取引記録 INSERT が同一トランザクションに入っていても、ロックなしでは並行リクエスト間で TOCTOU が発生する」を発見する流れ。Advance 範囲(Action 内)のため Basic 例外注記は不要

## 補足

### 想定 Q&A

| 質問 | 回答 |
|---|---|
| この Bug は通常のブラウザ操作で再現できますか? | 厳密な再現には「同一受講生で並行 2 リクエスト」を狙う必要があるため、通常のクリック操作では再現困難。並行 HTTP クライアント(`curl` 並走 / 並行テスト / 簡易スクリプト)で再現する想定。動作確認動画も並行リクエスト再現を撮影する |
| 残数がマイナスになることは仕様上ありえますか? | ありえません。残数は `User.max_meetings + SUM(取引記録.amount)` で集計され、面談 1 件成立につき消費(-1)が 1 件追加される設計。マイナスは並行性バグ以外では発生し得ない |
| 排他ロックは取引記録テーブル側 / 受講生(User)行 どちらに掛けるべき? | **受講生(User)行**。残数集計は「特定受講生の取引記録合計」なので、同一受講生に対する後続の消費要求を待たせるのが目的。取引記録テーブル(`meeting_quota_transactions`)は INSERT only で並行 INSERT 自体は問題なく、行レベルロックの対象に適さない |
| 楽観ロック(version カラム + リトライ)で実装してはダメ? | 受け入れ条件を満たせばどちらでも可。ただし面談予約はリクエスト頻度が低く、楽観ロックのリトライ実装は複雑さに見合わない。本チケットでは悲観ロックを採用 |
| ロックを掛けると他の受講生の予約まで止まりませんか? | 止まりません。`lockForUpdate()` は対象の `User` 行 1 行だけをロックします。別受講生が同時刻に予約しても別の行なのでロック干渉しません |
| Sanctum 認証 API(Advance)経由でも同じバグが起きますか? | 起きます。`ConsumeQuotaAction` は HTTP 認証経路と独立した業務ロジック層で、Web セッション / Sanctum どちらの経路から呼ばれても並行リクエスト性質は同じです |
| キャンセル時の返却処理(`RefundQuotaAction`)にもロックは必要? | 設計判断の領域です。返却は「消費が必ず 1 件先行している」状況での INSERT なので、並行返却で残数がマイナスになる経路は通常存在しません。本 Bug の範囲ではキャンセル経路には触らないでください |
| 排他ロックはトランザクションなしでも効きますか? | 効きません。`lockForUpdate()` は `DB::transaction()` 内でのみ有効(トランザクション境界が解放トリガ)。`ConsumeQuotaAction` 自身が `DB::transaction()` でラップされているため、呼び出し元(`Meeting\StoreAction`)の Tx とネストしても安全に動きます |
| 他 Feature(MockExam の並行採点 / Notification の並行既読化)にも同種のバグはありますか? | 別 Feature の並行性は本チケットのスコープ外です。「集計値の SELECT → INSERT」構造が他箇所にもあれば同種リスクはあり得るため、ヒアリングで該当 Feature が示唆された場合のみ別チケットとして検討します |
