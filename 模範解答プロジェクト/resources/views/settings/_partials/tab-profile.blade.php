@php
    use App\Enums\UserRole;
@endphp

<div class="grid gap-5 lg:grid-cols-[1fr_320px]">
    {{-- 左: プロフィール本体フォーム --}}
    <x-card padding="md" shadow="sm">
        <x-slot:header>
            <h2 class="text-sm font-bold text-ink-900">プロフィール情報</h2>
        </x-slot:header>

        <form method="POST" action="{{ route('settings.profile.update') }}" class="space-y-5">
            @csrf
            @method('PATCH')

            <x-form.input
                name="name"
                label="氏名"
                :value="old('name', $user->name)"
                :required="true"
                maxlength="50"
                hint="50 文字以内で入力してください"
            />

            <x-form.input
                name="email"
                label="メールアドレス"
                type="email"
                :value="$user->email"
                :readonly="true"
                hint="メールアドレスの変更は管理者にご依頼ください"
            />

            <x-form.textarea
                name="bio"
                label="自己紹介"
                :value="old('bio', $user->bio)"
                :rows="5"
                :maxlength="1000"
                hint="自分の経歴 / 学習目標 / 興味のある分野などを 1000 文字以内で記入してください"
            />

            @if ($user->role === UserRole::Coach)
                <x-form.input
                    name="meeting_url"
                    label="固定面談 URL"
                    type="url"
                    :value="old('meeting_url', $user->meeting_url)"
                    placeholder="https://meet.google.com/xxx-yyyy-zzz"
                    hint="受講生との面談に使う Web 会議 URL を登録します(Google Meet / Zoom 等)"
                    autocomplete="url"
                />
            @endif

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-[var(--border-subtle)]">
                <x-button type="submit" variant="primary">変更を保存</x-button>
            </div>
        </form>
    </x-card>

    {{-- 右: アバター画像アップロード / 削除 --}}
    <aside class="space-y-5">
        <x-card padding="md" shadow="sm">
            <x-slot:header>
                <h2 class="text-sm font-bold text-ink-900">アイコン画像</h2>
            </x-slot:header>

            <div class="flex flex-col items-center gap-4">
                <x-avatar :src="$user->avatar_url" :name="$user->name" size="xl" />

                <p class="text-xs text-ink-500 text-center">
                    画像を登録しない場合は、氏名のイニシャルが自動表示されます。
                </p>
            </div>

            <form
                method="POST"
                action="{{ route('settings.avatar.store') }}"
                enctype="multipart/form-data"
                class="mt-5 space-y-3"
                data-avatar-form
            >
                @csrf

                <x-form.file
                    name="avatar"
                    label="新しい画像を選ぶ"
                    accept="image/png,image/jpeg,image/webp"
                    :error="$errors->first('avatar')"
                    hint="PNG / JPG / WebP、2MB 以内"
                    data-avatar-input
                />

                <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                    <x-button type="submit" variant="primary" size="sm" data-avatar-submit>
                        アップロード
                    </x-button>
                </div>
            </form>

            @if ($user->avatar_url)
                <form
                    method="POST"
                    action="{{ route('settings.avatar.destroy') }}"
                    class="mt-3 border-t border-[var(--border-subtle)] pt-3"
                    onsubmit="return confirm('アイコン画像を削除します。よろしいですか?');"
                >
                    @csrf
                    @method('DELETE')
                    <x-button type="submit" variant="ghost" size="sm" class="w-full">
                        画像を削除する
                    </x-button>
                </form>
            @endif
        </x-card>
    </aside>
</div>
