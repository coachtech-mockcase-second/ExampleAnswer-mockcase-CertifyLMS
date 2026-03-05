# ManaBase - 模擬案件プロジェクト

## このプロジェクトについて

プログラミングスクールCoachTechの受講生向け **2つ目の模擬案件** を制作するプロジェクト。
本リポジトリは模範解答コード + 関連ドキュメントを管理する。

### 対象者（ペルソナ）

CoachTechの受講生。以下を修了済みの状態でこの模擬案件に取り組む：

- **教材**: HTML/CSS → PHP → Laravel基礎〜応用 → API設計 → テスト（約15-19週）
- **確認テスト**: お問い合わせフォームアプリ（ContactForm）をゼロから構築
- **1つ目の模擬案件**: 書籍レビューアプリ（BookShelf）をゼロから構築

習得済みスキル: Laravel CRUD、Eloquent（リレーション・Eager Loading）、Fortify認証、Policy認可、FormRequest、PHPUnit、REST API基礎、Docker/Sail、Git/GitHub

### 目的

- **「既存プロジェクトへの参画」を体験させる**（前2つはゼロから構築だった）
- 他人が書いたコードを読み解き、仕様を理解する力を鍛える
- バグフィックス・改修・新機能開発・リファクタリング・テストという実務の一連のタスクを経験させる

### テーマ

**ManaBase** — オンライン学習プラットフォーム（LMS）。
受講生にとって馴染みのあるドメイン（自分が使ってきたLMSと同種）のため、要件理解がスムーズ。

- 3ロール: 管理者(admin)、講師(instructor)、受講生(student)
- コンテンツ階層: Course → Chapter → Section
- 詳細: `docs/specs/overview.md`

### 提供形態

- Blade（フロントエンド）は **全て実装済み** で提供
- バックエンドは **一部実装済み / 一部バグあり / 一部未実装** の状態で提供
- 受講生はBladeを読んで「バックエンドが何を返すべきか」を自分で把握する

---

## 技術スタック

Laravel 10, PHP 8.2, MySQL 8.0, Docker/Sail, Tailwind CSS, Alpine.js, Fortify（BookShelfと同じ）

---

## 出題設計

### 5カテゴリ
1. **バグフィックス** — 壊れている→直す
2. **既存機能の改修** — 動いている→拡張する
3. **新機能開発** — 存在しない→作る
4. **リファクタリング** — 動くが汚い→改善する
5. **テスト追加** — テストがない→書く

### Basic / Advance
- **Basic**: 教材の習得範囲内で解ける → Basicブランチ
- **Advance**: 教材範囲外OK（Sanctum API、Gemini AIチャットボット、パフォーマンスチューニング等） → Advanceブランチ（Basic + 応用）
- 判定基準の詳細: `docs/textbook-scope.md`

### 設計方針
- コードリーディングを独立チケットにせず、各チケット内に自然に組み込む
- お手本機能のテストを提供し、テストの読み方を学ばせる
- バグはコード起因 + データ起因の両方を含める
- 実装ボリュームはBookShelfの80%目標（コードリーディング時間を考慮）

---

## 制作フェーズ

| Phase | 内容 | 状態 | 主な成果物 |
|-------|------|------|-----------|
| 1 | 設計 | **進行中** | specs, チケット設計 |
| 2 | プロジェクト構築 | 未着手 | 仮PJ（動作確認）→ 提供版（バグ仕込み・スタブ化） |
| 3 | Basic完全手順書 | 未着手 | Basic手順書 |
| 4 | Basic完成版構築 | 未着手 | 手順書に沿って検証＆構築 → Basicブランチ確定 |
| 5 | Advance完全手順書 | 未着手 | Advance手順書 |
| 6 | Advance完成版構築 | 未着手 | 手順書に沿って検証＆構築 → Advanceブランチ確定 |
| 7 | ドキュメント作成 | 未着手 | 評価項目 → 要件定義書(100%/50%) → 復習教材 |

依存: `1 → 2 → 3 → 4(Basic確定) → 5 → 6(Advance確定) → 7`

### 現在地: Phase 1
- [x] 1.1 概要（テーマ・ロール・機能・エンティティ） → `docs/specs/overview.md`
- [ ] 1.2 チケット設計（中詳細: カテゴリ・対象エンティティ・受講生の作業内容・お手本機能の選定）
  - Basic/Advance全チケットを一緒に設計する
  - ★ 検証: AdvanceがBasicへの純粋な追加であること（Basicのコード変更が不要なこと）
- [ ] 1.3 DB設計 → `docs/specs/database.md`
- [ ] 1.4 ルーティング設計 → `docs/specs/routes.md`

### 確定済みの方針
- 新機能チケット: 受講生がマイグレーション + モデル + コントローラを全て作る
- 新機能のBlade: ファイルとして提供するがルートは存在しない（受講生が作る）

### 各フェーズのゲート（進む前に確認）
- **Phase 2 開始前**: チケット設計でAdvanceの純粋追加性を検証済みか
- **Phase 2 提供版構築時**: provided-state.md（何が壊れ/スタブ/不在か）を作成する
- **Phase 4 Basic確定前**: 全Advanceチケットがこの状態に追加可能か再確認
- **Phase 6 Advance確定前**: 全ドキュメント（Phase 7）に必要な情報が揃っているか確認

---

## リポジトリ

### 関連リポジトリのローカルパス
- 本プロジェクト: `/Users/yotaro/ExampleAnswer-mockcase-ManaBase`
- 教材: `/Users/yotaro/pj-ct-newtext`
- BookShelf: `/Users/yotaro/ExampleAnswer-mockcase-BookShelf`
- 確認テスト: `/Users/yotaro/ExampleAnswer-ConfirmationTest-ContactForm`

### 成果物リポジトリ構成
| リポジトリ | 内容 |
|-----------|------|
| ExampleAnswer-mockcase-ManaBase（本リポ） | 模範解答コード + ドキュメント。Basic/Advanceブランチ |
| Preparedblade-mockcase-ManaBase | Blade提供用。resources/ をコピー |
| AssignedProject-mockcase-ManaBase | 受講生提供用。Blade完成済み + バックエンド壊れ/未実装 |

---

## ドキュメント構成

```
docs/
├── specs/              # 設計の正（段階的に作成）
│   └── overview.md     # テーマ・ロール・機能・エンティティ（確定済み）
├── textbook-scope.md   # 基本/応用の技術スコープ判定基準
└── archive/            # 旧版ドキュメント（参照のみ）
```

---

## 運用ルール

- チケット単位でブランチを切りPRを出す
- 提供Bladeは coachtech-prepared-file Organizationに配置
- 100%要件書を先に作成→削って50%要件書を作成
- specsファイルは必要になった時点で作成（空ファイルを事前に作らない）
