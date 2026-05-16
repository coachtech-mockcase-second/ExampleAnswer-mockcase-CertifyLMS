<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

/**
 * Fortify 公式パターンの Action（`Laravel\Fortify\Contracts\UpdatesUserPasswords` 実装）。
 * **本プロジェクトの `App\UseCases\{Entity}\{Action}Action` とは別物**で、Fortify 固有のパスワード更新フロー（ログイン中ユーザーが自分のパスワードを変更）から呼ばれる例外領域。
 *
 * 詳細は `.claude/rules/backend-usecases.md` の「Fortify Action と UseCase Action の名前空間衝突」セクション参照。
 */
class UpdateUserPassword implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and update the user's password.
     *
     * @param array<string, string> $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => $this->passwordRules(),
        ], [
            'current_password.current_password' => __('The provided password does not match your current password.'),
        ])->validateWithBag('updatePassword');

        $user->forceFill([
            'password' => Hash::make($input['password']),
        ])->save();
    }
}
