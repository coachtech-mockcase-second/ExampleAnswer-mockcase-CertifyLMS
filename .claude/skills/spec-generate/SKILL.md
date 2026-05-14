---
name: spec-generate
description: 1 Feature の docs/specs/{name}/{requirements,design,tasks}.md の3点セットを生成する。$ARGUMENTS に Feature 名（例 mock-exam）を渡す。自己完結・直列。並列で複数 Feature を生成したい場合は worktree-spawn Skill で別 Claude セッションを立ち上げて各セッションでこの Skill を使う
---

# spec-generate

1 Feature の **完成形 SDD（spec 3点セット）** を生成するスキル。直列実行。

**2 フェーズ構成**:

- **Phase 0**: チャット上でユーザー目線の要件壁打ち（4 項目: 概要 / 背景・目的 / 要件 / 受け入れ条件）。ファイル化なし。ユーザーが明示 OK するまで Phase 1 に進まない。
- **Phase 1**: 開発・技術目線の 3 ファイル生成（requirements.md / design.md / tasks.md）+ セルフレビュー 5 ラウンド。Phase 0 の合意内容を入力とする。

> Why 2 フェーズ化: 技術 spec 3 点セットは詳細度が高くユーザーが全体を読み切るのが困難。技術詳細に入る前にプロダクト目線で握ることで Phase 1 のアウトプットがユーザー意図と乖離するリスクを減らす。

> 🛑 **Phase 0 → Phase 1 の移行は autonomous モードでも止まる（最重要）**: 親プロンプトの `effort=max` / システムリマインダの「clarifying questions なしで進めて」「judgment call で続けて」/ ユーザの「お任せします」発言があっても、**Phase 0 の明示 OK 待ちは本 Skill のハードバリアとして遵守する**。
>
> 理由: Phase 0 は **clarifying question ではなく**「何を作るか」をユーザと握る上流チェックポイント。ここを飛ばして Phase 1 に走ると 1000 行超のアウトプットがユーザ意図と乖離した状態で固まり、`/feature-implement` まで通って初めて齟齬に気付く → spec 全面再生成という最大級の手戻りになる。autonomous 指示が想定する「些細な選択肢確認の省略」とは性質が違う（autonomous は調査・読み取り中の小さな分岐を勝手に決めて良いという意味であって、合意フェーズそのものを飛ばして良いという意味ではない）。
>
> 過去事例: 本 Skill 実行中に autonomous 指示があったセッションで「Phase 0 を提示して即 Phase 1」を実行した結果、Phase 1 で 1500 行生成した後にユーザから設計判断（`Answer` / `QuestionAttempt` 分離方針等）の確認が来て、初稿段階で握れていればもっと素直に書けた、というケースがあった。**autonomous = Phase 0 スキップ可、ではない**。

## 入力

`$ARGUMENTS`: Feature 名（kebab-case）。例: `mock-exam`, `enrollment`, `auth`
無ければユーザーに確認する。

## 記述言語

3 ドキュメントは **日本語ベース** で記述する（模範解答仕様としてコーチ・受講生が読む可能性を想定）。

EARS は **構造キーワード（`shall` / `when` / `if` / `while`）だけを英語のまま残し、述語は日本語**で書く「ハイブリッド形式」を採用する。iField LMS の Kiro 流に倣う。

**書き方の型**:

- `The {主語} shall {日本語述語}。`
- `When {日本語条件}, the {主語} shall {日本語述語}。`
- `If {日本語条件}, then the {主語} shall {日本語述語}。`
- `While {日本語条件}, the {主語} shall {日本語述語}。`

**例**:

- The system shall ULID 主キー / `email` UNIQUE を備えた `users` テーブルを提供する。
- When ユーザーが招待 URL にアクセスした際, the system shall URL 署名・有効期限・`Invitation.status` を検証する。
- If 招待トークンが期限切れの場合, then the system shall HTTP 410 Gone で拒否する。

主語は `the system` / `the {Feature} Module` / `the {Entity} Controller` 等、文脈に応じて使い分ける。**1 spec 内では主語スタイルを統一**する（混在を避ける）。

> Why ハイブリッド形式: 構造キーワードを英語で残すとトレーサビリティ・網羅性チェック・when/if 分布分析がパターン認識で機能する。一方、述語まで英語にすると受講生・コーチへの説明コストが上がる。iField LMS / Kiro / 大手 SI のテンプレも同じ折衷。

## 必須読み込み

実行前に Read:

1. `CLAUDE.md` — 「実装プラン」セクション（Feature 一覧・依存関係を確認）
2. `docs/steering/product.md` — 該当 Feature の説明 + 関連 UXフロー + stateDiagram
3. `docs/steering/tech.md` — Clean Architecture / 命名規則 / Action命名 / PR規約
4. `docs/steering/structure.md` — ディレクトリ / 命名規則 / specs/ 作成ルール
5. `.claude/rules/` 配下のルール（paths frontmatter で自動ロード）
6. 依存先 Feature の `docs/specs/{dep}/design.md`（あれば）

## 参考にする既存実装

設計の **主軸は Laravel コミュニティ標準 + `.claude/rules/` 配下の規約**。`/Users/yotaro/lms/backend/`（COACHTECH LMS）は **判断に迷う Feature 固有パターン（招待フロー / 状態遷移ログ / 集計 Service 等）の確認時のみ補助参照**。Certify 独自方針（ULID / SoftDeletes / 3 ロール / 教育PJスコープ）と矛盾する箇所、Laravel 標準とズレている独自命名・過剰 polymorphic 等は **採用しない**。参考にした場合は完了報告に「観察パターン / Laravel 標準との差分 / Certify への適用判断」を簡潔に記す。

**iField LMS の優先参照**: `/Users/yotaro/ifield-lms/.kiro/specs/` から **生成中 Feature と同名 / 類似の spec を最優先で読む**。SDD の書きぶり（粒度・密度・コンポーネント分割の流儀）を学ぶ用途、実装は流用しない（スタックが違う）。

| 生成中の Feature | iField の優先参照 |
|---|---|
| `auth` / `user-management` / `dashboard` / `quiz-answering` / `settings-profile` | 同名ディレクトリ |
| `mock-exam` | `mock-projects/` |
| `content-management` | `contents-and-quizzes/` / `content-sync-workflow/` / `content-version-management/` |
| 上記以外 | `contents-and-quizzes/`（大型 Feature の代表サンプル）|

COACHTECH 探索コマンド: `ls /Users/yotaro/lms/backend/app/Models/` / `grep -rli "{keyword}" /Users/yotaro/lms/backend/app/` / `find /Users/yotaro/lms/backend/database/migrations -name "*{keyword}*"`

## Phase 0: ユーザー目線の要件壁打ち（チャット上、ファイル化なし）

### 目的

`docs/steering/product.md` は **プロダクト全体の俯瞰** を集約しているため、各 Feature の記述は **意図的に抽象的** に留まっている。Phase 0 はその抽象記述をユーザー ↔ Claude の対話で **詳細度を上げ、認識を揃える** ことを目的とした上流フェーズ。

requirements.md / design.md / tasks.md は **開発・技術目線** で詳細度が高く、ユーザーが全体を読み切るのが困難。技術詳細に入る前にプロダクト目線で握っておけば、Phase 1 の technical spec はその合意の **実装翻訳** に専念できる。

Phase 0 完了時点でユーザー ↔ Claude が「何を作るか / なぜ作るか / 完成判定は何か」を共有する。

> **product.md 側の更新も Phase 0 のスコープに含む**: 壁打ちの結果、product.md の該当 Feature 記述（説明文 / 主モデル / 状態遷移 / 集計責務マトリクス / スコープ外）に修正・追加が必要と分かった場合、spec を生成する前に **まず `docs/steering/product.md` を更新する**。product.md は複数 Feature を横断する一貫性の源なので、1 spec 内で独断せず steering 側を直してから spec に進む（「## ユーザー確認の方針」の steering 矛盾ガードと整合）。

### 出力する 4 項目（ユーザー目線・必要最低限）

チャット上に Markdown で 4 セクションを提示する。**ファイル化しない** — チャット履歴がそのまま壁打ちの記録になる。

#### 1. 概要

1-2 行で「何を作るか」。技術用語は使わず、Feature の責任範囲を端的に。

例（certification-management）:
> admin が資格マスタを管理し、受講生が資格カタログを閲覧する機能。修了時の修了証 PDF 発行も含む。

#### 2. 背景・目的

- なぜこの Feature が必要か
- どんな課題を解決するか
- 誰がどう嬉しくなるか

product.md の該当部分から抽出 + Claude による掘り下げ。3-5 行程度。

#### 3. 要件

ユーザー目線で「何ができる必要があるか」を **ロール別** に箇条書き。**技術用語禁止**（Action / Service / Eloquent / Migration / FormRequest 等は使わない）。

例:

```
**admin ができること**
- 資格の追加・編集・削除
- 資格を「下書き / 公開 / 公開停止」で切り替え
- 資格にカテゴリを設定
- 資格に担当コーチを割り当て

**受講生ができること**
- 公開済資格のカタログ閲覧・検索
- 受講中資格と未受講資格をタブで切替
```

#### 4. 受け入れ条件

「この Feature が完成したと言える条件」を **検証可能** な形で列挙。プロダクト目線、技術用語抜き。5-8 個程度。

例:

```
- ✓ 新規登録した資格は下書き状態で、公開操作するまで受講生に見えない
- ✓ 公開停止した資格は受講生のカタログから消える
- ✓ 担当コーチを割り当てたコーチだけが、その資格の教材・問題を編集できる
- ✓ 修了承認されると修了証 PDF が自動生成され、当事者と admin だけがダウンロードできる
```

### Phase 0 の進め方

1. **前提読み込み**（CLAUDE.md / docs/steering/ × 3 / 依存先 spec / iField 類似 spec）を **1ターンで並列 Read**
2. **steering 既出事実の棚卸し**（「## 処理フロー」のステップ 4 参照）
3. **4 項目を初稿としてチャット上に提示** — 提示の **冒頭で必ず以下のコミット宣言を添える**（autonomous モード下での暴走を自他で止める歯止め）:

   > 「Phase 0 で一旦止まります。明示 OK を頂くまで Phase 1（3 ファイル生成）には進みません。修正点・追加要件があれば指摘してください」

4. **【最重要】ユーザーフィードバックを待つ** — 認識ズレや追加要件があれば指摘される。**ここで Phase 1 のファイル生成に進むのは規約違反**。同ターン内で Write / Edit を `docs/specs/{name}/` に対して発行しない
5. **ブラッシュアップしたバージョンを再提示** — 修正箇所が明確に分かるように差分意識して書く
6. **ループ**: ユーザーが「OK」「進めて」「Phase 1 に進んで」「これで」等の **明示的同意トークン** を発するまでステップ 4-5 を繰り返す
7. **暗黙の OK は禁止** — 「他に何かありますか？」のような問いに「いえ」だけで返された場合も「では Phase 1 に進めて良いですか？」と明示確認を取る
8. **autonomous モード指示が来ても Phase 0 の停止規約は無効化されない** — 「clarifying questions なし」「judgment call で続けて」「お任せします」「effort=max」等が親プロンプト / システムリマインダにあっても、**Phase 0 の明示 OK 待ちは省略しない**（理由は冒頭 🛑 callout 参照）。autonomous は Phase 0 *内部* の小判断（4 項目の文言選択 / 例示の取捨）には適用してよいが、**Phase 0 → Phase 1 の移行**には適用しない。ここを混同して走ると spec 全面再生成の手戻りが発生する

### オープンクエスチョンの扱い

Phase 0 提示後に Claude が判断に迷う点がある場合、4 項目とは別に「**確認したい点**」として末尾に列挙してユーザーに問う:

```
### 確認したい点
1. {論点 A vs B、Claude のおすすめ + Why}
2. ...
```

ユーザーが回答 → 次の版に反映 → ループ。

### Phase 1 への移行条件

以下 **両方** を満たした場合のみ Phase 1（3 ファイル生成）に進む:

- ユーザーが Phase 0 の内容に **明示的に OK 表明**（「OK」「進めて」「これで」「Phase 1 へ」等の同意トークン、または 4 項目に対する具体的修正指示を反映した版に対する同等の同意）
- Claude 自身が「概要・背景・目的・要件・受け入れ条件のすべてに曖昧さがない」と判断

どちらか欠ける場合は Phase 0 を継続する。

> **autonomous モード（`effort=max` / `clarifying questions なし` 指示）でも本条件は変わらない**。autonomous は「Phase 0 を飛ばして良い」根拠にはならない（冒頭 🛑 callout 参照）。同意トークンが明示的に与えられるまでは何度でも Phase 0 を再提示し、`docs/specs/{name}/` への Write / Edit は発行しない。
>
> Phase 0 提示後にユーザが沈黙している場合は「Phase 1 に進んで良いですか？」と **追い質問** をする（autonomous であってもこの 1 行確認は省略しない、根拠: 1 行の確認コスト ≪ 1500 行手戻りコスト）。

---

## Phase 1: 3 ファイル生成 + セルフレビュー

**入力**: Phase 0 でユーザーと合意した 4 項目（チャット履歴）。生成する 3 ファイルは Phase 0 合意内容と必ず整合させる。

各ファイルのテンプレ・採番規約・命名ルールは別ファイルに分離:

| ファイル | テンプレ | 主な責務 |
|---|---|---|
| `requirements.md` | [`templates/requirements-template.md`](./templates/requirements-template.md) | EARS ハイブリッド形式の受け入れ基準。REQ ID 採番規約含む |
| `design.md` | [`templates/design-template.md`](./templates/design-template.md) | Mermaid 図 / データモデル / Action シグネチャ / 関連要件マッピング |
| `tasks.md` | [`templates/tasks-template.md`](./templates/tasks-template.md) | Step 単位チェックボックス、各タスクに REQ ID inline 注釈 |

> **コマンドは Sail プレフィックス必須**: tasks.md / 完了報告に書くコマンドはすべて `sail artisan ...` / `sail npm ...` / `sail bin pint` 形式（`tech.md`「コマンド慣習」参照）。

## 処理フロー

| Step | 内容 | 詳細セクション |
|---|---|---|
| 1 | `$ARGUMENTS` で Feature 名取得 | — |
| 2 | TaskCreate で 6 タスク作成（前提読み込み / Phase 0 / requirements / design / tasks / セルフレビュー） | — |
| 3 | 前提読み込みを **1 ターンで並列 Read**（CLAUDE.md / docs/steering/ × 3 / .claude/rules/ / iField 類似 spec / 依存先 specs） | 「## 必須読み込み」 |
| 4 | steering 既出事実の棚卸し（後述） | 「## ユーザー確認の方針」 |
| 5 | **Phase 0 実行**: 「Phase 0 で止まります」宣言 → 4 項目をチャット上に提示 → ユーザー壁打ち → 明示 OK トークンまでループ（**autonomous モードでも省略不可、冒頭 🛑 callout 参照**） | 「## Phase 0」 |
| 6 | **Phase 1 開始**（ユーザー明示 OK トークン受領後のみ、`effort=max` / `clarifying questions なし` 等の autonomous 指示は本移行の根拠にならない）: requirements → design → tasks の順に 3 ファイル生成 | 「## Phase 1」+ `templates/` |
| 7 | セルフレビューループ実行（5 ラウンド + 必要に応じ +2 サイクル） | 「## セルフレビューループ」 |
| 8 | 完了報告（Phase 0 合意サマリ + Phase 1 セルフレビュー修正サマリ含む） | 「## 完了報告フォーマット」 |

### ステップ 4: steering 既出事実の棚卸し（Phase 0 / Phase 1 共通で必須）

- `product.md` の **該当 Feature の表行**（主モデル / 概要 / Advance 連携）を抜き出す
- `product.md` の **「## ステータス遷移」** で本 Feature が所有するエンティティの state diagram を抜き出す
- `product.md` の **「## 集計責務マトリクス」** で本 Feature が所有する Service を抜き出す
- これらは **既出の事実** として扱い、spec で勝手に変更しない。逸脱しそうな設計判断が出たら **作業を止めてユーザーに確認**（「## ユーザー確認の方針」参照）

## 制約

- **`docs/specs/{name}/` 配下以外のファイルを編集しない**（ただし Phase 0 で product.md の更新が必要と判明した場合は別途確認の上で更新）
- product.md / tech.md / structure.md / .claude/rules/ との整合性
- 命名は structure.md の規約に厳格に従う
- 1 Skill 実行 = 1 Feature

## ユーザー確認の方針

仕様 / 設計判断で **不整合 / 曖昧さ / 提案したい変更** が出てきたら、勝手に進めず作業を止めてユーザーに確認する。

### 🔴 最優先で停止すべきケース — steering との矛盾

以下は **無条件で停止 + 確認**。spec で独自判断して steering を逸脱しない:

- `product.md` の **state diagram** と矛盾する状態モデルを設計しようとしている
- `product.md` の **Feature 一覧表** の「主モデル」「概要」と異なるエンティティ構成を提案しようとしている
- `product.md` の **集計責務マトリクス** と異なる Service 所有 Feature を提案しようとしている
- `tech.md` のアーキテクチャ方針（Clean Architecture 軽量版、Action 命名 等）に反する設計をしようとしている
- `structure.md` の命名規則・ディレクトリ構成に反する配置をしようとしている

> Why: steering は **複数 Feature を横断する一貫性の源**。1 spec で独断したら他 Feature の spec / 実装と矛盾する。steering 自体が間違っていると判断したら、まず steering を直してから spec に着手するのが正しい順序。

### その他の確認ケース

- **不整合**: 既存 specs と矛盾する要求が見つかった
- **曖昧さ**: EARS の主語が定まらない、状態遷移の起点 / 終点が不明、命名候補が複数ある
- **設計判断の拮抗**: 複数の妥当な設計案があり、Why の決定打がない（COACHTECH 流 と Certify 流が衝突する等）
- **提案**: steering ドキュメントへの追加・修正提案、新 Feature 分割提案、依存関係の見直し
- **スコープ越え**: `docs/specs/{name}/` 配下を超えた変更が必要そうな状況

確認の仕方: 選択肢を 2-3 個用意して `AskUserQuestion` で聞くのが最短。曖昧なまま進めて手戻りするより、止まる方が安い。

## セルフレビューループ（最大 5 ラウンド + 必要に応じ +2 サイクル）

生成した 3 ファイルは **必須読み込みファイル群を「正」として** 段階的にレビューし、不整合は同ターン内で Edit して矯正する。

> Why ループ式: 観点を 5 つに分解し各ラウンドで該当ファイルだけを再 Read することで、(a) 1 回の頭で全観点を見ようとして起きる粗さ、(b) コンテキスト圧迫、の両方を避ける。**過剰修正は禁止** — 既に整合している箇所をいじって新たな歪みを入れない。

### 各ラウンドの進め方

1. **再 Read**: 当該ラウンドの「対象ファイル」を並列 Read（既に context にあっても、観点項目を verbatim 引用で照合するため再 Read する）
2. **照合**: 観点項目を 1 つずつ生成物と突き合わせる
3. **修正**: 不整合があれば Edit で同ターン内に反映。「修正なし」も明示的に記録（後段の収束判定に使う）
4. **波及確認**: 修正があった場合、後段ラウンドの観点を壊していないかを次ラウンドで自然に拾う

### 事前条件（ラウンド 0、これが満たされていなければループに入らない）

- [ ] 3 ファイルが `docs/specs/{name}/` に存在（requirements.md / design.md / tasks.md）
- [ ] 各ファイルが空でない（最低でも requirements 60 行 / design 150 行 / tasks 80 行を目安）

### ラウンド 1: steering 整合性レビュー（最優先）

steering と spec が乖離したら他 Feature の整合性まで連鎖して崩れるため、最初に潰す。

- **再 Read**: `docs/steering/product.md`（該当 Feature 行 + 「## ステータス遷移」 + 「## 集計責務マトリクス」） / `docs/steering/tech.md`（アーキテクチャ方針） / `docs/steering/structure.md`（命名・配置）
- **観点**:
  - [ ] product.md の **Feature 一覧表の該当行**（主モデル / 概要 / 主ロール / 提供状態 / Advance 連携）と spec のエンティティ構成・主要カラム・Controller 配置が一致
  - [ ] product.md の **state diagram** で本 Feature 所有エンティティが定義されている場合、spec の状態遷移と Enum 値・遷移先・トリガが完全一致。所有 state diagram が無い場合は spec で独自定義可だが、product.md の他箇所（Feature 一覧表の概要欄等）の言及と矛盾しない
  - [ ] product.md の **集計責務マトリクス** と spec の Service 所有が一致
  - [ ] tech.md のアーキテクチャ方針（Clean Architecture 軽量版 / Action 命名 / Repository は外部 API 限定 / PR 7 セクション 等）に違反する設計をしていない
  - [ ] structure.md のディレクトリ構成・specs 作成ルールに違反していない
- **不整合検出時**: 軽微（用語ゆれ・主モデル列挙漏れ等）→ spec 側を Edit。重大（steering 逸脱の設計判断）→ **作業を停止し AskUserQuestion**

### ラウンド 2: 要件トレース三角測量

requirements → design → tasks の三角形で **逆引きが両方向に成立** しているかを点検する。

- **再 Read**: 生成した 3 ファイル（requirements.md / design.md / tasks.md）
- **観点**:
  - [ ] 要件 ID `REQ-{name}-NNN` / `NFR-{name}-NNN` が 10 刻みで採番され、重複・予期せぬ欠番がない
  - [ ] requirements.md の **すべての REQ / NFR** が design.md の「関連要件マッピング」表に登場する
  - [ ] design.md の「関連要件マッピング」表の各行で示された **実装ポイント**（クラス / メソッド / migration / Blade）が tasks.md にチェックボックスとして存在する
  - [ ] tasks.md の各タスクに関連要件 ID が inline 注釈で付いている（テスト系・整形系・動作確認系は省略可）
  - [ ] design.md で言及している Action / Service / Policy / FormRequest / Migration / Blade / Exception が tasks.md の Step 1-6 に過不足なく登場する
- **不整合検出時**: 漏れている側に Edit で追記。REQ を削るのは要件削減なので原則 NG

### ラウンド 3: Mermaid・EARS 記述スタイル

可読性とパース安定性に直結する、生成時のうっかりを潰す。Mermaid は壊れているとレンダリング自体が落ちるので必ず修正。

- **再 Read**: 生成した design.md（Mermaid ブロック中心）+ requirements.md（EARS 行中心）+ 本 SKILL.md の「## 記述言語」セクション
- **観点**:
  - [ ] `stateDiagram-v2` のラベルは単行、ラベル内に `:` を含まない（`draft: draft（下書き）` の外側 `:` のみ）
  - [ ] `erDiagram` のカラム説明文に `:` `,` を含まない（Mermaid パーサが誤認する）
  - [ ] `flowchart` / `sequenceDiagram` のノードラベル内に裸の `[` `]` `{` `}`（特に `[[...]]` の wikilink 表記）を含めない。特殊文字を含むラベルは **ダブルクオートで囲む**（例: `Foo["text with / and *"]`）
  - [ ] `sequenceDiagram` / `flowchart` のテキスト改行は `<br/>` を使う
  - [ ] EARS のすべての行で **構造キーワード `shall` / `when` / `if` / `while` が英語のまま** 残っている
  - [ ] 主語スタイルが 1 spec 内で統一（`the system` 主体 or `the {Module}` 主体）
  - [ ] 他 Feature への参照は `[[feature-name]]` wikilink で書かれ、フラットなテキストになっていない
- **不整合検出時**: Edit で書き換え。Mermaid は壊れていれば優先度最高

### ラウンド 4: 命名・用語整合

structure.md と `.claude/rules/` の規約、および依存先 Feature spec との用語ゆれを潰す。

- **再 Read**: `docs/steering/structure.md` + `.claude/rules/backend-models.md` / `backend-http.md` / `backend-usecases.md` / `backend-services.md` / `backend-policies.md` / `backend-repositories.md` + 依存先 Feature の `docs/specs/{dep}/design.md`（あれば、用語整合のため）
- **観点**:
  - [ ] クラス名 **PascalCase**、テーブル名 **snake_case 複数形**、カラム名 **snake_case 単数形**、ファイル名 **kebab-case**
  - [ ] `{Entity}Controller` / `{Action}Action` / `{Feature}Service` / `{Entity}Policy` / `{Action}Request` / `{Entity}{Reason}Exception` の命名規則を厳格遵守
  - [ ] **Controller method 名 = Action クラス名（PascalCase 化）** 規約に違反していない
  - [ ] Feature 横断で他 Feature の Action を Controller から直接 DI していない（規約上、呼出元 Feature 配下に同名ラッパー Action を作る）
  - [ ] Enum 値・カラム名・テーブル名が product.md の表記と完全一致
  - [ ] 依存先 Feature の design.md と同一エンティティ・サービスを参照する箇所の表記が一致
  - [ ] Repository を DB 専用に作っていない（外部 API 連携時のみ採用）
- **不整合検出時**: Edit でリネーム。依存先 spec で使われている表記に **本 spec を寄せる**

### ラウンド 5: スコープ・関連 Feature リンクの最終確認

scope creep と Feature 間整合の最後の砦。

- **再 Read**: requirements.md の「## スコープ外」「## 関連 Feature」 + 依存先 Feature の `docs/specs/{dep}/design.md`（あれば）+ `.claude/rules/backend-exceptions.md`
- **観点**:
  - [ ] `docs/specs/{name}/` 配下以外のファイルを編集していない（Phase 0 で product.md 更新が合意された場合を除く）
  - [ ] tech.md / structure.md / `.claude/rules/` を本 Skill 実行中に変更していない
  - [ ] requirements.md の「## スコープ外」に書いた項目が design.md / tasks.md にうっかり紛れ込んでいない
  - [ ] 「## 関連 Feature」の **依存先** に挙げた Feature の spec が既存なら、その design.md と本 spec の連携記述が矛盾しない
  - [ ] 想定例外（`app/Exceptions/{Domain}/*Exception.php`）が `backend-exceptions.md` の親クラス対応表に沿った HTTP ステータスを返す設計になっている
- **不整合検出時**: 本 spec 側を Edit で修正。依存先 Feature 側の不整合は完了報告に記録

### ループ終了判定

| 状況 | アクション |
|---|---|
| ラウンド 5 完了 + 全ラウンドで「修正なし」または最後の数ラウンドが「修正なし」で安定 | 完了報告へ |
| ラウンド 5 で **まだ修正が発生** | ラウンド 1 から **追加 1 サイクル**（最大 +2 サイクル = 計 7 ラウンドまで）|
| +2 サイクル後もまだ収束しない | 設計に根本的な問題がある可能性。**作業を止めて AskUserQuestion**（要件見直し / 設計やり直し / Feature 分割 等の選択肢を提示） |

> Why 追加サイクル: 命名修正（R4）が REQ ID 参照（R2）を壊す / Mermaid 修正（R3）が概念整合（R1）を壊す等の波及がありうるため、収束まで回す。

## 完了報告フォーマット

実行完了時、以下のテンプレでユーザーに報告する:

```
`docs/specs/{name}/` に 3 ファイル生成完了（requirements: {N} 行 / design: {N} 行 / tasks: {N} 行 / 合計 {N} 行）。

### Phase 0 の合意サマリ

ユーザーと壁打ちで合意した内容（ブラッシュアップ {M} ラウンド）:

- **概要**: ...
- **背景・目的**: ...
- **要件**: ...
- **受け入れ条件**: ...

> 主な調整点: {ユーザーフィードバックで Claude 初稿から変更された箇所のサマリ。なければ「初稿で合意」}

### 主要な設計判断（Phase 1）

- **{論点 1}**: {採用した方針}。{なぜ}（Why の言語化、代替案との比較）
- **{論点 2}**: {採用した方針}。{なぜ}
- **{論点 3}**: {採用した方針}。{なぜ}

### セルフレビューループでの修正サマリ

{Nラウンド × Mサイクル} 回したうちで修正が発生した箇所のみ記載。

| ラウンド | 観点 | 修正点 |
|---|---|---|
| R1 steering 整合性 | {例: state diagram と Enum 値ズレ} | {例: `Draft` → product.md 記述の `draft` に揃えた} |
| R2 要件トレース | {例: REQ-xxx-042 が design 表に未登場} | {例: design.md の関連要件マッピングに 1 行追加} |
| R3 Mermaid/EARS | {例: flowchart ノードラベルに wikilink 表記} | {例: ノード名をダブルクオート保護に変更} |
| R4 命名・用語 | (修正なし) | — |
| R5 スコープ/リンク | {例: 依存先 spec の Service 名と表記ズレ} | {例: 表記統一} |

> ループ収束判定: {例: 標準 5 ラウンドで収束 / +1 サイクル要した（R4 修正の波及で R2 が再度壊れた）}

### COACHTECH LMS 参考の有無（参考にした場合のみ記載）

| 観察パターン | Laravel 標準との差分 | Certify への適用判断 |
|---|---|---|
| ... | ... | ... |

### Certify 固有の差異（論点が出た場合のみ）

- {差異}: COACHTECH は ... 、Certify は ...

`/feature-implement {name}` で Step 1 から順次実装に移れます。
```

> Why このフォーマット: PR の「原因分析 / 設計判断」欄に直結する Why の言語化（AI 丸投げ排除）+ セルフレビューが形骸化していないかの透明性 + COACHTECH 参考の判断責任、を一箇所に集約する。
