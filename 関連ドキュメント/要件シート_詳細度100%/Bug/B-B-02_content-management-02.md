# B-B-02 教材管理の階層一覧（Part / Chapter / Section）の取得に明示的な並び順指定が無く、DB のインデックス順序に暗黙依存している

## メタ情報

| 項目 | 値 |
|---|---|
| チケット ID | `B-B-02` |
| Feature 連番 | `content-management-02` |
| Feature | content-management |
| 種別 | Bug |
| サブカテゴリ | 機能(ソート) |
| 難易度 | Basic |
| 工数 (h) | 3.5 |
| 依存チケット | (なし) |

## 概要

教材管理の各階層一覧(Part 一覧 / Part 詳細画面の Chapter 一覧 / Chapter 詳細画面の Section 一覧)の取得クエリが、**明示的な並び順指定(順序番号 `order` の昇順)を欠き、DB のインデックス順序に暗黙依存**している。現状の MySQL + 複合インデックス構成では結果的に並び順どおりに表示されるため画面では症状が出ないが、明示的な整列が無いため、インデックス構成・クエリ・DB エンジンが変われば並び順が崩れる**潜在的な不具合**(implicit ordering への依存)。

## 再現手順

**前提**: 管理者またはコーチでログイン済み。

1. 教材管理(Part 一覧)/ Part 詳細(Chapter 一覧)/ Chapter 詳細(Section 一覧)を開く → **現状の DB 構成では昇順で表示され、画面操作だけでは症状を観測できない**(各取得クエリが `order` を含む複合インデックスを使い、明示 `ORDER BY` 無しでも偶然昇順で返るため)
2. コードを確認すると、各階層の一覧取得に明示的な並び順指定(`order` の昇順)が無く、DB のインデックス順序に暗黙依存していることがわかる

## 期待する結果

各階層の一覧取得クエリが、明示的に並び順(`order` の昇順)を指定している(DB のインデックス順序に暗黙依存しない)。

## 実際の結果

各階層の一覧取得から明示的な並び順指定が抜けており、DB のインデックス順序に暗黙依存している。現構成では表示は崩れないが、構成変更で崩れ得る潜在バグ。

## 受け入れ条件

- [ ] 教材管理の各階層一覧(Part 一覧 / Part 詳細の Chapter 一覧 / Chapter 詳細の Section 一覧)の取得クエリが、明示的に並び順(`order` の昇順)を指定している(DB のインデックス順序に暗黙依存しない)
  - 確認方法（コード）: `app/UseCases/Part/IndexAction.php`(トップレベル)/ `app/UseCases/Part/ShowAction.php`(chapters の Eager Load クロージャ)/ `app/UseCases/Chapter/ShowAction.php`(sections の Eager Load クロージャ)の各一覧取得に `->ordered()`(= `orderBy('order')`)が指定されていることをコード検索で確認

## 実装方針(参考)

### 原因

- **主要ファイル**: 各階層の一覧取得を担う 3 つの Action にそれぞれ整列指定が抜けている。
  - `app/UseCases/Part/IndexAction.php`(Part 一覧)
  - `app/UseCases/Part/ShowAction.php`(Part 詳細画面の Chapter 一覧)
  - `app/UseCases/Chapter/ShowAction.php`(Chapter 詳細画面の Section 一覧)
- **仕込み内容**: 各一覧取得クエリから並び順の整列指定(対象 Model の `scopeOrdered()` = 並び順カラム `order` の昇順)が抜けている。整列指定がないため Eloquent は既定(主キー / 登録順)で返し、コーチが設定した並び順が無視される。整列を書く場所は階層で 2 種類ある: Part 一覧は取得クエリのトップレベル(`->ordered()`)、Chapter / Section は親モデルの Eager Load の制約クロージャ内(`->with(['chapters' => fn ($q) => $q->ordered()])` のクロージャ内 / `->load(['sections' => fn ($q) => $q->ordered()])` のクロージャ内)。
- **修正範囲**: 上記 3 箇所の一覧取得に並び順昇順の整列(`->ordered()` スコープ)を戻す。各箇所 1 行で完結。トップレベルの整列とリレーション先 Eager Load 内の整列の両方を、漏れなく直す必要がある。
- **注記(検証手段)**: `parts(certification_id, order)` 等の複合インデックスにより、現状の MySQL では明示 `ORDER BY` を外しても結果が昇順のまま返るため、**画面でも同梱テストでも症状が観測できない**(implicit ordering への依存)。よって本チケットの確認は**コード確認**(`->ordered()` の有無を grep)で行い、同梱テストの赤/緑では判定しない。「暗黙のインデックス順序に頼らず明示的に整列する」という実務級の設計規律を学ぶ題材。

## 補足

### 想定 Q&A

| 質問 | 回答 |
|---|---|
| 並び順の基準は何か? | 各 Part / Chapter / Section に設定された順序番号の昇順。登録日時順ではない |
| 3 階層すべてが対象か? | はい。Part 一覧・Part 詳細画面の Chapter 一覧・Chapter 詳細画面の Section 一覧の 3 つすべてで、並び順(順序番号の昇順)に整列して表示される。症状は同一(整列漏れ)だが、整列を書く場所は階層で異なる |
| 並び順が同値の場合の二次キーは? | 実装依存で可。受け入れ条件は「並び順昇順で表示される」ことであり、同値時の二次整列(登録日時など)は受講生判断 |
