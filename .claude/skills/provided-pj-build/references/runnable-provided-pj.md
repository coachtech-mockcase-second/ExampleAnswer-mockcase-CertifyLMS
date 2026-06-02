# 提供 PJ を実行可能にする

症状再現(Playwright)とテスト実行には提供 PJ のランタイムが要る。だが提供 PJ は **vendor / node_modules / .env を含めずに配布**される(git 追跡外)ので、そのままでは `vendor/bin/sail` すら無い。模範解答の成果物を流用して最短で起動可能にする。

## なぜ流用できるか

提供 PJ は模範解答のコピー(から引き算)なので **composer.lock / package-lock.json が同一**。依存も FE ビルドも同一。だから模範解答の `vendor` / `public/build` をコピーすれば再インストール不要で起動できる。

## 手順(模範解答とポートを分けて共存)

模範解答が同じポートで動いている場合は止めるか、提供 PJ のポート/プロジェクト名をずらして共存させる。

```bash
cd 提供プロジェクト          # 実装ディレクトリ名は CLAUDE.md 参照
MA=../模範解答プロジェクト

# 1. ランタイム資産を流用(同一 lock)
[ -f .env ]          || cp $MA/.env .env
[ -d vendor ]        || cp -r $MA/vendor vendor
[ -d public/build ]  || cp -r $MA/public/build public/build   # FE は同一なので流用可
# FE を改修するクラスタは node_modules を用意してから build。⚠️ `cp -r $MA/node_modules` は vite の `.bin/*` symlink を壊し `ERR_MODULE_NOT_FOUND`(vite 起動不可)になるので、**コンテナ内 `sail npm ci`**(確実)or `cp -a`(symlink 保持)を使う。FE 無改修なら public/build 流用で build 不要(verification-gate 参照)

# 2. storage / bootstrap-cache の骨格を用意(コピー除外されている場合)
mkdir -p storage/framework/{cache/data,sessions,testing,views} storage/app/public storage/logs bootstrap/cache
rm -f bootstrap/cache/{config,routes-v7,packages,services}.php 2>/dev/null   # コピー由来の stale を除去
```

`.env` の **`COMPOSE_PROJECT_NAME`** を模範解答と変える(別ボリューム/別コンテナにして模範解答 DB を汚さない)。ポートが衝突するなら `APP_PORT` 等もずらす。模範解答を `sail down` してポートを空ければ、提供 PJ をデフォルトポートで起動するのが一番シンプル。

```bash
# COMPOSE_PROJECT_NAME=certify-lms-provided   (.env を編集)
sail up -d --wait          # --wait で mysql healthy まで待つ
sail artisan optimize:clear
sail artisan migrate:fresh --seed
sail artisan storage:link
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:<APP_PORT>/login   # 200 を確認
```

> これで提供 PJ が単体で boot + seed + テスト実行できる = 成果物の起動性検証も兼ねる(受講生が Use this template 後に同じ手順で動く保証)。

## ログイン用アカウント

Seeder が投入する固定アカウント(プロジェクトの README / UserSeeder 参照。例: `admin@/coach@/student@<domain>`、パスワードは共通)。**状態網羅 demo ユーザー**(招待中 / 卒業 / 退会等)は Factory 生成で email がランダムなことがある → DB から引く:

```bash
sail artisan tinker --execute="echo App\Models\User::where('status','graduated')->value('email');"
```

Factory のパスワードは大抵共通値(`Hash::make('password')` 等。UserFactory を確認)。

## メール/通知が届かない時(非同期化クラスタ)

通知・メールが ShouldQueue + `QUEUE_CONNECTION=database` のままだと、worker 無しでは jobs テーブルに滞留して Mailpit に届かない。症状再現で「招待メールが来ない」となったら:

- 滞留を流す: `sail artisan queue:work database --stop-when-empty`(既存ジョブを処理して Mailpit へ配送)
- 検証用に同期化: `.env` を `QUEUE_CONNECTION=sync` → `sail artisan config:clear`(以降の送信が即時)。**gitignore 配下の検証用 .env のみ**。非同期化を巻き戻すクラスタを実装すれば本来 sync に戻る。

## 後片付け

- 検証用に作った `.env` / vendor / public/build / storage は gitignore 配下 → 成果物・PR に混入しない(`git status` で確認)。
- スクショ等の検証アーティファクトは Playwright MCP 許可ルート(リポ直下 gitignore 配下等。`/tmp` は許可外のことあり)に保存し、コミット対象に含めない。
- 模範解答に一時 cp して検証した場合は `git checkout -- 模範解答プロジェクト/` で復元 + `git status` clean 確認。
