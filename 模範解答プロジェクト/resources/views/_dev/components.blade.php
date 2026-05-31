{{-- 開発専用: 全コンポーネントの variant / size を視認できるショーケース --}}
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Components | _dev</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-surface-canvas text-ink-900">
    <div class="max-w-5xl mx-auto p-6 lg:p-10 space-y-12">
        <header class="space-y-2">
            <p class="eyebrow">_dev</p>
            <h1 class="display-score">Components</h1>
            <p class="text-sm text-ink-500">本ページは <code class="font-mono bg-ink-50 px-1.5 py-0.5 rounded">APP_ENV=local</code> 環境のみで表示されます。各 variant / size / state を視認確認するためのショーケース。</p>
        </header>

        {{-- Buttons --}}
        <section class="space-y-4">
            <h2 class="text-xl font-bold">Buttons</h2>
            <x-card>
                <div class="space-y-4">
                    <div>
                        <p class="eyebrow mb-2">Variants (size: md)</p>
                        <div class="flex flex-wrap gap-2">
                            <x-button variant="primary">受験を開始する</x-button>
                            <x-button variant="outline">下書きに戻す</x-button>
                            <x-button variant="ghost">キャンセル</x-button>
                            <x-button variant="danger">削除</x-button>
                            <x-button variant="secondary">詳細</x-button>
                        </div>
                    </div>
                    <div>
                        <p class="eyebrow mb-2">Sizes (variant: primary)</p>
                        <div class="flex flex-wrap items-center gap-2">
                            <x-button variant="primary" size="sm">sm</x-button>
                            <x-button variant="primary" size="md">md</x-button>
                            <x-button variant="primary" size="lg">lg</x-button>
                        </div>
                    </div>
                    <div>
                        <p class="eyebrow mb-2">States</p>
                        <div class="flex flex-wrap items-center gap-2">
                            <x-button variant="primary">default</x-button>
                            <x-button variant="primary" disabled>disabled</x-button>
                            <x-button variant="primary" loading>loading</x-button>
                            <x-link-button variant="outline" href="#">LinkButton</x-link-button>
                        </div>
                    </div>
                </div>
            </x-card>
        </section>

        {{-- Badges --}}
        <section class="space-y-4">
            <h2 class="text-xl font-bold">Badges</h2>
            <x-card>
                <div class="flex flex-wrap gap-2">
                    <x-badge variant="success">公開中</x-badge>
                    <x-badge variant="warning">下書き</x-badge>
                    <x-badge variant="danger">不合格</x-badge>
                    <x-badge variant="info">進行中</x-badge>
                    <x-badge variant="primary">管理者</x-badge>
                    <x-badge variant="secondary">コーチ</x-badge>
                    <x-badge variant="gray">受講生</x-badge>
                    <x-badge variant="danger" size="sm">12</x-badge>
                </div>
            </x-card>
        </section>

        {{-- Forms --}}
        <section class="space-y-4">
            <h2 class="text-xl font-bold">Forms</h2>
            <x-card>
                <div class="grid md:grid-cols-2 gap-6">
                    <x-form.input name="email" label="メールアドレス" type="email" required placeholder="user@example.com" hint="ログインに使用します" />
                    <x-form.input name="exam_date" label="受験予定日" type="date" value="2026-07-12" hint="逆算で学習計画を立てます" />
                    <x-form.input name="passing_score" label="合格点" type="number" value="600" disabled hint="資格マスタから自動入力" />
                    <x-form.input name="password" label="パスワード" type="password" :error="'8 文字以上で入力してください'" required />

                    <x-form.select name="role" label="ロール" :options="['admin' => '管理者', 'coach' => 'コーチ', 'student' => '受講生']" placeholder="選択してください" />
                    <x-form.textarea name="bio" label="自己紹介" :rows="3" :maxlength="200" :value="''" hint="あなたの学習目標や経歴を簡単に記入してください" />

                    <div class="space-y-2">
                        <x-form.label>難易度</x-form.label>
                        <div class="flex gap-4">
                            <x-form.radio name="difficulty" value="easy" label="易" />
                            <x-form.radio name="difficulty" value="medium" label="中" :checked="true" />
                            <x-form.radio name="difficulty" value="hard" label="難" />
                        </div>
                    </div>

                    <div class="space-y-2">
                        <x-form.label>通知チャンネル</x-form.label>
                        <div class="flex flex-col gap-2">
                            <x-form.checkbox name="notify_approval" label="面談承認" :checked="true" />
                            <x-form.checkbox name="notify_complete" label="修了認定" :checked="true" />
                            <x-form.checkbox name="notify_chat" label="新着 chat" />
                        </div>
                    </div>

                    <x-form.file name="avatar" label="プロフィール画像" accept="image/png,image/jpeg" hint="PNG / JPG、最大 2MB" />
                </div>

                {{-- 単体使用の補助コンポーネント（label / hint / error / fieldset を個別に組む場合）--}}
                <div class="border-t border-subtle pt-4">
                    <p class="eyebrow mb-2">Form helpers (standalone)</p>
                    <div class="grid md:grid-cols-2 gap-6">
                        <div class="space-y-1.5">
                            <x-form.label for="custom-name" :required="true">名前</x-form.label>
                            <input id="custom-name" name="custom_name" class="block w-full rounded-md border border-ink-200 px-3 py-2 text-sm" />
                            <x-form.hint>50 文字以内で入力してください</x-form.hint>
                            <x-form.error message="名前は必須です" />
                        </div>

                        <x-form.fieldset legend="個人目標">
                            <x-form.input name="goal_title" label="タイトル" placeholder="基本情報技術者に合格する" />
                            <x-form.input name="goal_deadline" label="期限" type="date" />
                        </x-form.fieldset>
                    </div>
                </div>
            </x-card>
        </section>

        {{-- Alerts --}}
        <section class="space-y-4">
            <h2 class="text-xl font-bold">Alerts</h2>
            <div class="space-y-3">
                <x-alert type="success" title="処理が完了しました">ユーザーを招待しました。</x-alert>
                <x-alert type="error" :dismissible="true">入力内容に誤りがあります。</x-alert>
                <x-alert type="warning">招待リンクの有効期限が 24 時間後に切れます。</x-alert>
                <x-alert type="info">直近 3 回の平均正答率は 72% です。合格可能性: <strong>中</strong></x-alert>
            </div>
        </section>

        {{-- Avatar --}}
        <section class="space-y-4">
            <h2 class="text-xl font-bold">Avatars</h2>
            <x-card>
                <div class="flex items-end gap-4">
                    <x-avatar size="sm" name="山田太郎" />
                    <x-avatar size="md" name="鈴木花子" />
                    <x-avatar size="lg" name="佐藤次郎" />
                    <x-avatar size="xl" name="高橋一郎" />
                </div>
            </x-card>
        </section>

        {{-- Cards --}}
        <section class="space-y-4">
            <h2 class="text-xl font-bold">Cards</h2>
            <div class="grid md:grid-cols-2 gap-4">
                <x-card>
                    <p class="eyebrow mb-2">受講中資格</p>
                    <h3 class="text-lg font-bold">基本情報技術者</h3>
                    <p class="text-sm text-ink-500 mt-1">受験日まで <span class="tnum font-display font-bold text-primary-700">23</span> 日</p>
                </x-card>
                <x-card>
                    <x-slot:header>進捗ゲージ</x-slot:header>
                    <p class="text-sm">67% 完了</p>
                    <x-slot:footer>
                        <x-link-button href="#" variant="ghost" size="sm">詳細を見る →</x-link-button>
                    </x-slot:footer>
                </x-card>
            </div>
        </section>

        {{-- Empty State --}}
        <section class="space-y-4">
            <h2 class="text-xl font-bold">Empty state</h2>
            <x-card>
                <x-empty-state
                    icon="document-magnifying-glass"
                    title="該当する教材がありません"
                    description="検索キーワードを変えてもう一度お試しください"
                >
                    <x-slot:action>
                        <x-link-button href="#" variant="primary">教材一覧へ戻る</x-link-button>
                    </x-slot:action>
                </x-empty-state>
            </x-card>
        </section>

        {{-- Table --}}
        <section class="space-y-4">
            <h2 class="text-xl font-bold">Table</h2>
            <x-table>
                <x-slot:head>
                    <x-table.row>
                        <x-table.heading>名前</x-table.heading>
                        <x-table.heading>ロール</x-table.heading>
                        <x-table.heading>ステータス</x-table.heading>
                    </x-table.row>
                </x-slot:head>
                @foreach (\App\Models\User::query()->limit(5)->get() as $user)
                    <x-table.row>
                        <x-table.cell>
                            <div class="flex items-center gap-2">
                                <x-avatar :name="$user->name ?? '?'" size="sm" />
                                <span>{{ $user->name ?? '(未設定)' }}</span>
                            </div>
                        </x-table.cell>
                        <x-table.cell><x-badge variant="primary">{{ $user->role->label() }}</x-badge></x-table.cell>
                        <x-table.cell><x-badge variant="success">{{ $user->status->label() }}</x-badge></x-table.cell>
                    </x-table.row>
                @endforeach
            </x-table>
        </section>

        {{-- Breadcrumb / Tabs --}}
        <section class="space-y-4">
            <h2 class="text-xl font-bold">Breadcrumb / Tabs</h2>
            <x-card>
                <x-breadcrumb :items="[
                    ['label' => 'ホーム', 'href' => '#'],
                    ['label' => '教材', 'href' => '#'],
                    ['label' => '基本情報技術者'],
                ]" />
                <div class="mt-4">
                    <x-tabs :tabs="['catalog' => '資格カタログ', 'enrolled' => '受講中']" active="catalog" />
                </div>
            </x-card>
        </section>

        {{-- Dropdown / Modal --}}
        <section class="space-y-4">
            <h2 class="text-xl font-bold">Dropdown / Modal</h2>
            <x-card>
                <div class="flex items-start gap-4 flex-wrap">
                    <x-dropdown align="left">
                        <x-slot:trigger>
                            <x-button variant="outline">操作 <x-icon name="chevron-down" class="w-4 h-4" /></x-button>
                        </x-slot:trigger>
                        {{-- href のみ = リンク項目 --}}
                        <x-dropdown.item href="#" icon="pencil">編集</x-dropdown.item>
                        <x-dropdown.item href="#" icon="document-duplicate">複製</x-dropdown.item>
                        {{-- href + method = DELETE 偽装フォーム送信ボタン（内部で hidden form + CSRF を出力）--}}
                        <x-dropdown.item href="#" method="delete" icon="trash" variant="danger">削除</x-dropdown.item>
                        {{-- href なし = ただのボタン（onclick 等を呼び出し側で付与する用途）--}}
                        <x-dropdown.item icon="arrow-path">ボタン項目</x-dropdown.item>
                    </x-dropdown>

                    <x-modal id="demo-modal" title="モーダルのデモ" size="md">
                        <x-slot:trigger>
                            <x-button data-modal-trigger="demo-modal">モーダルを開く</x-button>
                        </x-slot:trigger>
                        <x-slot:body>
                            <p class="text-sm">これは <code class="font-mono">&lt;x-modal&gt;</code> の動作確認です。Esc / バックドロップクリック / × ボタンで閉じます。</p>
                        </x-slot:body>
                        <x-slot:footer>
                            <x-button variant="ghost" data-modal-close="demo-modal">キャンセル</x-button>
                            <x-button variant="primary" data-modal-close="demo-modal">OK</x-button>
                        </x-slot:footer>
                    </x-modal>
                </div>
            </x-card>
        </section>

        {{-- Content status pill (Feature: content-management) --}}
        <section class="space-y-4">
            <h2 class="text-xl font-bold">Content status pill</h2>
            <x-card>
                <div class="flex flex-wrap items-center gap-3">
                    <x-content-management.status-pill :status="\App\Enums\ContentStatus::Published" />
                    <x-content-management.status-pill :status="\App\Enums\ContentStatus::Draft" />
                </div>
            </x-card>
        </section>

        {{-- Confirm modals (Feature: content-management) --}}
        <section class="space-y-4">
            <h2 class="text-xl font-bold">Confirm modals</h2>
            <x-card>
                <div class="flex flex-wrap items-start gap-4">
                    <x-button data-modal-trigger="demo-publish-modal" variant="primary">公開する</x-button>
                    <x-content-management.publish-confirm-modal id="demo-publish-modal" action="#" />

                    <x-button data-modal-trigger="demo-draft-modal" variant="secondary">下書きに戻す</x-button>
                    <x-content-management.publish-confirm-modal
                        id="demo-draft-modal"
                        title="下書きに戻しますか？"
                        description="下書きに戻すと受講生の教材閲覧画面から非表示になります。"
                        action="#"
                        button-label="下書きに戻す"
                        button-variant="secondary" />

                    <x-button data-modal-trigger="demo-delete-modal" variant="danger">削除する</x-button>
                    <x-content-management.delete-confirm-modal id="demo-delete-modal" action="#" />
                </div>
            </x-card>
        </section>

        {{-- Enrollment switcher（empty-state variant、受講中資格が無い状態のプレビュー）--}}
        <section class="space-y-4">
            <h2 class="text-xl font-bold">Enrollment switcher (empty-state)</h2>
            <x-enrollment-switcher variant="empty-state" />
        </section>

        {{-- Paginator --}}
        <section class="space-y-4">
            <h2 class="text-xl font-bold">Paginator</h2>
            @php
                // ショーケース用にダミーのページネータを生成（全 47 件・10 件/頁・現在 2 頁目）
                $demoPaginator = new \Illuminate\Pagination\LengthAwarePaginator(
                    items: collect(range(11, 20)),
                    total: 47,
                    perPage: 10,
                    currentPage: 2,
                    options: ['path' => url('/_dev/components')],
                );
            @endphp
            <x-card>
                <x-paginator :paginator="$demoPaginator" />
            </x-card>
        </section>

        {{-- Sidebar nav (preview, isolated) --}}
        <section class="space-y-4">
            <h2 class="text-xl font-bold">Sidebar (preview)</h2>
            <x-card padding="none">
                <div class="grid md:grid-cols-3 divide-x divide-subtle">
                    <div>
                        <p class="eyebrow px-4 pt-4">student</p>
                        @include('layouts._partials.sidebar-student', ['sidebarBadges' => ['unfinishedMockExams' => 2, 'unattendedChat' => 1, 'notifications' => 7]])
                    </div>
                    <div>
                        <p class="eyebrow px-4 pt-4">coach</p>
                        @include('layouts._partials.sidebar-coach', ['sidebarBadges' => ['unattendedChat' => 3]])
                    </div>
                    <div>
                        <p class="eyebrow px-4 pt-4">admin</p>
                        @include('layouts._partials.sidebar-admin', ['sidebarBadges' => ['pendingCompletions' => 5, 'notifications' => 12]])
                    </div>
                </div>
            </x-card>
        </section>

        <footer class="text-center text-xs text-ink-500 py-8">_dev/components — Wave 0b 完成判定用ショーケース</footer>
    </div>
</body>
</html>
