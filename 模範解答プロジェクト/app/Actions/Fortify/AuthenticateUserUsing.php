<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Fortify;

/**
 * Fortify 公式パターンの Action(`Fortify::authenticateUsing()` に登録するコールバックを Action クラス化)。
 * 本プロジェクトの `App\UseCases\{Entity}\{Action}Action` とは別物で、Fortify のログイン認証フローから呼ばれる例外領域。
 *
 * 認証通過条件は次の AND を満たすこと:
 * - email で User が一意に引ける
 * - `status` が `in_progress` または `graduated` (graduated もログインのみは可、プラン機能は EnsureActiveLearning で別途ロック)
 * - パスワードが NOT NULL かつ Hash::check に通る
 *
 * `invited` / `withdrawn` ユーザーは存在しても認証通過させない(呼出元の Fortify が「認証情報が正しくありません」共通エラーに変換するため、
 * status の存在を呼出側に漏洩しない)。
 */
final class AuthenticateUserUsing
{
    public function __invoke(Request $request): ?User
    {
        $user = User::where('email', $request->input(Fortify::username()))->first();

        if ($user === null || $user->password === null) {
            return null;
        }

        $loggableStatuses = [UserStatus::InProgress, UserStatus::Graduated];

        if (! in_array($user->status, $loggableStatuses, true)) {
            return null;
        }

        if (! Hash::check($request->input('password'), $user->password)) {
            return null;
        }

        return $user;
    }
}
