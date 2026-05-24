# B-B-01 コーチが担当資格の教材管理にアクセスすると 403

## メタ情報

| 項目 | 値 |
|---|---|
| チケット ID | `B-B-01` |
| Feature 連番 | `content-management-01` |
| Feature | content-management |
| 種別 | Bug |
| サブカテゴリ | 認可・認証(Policy 解禁) |
| 難易度 | Basic |
| 工数 (h) | 4 |
| 依存チケット | (なし) |

## 概要

コーチ(coach)が自分の担当資格配下の教材管理画面(Part / Chapter / Section / 演習問題 / 教材内画像 / 出題分野マスタ)にアクセスすると 403 が返り、本来できるはずの教材閲覧・編集・公開・並び替えが一切実行できない。同じ画面でも管理者(admin)は問題なくアクセスできるため、コーチ専用の運用導線が完全に塞がれている状態。

## 再現手順

**前提**: コーチ A としてログイン済み。コーチ A は資格 X の担当割当を持つ(その他資格は非担当)。

1. コーチ A で資格 X の教材管理画面(Part 一覧)を開く
2. → 403 Forbidden が返り、画面が表示されない
3. 同様に Part 詳細 / Chapter 詳細 / Section 編集 / 演習問題一覧 / 出題分野マスタ一覧 のいずれを開いても 403
4. (参考)同じ画面を管理者で開くと 200 で正常表示される

## 期待する動作

コーチ A は担当資格 X 配下の教材管理画面に 200 でアクセスでき、Part / Chapter / Section / 演習問題 / 教材内画像 / 出題分野マスタの **作成・編集・削除・公開・非公開・並び替え** を実行できる。一方で、コーチ A が **担当していない資格** Y 配下の教材管理にアクセスすると引き続き 403 が返り、ロール横断のアクセスは遮断される。

## 実際の動作

コーチが教材管理関連の全画面・全操作(一覧表示 / 作成 / 編集 / 削除 / 公開・非公開 / 並び替え / 画像アップロード)に対して、担当資格・担当外資格を問わず 403 Forbidden が返る。Part 一覧の入口だけでなく、配下の Chapter / Section / 演習問題 / 教材内画像アップロード / 出題分野マスタの全操作が封鎖されている。管理者は同じ画面・操作で正常動作するため、コーチに対する認可判定だけが過剰拒否となっている。

## 受け入れ条件

- [ ] **担当資格 - 一覧表示**: 担当資格を持つコーチがログイン後、当該資格の教材管理画面(Part 一覧) を開くと 200 が返り、当該資格配下の Part 一覧が表示される(配下の Chapter 詳細 / Section 編集 / 演習問題一覧 / 出題分野マスタ一覧画面も同様に 200 で表示される)
- [ ] **担当資格 - CRUD 実行**: 担当資格を持つコーチが、Part / Chapter / Section / 演習問題 / 出題分野マスタの新規作成・編集・削除・公開・非公開・並び替え、および教材内画像のアップロード・削除を実行すると成功し、対象画面へリダイレクトされる(認可で弾かれず操作が完了し、データに反映される)
- [ ] **担当外資格 - 認可維持**: 担当資格を持たないコーチが他資格の教材管理画面(一覧 / 詳細 / 作成・編集・削除 / 状態遷移 / 並び替え / 画像アップロード / 出題分野マスタ)にアクセスすると 403 が返る(本修正で担当外資格まで開放されていないこと)

## 実装方針

> **参考設計の一例**。受け入れ条件を満たせれば実装手段は問わない。受講生は提供 PJ コード + ヒアリングで自分の調査・修正方針を組み立てる。

### 主要 URL

> コーチ・管理者が利用する教材管理(content-management Feature)関連エンドポイント。本 Bug 発生時はこれらすべてでコーチが 403 を観測する。

| メソッド | パス | 振る舞い |
|---|---|---|
| GET | `/admin/certifications/{certification}/parts` | Part 一覧 |
| GET / POST | `/admin/certifications/{certification}/parts/create` / `/admin/certifications/{certification}/parts` | Part 新規作成 |
| GET / PATCH / DELETE | `/admin/parts/{part}` ほか | Part 詳細 / 編集 / 削除 |
| POST | `/admin/parts/{part}/publish` / `unpublish` | Part 公開 / 非公開 |
| PATCH | `/admin/certifications/{certification}/parts/reorder` | Part 並び替え |
| GET / POST / PATCH / DELETE | `/admin/parts/{part}/chapters` 配下、`/admin/chapters/{chapter}` ほか | Chapter CRUD + publish / unpublish / reorder |
| GET / POST / PATCH / DELETE | `/admin/chapters/{chapter}/sections` 配下、`/admin/sections/{section}` ほか | Section CRUD + publish / unpublish / reorder / preview |
| GET / POST / PATCH / DELETE | `/admin/sections/{section}/questions` 配下、`/admin/section-questions/{sectionQuestion}` ほか | 演習問題 CRUD + publish / unpublish |
| POST / DELETE | `/admin/sections/{section}/images` / `/admin/section-images/{image}` | 教材内画像 アップロード / 削除 |
| GET / POST / PATCH / DELETE | `/admin/certifications/{certification}/question-categories` 配下、`/admin/question-categories/{category}` ほか | 出題分野マスタ CRUD |

### 原因箇所メモ(コーチ用、受講生に直接教えない)

> **コーチが Bug の原因コードを事前把握しておく材料**。受講生のヒアリング応答時は **ファイルパスや修正コードを直接教えない**(教育上 NG)。「教材管理画面で 403 が出るとき、Laravel ではどのレイヤーがアクセスを判定している?」「同じ画面が admin だけ通る挙動だと、ロール判定なのか担当判定なのかどちらが疑わしい?」のような **問い返し / 方向性ヒント** で受講生自身のコードリーディングを促す。

- 原因の主要ファイル: `app/Policies/PartPolicy.php`
- 関連ファイル: `app/Policies/ChapterPolicy.php` / `app/Policies/SectionPolicy.php` / `app/Policies/SectionQuestionPolicy.php` / `app/Policies/SectionImagePolicy.php` / `app/Policies/QuestionCategoryPolicy.php`
- 仕込み内容(Step 4 引き算): 上記 6 Policy 内で coach ロールの判定箇所(`canManage()` / `viewAny()` / `view()` 内の `UserRole::Coach => ...` 分岐、および担当判定ヘルパー `assignedCoach()`)を「常に `false` を返す」状態に書き換える。`assignedCoach()` ヘルパーメソッドを持つ Policy(Part / Chapter / Section / SectionQuestion)はヘルパー戻り値が `false` 固定、ヘルパーを持たない Policy(SectionImage / QuestionCategory)は `match` 文の `UserRole::Coach` 分岐を `false` に直接置換するパターン。**Middleware(`auth + role:admin,coach`)はそのまま** で、ロール判定はパスする(role:coach がブロックされたら admin が混入しなくなり別問題に見えてしまうため、Policy 層だけで再現する)。
- 受講生が辿るべき修正範囲: 6 Policy の coach 判定を「`certification_coach_assignments` 経由で担当資格判定し、担当ありなら true / 担当なしなら false」に戻す。担当判定の SQL は `$certification->coaches()->where('users.id', $coach->id)->exists()` で実装可能(`Certification::coaches()` リレーションが既存)。

## 補足

### 想定ヒアリング Q&A

> 受講生の **仮説提案型ヒアリング**(「○○と振る舞うべきと理解しましたが正しいですか?」)に対して OK / NG / 別案 を返すスタイル。**要件・仕様レベル(正しい振る舞いの確認)のみ** 記述する(設計判断 / 実装詳細は書かない)。

| 質問 | 回答 |
|---|---|
| 修正後の正しい振る舞いは? | コーチ × 担当資格 → 教材管理画面の全 CRUD 可、コーチ × 担当外資格 → 403、管理者 → 全資格 CRUD 可、受講生 → 教材管理画面そのものへのアクセス導線なし(受講生の閲覧導線は learning Feature 経由の教材閲覧画面で、本 Bug の影響範囲外) |
| 管理者の挙動は壊れていないか? | 壊れていない。管理者は本来の振る舞い(全資格 CRUD 可)が維持されている。動作確認時は管理者で同じ画面を開いて 200 を確認できる |
| 受講生の閲覧導線は本 Bug の影響を受けるか? | 受けない。受講生は教材管理画面ではなく learning Feature の教材閲覧画面を経由してアクセスし、本 Bug の修正は受講生の閲覧経路に影響しない |
| 学習有効性ガード(受講停止状態のブロック)の影響は? | 影響なし。これは受講生の利用状態(受講中 / 修了 / 退会 等)を制御する仕組みで、管理者 / コーチの経路には適用されていない |
| 公開済 Part / Chapter の削除ガード(公開中は削除不可、409)が動かなくなることはあるか? | ない。状態ベースのガード(公開中状態では削除を拒否する判定)は認可とは別の仕組みで実装されており、本 Bug の認可修正の範囲とは独立 |
