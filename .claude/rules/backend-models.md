---
paths:
  - "提供プロジェクト/app/Models/**"
  - "提供プロジェクト/app/Enums/**"
  - "提供プロジェクト/database/migrations/**"
  - "提供プロジェクト/database/factories/**"
  - "模範解答プロジェクト/app/Models/**"
  - "模範解答プロジェクト/app/Enums/**"
  - "模範解答プロジェクト/database/migrations/**"
  - "模範解答プロジェクト/database/factories/**"
---

# Eloquent モデル規約

## 必須事項

- 主キーは **ULID**（`use HasUlids`）。URL 安全 + 時系列ソート可
- **`SoftDeletes`** 採用（論理削除）。学習履歴の保持に有用
- `$fillable` 必須（mass assignment 防止）
- `$casts` で型変換明示（datetime / enum / boolean / array）
- リレーションメソッドは複数形 + lower camelCase（`hasMany` / `belongsTo`）
- 再利用クエリは `scope*` メソッドで定義

## モデルテンプレート

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Enums\EnrollmentStatus;

class Enrollment extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'user_id', 'certification_id', 'exam_date', 'status', 'current_term', 'passed_at',
    ];

    protected $casts = [
        'exam_date' => 'date',
        'passed_at' => 'datetime',
        'status' => EnrollmentStatus::class,
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function certification() { return $this->belongsTo(Certification::class); }
    public function goals() { return $this->hasMany(EnrollmentGoal::class); }

    public function scopeLearning($query) { return $query->where('status', EnrollmentStatus::Learning); }
}
```

## マイグレーション規約

- ULID 主キー: `$table->ulid('id')->primary();`
- 外部キー: `$table->foreignUlid('user_id')->constrained()->cascadeOnDelete();`
- `$table->softDeletes();` / `$table->timestamps();` を必ず付ける
- 命名: `create_{table_name}_table` / `add_{column}_to_{table}_table`

## Enum

- 状態は PHP Enum（`backed enum`、string）で表現
- `label()` メソッドで日本語表示名を返す
- 例: `EnrollmentStatus::Learning`, `MockExamSessionStatus::NotStarted`

## ファクトリー

- `database/factories/{Model}Factory.php` に配置
- テスト・シーダー双方で使う前提でリアルな値を返す
- `state()` でバリエーション提供（例: `EnrollmentFactory::new()->passed()`）
