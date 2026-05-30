{{--
    お知らせ配信フォームの「配信対象」入力部品（create フォームから @include）。
    構成: 配信対象タイプのラジオ群（各タイプの説明文付き）→ 区切り線 → 対象資格セレクト + 対象受講生セレクト。
    対象を絞る 2 つのセレクトは常時表示で、選んだタイプに対応する欄だけを入力する想定（表示切替の JS は持たない）。
--}}
@php
    use App\Enums\AnnouncementTargetType;

    $selectedType = old('target_type', AnnouncementTargetType::AllStudents->value);
@endphp

<x-form.fieldset legend="配信対象">
    <div class="space-y-3">
        @foreach (AnnouncementTargetType::cases() as $type)
            <label class="flex items-start gap-3 rounded-md border border-subtle p-3 cursor-pointer hover:bg-ink-50 transition-colors has-[:checked]:border-primary-300 has-[:checked]:bg-primary-50/40">
                <input
                    type="radio"
                    name="target_type"
                    value="{{ $type->value }}"
                    {{ $selectedType === $type->value ? 'checked' : '' }}
                    class="mt-0.5 h-4 w-4 border-ink-300 text-primary-600 focus:ring-primary-500"
                >
                <span class="text-sm">
                    <span class="block font-semibold text-ink-900">{{ $type->label() }}</span>
                    <span class="block text-xs text-ink-600">
                        @switch($type)
                            @case(AnnouncementTargetType::AllStudents)
                                受講中の全受講生に配信します。
                                @break
                            @case(AnnouncementTargetType::Certification)
                                指定資格に受講登録している受講生のみに配信します。下の「対象資格」を選択してください。
                                @break
                            @case(AnnouncementTargetType::User)
                                指定したユーザー 1 名のみに配信します。下の「対象受講生」を選択してください。
                                @break
                        @endswitch
                    </span>
                </span>
            </label>
        @endforeach
    </div>

    {{-- 配信対象タイプに応じて使う入力欄（常時表示。選んだタイプに対応する欄のみ入力する）。
         表示切替の JS は持たない。 --}}
    <div class="mt-4 space-y-4 border-t border-subtle pt-4">
        <x-form.select
            name="target_certification_id"
            label="対象資格（配信対象タイプが「資格指定」の場合）"
            :options="$certifications->pluck('name', 'id')->all()"
            :value="old('target_certification_id')"
            :error="$errors->first('target_certification_id')"
            placeholder="資格を選択してください"
        />

        <x-form.select
            name="target_user_id"
            label="対象受講生（配信対象タイプが「ユーザー指定」の場合）"
            :options="$students->mapWithKeys(fn ($u) => [$u->id => $u->name.' ('.$u->email.')'])->all()"
            :value="old('target_user_id')"
            :error="$errors->first('target_user_id')"
            placeholder="受講生を選択してください"
        />
    </div>
</x-form.fieldset>
