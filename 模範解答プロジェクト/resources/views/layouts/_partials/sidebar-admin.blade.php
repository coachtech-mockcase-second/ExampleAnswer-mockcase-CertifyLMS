<x-nav.sidebar>
    <x-nav.item route="dashboard.index" icon="home" label="ダッシュボード" />

    <x-nav.section title="運用" :routes="['admin.users.index', 'admin.plans.index', 'admin.certifications.index', 'admin.certification-categories.index']" />
    <x-nav.item route="admin.users.index" icon="users" label="ユーザー管理" />
    <x-nav.item route="admin.plans.index" icon="credit-card" label="プラン管理" />
    <x-nav.item route="admin.certifications.index" icon="academic-cap" label="資格マスタ管理" />
    <x-nav.item route="admin.certification-categories.index" icon="tag" label="カテゴリ管理" />

    <x-nav.section title="承認" :routes="['admin.enrollments.pending']" />
    <x-nav.item route="admin.enrollments.pending" icon="check-badge" label="修了申請承認" :badge="$sidebarBadges['pendingCompletions'] ?? 0" />

    <x-nav.section title="分析" :routes="['admin.stats.index']" />
    <x-nav.item route="admin.stats.index" icon="chart-bar" label="運用統計" />

    <x-nav.section title="共通" :routes="['notifications.index', 'settings.profile.edit']" />
    <x-nav.item route="notifications.index" icon="bell" label="通知" :badge="$sidebarBadges['notifications'] ?? 0" />
    <x-nav.item route="settings.profile.edit" icon="cog-6-tooth" label="設定" />
</x-nav.sidebar>
