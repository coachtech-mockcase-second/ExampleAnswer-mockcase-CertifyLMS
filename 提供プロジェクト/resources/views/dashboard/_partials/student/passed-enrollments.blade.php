{{--
    修了済資格リストの本体。見出し（タイトル + 件数）は呼び出し側が用意する。
    構成: 修了資格の行リスト（資格名 + 修了日 / 経過日数 + 修了バッジ + 復習導線 + 修了証 PDF ダウンロード）。0 件は空状態文。
--}}
@props([
    'enrollments',
])

<x-card padding="md">
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
                <li class="flex items-center gap-3.5 py-3 border-b border-subtle last:border-b-0">
                    <span class="inline-flex w-8 h-8 flex-shrink-0 items-center justify-center rounded-full bg-success-100 text-success-700">
                        <x-icon name="check-badge" class="w-4 h-4" />
                    </span>
                    <div class="flex-1 min-w-0">
                        <a href="{{ route('enrollments.show', $enrollment->id) }}"
                           class="block text-sm font-semibold text-ink-900 truncate hover:text-primary-700 transition-colors">
                            {{ $enrollment->certification->name }}
                        </a>
                        <p class="text-[11px] text-ink-500 mt-0.5">{{ $passedAt->format('Y/m/d') }} 修了 · 経過 {{ $daysSince }} 日</p>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        {{-- 修了後も教材は復習モードで閲覧できる --}}
                        <a href="{{ route('learning.enrollments.show', $enrollment->id) }}"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-ink-700 hover:bg-ink-50 rounded-lg text-xs font-semibold transition-colors">
                            <x-icon name="book-open" class="w-3 h-3" />
                            復習
                        </a>
                        @if ($certificate !== null && Route::has('certificates.download'))
                            <a href="{{ route('certificates.download', $certificate) }}"
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-secondary-600 hover:bg-secondary-700 text-white rounded-lg text-xs font-semibold transition-colors">
                                <x-icon name="document-text" class="w-3 h-3" />
                                修了証 PDF
                            </a>
                        @else
                            <span class="text-[11px] text-ink-500">PDF 準備中</span>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
