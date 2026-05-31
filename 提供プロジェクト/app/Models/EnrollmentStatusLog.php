<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EnrollmentStatus;
use Database\Factories\EnrollmentStatusLogFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Enrollment 状態遷移の監査ログ。SoftDelete 非採用(履歴は不可逆)、INSERT only。
 *
 * from_status / to_status で遷移を表現する(event_type カラムは持たない)。
 * 初回登録時は from_status NULL、それ以降の遷移では必須。
 * 集約は EnrollmentStatusChangeService::recordStatusChange に一本化される。
 */
class EnrollmentStatusLog extends Model
{
    /** @use HasFactory<EnrollmentStatusLogFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'enrollment_id',
        'from_status',
        'to_status',
        'changed_by_user_id',
        'changed_at',
        'changed_reason',
    ];

    protected $casts = [
        'from_status' => EnrollmentStatus::class,
        'to_status' => EnrollmentStatus::class,
        'changed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * 操作者。null はシステム自動(Schedule Command など)。
     *
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
