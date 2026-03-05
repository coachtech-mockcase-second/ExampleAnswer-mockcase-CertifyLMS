# カリキュラム調査

## 1. 教材（pj-ct-newtext）
- パス: `/Users/yotaro/pj-ct-newtext`
- リポジトリ: https://github.com/coachtech-material/pj-ct-newtext

### 概要
- 230ファイル、13チュートリアル、48章の包括的なプログラミング教材
- 完全初心者→フルスタックWebアプリ開発者への成長を想定

### カリキュラム構成
| フェーズ | チュートリアル | 内容 | 期間目安 |
|---------|--------------|------|---------|
| 基礎 | 1-4 | 学習心構え、環境構築、PC操作、Git/GitHub | 2-3週 |
| Web技術 | 5-6 | HTML/CSS、Docker | 3-4週 |
| プログラミング基礎 | 7-8 | PHP基礎、データベース/SQL | 3-4週 |
| フレームワーク | 9-11 | Laravel基礎〜応用、API設計 | 4-5週 |
| 実践 | 12-13 | チーム開発、要件定義、テスト | 2-3週 |

### 技術スタック（教材で教えるもの）
- **バックエンド**: PHP 8.x, Laravel
- **フロントエンド**: HTML5, CSS3, Blade, Alpine.js
- **DB**: SQL, MySQL
- **インフラ**: Docker, Docker Compose
- **ツール**: Git/GitHub, CLI
- **その他**: RESTful API, テスト(PHPUnit), 認証(Fortify)

### 卒業時に身につくスキル
- フルスタックWebアプリ開発（Laravel + MySQL + Blade）
- DB設計、ORM、マイグレーション
- 認証・認可
- API設計（REST）
- テスト（PHPUnit）
- Docker環境構築
- Git/GitHubでのバージョン管理
- 要件定義、課題駆動開発

---

## 2. 確認テスト - お問い合わせフォーム（ContactForm）
- パス: `/Users/yotaro/ExampleAnswer-ConfirmationTest-ContactForm`
- リポジトリ: https://github.com/coachtech-confirmationtest/ExampleAnswer-ConfirmationTest-ContactForm

### 概要
- ファッションブランド「FashionablyLate」のお問い合わせシステム
- パブリックお問い合わせフォーム + 認証付き管理画面

### 技術スタック
- Laravel 10, PHP 8.1+, MySQL 8.4
- Tailwind CSS 3.4, Alpine.js 3.4, Vite 5.0
- Laravel Fortify（認証）, Sanctum
- Docker/Sail

### 主要機能
| 機能 | 詳細 |
|------|------|
| お問い合わせフォーム | 名前、性別、メール、電話、住所、カテゴリ、メッセージ |
| 確認画面 | 入力→確認→送信完了の2ステップフロー |
| バリデーション | サーバーサイド(FormRequest) + クライアントサイド(JS) |
| 管理画面 | ログイン/登録、一覧表示、検索/フィルタ、詳細モーダル、削除 |
| API | RESTful API（カテゴリ取得、コンタクトCRUD、検索/ページネーション） |

### DB設計
- **categories**: id, content
- **contacts**: id, category_id(FK), first_name, last_name, gender, email, tel, address, building, detail
- **users**: 標準Laravel認証テーブル

### コード構成
- コントローラ: ContactController, AdminController, Api\ContactController, Api\CategoryController
- FormRequest: StoreContactRequest, IndexContactRequest
- Resource: ContactResource, CategoryResource
- JS: モジュール分割（API層、UI層、バリデーション層）
- Blade: guest/admin/authレイアウト分離

### 学習ポイント
- フォーム処理（入力→確認→完了のフロー）
- REST API設計（検索/フィルタ/ページネーション）
- FormRequestによるバリデーション
- Fortifyによる認証
- モジュラーJavaScript（ES6）
- Tailwind CSSによるレスポンシブUI

---

## 3. 模擬案件 - BookShelf
- パス: `/Users/yotaro/ExampleAnswer-mockcase-BookShelf`
- リポジトリ: https://github.com/coachtech-mockcase-first/ExampleAnswer-mockcase-BookShelf

### 概要
- 書籍レビューシステム（登録、レビュー投稿、お気に入り、ランキング）
- 基本フェーズ + 応用フェーズの2段階構成

### 技術スタック
- Laravel 10, PHP 8.1+, MySQL 8.0
- Tailwind CSS 3.1+, Alpine.js 3.4+, Vite
- Laravel Fortify, Sanctum
- Docker/Sail

### 主要機能

#### 基本フェーズ
| 機能 | 詳細 |
|------|------|
| 認証 | 登録/ログイン/ログアウト（Fortify） |
| 書籍CRUD | 作成/閲覧/編集/削除（所有者のみ編集削除） |
| ジャンル管理 | CRUD、多対多リレーション、使用中削除禁止 |
| レビュー | 投稿/編集/削除、1-5星評価、1ユーザー1レビュー制約 |
| お気に入り | トグル、一覧表示 |
| レビューいいね | トグル、カウント表示 |
| ランキング | 平均評価トップ10 |

#### 応用フェーズ
| 機能 | 詳細 |
|------|------|
| 高度な検索 | キーワード、ジャンル、ソート（新着/古い順/タイトル/評価） |
| CSVエクスポート | StreamedResponseでメモリ効率的に出力 |
| ISBN検索 | Google Books API連携で自動入力 |
| 読書レポート | 統計（総レビュー数、平均評価、評価分布、ジャンル傾向） |
| 公開API | GET /api/v1/books, GET /api/v1/books/{id} |

### DB設計（7テーブル）
- **users**: 標準
- **books**: user_id(FK), title, author, isbn(unique), published_date, description, image_url
- **genres**: name(unique)
- **book_genre**: book_id(FK), genre_id(FK) ※多対多ピボット
- **reviews**: user_id(FK), book_id(FK), rating(1-5), comment ※ユニーク制約
- **favorites**: user_id(FK), book_id(FK) ※ユニーク制約
- **review_likes**: user_id(FK), review_id(FK) ※ユニーク制約

### コード品質
- Policy（BookPolicy, ReviewPolicy）による認可
- FormRequest 6つ（Store/Update × Book/Review/Genre）
- Eager Loading（N+1防止）
- テスト: 6 Featureテスト + 1 Unitテスト（基本60%/応用80%カバレッジ目標）
- PSR-12準拠、Laravel Pint
- 型宣言、PHPDocコメント（応用フェーズ）

---

## 比較まとめ

| 観点 | 確認テスト(ContactForm) | 模擬案件(BookShelf) | 4つ目の模擬案件(今回) |
|------|----------------------|--------------------|--------------------|
| DBテーブル数 | 2 (+users) | 7 (+users) | TBD |
| モデル数 | 2 | 6 | TBD |
| コントローラ数 | 4 | 8 | TBD |
| ビュー数 | 5-8 | 30+ | TBD |
| リレーション | 1対多のみ | 1対多 + 多対多 | TBD |
| 認証 | あり | あり | TBD |
| 認可(Policy) | なし | あり | TBD |
| テスト | なし | あり(14+) | TBD |
| 外部API | なし | Google Books API | TBD |
| データエクスポート | なし | CSV | TBD |
| 複雑度 | ★★☆☆☆ | ★★★★☆ | TBD |
| 形式 | ゼロから新規作成 | ゼロから新規作成 | 既存プロジェクト参画 |

## 4つ目の模擬案件で差別化すべきポイント
1. **形式**: ゼロからではなく既存プロジェクトへの参画（バグフィックス・新機能・リファクタ）
2. **ヒアリングスキル**: PMへの要件確認プロセス
3. **既存コード読解力**: 他人が書いたコードを理解する力
4. **実務的なスキル**: 1〜3で学んだ技術の実践的な応用
5. **難易度**: BookShelfと同等以上のコードベース規模が必要
