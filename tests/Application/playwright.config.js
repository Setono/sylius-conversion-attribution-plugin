// End-to-end tests (tests/e2e) running the test application in a real browser, see CLAUDE.md
const {defineConfig, devices} = require('@playwright/test');
const {APP_ENV, PHP} = require('../e2e/support/app');

const port = Number(process.env.E2E_PORT || 8089);

module.exports = defineConfig({
    testDir: '../e2e',
    globalSetup: require.resolve('../e2e/support/global-setup'),
    // The tests share one database and one PHP server
    workers: 1,
    forbidOnly: Boolean(process.env.CI),
    reporter: process.env.CI ? [['github'], ['html', {open: 'never'}]] : 'list',
    use: {
        baseURL: `http://127.0.0.1:${port}`,
        trace: 'retain-on-failure',
    },
    projects: [
        {
            name: 'chromium',
            use: {
                ...devices['Desktop Chrome'],
                // A regular desktop Chrome, not HeadlessChrome: the server side bot detection must not
                // drop the test browser's page views
                userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36',
            },
        },
    ],
    webServer: {
        command: `${PHP} -S 127.0.0.1:${port} -t public ../e2e/support/router.php`,
        cwd: __dirname,
        env: {...APP_ENV, PHP_CLI_SERVER_WORKERS: '4'},
        url: `http://127.0.0.1:${port}/robots.txt`,
        // Always our own server: an app already listening on the port would be tested instead
        reuseExistingServer: false,
    },
});
