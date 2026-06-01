# 検証ゲート

引き算が「消しすぎ / 消し足りない / 仕込み誤り」でないかを客観判定する。クラスタ毎 + 最終で通す。**「テストが赤になった」だけで満足せず、「チケットが書いた症状が実際に再現するか」まで見る**のが要点。

## 0. 提供 PJ を実行可能にする

症状再現・テスト実行に提供 PJ のランタイムが要る。**references/runnable-provided-pj.md** の手順で起動可能にしておく(Phase 0 で実施済みのはず)。

## 1. 静的検査

```bash
composer dump-autoload          # オートロード壊れ検出
sail artisan about              # boot 正常
sail artisan route:list         # 削除 Controller を指す死にルート検出(Story gut のやりすぎ検出)
sail npm run build              # アセットビルド。FE(Blade/JS)無改修クラスタは既存 public/build で十分=スキップ可(node_modules 未流用だと vite: not found になるが FE 不変なら無害)。FE を触ったクラスタは node_modules 流用 or npm ci 後にビルド
```

死にルート・autoload エラーが出たら「消しすぎ」or「coming-soon プレースホルダ未温存」。

## 2. DB 整合

```bash
sail artisan migrate:fresh --seed
```

緑であること。赤になる典型 = 削除したテーブルへの **Seeder / FK / Factory 参照切れ**(Story gut でデータ層を消したのに参照が残っている)。

## 3. 同梱テストの赤/緑(バケット①の核)

各種別でテストの所在が違う:

- **Bug / perf-Task**: 同梱テストを**残す**。仕込み後は **赤**(修正前=失敗)になるべき。
- **Story / refactor-Task**: テストは**削除済み**であるべき(② バケット = 受講生が書く)。

確認手順:

1. 仕込んだ Bug / perf-Task の **代表テストを `path::method` で名指し実行** → **赤を確認**:
   ```bash
   sail artisan test --filter test_xxx
   ```
2. **想定外の赤が無いか**を確認。局所バグなら数件、広域(ミドルウェア等)なら多数赤になるが、**全部が「その仕込み起因」と説明できる**こと。説明できない赤 = 別の仕込みミス。
   - **前クラスタのブランチから分岐した場合、前クラスタの注入赤がベースライン**(例: 前クラスタ 4 Bug で 22 件赤が継承される)。当該クラスタの**差分の赤だけ**を評価し、継承赤は「想定内」と仕分ける(初見で「22 件も赤? 消しすぎ?」と誤認しない)。前クラスタ分岐時は「前クラスタ tip での全テスト結果」を先に控えておくと差分が取りやすい。
   - 広域仕込みは全テスト実行で blast radius を見る(`sail artisan test` 全体 → 赤ファイル一覧を種別に紐付け)。
3. **赤にならない**なら危険信号 → Phase 3 reconcile(silent gap / 別層マスク / 仕込み箇所違い)。

### 提供 PJ で直接テストするか、模範解答に一時適用するか

- **提供 PJ に vendor を用意できている**なら、提供 PJ で直接 `sail artisan test` が最も確実(実成果物そのものを検証)。
- vendor 準備前 / 軽く確認したいだけなら、**模範解答に仕込み済みファイルを一時 cp → テスト → `git checkout` で復元** でも可(同一 git リポ内なら安全に巻き戻せる)。Bug クラスタ向きの軽量法。ただし**復元を必ず確認**(`git status` clean)。

## 4. Playwright で症状再現(本ゲートの心臓)

チケットの **「再現手順」「実際の結果」どおりの症状** が、提供 PJ を実ブラウザ操作して再現するかを確認する。`mcp__playwright__*` を使う。

手順の型:
1. `browser_navigate` で `/login` → 該当ロールのアカウントでログイン(アカウントはプロジェクトの Seeder/README から。例: 卒業ユーザー・招待中ユーザー等の状態網羅 demo は email を DB から引く)
2. チケットの再現手順どおりに操作
3. **症状を目視**(`browser_snapshot` / `browser_take_screenshot`):
   - 例(認可バグ): 卒業ユーザーで `/certifications` → 403 でなく**表示される**
   - 例(検証バグ): オンボーディングで確認用パスワード不一致 → 422 でなく**登録成功**
   - 例(状態遷移バグ): オンボ後ログアウト→再ログイン → **「認証情報が正しくありません」で入れない**
4. スクショは Playwright MCP の許可ルート内(リポ直下の gitignore 済ディレクトリ等。`/tmp` は許可外で拒否されることがある)に保存し、成果物に混入させない

**症状が出ない/別症状なら Phase 3 reconcile**:
- 出ない → 別層マスク(別の Policy/FormRequest が同じ判定をしている)の可能性。マスクしている層を特定。
- 別症状(例: 500 が出る) → チケットの想定フローと実装が違う。

> 症状の観察ポイント: バグによっては別の正しいガードが downstream にあり、特定画面では症状がマスクされる(例: 面談「予約」画面は受講中以外を別途 `abort_unless` で弾くので、active-learning バグの症状が出ない)。**マスクされない素直なルートを選んで観察**する(例: 一覧/カタログ系)。

## 5. 全ロール巡回 500 ゼロ

主要ロール(admin / coach / student 等)で主要ルートを巡回し、引き算が boot/描画を壊して 500 を出していないことを確認。バグは「振る舞いが変」であって「クラッシュ」ではないのが基本(ガード削除で別経路がクラッシュしていないか確認)。e2e スイートがあればそれを採点オラクル兼用で回す。

## メール/通知が絡む検証の注意

通知・メールが **非同期化(ShouldQueue + `QUEUE_CONNECTION=database`)** されたままのクラスタでは、worker を起動しないと Mailpit に届かない(招待メール等の症状再現が「メール来ない」で詰まる)。検証用途では `.env` を `QUEUE_CONNECTION=sync` にする(gitignore 環境のみ)か `sail artisan queue:work --stop-when-empty` で滞留を流す。詳細は runnable-provided-pj.md。

## ゲート通過の判定

- 静的 OK / `migrate:fresh --seed` 緑 / `npm run build` 緑 / 巡回 500 ゼロ
- 仕込んだ Bug・perf-Task の代表テストが**赤**(想定外の赤が無い)、Story・refactor のテストは削除済み
- チケットの**症状が Playwright で再現**(観察可能なもの)
- 食い違いは Phase 3 で reconcile 済み
