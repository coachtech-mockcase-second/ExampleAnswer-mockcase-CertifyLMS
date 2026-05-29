// @ts-check
const { test, expect } = require('@playwright/test');
const { login, ACCOUNTS } = require('../../fixtures/auth');

test.describe('auth: ログイン', () => {
    test('ログイン画面が表示される', async ({ page }) => {
        await page.goto('/login');
        await expect(page.locator('input[name="email"]')).toBeVisible();
        await expect(page.locator('input[name="password"]')).toBeVisible();
        await expect(page.locator('button[type="submit"], input[type="submit"]')).toBeVisible();
    });

    test('正常: 受講生がログインしダッシュボードへ遷移する', async ({ page }) => {
        await login(page, 'student');
        // ログイン後は /login から離脱している（認証済み）
        await expect(page).not.toHaveURL(/\/login$/);
    });

    test('異常: 誤ったパスワードはログインできず /login に留まる', async ({ page }) => {
        await page.goto('/login');
        await page.fill('input[name="email"]', ACCOUNTS.student.email);
        await page.fill('input[name="password"]', 'wrong-password');
        await page.click('button[type="submit"], input[type="submit"]');
        // 認証失敗 → /login のまま、入力フォームが再表示される
        await expect(page).toHaveURL(/\/login$/);
        await expect(page.locator('input[name="email"]')).toBeVisible();
    });
});
