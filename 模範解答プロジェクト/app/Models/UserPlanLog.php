<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserPlanLogEventType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * User の Plan 期間 / 初期付与面談回数 の遷移を記録する append-only 監査ログ。
 *
 * event_type で「割当 / 延長 / キャンセル / 期限満了」を区別。SoftDelete 不採用(履歴は不可逆)。
 * 書込責務は UserPlanLogService::record() に集約。
 *
 * 関連: User / Plan / changedBy(User、システム自動の場合 NULL)
 */
class UserPlanLog extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'plan_id',
        'event_type',
        'plan_started_at',
        'plan_expires_at',
        'meeting_quota_initial',
        'changed_by_user_id',
        'changed_reason',
        'occurred_at',
    ];

    protected $casts = [
        'event_type' => UserPlanLogEventType::class,
        'plan_started_at' => 'datetime',
        'plan_expires_at' => 'datetime',
        'meeting_quota_initial' => 'integer',
        'occurred_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id')->withTrashed();
    }
}
