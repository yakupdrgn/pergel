/**
 * Playwright Test: the shop end to end (tests/shop), for the CI shop job.
 *
 *   WP_DIR=/tmp/pergel-wp PERGEL_BASE=http://127.0.0.1:8080 npm run test:shop
 *
 * The tests change shop settings through WP-CLI and put them back, so they
 * run one at a time. On Windows, where wp is a .cmd shim Node cannot start
 * by itself, set PERGEL_WP_PHAR to wp-cli.phar (run with php).
 */
import { defineConfig } from 'playwright/test';

export default defineConfig( {
	testDir: 'tests/shop',
	outputDir: 'test-results/shop',
	fullyParallel: false,
	workers: 1,
	forbidOnly: !! process.env.CI,
	timeout: 60_000,
	reporter: process.env.CI ? [ [ 'list' ], [ 'html', { open: 'never', outputFolder: 'playwright-report/shop' } ] ] : 'list',
	use: {
		baseURL: ( process.env.PERGEL_BASE || 'http://127.0.0.1:8080' ).replace( /\/$/, '' ),
		channel: process.env.PERGEL_CHANNEL || undefined,
		reducedMotion: 'reduce',
		trace: 'retain-on-failure',
	},
} );
