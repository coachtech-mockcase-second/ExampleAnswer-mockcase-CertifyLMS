# design.md テンプレート

`docs/specs/{name}/design.md` の構造とフォーマット規約。

## 生成方針

Laravel コミュニティ標準 + `.claude/rules/` の規約を主軸に設計する。COACHTECH LMS は判断に迷う点で必要時のみ参考にし、観察したパターンは **設計内容そのものに織り込む**（design.md 内に「参考実装」セクションは設けない、参考にした場合のみ調査結果のサマリを完了報告で伝える）。

## ファイル構造

```markdown
# {Feature 名} 設計

## アーキテクチャ概要
（Mermaid sequenceDiagram or flowchart）

## データモデル
- Eloquent モデル一覧（structure.md 準拠、ULID + SoftDeletes）
- リレーション図（Mermaid erDiagram）
- 主要カラム + Enum

## 状態遷移
（該当する場合のみ。stateDiagram-v2 単行ラベル、`:` をラベル内で使わない）

## コンポーネント

### Controller
- {Entity}Controller — メソッド一覧（index/show/store/update/destroy + カスタム）

### Action（UseCase）

各 Action は **PHP シグネチャを明示** する。曖昧な振る舞い（force flag、optional behavior）は **必ず引数で表現** し、文章で「呼び出し側が指定する」とぼかさない。Action の責務とトランザクション境界も併記。

```php
// app/UseCases/{Entity}/{Action}Action.php
class {Action}Action
{
    public function __construct(
        private {Dep1} $dep1,
        private {Dep2} $dep2,
    ) {}

    public function __invoke({Type1} $arg1, {Type2} $arg2, bool $force = false): {ReturnType}
    {
        // 整合性チェック → DB::transaction で状態変更 → 戻り値
    }
}
```

- **IndexAction / ShowAction / StoreAction / UpdateAction / DestroyAction**: CRUD 系。Controller method 名と一致
- **{Custom}Action**: 業務操作。`Fetch{Name}Action`（取得系）/ 動詞 + Action（操作系）
- 各 Action 末尾に **責務 1 行 + 例外 1 行** を併記

### Service
- {Feature}Service — 共有計算ロジック（あれば）。公開メソッドはシグネチャを明示

### Policy
- {Entity}Policy — viewAny / view / create / update / delete + カスタム
- 各メソッドのロール別判定ルールを箇条書き

### FormRequest
- StoreRequest / UpdateRequest — バリデーション・認可
- 主要 rule とエラーメッセージの方針

### Resource（API のみ）
- {Entity}Resource

## Blade ビュー
- 画面一覧（index / show / form / etc.）
- 主要コンポーネント

## エラーハンドリング
- 想定例外（app/Exceptions/{Domain}/ 配下）
- 状態整合性違反時の例外
- 列挙攻撃等のセキュリティ配慮（該当時）

## 関連要件マッピング

| 要件ID | 実装ポイント |
|---|---|
| REQ-{name}-001 | {file path / class / method} |
| REQ-{name}-002 | {file path / class / method} |
| NFR-{name}-001 | {file path / config} |
```

**すべての主要要件**（NFR 含む）が表に出現すること。逆引きで未実装の REQ を検出できるようにする。
