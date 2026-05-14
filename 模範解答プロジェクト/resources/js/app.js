import './bootstrap';

import { initModals } from './components/modal';
import { initDropdowns } from './components/dropdown';
import { initTabs } from './components/tabs';
import { initFlash } from './components/flash';
import { initSidebarDrawer } from './components/sidebar-drawer';
import { initTextareaCounter } from './components/textarea-counter';

document.addEventListener('DOMContentLoaded', () => {
    initModals();
    initDropdowns();
    initTabs();
    initFlash();
    initSidebarDrawer();
    initTextareaCounter();
});
