// @ts-check
const { ACCOUNTS } = require('./accounts');

/**
 * Fortify 標準のログインフォーム（name="email" / name="password"）でログインする。
 * 成功時は /login から離脱するまで待つ。
 *
 * @param {import('@playwright/test').Page} page
 * @param {'admin'|'coach'|'coach2'|'student'} role
 */
async function login(page, role) {
    const account = ACCOUNTS[role];
    if (!account) {
        throw new Error(`未知のロール: ${role}`);
    }
    await page.goto('/login');
    await page.fill('input[name="email"]', account.email);
    await page.fill('input[name="password"]', account.password);
    await page.click('button[type="submit"], input[type="submit"]');
    // waitUntil:'commit' = URL 確定で解決（重いダッシュボードの全リソース load を待たない）。
    // 単一スレッド php artisan serve でも安定させるため timeout も緩和。
    await page.waitForURL((url) => !url.pathname.replace(/\/$/, '').endsWith('/login'), {
        timeout: 30000,
        waitUntil: 'commit',
    });
}

module.exports = { login, ACCOUNTS };
