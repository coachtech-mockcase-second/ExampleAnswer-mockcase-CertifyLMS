# B-B-02 教材管理の階層一覧（Part / Chapter / Section）が並び順を無視して表示される

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

教材管理の各階層一覧(Part 一覧 / Part 詳細画面の Chapter 一覧 / Chapter 詳細画面の Section 一覧)が、コーチが設定した並び順(順序番号 `order` の昇順)を無視して表示される。各階層とも設定した並び順ではなく登録順で並んでしまい、コーチが意図した構成順で教材が提示されない。

## 再現手順

**前提**: 管理者またはコーチでログイン済み。並び順(`order`)を登録順とずらした Part / Chapter / Section が存在する(例: `order=3` を最初に、`order=1` を次に、`order=2` を最後に登録)。

1. 教材管理(Part 一覧)を開く → 並び順(1, 2, 3)ではなく登録順(3, 1, 2)で表示される
2. Part 詳細(配下の Chapter 一覧)を開く → 同様に並び順を無視した登録順で表示される
3. Chapter 詳細(配下の Section 一覧)を開く → 同様に並び順を無視した登録順で表示される

## 期待する結果

各階層の一覧(Part 一覧 / Part 詳細の Chapter 一覧 / Chapter 詳細の Section 一覧)が、いずれも設定された並び順(`order` の昇順)で表示される。

## 実際の結果

各階層の一覧が `order` を無視して登録順で表示され、コーチが設定した並び順が反映されない。

## 受け入れ条件

- [ ] 【管理者・コーチ】教材管理の階層一覧が並び順で表示される
  1. 資格管理マスタの各階層一覧(Part 一覧 / Part 詳細の Chapter 一覧 / Chapter 詳細の Section 一覧)が、いずれも並び順(`order` の昇順)で表示される
- [ ] 同梱の並び順テスト(修正前は赤)が pass する
  - 確認方法（テスト）: `tests/Feature/Http/Part/IndexTest.php::test_parts_are_listed_in_order_ascending`(Part 一覧) / `tests/Feature/Http/Part/ShowTest.php::test_chapters_are_listed_in_order_ascending`(Chapter 一覧) / `tests/Feature/Http/Chapter/ShowTest.php::test_sections_are_listed_in_order_ascending`(Section 一覧)

## 実装方針(参考)

### 原因

- **主要ファイル**: 各階層の一覧取得を担う 3 つの Action にそれぞれ並び順の整列指定が抜けている。
  - `app/UseCases/Part/IndexAction.php`(Part 一覧)
  - `app/UseCases/Part/ShowAction.php`(Part 詳細画面の Chapter 一覧)
  - `app/UseCases/Chapter/ShowAction.php`(Chapter 詳細画面の Section 一覧)
- **原因の内容**: 各一覧取得クエリに並び順の整列指定(対象 Model の `scopeOrdered()` = 並び順カラム `order` の昇順)が無い。明示的な整列が無いため登録順で返り、コーチが設定した並び順が無視される。整列を書く場所は階層で 2 種類ある: Part 一覧は取得クエリのトップレベル(`->ordered()`)、Chapter / Section は親モデルの Eager Load の制約クロージャ内(`->with(['chapters' => fn ($q) => $q->ordered()])` のクロージャ内 / `->load(['sections' => fn ($q) => $q->ordered()])` のクロージャ内)。
- **修正範囲**: 上記 3 箇所の一覧取得に並び順昇順の整列(`->ordered()` スコープ)を加える。各箇所 1 行で完結。トップレベルの整列とリレーション先 Eager Load 内の整列の両方を、漏れなく直す必要がある。

## 補足

### 想定 Q&A

| 質問 | 回答 |
|---|---|
| 並び順の基準は何か? | 各 Part / Chapter / Section に設定された順序番号(`order`)の昇順。登録日時順ではない |
| 3 階層すべてが対象か? | はい。Part 一覧・Part 詳細画面の Chapter 一覧・Chapter 詳細画面の Section 一覧の 3 つすべてで、並び順(順序番号の昇順)に整列して表示される。症状は同一(整列漏れ)だが、整列を書く場所は階層で異なる |
| 並び順が同値の場合の二次キーは? | 実装依存で可。受け入れ条件は「並び順昇順で表示される」ことであり、同値時の二次整列(登録日時など)は受講生判断 |
