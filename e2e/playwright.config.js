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
 * 認証:
 *   - `setup` プロジェクトでロールごとに1度だけログインし storageState を保存。
 *   - 各テストは `test.use({ storageState })` で再利用し、テストごとのログインを避ける
 *     （大量テスト時のログイン負荷／フレークを排除）。
 *
 * 方針:
 *   - 共有DB(MySQL)に対して走るため、状態競合を避けて直列実行する（PoC方針）。
 *   - assert は「提供Bladeのアンカー + 観測可能な結果」に寄せ、受講生実装の内部DOMに依存させない。
 */
module.exports = defineConfig({
    testDir: './tests',
    fullyParallel: false,
    workers: 1,
    forbidOnly: !!process.env.CI,
    // 稼働中 Sail/Vite との競合やネットワーク揺れを retry で吸収（ログイン throttle を踏まないよう最小限）
    retries: 1,
    // 遅いサーバ応答でも安定するよう assert の既定 timeout を引き上げ
    expect: { timeout: 10000 },
    reporter: [['list'], ['html', { open: 'never' }]],
    use: {
        baseURL: process.env.E2E_BASE_URL || 'http://localhost:8000',
        // 録画は常時オーバーヘッドになるため off、trace は再試行時のみ（通常実行は軽量）
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'off',
        navigationTimeout: 30000,
    },
    projects: [
        // 認証状態を1度だけ確立（各テストは storageState 経由で再利用＝ログイン負荷を排除）
        { name: 'setup', testMatch: /.*\.setup\.js/, retries: 2 },
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
            dependencies: ['setup'],
            testIgnore: /.*\.setup\.js/,
        },
    ],
});
