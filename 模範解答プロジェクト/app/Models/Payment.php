<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Stripe Checkout Session 経由の決済記録。現状は追加面談購入(type=extra_meeting_quota)のみ扱う。
 *
 * pending(Checkout Session 作成直後) → succeeded(Webhook で確定) または failed / refunded に遷移。
 * stripe_checkout_session_id を UNIQUE 制約しており、Webhook の重複到着時の冪等性ガードとして機能する。
 * 決済完了で MeetingQuotaTransaction(type=purchased) が hasMany 経由で 1 件 INSERT される。
 *
 * 関連: User(購入者) / MeetingQuotaPlan(購入 SKU) / MeetingQuotaTransaction(履歴)
 */
class Payment extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'type',
        'meeting_quota_plan_id',
        'stripe_payment_intent_id',
        'stripe_checkout_session_id',
        'amount',
        'quantity',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'status' => PaymentStatus::class,
        'amount' => 'integer',
        'quantity' => 'integer',
        'paid_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<MeetingQuotaPlan, $this>
     */
    public function meetingQuotaPlan(): BelongsTo
    {
        return $this->belongsTo(MeetingQuotaPlan::class);
    }

    /**
     * @return HasMany<MeetingQuotaTransaction, $this>
     */
    public function meetingQuotaTransactions(): HasMany
    {
        return $this->hasMany(MeetingQuotaTransaction::class, 'related_payment_id');
    }
}
