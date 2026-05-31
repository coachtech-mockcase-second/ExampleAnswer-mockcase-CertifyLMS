<x-nav.sidebar>
    <x-nav.item route="dashboard.index" icon="home" label="ダッシュボード" />

    <x-nav.section title="受講生" :routes="['enrollments.index']" />
    <x-nav.item route="enrollments.index" icon="user-group" label="担当受講生" />

    <x-nav.section title="コンテンツ" :routes="['admin.certifications.index', 'admin.mock-exams.index']" />
    <x-nav.item route="admin.certifications.index" icon="academic-cap" label="資格マスタ管理" />
    <x-nav.item route="admin.mock-exams.index" icon="clipboard-document-check" label="模試マスタ管理" />

    <x-nav.section title="対応" :routes="['coach.chat.index', 'qa-board.index', 'coach.meetings.index']" />
    <x-nav.item route="coach.chat.index" icon="chat-bubble-left-right" label="chat 対応" :badge="$sidebarBadges['unattendedChat'] ?? 0" :active="request()->routeIs('coach.chat.*', 'chat.*')" />
    <x-nav.item route="qa-board.index" icon="question-mark-circle" label="質問対応" />
    <x-nav.item route="coach.meetings.index" icon="calendar-days" label="面談管理" :active="request()->routeIs('coach.meetings.*', 'meetings.*')" />

    <x-nav.section title="共通" :routes="['notifications.index', 'settings.profile.edit']" />
    <x-nav.item route="notifications.index" icon="bell" label="通知" />
    <x-nav.item route="settings.profile.edit" icon="cog-6-tooth" label="設定" />
</x-nav.sidebar>
