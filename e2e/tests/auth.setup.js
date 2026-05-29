// @ts-check
const { test: setup } = require('@playwright/test');
const fs = require('fs');
const { login } = require('../fixtures/auth');

/**
 * 各ロールの認証状態を1度だけ確立し storageState に保存する。
 * 以降の認証必須テストは test.use({ storageState }) で再利用し、テストごとのログインを避ける。
 * （2b でロールが増えたら同様に追記する）
 */
setup('authenticate as student', async ({ page }) => {
    if (!fs.existsSync('.auth')) {
        fs.mkdirSync('.auth');
    }
    await login(page, 'student');
    await page.context().storageState({ path: '.auth/student.json' });
});
