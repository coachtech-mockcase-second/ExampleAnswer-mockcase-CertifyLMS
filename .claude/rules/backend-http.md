---
paths:
  - "提供プロジェクト/app/Http/**"
  - "提供プロジェクト/routes/**"
  - "模範解答プロジェクト/app/Http/**"
  - "模範解答プロジェクト/routes/**"
---

# HTTP 層規約（Controller / FormRequest / Route / Resource / Middleware）

## Controller — 薄く保つ

- リクエスト受付 → バリデーション・認可は他層へ委譲 → Action / Service 呼び出し → レスポンス整形
- **メソッド内のビジネスロジックは原則 0行**。Controller に if 文や計算が増えたら Service / Action に移す
- 命名: `{Entity}Controller`、リソースコントローラパターン推奨（`index/show/store/update/destroy`）
- 配置: `app/Http/Controllers/{Entity}Controller.php`、ロール別 namespace は使わない（Policy で分岐）
- **認可は Controller で実施**（`$this->authorize()` または FormRequest の `authorize()`）。Action 内では呼ばない
- **1 Controller method = 1 Action**。Controller method 名と Action クラス名は一致させる（`update()` → `UpdateAction`、`submit()` → `SubmitAction`）。`backend-usecases.md` 参照

```php
namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\MockExamSession;
use App\UseCases\Enrollment\IndexAction;
use App\UseCases\Enrollment\ShowAction;
use App\UseCases\Enrollment\StoreAction;
use App\UseCases\Enrollment\UpdateAction;
use App\UseCases\Enrollment\DestroyAction;
use App\UseCases\MockExam\SubmitAction;
use App\Http\Requests\Enrollment\StoreRequest;
use App\Http\Requests\Enrollment\UpdateRequest;
use App\Http\Requests\MockExam\SubmitRequest;

class EnrollmentController extends Controller
{
    public function index(IndexAction $action)
    {
        return view('enrollments.index', ['enrollments' => $action(auth()->user())]);
    }

    public function show(Enrollment $enrollment, ShowAction $action)
    {
        $this->authorize('view', $enrollment);
        return view('enrollments.show', ['enrollment' => $action($enrollment)]);
    }

    public function store(StoreRequest $request, StoreAction $action)
    {
        $enrollment = $action(auth()->user(), $request->validated());
        return redirect()->route('enrollments.show', $enrollment);
    }

    public function update(Enrollment $enrollment, UpdateRequest $request, UpdateAction $action)
    {
        $this->authorize('update', $enrollment);  // ← Policy はここで
        $action($enrollment, $request->validated());
        return redirect()->route('enrollments.show', $enrollment);
    }

    public function destroy(Enrollment $enrollment, DestroyAction $action)
    {
        $this->authorize('delete', $enrollment);
        $action($enrollment);
        return redirect()->route('enrollments.index');
    }

    // カスタム業務操作も同じ規則: メソッド名 = Action クラス名
    public function submit(MockExamSession $session, SubmitRequest $request, SubmitAction $action)
    {
        $this->authorize('submit', $session);
        $action($session, $request->validated());
        return redirect()->route('mock-exams.show', $session);
    }
}
```

## FormRequest — バリデーション + 認可

- `app/Http/Requests/{Entity}/{Action}Request.php` に配置（例: `StoreRequest.php`）
- `rules()` でバリデーション、`authorize()` で実行可否
- メッセージは `messages()` / `attributes()` で日本語化
- Controller では `$request->validated()` のみ使用

```php
class StoreRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()->can('create', Enrollment::class); }
    public function rules(): array {
        return [
            'certification_id' => ['required', 'ulid', 'exists:certifications,id'],
            'exam_date' => ['required', 'date', 'after:today'],
        ];
    }
}
```

## Route

- `routes/web.php` に画面遷移ルート、`routes/api.php` に Sanctum API
- リソースルート優先（`Route::resource()`）
- ミドルウェアでロール分岐: `Route::middleware(['auth', 'role:coach'])->group(...)`
- ルート名は `{entity}.{action}` 形式（例: `enrollments.index`）

## Resource — 公開API用レスポンス整形

- `app/Http/Resources/{Entity}Resource.php`
- API のみで使う（Blade では Model 直接渡しでOK）
- Eager Loading 前提（N+1 注意）

## Middleware

- ロール存在確認のみ: `EnsureUserRole`（例: `role:coach` で `auth()->user()->role === 'coach'` を確認）
- リソース固有認可は Policy 側で実装（Middleware に詰め込まない）
