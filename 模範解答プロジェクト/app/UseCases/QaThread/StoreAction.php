<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Enums\QaThreadStatus;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 質問スレッド新規投稿ユースケース。投稿者 (student) と入力値からスレッドを INSERT し open 状態で開始する。
 *
 * バリデーション (`title` / `body` の文字数 / 全角空白拒否 / `certification_id` の publish 検証) は
 * FormRequest 側で完結している前提。
 */
final class StoreAction
{
    /**
     * @param array{certification_id: string, title: string, body: string} $validated
     */
    public function __invoke(User $author, array $validated): QaThread
    {
        return DB::transaction(function () use ($author, $validated): QaThread {
            return QaThread::create([
                'certification_id' => $validated['certification_id'],
                'user_id' => $author->id,
                'title' => $validated['title'],
                'body' => $validated['body'],
                'status' => QaThreadStatus::Open,
                'resolved_at' => null,
            ]);
        });
    }
}
