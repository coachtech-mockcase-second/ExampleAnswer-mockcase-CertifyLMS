# B-B-02 教材管理の階層一覧（Part / Chapter / Section）が並び順を無視して表示される

## 概要

教材管理の各階層一覧(Part 一覧 / Part 詳細画面の Chapter 一覧 / Chapter 詳細画面の Section 一覧)が、設定された並び順(順序番号)を無視して登録順で表示される。コーチが意図した構成順で教材が並ばない。

## 再現手順

**前提**: 管理者またはコーチでログイン済み。並び順を登録順とずらした Part / Chapter / Section が存在する。

1. 教材管理(Part 一覧 / Part 詳細の Chapter 一覧 / Chapter 詳細の Section 一覧)を開く
2. → いずれも設定した並び順ではなく登録順で表示される

## 期待する結果

各階層の一覧(Part 一覧 / Part 詳細の Chapter 一覧 / Chapter 詳細の Section 一覧)が、いずれも設定された並び順で表示される。

## 実際の結果

各階層の一覧が並び順を無視して登録順で表示され、コーチが設定した並び順が反映されない。

## メタ情報

| 項目 | 値 |
|---|---|
| チケット ID | `B-B-02` |
| Feature 連番 | `content-management-02` |
| Feature | content-management |
| 種別 | Bug |
| サブカテゴリ | 機能(ソート) |
| 難易度 | Basic |
| 依存チケット | (なし) |
