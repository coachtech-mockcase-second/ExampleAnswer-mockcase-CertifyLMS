import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/content-management/section-editor.js',
                'resources/js/content-management/image-uploader.js',
                'resources/js/content-management/option-correct.js',
                'resources/js/content-management/reorder.js',
                'resources/js/mock-exam/answer-autosave.js',
                'resources/js/mentoring/slot-picker.js',
                'resources/js/chat/realtime.js',
                'resources/js/settings-profile/avatar.js',
                'resources/js/settings-profile/availability-calendar.js',
                'resources/js/admin/announcement-form.js',
                'resources/js/ai-chat/index.js',
            ],
            refresh: true,
        }),
    ],
});
