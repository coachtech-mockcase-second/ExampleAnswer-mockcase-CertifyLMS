---
paths:
  - "提供プロジェクト/app/UseCases/**"
  - "模範解答プロジェクト/app/UseCases/**"
---

# UseCase（Action）実装ルール

ビジネスロジックの調停を行う UseCase の実装ルール。COACHTECH LMS 流に **クラス名は `{Action}Action.php`** で統一。

## いつ作るか

- **1業務操作 = 1 Action クラス**
- 複数 Model / Service / Repository を組み合わせる処理
- トランザクション境界が必要な処理
- 単純な CRUD で Controller → Model だけで済む場合は **作らなくて良い**

## ディレクトリ構成

```
app/UseCases/
├── Enrollment/
│   ├── IndexAction.php
│   ├── ShowAction.php
│   ├── StoreAction.php
│   ├── UpdateAction.php
│   ├── DestroyAction.php
│   └── ApproveCompletionAction.php
├── MockExam/
│   ├── StartAction.php
│   ├── SubmitAction.php
│   ├── CancelAction.php
│   └── FetchWeaknessHeatmapAction.php
└── Question/
    └── ...
```

## 命名規則

### 大原則: **Controller メソッド名 = Action クラス名（PascalCase化）**

1 Controller メソッド = 1 Action クラス。Controller メソッドは同名の Action を `__invoke()` で呼ぶだけの薄いラッパー。

| Controller method (camelCase) | Action class (PascalCase + Action) | 用途 |
|---|---|---|
| `index()` | `IndexAction` | 一覧取得（Laravel リソースルート GET /xxx）|
| `show()` | `ShowAction` | 単一取得（GET /xxx/{id}）|
| `store()` | `StoreAction` | 作成（POST /xxx）|
| `update()` | `UpdateAction` | 更新（PUT/PATCH /xxx/{id}）|
| `destroy()` | `DestroyAction` | 削除（DELETE /xxx/{id}）|
| `fetchCoaches()` | `FetchCoachesAction` | カスタム取得（index/show 以外）|
| `submit()` | `SubmitAction` | カスタム業務操作 |
| `approveCompletion()` | `ApproveCompletionAction` | カスタム業務操作 |
| `cancel()` | `CancelAction` | カスタム業務操作 |

### 規約

- クラス名: `{Action}Action.php`
- メソッド名: **`__invoke()`** を主とする（1クラス1責任）
- 配置: `app/UseCases/{Entity}/{Action}Action.php`
- **Controller method 名と Action 名（"Action" 接尾辞を除いた部分）は完全一致** させる

### 利点

- コード navigation が直感的（Controller method を見れば対応 Action がわかる）
- 「1 Controller method = 1 業務操作 = 1 Action」を機械的に保証
- Laravel リソースコントローラの規約と整合

## Action vs Service の違い

| 観点 | Action | Service |
|---|---|---|
| 単位 | 1業務操作（1ユースケース） | 複数 Action から共有される計算 |
| 例 | `SubmitAction`, `ApproveCompletionAction` | `ProgressService`, `ScoreService`, `TermJudgementService` |
| トランザクション | 必要に応じて `DB::transaction()` で囲む | 原則囲まない（呼び出し元の Action 側で管理） |

## Policy / 認可との関係（重要）

- **認可（Policy）は Action 内で呼ばない** — `backend-policies.md` 参照
- 認可は Controller の `$this->authorize()` または FormRequest の `authorize()` で実施
- Action 内では「**データ整合性チェック**」を行う:
  - 例: 「mock-exam が `not_started` 状態でなければキャンセル不可」
  - 例: 「対象 Enrollment が指定 User に所属しているか」
  - 不整合時は具象例外（`{Entity}NotFoundException` 等、`backend-exceptions.md` 参照）

## テンプレート

```php
<?php

namespace App\UseCases\MockExam;

use App\Models\MockExamSession;
use App\Services\ScoreService;
use App\Services\TermJudgementService;
use App\Enums\MockExamSessionStatus;
use App\Exceptions\MockExam\MockExamSessionAlreadyStartedException;
use Illuminate\Support\Facades\DB;

class SubmitAction
{
    public function __construct(
        private ScoreService $scoreService,
        private TermJudgementService $termJudgementService,
    ) {}

    public function __invoke(MockExamSession $session, array $answers): MockExamSession
    {
        // データ整合性チェック（認可とは別、状態ベースのガード）
        if ($session->status !== MockExamSessionStatus::InProgress) {
            throw new MockExamSessionAlreadyStartedException();
        }

        return DB::transaction(function () use ($session, $answers) {
            $session->answers()->createMany($this->normalize($answers));
            $session->update([
                'status' => MockExamSessionStatus::Submitted,
                'submitted_at' => now(),
                'total_score' => $this->scoreService->calculate($session),
            ]);

            $this->termJudgementService->recalculate($session->enrollment);

            return $session->fresh();
        });
    }

    private function normalize(array $answers): array { /* ... */ }
}
```

## 必須事項

- 状態変更を伴う処理は **`DB::transaction()` で囲む**
- 例外は **具象クラス**（`app/Exceptions/{Domain}/*.php`）を throw、汎用 `\Exception` は避ける
- DI は constructor injection
- 戻り値は明示的に型宣言
- **`__invoke()` 推奨**（1クラス1責任）

## ベストプラクティス

- Action は単一のユースケースのみを担当
- 依存は Service / Repository / 他 Action（注意して）/ Model に限定
- Model への直接アクセスは避け、Service / Eloquent スコープ経由を推奨
- トランザクションは Action 内で管理（Service には持たせない）

## テスト

- 配置: `tests/Feature/UseCases/{Entity}/{Action}ActionTest.php`（Action と同じディレクトリ構造）
- 正常系 + 異常系（例外発生 / 整合性不一致）必須
- 詳細は `backend-tests.md` 参照
