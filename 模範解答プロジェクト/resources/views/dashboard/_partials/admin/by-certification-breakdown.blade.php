{{--
    管理者ダッシュボードの資格別 受講中人数カード。
    構成: 見出し(件数 + 資格マスタへのリンク) → 空文 or 行リスト(資格名リンク + 修了/学習中止の補足 + 受講中人数バー + 人数)
    props: rows（資格ごとの内訳の行）
--}}
@props([
    'rows',
])

<x-card padding="md">
    <div class="flex items-baseline gap-2 mb-3">
        <h2 class="text-base font-bold text-ink-900 flex items-center gap-2">
            <x-icon name="academic-cap" class="w-4 h-4 text-ink-600" />
            資格別 受講中人数
        </h2>
        <span class="text-xs text-ink-500">上位 {{ $rows->count() }} 件</span>
        <span class="flex-1"></span>
        <a href="{{ route('admin.certifications.index') }}" class="text-xs text-primary-700 hover:underline">
            資格マスタ &rarr;
        </a>
    </div>

    @if ($rows->isEmpty())
        <p class="text-sm text-ink-500 py-2">受講中の資格はまだありません。</p>
    @else
        @php
            $maxLearning = max(1, $rows->max(fn (array $row): int => $row['learning']));
        @endphp
        <ul class="flex flex-col">
            @foreach ($rows as $row)
                @php
                    $widthPct = (int) round($row['learning'] / $maxLearning * 100);
                @endphp
                <li class="grid items-center gap-2.5 py-2.5 border-b border-subtle last:border-b-0"
                    style="grid-template-columns: 1fr auto auto;">
                    <div>
                        <a href="{{ route('admin.certifications.show', $row['certification_id']) }}"
                           class="text-sm font-semibold text-ink-900 hover:underline truncate block">
                            {{ $row['certification_name'] }}
                        </a>
                        <p class="text-[11px] text-ink-500 mt-0.5">
                            修了 {{ $row['passed'] }} / 学習中止 {{ $row['failed'] }}
                        </p>
                    </div>
                    <div class="w-44 h-2 bg-ink-100 rounded-full overflow-hidden">
                        <div class="h-full bg-primary-500 rounded-full" style="width: {{ $widthPct }}%"></div>
                    </div>
                    <div class="font-display text-lg font-bold tabular-nums tracking-tight text-ink-900 text-right min-w-[40px]">
                        {{ $row['learning'] }}
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
