<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\QaReplyFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 質問掲示板スレッドへの回答を表す Model。
 *
 * 編集 / 削除は本人 + admin に限り許可される。削除は物理削除。投稿者によるスレッド削除は、
 * 回答が 1 件でも残っていれば DestroyAction が QaThreadHasRepliesException (409) で拒否する (admin は無条件削除)。
 *
 * 関連: QaThread / User(回答者)
 */
class QaReply extends Model
{
    /** @use HasFactory<QaReplyFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'qa_thread_id',
        'user_id',
        'body',
    ];

    /**
     * @return BelongsTo<QaThread, $this>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(QaThread::class, 'qa_thread_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
