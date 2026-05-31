{{--
    個人目標の追加フォーム + 一覧(受講詳細ページの「個人目標」カード内)。
    構成: 新規追加フォーム(目標 / 期日 / 詳細) → 0 件メッセージ → 目標リスト(達成アイコン + 本文 + 期日/達成日 + 達成・取消・編集・削除の各操作)。
    閲覧者の権限で追加フォーム・各操作の表示を出し分け。削除は confirm() で誤操作防止(JS なし)。
--}}
@php
    use App\Models\EnrollmentGoal;

    $goals = $enrollment->goals;
    $canCreate = auth()->user()?->can('create', [EnrollmentGoal::class, $enrollment]) ?? false;
@endphp

@can('create', [EnrollmentGoal::class, $enrollment])
    <form novalidate method="POST" action="{{ route('enrollments.goals.store', $enrollment) }}" class="space-y-3 pb-4 border-b border-ink-100">
        @csrf
        <x-form.input
            name="title"
            label="目標"
            :value="old('title')"
            :error="$errors->first('title')"
            placeholder="例: 過去問 5 年分を解き終える"
            maxlength="100"
            :required="true"
        />
        <x-form.input
            name="target_date"
            label="目標期日"
            type="date"
            :value="old('target_date')"
            :error="$errors->first('target_date')"
        />
        <x-form.textarea
            name="description"
            label="詳細(任意)"
            :rows="2"
            :value="old('description')"
            :error="$errors->first('description')"
            :maxlength="1000"
        />
        <x-button type="submit" variant="primary" size="sm">
            <x-icon name="plus" class="w-4 h-4" />
            目標を追加
        </x-button>
    </form>
@endcan

@if ($goals->isEmpty())
    <p class="text-sm text-ink-500 pt-4">{{ $canCreate ? 'まだ目標が登録されていません。' : 'この受講生はまだ目標を登録していません。' }}</p>
@else
    <ul class="pt-4 space-y-3">
        @foreach ($goals as $goal)
            <li class="flex items-start gap-3 p-3 rounded-md border border-ink-100">
                <div class="shrink-0 mt-0.5">
                    @if ($goal->achieved_at)
                        <x-icon name="check-circle" variant="solid" class="w-5 h-5 text-success-600" />
                    @else
                        <x-icon name="circle-stack" class="w-5 h-5 text-ink-400" />
                    @endif
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-semibold text-ink-900 {{ $goal->achieved_at ? 'line-through text-ink-500' : '' }}">
                        {{ $goal->title }}
                    </div>
                    @if ($goal->description)
                        <div class="text-xs text-ink-500 mt-0.5">{{ $goal->description }}</div>
                    @endif
                    <div class="text-xs text-ink-400 mt-1 tabular-nums">
                        @if ($goal->target_date)
                            期日: {{ $goal->target_date->format('Y-m-d') }}
                        @endif
                        @if ($goal->achieved_at)
                            ・ 達成: {{ $goal->achieved_at->format('Y-m-d') }}
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-1">
                    @if ($goal->achieved_at)
                        @can('unmarkAchieved', $goal)
                            <form novalidate method="POST" action="{{ route('enrollment-goals.unmarkAchieved', $goal) }}">
                                @csrf
                                @method('DELETE')
                                <x-button type="submit" variant="ghost" size="sm">取消</x-button>
                            </form>
                        @endcan
                    @else
                        @can('markAchieved', $goal)
                            <form novalidate method="POST" action="{{ route('enrollment-goals.markAchieved', $goal) }}">
                                @csrf
                                <x-button type="submit" variant="ghost" size="sm">達成</x-button>
                            </form>
                        @endcan
                    @endif
                    @can('update', $goal)
                        <x-link-button href="{{ route('enrollment-goals.edit', $goal) }}" variant="ghost" size="sm">
                            <x-icon name="pencil-square" class="w-4 h-4" />
                        </x-link-button>
                    @endcan
                    @can('delete', $goal)
                        <form novalidate
                            method="POST"
                            action="{{ route('enrollment-goals.destroy', $goal) }}"
                            onsubmit="return confirm('この目標を削除しますか？');"
                        >
                            @csrf
                            @method('DELETE')
                            <x-button type="submit" variant="ghost" size="sm">
                                <x-icon name="trash" class="w-4 h-4" />
                            </x-button>
                        </form>
                    @endcan
                </div>
            </li>
        @endforeach
    </ul>
@endif
