<?php

declare(strict_types=1);

namespace App\Http\Requests\Invitation;

use App\Models\Invitation;
use Illuminate\Foundation\Http\FormRequest;

class ResendRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Invitation::class);
    }

    public function rules(): array
    {
        return [];
    }
}
