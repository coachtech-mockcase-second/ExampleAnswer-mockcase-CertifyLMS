<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class OnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        // 署名付き URL が認可の担い手。Controller 側で signed middleware が検証済み。
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'お名前',
            'bio' => '自己紹介',
            'password' => 'パスワード',
            'password_confirmation' => 'パスワード（確認）',
        ];
    }
}
