# B-A-01 面談予約の並行リクエストで面談回数が二重消費される

<!--
記述粒度規約: 実装粒度を記載できるのは `## 実装方針` 配下のみ。それ以外は業務語彙のみ。
-->

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

## 期待する動作

並行リクエストでも、残数 1 件の受講生が同時に 2 件の面談予約を成立させることはできない。**1 件目だけが成立し、2 件目は 409(残数不足)で拒否される**。残数は最終的に 0 となり、マイナスにはならない。

## 実際の動作

残数チェックと面談回数消費の取引記録 INSERT の間に他リクエストが割り込むため、両方のリクエストが「残数 1 件」を読み取った直後にそれぞれ消費の取引記録を INSERT してしまう。結果として 2 件とも予約が成立し、残数がマイナスになる(TOCTOU バグ)。

## 受け入れ条件

- [ ] 残数 1 件の受講生が並行 2 リクエストで予約を試みた場合、1 件のみ予約成立し、もう 1 件は 409(残数不足エラー)で拒否される
- [ ] 並行 2 リクエストが成立した側 / 拒否された側のどちらでも、消費の取引記録は最大 1 件しか作られず、最終的な残数が 0 を下回らない
- [ ] 単発の正常な予約 / 残数 0 での予約拒否 / 既存のキャンセル → 返却 などの単発系挙動は従来どおり維持されている

## 実装方針

> **参考設計の一例**。受け入れ条件を満たせれば実装手段は問わない。受講生は提供 PJ コード + ヒアリングで自分の調査・修正方針を組み立てる。

### 主要 URL

| メソッド | パス | 振る舞い |
|---|---|---|
| POST | `/meetings/enrollments/{enrollment}` | 面談予約(コーチ自動割当 + 面談回数消費 + コーチ宛通知。並行リクエストはこのエンドポイントを多重に叩いた状況で発生) |

### 原因箇所メモ

- 原因の主要ファイル: `app/UseCases/MeetingQuota/ConsumeQuotaAction.php`(面談回数消費の取引記録 INSERT)
- 関連:
  - `app/UseCases/Meeting/StoreAction.php`: 面談予約の総合 Action。`DB::transaction` 内で「残数チェック → コーチ選出 → `Meeting::create` → `ConsumeQuotaAction` 呼び出し」の順に動く
  - `app/Services/MeetingQuotaService::remaining(User $user): int`: 残数集計を 1 クエリで返すステートレス Service。`User.max_meetings + SUM(MeetingQuotaTransaction.amount WHERE type IN (consumed, refunded, purchased, admin_grant))` の合計
  - `app/Models/MeetingQuotaTransaction`: 面談回数の付与・消費・返却・購入の取引記録(INSERT only、SoftDelete 不採用)
  - `app/Exceptions/MeetingQuota/InsufficientMeetingQuotaException`(HTTP 409): 残数不足時に throw される
- 仕込み内容(Step 4 引き算): `ConsumeQuotaAction::__invoke` の冒頭にある「同一受講生の同時消費を直列化するため User 行に排他ロックを掛ける 1 行」(`User::query()->whereKey($user->id)->lockForUpdate()->first();`)を削除する。これにより `DB::transaction` 内で同一受講生に対する `remaining()` の集計 SELECT と `MeetingQuotaTransaction::create` の INSERT が並行リクエスト間で割り込み可能になり、両方のリクエストが「残数 1」を読んでから INSERT する TOCTOU が発生する
- 受講生が辿るべき修正範囲: 「残数チェック → 消費取引記録 INSERT」のクリティカルセクションが並行リクエストで割り込まれないよう、**同一受講生に対する排他ロック** をトランザクション冒頭で取得する形に戻す。`MeetingController::store` から `Meeting\StoreAction` を辿り、その中で呼ばれる `ConsumeQuotaAction` の `DB::transaction` 冒頭に排他ロックが必要であることを発見する流れ。「残数集計の SELECT と取引記録 INSERT が同一トランザクションに入っていても、ロックなしでは並行リクエスト間で TOCTOU が発生する」が学習ポイント

### 採用技術と判断理由

- **採用技術**: Eloquent の `lockForUpdate()`(`SELECT ... FOR UPDATE`、行レベル悲観ロック)を `DB::transaction()` 内で組み合わせる
- **設計判断**:
  1. **悲観ロックの対象は受講生(User)行**: 残数は `User.max_meetings + SUM(MeetingQuotaTransaction.amount)` で集計するため、同一受講生に対する並行消費を直列化したい。`User` 行を `lockForUpdate()` で掴むことで、同一受講生に対する後続の `ConsumeQuotaAction` はロック解放を待つ
  2. **取引記録テーブルへのロックは不採用**: `MeetingQuotaTransaction` は INSERT only テーブルで、並行 INSERT 自体は問題ない。問題は「残数集計 → 消費 INSERT」のクリティカルセクションで、ロック対象は「集計の起点となる受講生エンティティ」が自然
  3. **楽観ロック(version カラム + リトライ)は不採用**: Certify LMS の面談予約はリクエスト頻度が低く、`User.max_meetings` の集計対象も小規模(取引記録 0〜数十件)。リトライ実装の複雑さを抱えるより、悲観ロックでシンプルに直列化する方が運用負荷が低い
  4. **`DB::transaction` の有無**: 排他ロックは `DB::transaction()` 内でのみ有効(トランザクション境界が解放トリガ)。`ConsumeQuotaAction` 自身が `DB::transaction()` でラップされているため、呼び出し元(`Meeting\StoreAction`)の Tx と nested で動いても安全(Laravel のネスト Tx は SAVEPOINT に変換され外側 Tx 全体の commit/rollback と整合する)
  5. **UNIQUE 制約による別経路の防御は別 Bug 範囲**: `meetings` テーブルには `(coach_id, scheduled_at)` UNIQUE が既にあり、同一時刻 × 同一コーチの二重予約はこちらで防ぐ。本 Bug は「同一時刻 × **別コーチ**」または「別時刻 × 別コーチ」のような UNIQUE で防げない並行ケースを扱う

### テスト方針

- **並行性テスト**: PHP プロセス分離 + DB トランザクション直接操作で TOCTOU を再現する Feature テストを書く(`tests/Feature/UseCases/MeetingQuota/ConsumeQuotaActionTest` の追加ケース、または `tests/Feature/Concurrency/MeetingQuotaConcurrencyTest` のような専用ファイル)。最小再現は以下のシナリオ:
  1. `User::factory()->student()->create(['max_meetings' => 1])` + 2 件分の `Meeting` factory
  2. **トランザクション A** で `ConsumeQuotaAction` を実行(ロック取得 → 残数 1 確認 → INSERT 直前で commit せず待機)
  3. **トランザクション B** で 2 件目の `ConsumeQuotaAction` を実行(ロックなしならそのまま残数 1 を読んで INSERT、ロックありならロック解放待ち)
  4. A を commit、B を再開
  5. 修正後: B は `InsufficientMeetingQuotaException` で 409 / 修正前: B も成立して残数 -1
- **観測可能化**: 並行テストが組めない CI 環境では、ロック取得行の存在自体を `app/UseCases/MeetingQuota/ConsumeQuotaAction.php` のソース検査(`Storage::disk('local')->get(...)` or `file_get_contents`)で確認する **Architecture テスト** を補助として置く案もある(任意、振る舞いテストで成立しない場合のセーフティネット)
- **動作確認動画**: PR の動作確認セクションには「並行リクエストを模した再現スクリプト or 並行性テストの実行ログ」をスクリーンキャストで残す。`assertDatabaseCount('meeting_quota_transactions', ['type' => 'consumed'], 1)` のような assert が並行投入後にも維持されていることを示す
- **退行確認**: 単発予約成功 / 残数 0 での 409 拒否 / 既存キャンセル → 返却 → 残数復活 の単発系テスト(`ConsumeQuotaActionTest` の既存 3 ケース + `Meeting\StoreActionTest` / `Meeting\CancelActionTest`)が引き続き通ることを確認

## 補足

### 想定ヒアリング Q&A

| 質問 | 回答 |
|---|---|
| この Bug は通常のブラウザ操作で再現できますか? | 厳密な再現には「同一受講生で並行 2 リクエスト」を狙う必要があるため、通常のクリック操作では再現困難。並行 HTTP クライアント(`curl` 並走 / 並行テスト / 簡易スクリプト)で再現する想定。動作確認動画も並行リクエスト再現を撮影する |
| 残数がマイナスになることは仕様上ありえますか? | ありえません。残数は `User.max_meetings + SUM(取引記録.amount)` で集計され、面談 1 件成立につき consumed (-1) が 1 件追加される設計。0 件成立で残数が変動しない / 1 件成立で残数が 1 減る、のいずれかが正。マイナスは並行性バグ以外では発生し得ない |
| 排他ロックは取引記録テーブル側に掛けるべきですか?それとも受講生(User)行ですか? | **受講生(User)行**。残数集計は「特定受講生の取引記録合計」なので、同一受講生に対する後続の消費要求を待たせるのが目的。取引記録テーブル(`meeting_quota_transactions`)は INSERT only で並行 INSERT 自体は問題なく、行レベルロックの対象として適していない |
| 楽観ロック(version カラム + リトライ)で実装してはダメですか? | 振る舞いとして受け入れ条件を満たせばどちらでも可。ただし Certify LMS の面談予約はリクエスト頻度が低く、楽観ロックのリトライ実装は複雑さに見合わない。模範解答 PJ では悲観ロックを採用している |
| ロックを掛けると他の受講生の予約まで止まりませんか? | 止まりません。`lockForUpdate()` は **対象の `User` 行 1 行** だけをロックします。同時刻に別受講生 B が予約しても、B の `User` 行とは別の行なのでロック干渉しません |
| Sanctum 認証 API(Advance)経由でも同じバグが起きますか? | 起きます。ConsumeQuotaAction は HTTP 認証経路と独立した業務ロジック層で、Web セッション / Sanctum どちらの経路から呼ばれても並行リクエスト性質は同じです |
| キャンセル時の返却処理(`RefundQuotaAction`)にもロックは必要ですか? | 設計判断の領域です。返却は「消費が必ず 1 件先行している」状況での INSERT なので、並行返却で残数がマイナスになる経路は通常存在しません(消費レコードを起点にした冪等性を維持する方が筋)。本 Bug の範囲ではキャンセル経路には触らないでください |
| MockExam の並行採点や Notification の並行既読化にも同種のバグはありますか? | 別 Feature の並行性は本チケットのスコープ外です。ただし「集計値の SELECT → INSERT」の構造が他箇所にもあれば同種のリスクは存在し得るため、コーチへのヒアリングで該当 Feature が示唆された場合のみ別チケットとして検討します |
