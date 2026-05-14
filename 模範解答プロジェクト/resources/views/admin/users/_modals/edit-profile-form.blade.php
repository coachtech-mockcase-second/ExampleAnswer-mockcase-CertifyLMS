<x-modal id="edit-profile-modal" title="プロフィール編集" size="md">
    <form method="POST" action="{{ route('admin.users.update', $user) }}" id="edit-profile-form" class="space-y-4">
        @csrf
        @method('PATCH')

        <x-form.input
            name="name"
            label="お名前"
            :value="old('name', $user->name)"
            :required="true"
            maxlength="50"
        />

        <x-form.input
            name="email"
            label="メールアドレス"
            type="email"
            :value="old('email', $user->email)"
            :required="true"
            autocomplete="off"
        />

        <x-form.textarea
            name="bio"
            label="自己紹介"
            :value="old('bio', $user->bio)"
            :rows="4"
            :maxlength="1000"
        />

        <x-form.input
            name="avatar_url"
            label="プロフィール画像 URL"
            type="url"
            :value="old('avatar_url', $user->avatar_url)"
            placeholder="https://..."
            maxlength="500"
        />
    </form>

    <x-slot:footer>
        <x-button variant="ghost" data-modal-close="edit-profile-modal" type="button">キャンセル</x-button>
        <x-button type="submit" form="edit-profile-form" variant="primary">
            <x-icon name="check" class="w-4 h-4" />
            変更を保存
        </x-button>
    </x-slot:footer>
</x-modal>
