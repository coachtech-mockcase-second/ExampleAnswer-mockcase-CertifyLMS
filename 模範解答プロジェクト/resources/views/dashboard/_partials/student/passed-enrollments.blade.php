{{--
    修了済資格リスト。受講生ダッシュボードのメイン下部。
    構成: ヘッダ（タイトル + 件数）→ 修了資格の行リスト（資格名 + 修了日 / 経過日数 + 修了バッジ + 修了証 PDF ダウンロード）。0 件は空状態。
--}}
@props([
    'enrollments',
])

<x-card padding="md">
    <div class="flex items-baseline gap-2 mb-3">
        <h2 class="text-base font-bold text-ink-900 flex items-center gap-2">
            <x-icon name="check-badge" class="w-4 h-4 text-success-600" />
            修了済資格
        </h2>
        <span class="text-xs text-ink-500 font-medium">{{ $enrollments->count() }} 件</span>
    </div>

    @if ($enrollments->isEmpty())
        <p class="text-sm text-ink-500 py-2">
            まだ修了した資格はありません。受講中の資格を着実に進めて、合格点突破を目指しましょう。
        </p>
    @else
        <ul class="flex flex-col">
            @foreach ($enrollments as $enrollment)
                @php
                    $passedAt = $enrollment->passed_at;
                    $daysSince = (int) floor($passedAt->floatDiffInDays(now()));
                    $certificate = $enrollment->certificate;
                @endphp
                <li class="grid items-center gap-3.5 py-3 border-b border-subtle last:border-b-0"
                    style="grid-template-columns: auto 1fr auto auto;">
                    <span class="inline-flex w-8 h-8 flex-shrink-0 items-center justify-center rounded-full bg-success-100 text-success-700">
                        <x-icon name="check-badge" class="w-4 h-4" />
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-ink-900">{{ $enrollment->certification->name }}</p>
                        <p class="text-[11px] text-ink-500 mt-0.5">{{ $passedAt->format('Y/m/d') }} 修了 · 経過 {{ $daysSince }} 日</p>
                    </div>
                    <x-badge variant="success" size="sm">修了</x-badge>
                    @if ($certificate !== null)
                        <a href="{{ route('certificates.download', $certificate) }}"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-secondary-600 hover:bg-secondary-700 text-white rounded-lg text-xs font-semibold transition-colors">
                            <x-icon name="document-text" class="w-3 h-3" />
                            修了証 PDF
                        </a>
                    @else
                        <span class="text-[11px] text-ink-500">PDF 準備中</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
