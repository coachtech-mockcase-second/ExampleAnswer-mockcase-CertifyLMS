# 教材（pj-ct-newtext）スコープ分析

## 基本要件の範囲（教材で教えている内容）

### Laravel機能
- Controllers（基本CRUD）
- Models & Eloquent ORM
- Migrations（外部キー含む）
- Relationships: hasMany, belongsTo, belongsToMany
- Validation（FormRequest、カスタムメッセージ）
- Middleware（auth, guest, カスタム）
- Authentication（Laravel Fortify）
- Authorization / Policies（Gate、@can）
- Blade テンプレート（継承、コンポーネント）
- Seeders & Factories

### データベース
- マイグレーション（外部キー）
- リレーション: 1対多、多対多
- **Eager Loading / N+1問題**: 詳しくカバー（with, load, loadMissing, withCount）
- ソフトデリート
- トランザクション（概念レベル）
- Mass Assignment ($fillable)

### テスト
- PHPUnit
- Feature Tests（HTTPリクエストテスト）
- Unit Tests
- RefreshDatabase
- assertStatus, assertJson, assertDatabaseHas, actingAs

### コレクションメソッド
- map, filter, sortBy/sortByDesc, groupBy
- sum, avg, count, pluck
- first, last, isEmpty/isNotEmpty
- メソッドチェーン

### API開発
- RESTful API原則
- HTTPメソッド（GET, POST, PUT, DELETE）
- ステータスコード（200, 201, 204, 400, 404, 422, 500）
- JSONレスポンス
- CRUD API実装
- CORS

### セキュリティ
- CSRF保護
- SQLインジェクション防止（パラメータバインディング）
- XSS対策（Bladeエスケープ）
- パスワードハッシュ

### Git/GitHub
- ブランチ戦略
- マージ・コンフリクト解決
- Issue駆動開発
- PR・コードレビュー

---

## 応用要件の範囲（教材で教えていない内容）

### 明確に範囲外
- **Sanctum API認証**: 概念のみ教えている、実装は範囲外（教材に明記あり）
- **外部API連携の実装**: 概念のみ
- **ポリモーフィックリレーション**
- **Has-many-through**
- **高度なクエリ最適化**（N+1以外）
- **DBインデックス戦略**
- **キャッシュ戦略**
- **スロークエリ最適化**
- **モッキング・スタブ（高度なテスト）**
- **ブラウザテスト（Dusk）**
- **キュー・ジョブ**
- **WebSocket / Broadcasting**
- **ファイルストレージ**
- **メール送信**
- **OAuth / OpenID Connect**
- **レート制限（詳細実装）**

### 判定基準
- 教材で「実装まで」教えている → 基本要件
- 教材で「概念のみ」教えている → 応用要件（ただし学生に概念の理解があるため取り組みやすい）
- 教材で「触れていない」 → 応用要件（ヒントや参考リンクが必要）
