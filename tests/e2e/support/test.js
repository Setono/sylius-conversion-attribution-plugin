const base = require('@playwright/test');
const app = require('./app');

/**
 * Playwright's own test, extended with:
 * - the `webdriver` option: Playwright flags its browser with navigator.webdriver, which the tracking
 *   script rightly treats as automation and skips. Tests run as a regular browser unless they opt in
 * - `trackRequests`: the POST /track requests a page sends
 * - `clientId()` and `state()`: the visitor's client id and what got stored for it
 */
exports.test = base.test.extend({
    webdriver: [false, {option: true}],

    context: async ({context, webdriver}, use) => {
        await context.addInitScript((value) => {
            Object.defineProperty(Navigator.prototype, 'webdriver', {get: () => value});
        }, webdriver);

        await use(context);
    },

    trackRequests: async ({page}, use) => {
        const requests = [];
        page.on('request', (request) => {
            if (request.method() === 'POST' && new URL(request.url()).pathname === '/track') {
                requests.push(request);
            }
        });

        await use(requests);
    },

    clientId: async ({context}, use) => {
        await use(async () => {
            const cookie = (await context.cookies()).find((c) => c.name === 'setono_client_id');

            // version.firstSeenAt.lastSeenAt.clientId, see setono/client's Cookie
            return cookie ? decodeURIComponent(cookie.value).split('.').slice(3).join('.') : null;
        });
    },

    state: async ({clientId}, use) => {
        await use(async () => {
            const id = await clientId();

            return null === id ? {sources: [], orders: []} : app.state(id);
        });
    },
});

exports.expect = base.expect;

/**
 * Waits for the page view's POST /track to be answered
 */
exports.tracked = (page) => page.waitForResponse((response) => response.request().method() === 'POST' && new URL(response.url()).pathname === '/track');

/**
 * Makes the tracking script consider the session timed out, as if the visitor had been inactive for
 * longer than session_timeout (1800 s), so its next page view is tracked again
 */
exports.endSession = (page) => page.evaluate(() => {
    window.localStorage.setItem('setono_conversion_attribution_last_seen', String(Date.now() - 31 * 60 * 1000));
});
