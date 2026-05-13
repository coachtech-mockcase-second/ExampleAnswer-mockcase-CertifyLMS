---
paths:
  - "提供プロジェクト/app/Policies/**"
  - "模範解答プロジェクト/app/Policies/**"
---

# Policy 規約（リソース固有認可）

## 役割分担

| 層 | 役割 |
|---|---|
| **Middleware**（`EnsureUserRole`）| **ロール存在確認のみ**（`auth()->user()->role === 'coach'` 等） |
| **Policy** | **リソース固有認可**（「コーチは担当資格のみ」「受講生は自分のリソースのみ」等） |
| FormRequest `authorize()` | Policy を呼ぶラッパー |

## 命名・配置

- 配置: `app/Policies/{Entity}Policy.php`
- 命名: `{Entity}Policy`
- `AuthServiceProvider` の `$policies` で Model と紐付け（または自動検出に任せる）

## テンプレート

```php
<?php

namespace App\Policies;

use App\Models\Enrollment;
use App\Models\User;
use App\Enums\UserRole;

class EnrollmentPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Coach, UserRole::Student]);
    }

    public function view(User $user, Enrollment $enrollment): bool
    {
        return match ($user->role) {
            UserRole::Admin => true,
            UserRole::Coach => $enrollment->assigned_coach_id === $user->id,
            UserRole::Student => $enrollment->user_id === $user->id,
        };
    }

    public function update(User $user, Enrollment $enrollment): bool
    {
        return $user->role === UserRole::Admin
            || ($user->role === UserRole::Student && $enrollment->user_id === $user->id);
    }
}
```

## 呼び出し方

```php
// Controller
$this->authorize('update', $enrollment);

// Blade
@can('update', $enrollment) <button>編集</button> @endcan

// FormRequest
public function authorize(): bool
{
    return $this->user()->can('update', $this->route('enrollment'));
}
```

## 必須事項

- 各 Policy の判定ロジックは **ロール別の `match`** で読みやすく
- `UserRole` Enum を活用（マジック文字列禁止）
- `before()` メソッドで admin 全権バイパスを実装するのも可

## スコープ制御の典型例

| Entity | 認可ルール |
|---|---|
| `Certification` | admin: 全資格 / coach: 担当資格のみ / student: 登録資格のみ閲覧 |
| `Question` | admin: 全 / coach: 担当資格内のみCRUD / student: 登録資格内のみ閲覧 |
| `Enrollment` | admin: 全 / coach: 担当受講生のみ / student: 自分のみ |
| `ChatRoom` | admin: 全閲覧 / coach + student: 当事者のみ |

## テスト

- `tests/Unit/Policies/{Entity}PolicyTest.php` または `tests/Feature/Http/{Entity}/*.php` で認可分岐を網羅
- 各ロール × 各操作の真偽値を assert
