/**
 * Playwright Test: pattern accessibility and visual regression (tests/visual).
 *
 *   PERGEL_BASE=http://localhost/pergel-tema PERGEL_CHANNEL=chrome npm run test:a11y
 *   PERGEL_BASE=http://127.0.0.1:8080 npm run test:visual            (CI, Linux)
 *
 * Baselines are made in CI on Linux and committed; fonts render differently
 * on other systems, so local runs check accessibility and overflow only.
 */
import { defineConfig } from 'playwright/test';

export default defineConfig( {
	testDir: 'tests/visual',
	snapshotPathTemplate: '{testDir}/baselines/{arg}{ext}',
	outputDir: 'test-results',
	fullyParallel: true,
	workers: 2,
	forbidOnly: !! process.env.CI,
	timeout: 60_000,
	reporter: process.env.CI ? [ [ 'list' ], [ 'html', { open: 'never', outputFolder: 'playwright-report' } ] ] : 'list',
	use: {
		channel: process.env.PERGEL_CHANNEL || undefined,
		reducedMotion: 'reduce',
	},
	expect: {
		toHaveScreenshot: { animations: 'disabled', caret: 'hide', maxDiffPixelRatio: 0.002 },
	},
} );
