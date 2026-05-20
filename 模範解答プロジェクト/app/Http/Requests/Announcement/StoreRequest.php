<?php

declare(strict_types=1);

namespace App\Http\Requests\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Models\Announcement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 管理者お知らせ配信 (POST /admin/announcements) のリクエスト検証。
 *
 * target_type に応じて target_certification_id / target_user_id の required を切り替え、
 * 不要なフィールドが付随する場合は prohibited で拒否する (Action 側の整合性検査と二重防御)。
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Announcement::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
            'target_type' => ['required', Rule::enum(AnnouncementTargetType::class)],
            'target_certification_id' => [
                'nullable',
                'ulid',
                'required_if:target_type,'.AnnouncementTargetType::Certification->value,
                'prohibited_unless:target_type,'.AnnouncementTargetType::Certification->value,
                'exists:certifications,id',
            ],
            'target_user_id' => [
                'nullable',
                'ulid',
                'required_if:target_type,'.AnnouncementTargetType::User->value,
                'prohibited_unless:target_type,'.AnnouncementTargetType::User->value,
                'exists:users,id',
            ],
        ];
    }
}
