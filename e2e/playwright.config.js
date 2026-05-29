// @ts-check
const { defineConfig, devices } = require('@playwright/test');

/**
 * Certify LMS E2E 設定
 *
 * 位置づけ:
 *   - 模範解答PJ の「完璧性検証」と、将来の「振る舞いベース採点オラクル」を兼ねるスイート。
 *   - アプリ本体（Laravel/Sail, http://localhost:8000）は別途起動しておき、ここから driving する。
 *   - CI では GitHub Actions が Laravel + MySQL を立ち上げ、同じスイートを実行する。
 *
 * 方針:
 *   - 共有DB(MySQL)に対して走るため、状態競合を避けて直列実行する（PoC方針）。
 *     将来 worker 毎にDB分離すれば並列化できる。
 *   - assert は「提供Bladeのアンカー + 観測可能な結果（URL遷移・表示テキスト）」に寄せ、
 *     受講生が実装する内部DOM/JSの差異に依存させない（採点オラクルの頑健性のため）。
 */
module.exports = defineConfig({
    testDir: './tests',
    fullyParallel: false,
    workers: 1,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    reporter: [['list'], ['html', { open: 'never' }]],
    use: {
        baseURL: process.env.E2E_BASE_URL || 'http://localhost:8000',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
