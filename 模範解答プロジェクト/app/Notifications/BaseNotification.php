<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * 本 LMS の全 Notification クラスが継承する基底クラス。
 *
 * 共通責務:
 * - id を ULID で事前確定 (時系列ソート + DatabaseNotification の主キーで再利用)
 * - 配信チャネルを `database` + `mail` + `broadcast` の 3 系で固定 (broadcast は driver 有効時のみ追加)
 * - キュー化 (ShouldQueue) で Mail / Broadcast を非同期に逃がし、発火元 HTTP リクエストの応答を遮らない
 * - Mail のみ専用キュー名 `notifications` に振り、他ジョブと混ぜない
 *
 * 配信先・本文・data の組立はサブクラスで `toDatabase` / `toMail` / `broadcastOn` / `broadcastWith` を実装する。
 */
abstract class BaseNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->id = (string) Str::ulid();
    }

    /**
     * デフォルトの配信チャネルは database + mail + broadcast の 3 軸固定。
     * Broadcasting driver が `null` のとき (テスト / ローカル無効化) は Laravel が broadcast チャネルを no-op 扱いする。
     * 個別の Notification が mail を抑制したい場合 (chat のコーチ間 DB only など) はサブクラスで上書きする。
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail', 'broadcast'];
    }

    /**
     * mail チャネルだけは notifications キューに分離し、他ジョブと混ぜない。
     *
     * @return array<string, string>
     */
    public function viaQueues(): array
    {
        return [
            'mail' => 'notifications',
        ];
    }
}
