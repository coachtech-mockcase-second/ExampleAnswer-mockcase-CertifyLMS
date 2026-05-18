<x-nav.sidebar>
    <x-nav.item route="dashboard.index" icon="home" label="ダッシュボード" />

    <x-nav.section title="運用" :routes="['admin.users.index', 'admin.enrollments.index', 'admin.plans.index', 'admin.meeting-quota-plans.index', 'admin.certifications.index', 'admin.certification-categories.index', 'admin.mock-exams.index', 'admin.mock-exam-sessions.index']" />
    <x-nav.item route="admin.users.index" icon="users" label="ユーザー管理" />
    <x-nav.item route="admin.enrollments.index" icon="clipboard-document-list" label="受講登録管理" />
    <x-nav.item route="admin.plans.index" icon="credit-card" label="プラン管理" />
    <x-nav.item route="admin.meeting-quota-plans.index" icon="banknotes" label="追加面談プラン管理" />
    <x-nav.item route="admin.certifications.index" icon="academic-cap" label="資格マスタ管理" />
    <x-nav.item route="admin.certification-categories.index" icon="tag" label="カテゴリ管理" />
    <x-nav.item route="admin.mock-exams.index" icon="clipboard-document-check" label="模試マスタ管理" />
    <x-nav.item route="admin.mock-exam-sessions.index" icon="chart-pie" label="受験セッション閲覧" />

    <x-nav.section title="監査" :routes="['admin.chat-rooms.index']" />
    <x-nav.item route="admin.chat-rooms.index" icon="chat-bubble-left-right" label="chat 監査" />

    <x-nav.section title="分析" :routes="['admin.stats.index']" />
    <x-nav.item route="admin.stats.index" icon="chart-bar" label="運用統計" />

    <x-nav.section title="共通" :routes="['notifications.index', 'settings.profile.edit']" />
    <x-nav.item route="notifications.index" icon="bell" label="通知" :badge="$sidebarBadges['notifications'] ?? 0" />
    <x-nav.item route="settings.profile.edit" icon="cog-6-tooth" label="設定" />
</x-nav.sidebar>
