<div class="flex flex-col h-full">
    <x-nav.sidebar class="flex-1">
        <x-nav.item route="dashboard.index" icon="home" label="ダッシュボード" />

        <x-nav.section title="学習" :routes="['certifications.index', 'enrollments.index', 'learning.index', 'quiz.sections.show', 'quiz.sections.question', 'quiz.sections.result', 'quiz.drills.index', 'quiz.drills.category', 'quiz.drills.question', 'quiz.drills.result', 'quiz.history.index', 'quiz.stats.index', 'mock-exam.fallback.index', 'mock-exam.catalog.index', 'mock-exam.catalog.show', 'mock-exam-sessions.index', 'mock-exam-sessions.show']" />
        <x-nav.item route="certifications.index" icon="magnifying-glass" label="資格カタログ" />
        <x-nav.item route="enrollments.index" icon="clipboard-document-list" label="受講中資格" />
        <x-nav.item route="learning.index" icon="book-open" label="教材・演習" />
        <x-nav.item route="mock-exam.fallback.index" icon="clipboard-document-check" label="模試" />

        <x-nav.section title="相談" :routes="['chat.index', 'qa-board.index', 'ai-chat.index', 'meetings.index', 'meetings.fallback.create', 'meetings.create', 'meetings.show']" />
        <x-nav.item route="chat.index" icon="chat-bubble-left-right" label="chat" :badge="$sidebarBadges['unattendedChat'] ?? 0" />
        <x-nav.item route="qa-board.index" icon="question-mark-circle" label="質問掲示板" />
        <x-nav.item route="ai-chat.index" icon="sparkles" label="AI 相談" />
        <x-nav.item route="meetings.fallback.create" icon="calendar-days" label="面談予約" />

        <x-nav.section title="共通" :routes="['notifications.index', 'settings.profile.edit']" />
        <x-nav.item route="notifications.index" icon="bell" label="通知" />
        <x-nav.item route="settings.profile.edit" icon="cog-6-tooth" label="設定" />
    </x-nav.sidebar>

    <div class="px-3 pb-4 pt-3 border-t border-[var(--border-subtle)] shrink-0">
        <p class="px-1 mb-1.5 text-[10px] uppercase tracking-wider text-ink-500">デフォルト資格</p>
        @php
            // 現在の Feature 文脈の中で資格を切り替えられるように、Feature 別の 2 階層目ルートへ向ける。
            // 各 Feature の `{feature}.enrollments.show` ルートが存在すればそれを使い、無ければ受講登録管理画面に戻す。
            $sidebarTargetRoute = collect([
                'learning.*' => 'learning.enrollments.show',
                'meetings.create' => 'meetings.create',
                'meetings.availability' => 'meetings.create',
                'meetings.store' => 'meetings.create',
            ])->reduce(function (?string $carry, string $route, string $pattern): ?string {
                if ($carry !== null) {
                    return $carry;
                }

                return request()->routeIs($pattern) && \Illuminate\Support\Facades\Route::has($route)
                    ? $route
                    : null;
            }) ?? 'enrollments.show';
        @endphp
        <x-enrollment-switcher variant="sidebar" :target-route="$sidebarTargetRoute" />
    </div>
</div>
