<x-nav.sidebar>
    <x-nav.item route="dashboard.index" icon="home" label="ダッシュボード" />

    <x-nav.section title="学習" :routes="['certifications.index', 'contents.index', 'mock-exams.index']" />
    <x-nav.item route="certifications.index" icon="magnifying-glass" label="資格カタログ" />
    <x-nav.item route="contents.index" icon="book-open" label="教材" />
    <x-nav.item route="mock-exams.index" icon="clipboard-document-check" label="模試" :badge="$sidebarBadges['unfinishedMockExams'] ?? 0" />

    <x-nav.section title="相談" :routes="['chat.index', 'qa-board.index', 'ai-chat.index', 'meetings.index']" />
    <x-nav.item route="chat.index" icon="chat-bubble-left-right" label="chat (コーチへ)" :badge="$sidebarBadges['unattendedChat'] ?? 0" />
    <x-nav.item route="qa-board.index" icon="question-mark-circle" label="質問掲示板" />
    <x-nav.item route="ai-chat.index" icon="sparkles" label="AI 相談" />
    <x-nav.item route="meetings.index" icon="calendar-days" label="面談予約" />

    <x-nav.section title="共通" :routes="['notifications.index', 'settings.profile.edit']" />
    <x-nav.item route="notifications.index" icon="bell" label="通知" :badge="$sidebarBadges['notifications'] ?? 0" />
    <x-nav.item route="settings.profile.edit" icon="cog-6-tooth" label="設定" />
</x-nav.sidebar>
