// @ts-check
const { test, expect } = require('@playwright/test');
const { login } = require('../../fixtures/auth');

/**
 * mock-exam（受講生）— 振る舞いベース検証。
 *
 * 設計メモ:
 *   - 時間制限/タイマー/auto-submit は E-3(v3) で完全撤回済。本スペックでは扱わない。
 *   - 模範解答PJの固定 student シードを利用:
 *       第1回 Graded合格(100%) / 第2回 Graded不合格(33%) / 第3回 InProgress / 第4回 NotStarted / Canceled×1
 *   - シード状態を破壊しないよう、提出(InProgress→Graded)は行わず read-only + 冪等操作のみ。
 *     提出→採点の全周は DB が毎回 fresh な CI / Phase2b で扱う。
 */

/**
 * 進行中(InProgress)セッションの受験画面(take)を開く。
 * student は複数資格に登録されており、/mock-exams は資格選択の empty-state を返すため、
 * 各資格カタログを巡回して「受験を再開」リンクを持つ資格を見つける。
 *
 * @param {import('@playwright/test').Page} page
 */
async function openInProgressTakePage(page) {
    await page.goto('/mock-exams');
    const hrefs = await page
        .locator('a[href*="/enrollments/"]')
        .evaluateAll((els) =>
            els
                .map((e) => e.getAttribute('href'))
                .filter((h) => h && h.endsWith('/mock-exams')),
        );

    for (const href of hrefs) {
        await page.goto(href);
        const resume = page.getByRole('link', { name: /受験を再開/ });
        if ((await resume.count()) > 0) {
            await resume.first().click();
            return;
        }
    }
    throw new Error('進行中セッションを持つ資格カタログが見つかりませんでした');
}

test.describe('mock-exam（受講生）', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, 'student');
    });

    test('受験履歴に採点済セッションが並ぶ', async ({ page }) => {
        await page.goto('/mock-exam-sessions');
        await expect(page.getByRole('heading', { name: '模試受験履歴' })).toBeVisible();
        // シードに合格・不合格セッションがあるため、両バッジが履歴に現れる
        await expect(page.getByText('不合格', { exact: true }).first()).toBeVisible();
        await expect(page.getByText('合格', { exact: true }).first()).toBeVisible();
        await expect(page.getByRole('link', { name: '結果を見る' }).first()).toBeVisible();
    });

    test('採点結果（合格）: スコア・合格表示・弱点ヒートマップ', async ({ page }) => {
        await page.goto('/mock-exam-sessions');
        const passRow = page.getByRole('row').filter({ has: page.getByText('合格', { exact: true }) }).first();
        await passRow.getByRole('link', { name: '結果を見る' }).click();

        await expect(page.getByText('合格点 突破')).toBeVisible();
        await expect(page.getByText('分野別正答率 — 弱点ヒートマップ')).toBeVisible();
        await expect(page.getByText('解答の正誤')).toBeVisible();
        await expect(page.getByText('合格可能性スコア')).toBeVisible();
    });

    test('採点結果（不合格）: 合格点未達の表示', async ({ page }) => {
        await page.goto('/mock-exam-sessions');
        const failRow = page.getByRole('row').filter({ has: page.getByText('不合格', { exact: true }) }).first();
        await failRow.getByRole('link', { name: '結果を見る' }).click();

        await expect(page.getByText('合格点未達')).toBeVisible();
        await expect(page.getByText('分野別正答率 — 弱点ヒートマップ')).toBeVisible();
    });

    test('動的: 選択肢を選ぶと自動保存され DOM が更新される', async ({ page }) => {
        await openInProgressTakePage(page);

        const root = page.locator('[data-quiz-autosave-root]');
        await expect(root).toBeVisible();

        // 先頭問題で「今チェックされていない選択肢」を1つ選び、確実に change を発火させる
        const firstQuestionId = await page.locator('[data-save-status]').first().getAttribute('data-save-status');
        const options = page.locator(`input[name="answer-${firstQuestionId}"]`);
        const optionCount = await options.count();
        let target = null;
        for (let i = 0; i < optionCount; i += 1) {
            if (!(await options.nth(i).isChecked())) {
                target = options.nth(i);
                break;
            }
        }
        expect(target, '未選択の選択肢が見つかること').not.toBeNull();

        // autosave の PATCH が 200 で返ることを検証
        const [response] = await Promise.all([
            page.waitForResponse(
                (resp) => resp.request().method() === 'PATCH' && resp.url().includes('/answers'),
            ),
            target.check(),
        ]);
        expect(response.ok()).toBeTruthy();

        // 保存ステータスが「自動保存済」に更新される（answer-autosave.js の DOM 反映）
        await expect(page.locator(`[data-save-status="${firstQuestionId}"]`)).toContainText('自動保存済');
    });
});

test.describe('mock-exam（認可）', () => {
    test('未ログインは受験履歴にアクセスできずログインへ飛ばされる', async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        await page.goto('/mock-exam-sessions');
        await expect(page).toHaveURL(/\/login$/);
        await context.close();
    });
});
