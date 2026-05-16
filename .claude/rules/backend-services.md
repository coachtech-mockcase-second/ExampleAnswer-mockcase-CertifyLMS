---
paths:
  - "提供プロジェクト/app/Services/**"
  - "模範解答プロジェクト/app/Services/**"
---

# Service 層規約

## いつ作るか

- 複数 Action / Controller から **共有される計算ロジック**
- 単独の業務操作ではないが、まとめてメンテしたいロジック
- 例: 進捗率の再計算、スコア集計、弱点ヒートマップ生成

Action との違いは `backend-usecases.md` の比較表を参照。

## 命名・配置

- 配置: `app/Services/{Feature}Service.php`
- 命名: `{Feature}Service`（名詞 + Service）
- 例: `ProgressService`, `ScoreService`, `WeaknessAnalysisService`, `TermJudgementService`

## テンプレート

```php
<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Enums\TermType;

class TermJudgementService
{
    public function recalculate(Enrollment $enrollment): TermType
    {
        $hasActiveMock = $enrollment->mockExamSessions()
            ->whereIn('status', ['in_progress', 'submitted', 'graded'])
            ->exists();

        $newTerm = $hasActiveMock ? TermType::MockPractice : TermType::BasicLearning;

        if ($enrollment->current_term !== $newTerm) {
            $enrollment->update(['current_term' => $newTerm]);
        }

        return $newTerm;
    }
}
```

## 必須事項

- 公開メソッドは戻り値の型宣言必須
- 状態を持たない（プロパティはコンストラクタで注入された依存のみ）
- 外部API 呼び出しは Service ではなく Repository に委譲
- DB トランザクションが必要な箇所は Action 側で囲む（Service 単体では原則使わない）

## テスト

- `tests/Unit/Services/{Feature}ServiceTest.php`
- 計算ロジックを純粋関数的にテスト
- DB 依存がある場合は `RefreshDatabase` + ファクトリで前提データ構築

## Interface 採用判断指針（YAGNI と DIP の境界）

Service クラスに Interface（Contract）を切るかどうかは、**Feature 横断時のみ採用** を原則とする。同 Feature 内で完結する Service は具象クラス直接 DI で十分。

### Interface を切る判断軸

| 状況 | Interface 採用 |
|---|---|
| **複数 Feature から呼ばれ、正規実装が別 Feature にある** | ✅ 採用（例: `WeaknessAnalysisServiceContract` を quiz-answering が定義、mock-exam が正規実装、NullObject フォールバックを quiz-answering が登録） |
| **テストで mock したいが、`Http::fake` / `Mail::fake` 等の Laravel 標準 fake で対応可能** | ❌ 不採用（具象クラス + fake で十分） |
| **同 Feature 内で完結する Service**（`MarkdownRenderingService` / `CertificateSerialNumberService` 等）| ❌ 不採用（過剰抽象、YAGNI） |
| **外部 API を叩く Service**（Gemini / Google Calendar / Pusher 等）| ✅ 採用（Repository パターンとして、`backend-repositories.md` 参照） |

### 採用時の配置

- Interface: `app/Services/Contracts/{Service}Contract.php`
- 正規実装: 所有 Feature の `app/Services/{Service}.php`
- フォールバック実装（NullObject 等）: 依存 Feature の `app/Services/Null{Service}.php`
- `ServiceProvider::register()` で `bindIf($contract, $primaryImpl)` + `bindIf($contract, $nullImpl)` の 2 段登録

### 採用しない理由（教材として）

- **Interface の存在自体が複雑度コスト**。`backend-usecases.md` の「過剰抽象を避ける」原則と整合
- 受講生が「Service を作るたびに Interface も切るのか」と誤学習するのを防ぐ
- Pro 生レベルとして「Interface はいつ切るか」の判断軸を明確に学ぶ

### `WeaknessAnalysisServiceContract` のパターン（実例）

```php
// quiz-answering が Interface を所有
namespace App\Services\Contracts;
interface WeaknessAnalysisServiceContract
{
    public function getWeakCategories(Enrollment $enrollment): Collection;
}

// quiz-answering が NullObject フォールバックを所有
namespace App\Services;
final class NullWeaknessAnalysisService implements WeaknessAnalysisServiceContract
{
    public function getWeakCategories(Enrollment $enrollment): Collection
    {
        return collect();
    }
}

// quiz-answering の ServiceProvider で bindIf
$this->app->bindIf(
    WeaknessAnalysisServiceContract::class,
    NullWeaknessAnalysisService::class,
);

// mock-exam が正規実装で上書き bind
$this->app->bind(
    WeaknessAnalysisServiceContract::class,
    WeaknessAnalysisService::class,
);
```

これにより mock-exam 未実装環境でも UI が破綻しない。
