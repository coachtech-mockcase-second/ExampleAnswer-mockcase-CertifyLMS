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
