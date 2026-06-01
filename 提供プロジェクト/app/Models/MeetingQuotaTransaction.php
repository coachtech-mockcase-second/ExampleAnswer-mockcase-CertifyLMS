<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MeetingQuotaTransactionType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 面談回数の付与・消費の監査ログ。INSERT only(SoftDelete 不採用)で不可逆履歴として保持する。
 *
 * type ごとの amount 符号:
 *   - granted_initial / purchased / refunded / admin_grant: 正値(付与)
 *   - consumed: 負値(消費、原則 -1)
 *
 * 残数集計は `User.max_meetings + SUM(amount WHERE type != granted_initial)`(`granted_initial` は
 * `User.max_meetings` カラムと二重カウントしないよう除外)。詳細は `App\Services\MeetingQuotaService::remaining` 参照。
 *
 * 関連: User(残数所有者) / Meeting(消費・返却時) / Payment(購入時) / User(granted_by_user_id、管理者付与時)
 */
class MeetingQuotaTransaction extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'related_meeting_id',
        'related_payment_id',
        'granted_by_user_id',
        'note',
        'occurred_at',
    ];

    protected $casts = [
        'type' => MeetingQuotaTransactionType::class,
        'amount' => 'integer',
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
     * 面談予約(Meeting)モデルは面談予約 Feature 所有。本 Feature 単体で migrate:fresh する場合は
     * Meeting クラスが未定義となるが、`belongsTo` の class 解決は relation 実行時まで遅延されるため、
     * 関連レコードを Eager Loading しない限り問題ない。
     */
    public function relatedMeeting(): BelongsTo
    {
        /** @var class-string<Model> $meetingClass */
        $meetingClass = 'App\\Models\\Meeting';

        return $this->belongsTo($meetingClass, 'related_meeting_id');
    }

    /**
     * 決済(Payment)モデルは追加面談購入 Feature 所有。本 Feature 単体で migrate:fresh する場合は
     * Payment クラスが未定義となるが、`belongsTo` の class 解決は relation 実行時まで遅延されるため、
     * 関連レコードを Eager Loading しない限り問題ない。
     */
    public function relatedPayment(): BelongsTo
    {
        /** @var class-string<Model> $paymentClass */
        $paymentClass = 'App\\Models\\Payment';

        return $this->belongsTo($paymentClass, 'related_payment_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }
}
