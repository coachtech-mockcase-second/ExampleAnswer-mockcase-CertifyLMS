# 運用エクスポート API + GAS / Google Sheets セットアップ手順

Certify LMS の運用エクスポート API (`/api/v1/admin/...`) を Google Apps Script から叩いて Google Sheets に素データを書き込み、Sheet 関数 / ピボット / 条件付き書式で業務 KPI を組み立てるための雛形手順。

> 本書は受講生 / 採点者向け配布物。**API キー本体を Sheet / GAS のコード本文 / スクショに絶対に貼らないこと**。

---

## ① Laravel 側のセットアップ

1. `模範解答プロジェクト/.env` の `ANALYTICS_API_KEY` に 32 文字以上のランダム文字列をセットする (`openssl rand -hex 32` 推奨)。
2. `sail artisan config:clear` でキャッシュを破棄する。
3. キー疎通確認 (キー差し替え可、ローカル):
   ```bash
   curl -i -H "X-API-KEY: $ANALYTICS_API_KEY" "http://localhost:8000/api/v1/admin/users?per_page=5"
   ```
4. キー欠落で 401 / キー不一致で 401 / 未定義パスで 404 JSON が返ることをそれぞれ確認する。

---

## ② Google Sheets / Apps Script のセットアップ

1. Google ドライブで新規 Spreadsheet を作成 (例: 名前「Certify LMS 運用分析 ◯月」)。
2. メニュー: 「拡張機能 → Apps Script」を開く。
3. 開いた Apps Script エディタの左上のプロジェクト名を任意で変更。
4. デフォルトの `Code.gs` を全選択削除し、本ディレクトリの `gas-template.gs` の中身をすべて貼り付ける。
5. 左ペイン「設定 (歯車アイコン) → スクリプト プロパティ」を開き、以下 2 件を登録:
   | キー | 値 |
   |---|---|
   | `ANALYTICS_API_KEY` | Laravel `.env` で発行したキー (32 文字以上) |
   | `ANALYTICS_API_BASE_URL` | API のベース URL (`http://localhost:8000/api/v1/admin` など) |
6. エディタ上部の「関数」プルダウンから `importUsersToSheet` を選択し、▶ 実行。
7. 初回のみ OAuth 同意ダイアログが表示される (「詳細 → 安全ではないページに移動」→ 許可)。
8. Sheet 側に「ユーザー一覧」タブが自動生成され、`/api/v1/admin/users` の素データが書き込まれる。
9. 同様に `importEnrollmentsToSheet` / `importMockExamSessionsToSheet` を実行する。

---

## ③ Sheet 側で業務 KPI を組み立てる例

`gas-template.gs` は **素データ取得のみ** を提供し、業務集計は Sheet 内で実装する想定。以下は組立例 (採点者シェア時はここに自分の工夫を見せること):

- 「ロール内訳」: `=COUNTIF(ユーザー一覧!D:D, "student")` で受講生数を出す
- 「合格率」: `=COUNTIF(模試結果一覧!H:H, TRUE) / COUNTA(模試結果一覧!H:H)` (`pass` 列)
- 「資格別合格率」: ピボットテーブルで `mock_exam_id` ごとの `pass` 平均を出す
- 「進捗ヒートマップ」: 受講登録一覧の `progress_rate` 列に条件付き書式
- 「コーチ別担当一覧」: `certification_coach_assignments` は本 API には含まれないため、別途 LMS の admin 画面から CSV エクスポート機能 (BookShelf 既習) でダウンロードして VLOOKUP で結合する

---

## ④ 採点者へのシェア手順

1. 採点担当者の Google アカウント (例: `grader@coachtech.example`) に Sheet を「閲覧者」または「編集者」権限で共有する。
2. Apps Script プロジェクト側の権限は **共有不要** (Sheet の権限とは独立)。実行する人ごとに OAuth 同意が必要。
3. 採点者は Sheet を開いた状態で「拡張機能 → Apps Script」を開き、本 README ② の手順 5 の **Script Properties** に同じキー / URL をセットすれば再実行できる。
4. **シェア時の禁止事項**:
   - Sheet のどこかに API キーをセルとして貼らない (Script Properties のみに保存)
   - スクリーンショットに Apps Script の「スクリプトプロパティ」画面を写さない
   - PR / Slack 等のチャットに API キーを貼らない

---

## ⑤ PR 動作確認 4 点セット

PR を出す際は以下 4 点を必ず Description に含める:

1. **Sheet URL** (採点者の Google アカウントに共有済) — 「閲覧者」権限で URL 直リンク
2. **採点者シェアのスクショ** — Google Drive の「共有」ダイアログを開いて採点者の Google アカウントが追加されている状態を撮影 (API キーが画面内に映らないこと)
3. **Sheet 内容のスクショ** — 上記 ③ の組立例から自分で 1 つ以上の業務 KPI を完成させた状態を撮影
4. **GAS コード** — 自身のカスタマイズ箇所を含む `Code.gs` の全文を PR に添付 (API キーが含まれていないこと、`getApiKey_()` 経由のみで参照していること)

---

## ⑥ v3 改修反映の注意事項

- **`?assigned_coach_id` クエリは撤回されている** — 担当コーチ別フィルタは GAS / Sheet 側で別途取得した `certification_coach_assignments` と結合して実装する必要がある。一覧結合のサンプルは Sheet 関数 `VLOOKUP` / `INDEX-MATCH` / `QUERY` で容易。
- **`UserResource` に `plan_id` / `plan_started_at` / `plan_expires_at` / `max_meetings` が追加** — プラン期限切れ受講生の検出 / 残面談回数の集計に利用可。
- **`EnrollmentResource.status` は 3 値** (`learning` / `passed` / `failed`) — `paused` は撤回されたので Sheet 集計から除外して良い。
- **`MockExamSessionResource.category_breakdown`** — graded セッションのみ非空。問題テーブルは `MockExamQuestion` ベースで集計されている。

---

## ⑦ よくあるエラー

| 症状 | 原因 | 対処 |
|---|---|---|
| `API エラー (status=401)` | Script Properties の `ANALYTICS_API_KEY` 未設定 or 古いキー | Laravel `.env` の値と一致させる |
| `API エラー (status=503)` で `API_KEY_NOT_CONFIGURED` | Laravel `.env` 側 `ANALYTICS_API_KEY` が空 | キーを設定後 `sail artisan config:clear` |
| `API エラー (status=429)` で `RATE_LIMIT_EXCEEDED` | 1 分 60 リクエスト超過 | `fetchAllPages_` の per_page を 200 まで拡張、または `Utilities.sleep(1000)` を挟む |
| 「権限が必要です」OAuth ダイアログから先に進めない | スクリプトの実行権限が無い | 「詳細 → 安全でないページに移動」→ 許可 (Google 個人アカウントの安全機能) |
| `ANALYTICS_API_BASE_URL が Script Properties に登録されていません。` | URL 設定漏れ | スクリプトプロパティに登録する (例: `http://localhost:8000/api/v1/admin`) |
