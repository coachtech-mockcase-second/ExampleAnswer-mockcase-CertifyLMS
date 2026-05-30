{{--
    資格詳細画面の直近修了証カード partial。
    構成: ヘッダ(見出し + 発行件数) → 修了証行リスト(受講者アバター + 名前 / 通番 + 発行日 + PDF ダウンロードリンク、0 件時はプレースホルダ)
--}}
<x-card padding="lg" shadow="sm">
    <div class="flex items-center justify-between gap-2">
        <div>
            <h2 class="text-base font-semibold text-ink-900">直近の修了証</h2>
            <p class="text-xs text-ink-500 mt-1">発行済 {{ $certification->certificates_count ?? $certification->certificates->count() }} 件 / 最新 10 件を表示</p>
        </div>
    </div>

    @if ($certification->certificates->isEmpty())
        <div class="mt-6 text-sm text-ink-500 text-center py-6">
            まだ修了証は発行されていません。
        </div>
    @else
        <ul class="mt-4 divide-y divide-subtle">
            @foreach ($certification->certificates as $cert)
                <li class="flex items-center justify-between gap-3 py-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <x-avatar :src="$cert->user?->avatar_url" :name="$cert->user?->name ?? '?'" size="sm" />
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-ink-900 truncate">{{ $cert->user?->name ?? '(退会済)' }}</div>
                            <div class="text-xs text-ink-500 font-mono">{{ $cert->serial_no }}</div>
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <div class="text-xs text-ink-500 font-mono tabular-nums">{{ $cert->issued_at?->format('Y-m-d') }}</div>
                        <a href="{{ route('certificates.download', $cert) }}" class="text-xs text-primary-700 font-semibold hover:underline">
                            PDF をダウンロード
                        </a>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
