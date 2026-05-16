<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Fortify 公式パターンの Action（`Laravel\Fortify\Contracts\CreatesNewUsers` 実装）。
 * **本プロジェクトの `App\UseCases\{Entity}\{Action}Action` とは別物**で、Fortify 固有の認証フロー（登録 / オンボーディング）から呼ばれる例外領域。
 *
 * 詳細は `.claude/rules/backend-usecases.md` の「Fortify Action と UseCase Action の名前空間衝突」セクション参照。
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param array<string, string> $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
        ]);
    }
}
