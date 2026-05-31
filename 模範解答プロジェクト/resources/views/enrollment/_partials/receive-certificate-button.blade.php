{{--
    修了証の受領パネル(受講詳細ページ内)。
    構成: 学習中なら受領カード(条件達成で発行ボタン / 未達なら無効ボタン) → 修了済なら PDF ダウンロード alert。
    発行ボタンは confirm() で誤操作防止(JS なし)。
--}}
@php
    use App\Enums\EnrollmentStatus;
    use App\Services\CompletionEligibilityService;

    $isEligible = false;
    if ($enrollment->status === EnrollmentStatus::Learning) {
        $isEligible = app(CompletionEligibilityService::class)->isEligible($enrollment);
    }
@endphp

@if ($enrollment->status === EnrollmentStatus::Learning)
    <div class="mt-4">
        <x-card padding="md" shadow="sm">
            <div class="flex items-start gap-3">
                <div class="shrink-0 inline-flex h-10 w-10 items-center justify-center rounded-full bg-success-50 text-success-700">
                    <x-icon name="trophy" class="w-5 h-5" />
                </div>
                <div class="flex-1">
                    <h2 class="text-base font-semibold text-ink-900">修了証を受け取る</h2>
                    @if ($isEligible)
                        <p class="text-sm text-ink-500 mt-1">
                            公開中の模試すべてで合格点を超えています。下記ボタンから修了証を受領できます。
                        </p>
                        <form novalidate
                            method="POST"
                            action="{{ route('enrollments.receiveCertificate', $enrollment) }}"
                            class="mt-3"
                            onsubmit="return confirm('修了証を発行します。発行後は受講中状態に戻せません。よろしいですか？');"
                        >
                            @csrf
                            <x-button type="submit" variant="primary">
                                <x-icon name="check-circle" class="w-4 h-4" />
                                修了証を受け取る
                            </x-button>
                        </form>
                    @else
                        <p class="text-sm text-ink-500 mt-1">
                            公開中の模試すべてで合格点を超えると、ここから修了証を受け取れるようになります。
                        </p>
                        <div class="mt-3">
                            <x-button type="button" variant="primary" disabled>
                                <x-icon name="lock-closed" class="w-4 h-4" />
                                条件未達のため受領できません
                            </x-button>
                        </div>
                    @endif
                </div>
            </div>
        </x-card>
    </div>
@elseif ($enrollment->status === EnrollmentStatus::Passed && $enrollment->certificate)
    <div class="mt-4">
        <x-alert type="success">
            <x-slot:title>修了済み</x-slot:title>
            <a href="{{ route('certificates.download', $enrollment->certificate) }}" class="underline font-semibold">
                修了証 PDF をダウンロード
            </a>
        </x-alert>
    </div>
@endif
