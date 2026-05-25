# T-B-02 コーチダッシュボードの担当受講生一覧の N+1 解消

<!--
記述粒度規約: 実装粒度を記載できるのは `## 実装方針` 配下のみ。それ以外は業務語彙のみ。
受け入れ条件は構造記述 / Before/After 計測値ベース。
-->

## メタ情報

| 項目 | 値 |
|---|---|
| チケット ID | `T-B-02` |
| Feature 連番 | `mentoring-05` |
| Feature | mentoring(コーチダッシュボード) |
| 種別 | Task |
| サブカテゴリ | パフォーマンス(N+1 解消 + 集約取得) |
| 難易度 | Basic |
| 工数 (h) | 4 |
| 依存チケット | (なし) |

## 概要

コーチ(coach)のダッシュボードに表示される担当受講生一覧で、各受講生の関連情報(受講生氏名 / 担当資格名 / 最終活動日時)の取得が受講生数に応じて個別クエリを発火する N+1 状態になっている。リレーションの一括取得と最終活動日時の集約取得を組み合わせて、担当受講生数に依らずクエリ本数を定数に抑える。

## 背景・目的

- **現状の問題**: コーチが担当する受講生の一覧では、行ごとに「受講生情報」「担当資格名」「最終活動日時」を取得するクエリが個別に発火する。担当受講生が増えるほどクエリ本数が線形に増え、ダッシュボードの表示が重くなる(担当受講生 N 人で本体 1 + N×3 本の発行)。
- **達成したい状態**: 担当受講生件数に依らずクエリ本数を定数に保ち、コーチが多くの受講生を抱えても安定して表示できる状態にする。リレーションの一括取得と、関連テーブルからの最大値(最終活動日時)取得を集約 1 本にまとめる。
- **教材的価値**: 単純な `with()` だけでなく、関連テーブルからの集約値(最大値 / 件数)を 1 ショットで取る `withMax` / `withCount` 系の使い分けを学ぶ題材。`T-B-01` の単一 Eager Loading に対して、複合パターンとして配置。

## やること

- コーチダッシュボードの担当受講生一覧の N+1 を解消する(受講生 / 担当資格 / 最終活動日時の一括取得)
- Before / After のクエリ本数を計測し、PR に記載する
- 担当受講生件数を増やしてもクエリ本数が線形増加しないことを検証する自動テストを追加する

## やらないこと

- 管理者のユーザー管理画面の N+1 解消 — `T-B-01` で扱う
- ダッシュボードの他カード(プラン情報 / 通知 / 学習進捗ゲージ 等)の最適化
- キャッシュ層の導入
- 一覧表示項目・並び順・絞り込み仕様の変更(振る舞いは不変)

## 受け入れ条件

- [ ] コーチダッシュボードの担当受講生一覧の表示で、担当受講生件数に依らずクエリ本数が定数(目安 5 本以下)に収まっている
- [ ] 担当受講生一覧取得に `with(['user', 'certification'])` 相当のリレーション一括取得が実装されている
- [ ] 最終活動日時が `withMax('learningSessions as last_activity_at', 'started_at')` 相当の集約取得で取られている(関連テーブル全件の Eager Loading ではなく最大値のみ)
- [ ] 既存のダッシュボード表示内容(担当受講生名 / 担当資格 / ターム / 状態バッジ / 最終活動日時)が変更前と一致する(振る舞い不変)
- [ ] 担当受講生数を増やしてもクエリ本数が線形増加しないことを検証する自動テストが追加されている

## 実装方針

> **参考設計の一例**。受け入れ条件を満たせれば実装手段は問わない。受講生は提供 PJ コード + ヒアリングで自分の設計を組み立てる。

### 主要 URL

| メソッド | パス | 振る舞い |
|---|---|---|
| GET | `/dashboard` | ダッシュボード表示(コーチでアクセスするとコーチ向けビュー = 担当受講生一覧などを描画) |

### 変更対象と変更前後の状態

- **変更対象ファイル候補**: `app/UseCases/Dashboard/FetchCoachDashboardAction.php`(Basic 受講生が Controller 内完結で実装している場合は `app/Http/Controllers/DashboardController::index()` 内のコーチ向けデータ取得処理が対象)
- **変更前(提供 PJ の状態)**: 担当受講生(Enrollment)一覧の取得クエリで `->with(['user', 'certification'])` と `->withMax('learningSessions as last_activity_at', 'started_at')` が抜けており、Blade で `$enrollment->user->name` / `$enrollment->certification->name` / `$enrollment->last_activity_at` を参照する箇所が件数分の遅延ロードを発火する
- **変更後(理想形)**: 取得クエリにリレーションの一括取得と最終活動日時の集約取得を追加し、担当受講生件数に依らない定数本数で取得する

### 計測指標と目標値(Performance)

| 項目 | 内容 |
|---|---|
| 計測手法 | `DB::enableQueryLog()` + `DB::getQueryLog()` の本数カウント(テスト内)/ Laravel Debugbar(ローカル目視) |
| 計測指標 | コーチダッシュボード表示 1 回あたりの「担当受講生一覧取得関連」の SQL 発行本数 |
| 改善前(Before) | 担当受講生 N 人の場合、本体 1 + 受講生個別 N + 担当資格個別 N + 最終活動日時 個別 N = **1 + 3N 本**(N=10 で 31 本、N=20 で 61 本) |
| 改善後(After 目標) | 本体 1 + 受講生 IN 1 + 担当資格 IN 1 + 最終活動日時集約(本体クエリに統合)= **3 本**。担当受講生件数に依存しない定数 |

### テスト方針

| 種別 | 観点 |
|---|---|
| 振る舞い不変 | 既存のダッシュボード関連テスト(`tests/Feature/Http/Dashboard/*` / `tests/Feature/UseCases/Dashboard/FetchCoachDashboardActionTest.php`)が全件 pass(担当受講生表示内容 / 担当外受講生の非表示 / 最終活動日時の正確性) |
| 性能(N+1 検知) | 担当受講生を 10 件程度生成した上で `DB::listen()` でクエリをカウントし、上限本数(例: 担当受講生件数の 1〜2 倍以下、N の線形にならない値)を assert。既存の `DashboardQueryCountTest` のパターンを踏襲 |

### 採用技術と判断理由

- **採用技術**: Eloquent の `with(['user', 'certification'])`(リレーション一括取得)+ `withMax('learningSessions as last_activity_at', 'started_at')`(関連テーブルからの最大値の集約取得)
- **判断理由**:
  1. 担当資格 / 受講生は BelongsTo なので `with()` の IN クエリ 1 本ずつで一括取得できる
  2. 最終活動日時は関連テーブル(学習セッション)の最大値であり、関連レコード全件を Eager Loading する必要はない。`withMax` 系で本体クエリのサブクエリ / JOIN として組み込めば最大値だけが取れる(全件取得より大幅に軽い)
  3. ページネーションは導入しない(コーチ 1 名あたりの担当受講生は通常少数のため、全件取得で問題ない)

### 改善対象コードメモ

- 改善対象の主要ファイル: `app/UseCases/Dashboard/FetchCoachDashboardAction.php`(担当受講生一覧の取得クエリに `->with(['user', 'certification'])` + `->withMax('learningSessions as last_activity_at', 'started_at')` がある状態が正、Step 4 でこの 2 行を取り除いて N+1 状態を作る)
- 関連: `app/Models/Enrollment.php`(`user` / `certification` / `learningSessions` 各リレーション)/ `resources/views/dashboard/coach.blade.php` および同階層の partial(`_partials/coach/assigned-students-list.blade.php`)/ `tests/Feature/UseCases/Dashboard/FetchCoachDashboardActionTest.php`(`last_activity_at` の正確性テストを保持)/ `tests/Feature/Http/Dashboard/DashboardQueryCountTest.php`(クエリ本数 assert の参考実装)

## 補足

### 想定ヒアリング Q&A

| 質問 | 回答 |
|---|---|
| クエリ本数の目標値は? | 担当受講生件数に依らない定数本数(目安 5 本以下)。本体 + 受講生 IN + 担当資格 IN + 集約(本体に統合)程度の構成 |
| 最終活動日時を関連テーブルから取るのに、`with('learningSessions')` ではなく `withMax` を使うのはなぜか? | `with('learningSessions')` は対象 Enrollment の全 LearningSession を Eager Loading してから PHP 側で MAX を計算するため、関連レコードが大量にあるとメモリと SQL 取得量の両方で重くなる。`withMax` は SQL の集約関数(MAX)を本体クエリに組み込むため、最大値だけが取れて転送量も最小 |
| 担当資格スコープ(コーチが見える受講生)はどう絞り込まれているか? | コーチが担当する資格の ID 群を取得し、その資格に属する受講登録(`learning` / `passed`)に絞る。担当外資格の受講生は元々取得対象に含まれない(本最適化の影響範囲外) |
| ページネーションは導入しないのか? | 本チケットでは導入しない。コーチ 1 名あたりの担当受講生は通常少数で、全件取得でも問題のない規模。将来的に大量になる場合はページネーションを別途検討する |
| `T-B-01` との関係は? | 同じ N+1 解消だが対象 / 解法が異なる。`T-B-01` は管理者のユーザー一覧 × プラン情報の単純 Eager Loading、`T-B-02` はコーチ視点の担当受講生一覧 × 複数リレーション + 関連テーブルからの集約取得(`withMax` を含む)。受講生は両方を通じて「N+1 解消の引き出しの広さ」を学ぶ |
