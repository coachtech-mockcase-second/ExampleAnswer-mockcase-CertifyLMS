/**
 * アプリ全体の JS エントリ。各モジュールの init 関数をまとめて import し、
 * DOMContentLoaded で順に呼び出す（自動実行系モジュールは import するだけで副作用が走る）。
 */

import './bootstrap';

import { initModals } from './components/modal';
import { initDropdowns } from './components/dropdown';
import { initFlash } from './components/flash';
import { initSidebarDrawer } from './components/sidebar-drawer';
import { initTextareaCounter } from './components/textarea-counter';
import { initEnrollmentSwitchers } from './components/enrollment-switcher';
import { initNotificationPopover } from './notification/notification-popover';
import './notification/realtime';
import { initAiChatWidget } from './ai-chat/floating-widget';
import { initLearningCalendar } from './dashboard/learning-calendar';

document.addEventListener('DOMContentLoaded', () => {
    initModals();
    initDropdowns();
    initFlash();
    initSidebarDrawer();
    initTextareaCounter();
    initEnrollmentSwitchers();
    initNotificationPopover();
    initAiChatWidget();
    initLearningCalendar();
});
