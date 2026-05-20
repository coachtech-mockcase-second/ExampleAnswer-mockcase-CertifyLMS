@php
    use App\Enums\AdminAnnouncementTargetType;

    $selectedType = old('target_type', AdminAnnouncementTargetType::AllStudents->value);
@endphp

<x-form.fieldset legend="配信対象">
    <div class="space-y-3" data-announcement-target-fields>
        @foreach (AdminAnnouncementTargetType::cases() as $type)
            <label class="flex items-start gap-3 rounded-md border border-[var(--border-subtle)] p-3 cursor-pointer hover:bg-ink-50 transition-colors has-[:checked]:border-primary-300 has-[:checked]:bg-primary-50/40">
                <input
                    type="radio"
                    name="target_type"
                    value="{{ $type->value }}"
                    {{ $selectedType === $type->value ? 'checked' : '' }}
                    data-announcement-target-radio
                    class="mt-0.5 h-4 w-4 border-ink-300 text-primary-600 focus:ring-primary-500"
                >
                <span class="text-sm">
                    <span class="block font-semibold text-ink-900">{{ $type->label() }}</span>
                    <span class="block text-xs text-ink-600">
                        @switch($type)
                            @case(AdminAnnouncementTargetType::AllStudents)
                                受講中の全受講生に配信します。
                                @break
                            @case(AdminAnnouncementTargetType::Certification)
                                指定資格に受講登録している受講生のみに配信します。
                                @break
                            @case(AdminAnnouncementTargetType::User)
                                指定したユーザー 1 名のみに配信します。
                                @break
                        @endswitch
                    </span>
                </span>
            </label>
        @endforeach

        <div
            data-announcement-target-panel="{{ AdminAnnouncementTargetType::Certification->value }}"
            @class([
                'pl-2 hidden' => $selectedType !== AdminAnnouncementTargetType::Certification->value,
                'pl-2' => $selectedType === AdminAnnouncementTargetType::Certification->value,
            ])
        >
            <x-form.select
                name="target_certification_id"
                label="対象資格"
                :options="$certifications->pluck('name', 'id')->all()"
                :value="old('target_certification_id')"
                :error="$errors->first('target_certification_id')"
                placeholder="資格を選択してください"
            />
        </div>

        <div
            data-announcement-target-panel="{{ AdminAnnouncementTargetType::User->value }}"
            @class([
                'pl-2 hidden' => $selectedType !== AdminAnnouncementTargetType::User->value,
                'pl-2' => $selectedType === AdminAnnouncementTargetType::User->value,
            ])
        >
            <x-form.select
                name="target_user_id"
                label="対象受講生"
                :options="$students->mapWithKeys(fn ($u) => [$u->id => $u->name.' ('.$u->email.')'])->all()"
                :value="old('target_user_id')"
                :error="$errors->first('target_user_id')"
                placeholder="受講生を選択してください"
            />
        </div>
    </div>
</x-form.fieldset>
