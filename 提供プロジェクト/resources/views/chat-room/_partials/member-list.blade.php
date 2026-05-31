{{--
    chat ルームの参加メンバー一覧カード partial。props 相当: room, coaches。
    構成: カード(ヘッダ「参加メンバー」) → メンバー行の繰り返し(アバター + 名前 + ロール) → 担当コーチ未割当時の注意ブロック。
    JS なし。
--}}
<x-card>
    <x-slot:header>参加メンバー</x-slot:header>

    @php
        $members = $room->members->loadMissing('user');
    @endphp

    <ul class="space-y-3">
        @foreach ($members as $member)
            @php
                $user = $member->user;
                if ($user === null) { continue; }
                $roleLabel = $user->role?->label() ?? '';
            @endphp
            <li class="flex items-center gap-3">
                <x-avatar :src="$user->avatar_url" :name="$user->name" size="sm" />
                <div class="min-w-0">
                    <div class="text-sm font-medium text-ink-900 truncate">{{ $user->name }}</div>
                    <div class="text-[11px] text-ink-500">{{ $roleLabel }}</div>
                </div>
            </li>
        @endforeach
    </ul>

    @if ($coaches->isEmpty())
        <div class="mt-4 p-3 rounded-xl bg-warning-50 text-warning-800 text-xs leading-relaxed">
            この資格には担当コーチが割り当てられていません。担当コーチが追加されると自動的にこのルームに参加し、メッセージを送れるようになります。
        </div>
    @endif
</x-card>
