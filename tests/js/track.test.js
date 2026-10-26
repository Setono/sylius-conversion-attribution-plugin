const path = require('path');

const SCRIPT = path.resolve(__dirname, '../../src/Resources/public/js/track.js');
const STORAGE_KEY = 'setono_conversion_attribution_last_seen';
const NOW = 1_700_000_000_000;
const SESSION_TIMEOUT = 1800;

/**
 * Runs the tracking script as if it was loaded by <script data-endpoint=... data-session-timeout=...>
 */
function run({endpoint = '/track', sessionTimeout = String(SESSION_TIMEOUT)} = {}) {
    const script = document.createElement('script');
    if (endpoint !== null) {
        script.setAttribute('data-endpoint', endpoint);
    }
    if (sessionTimeout !== null) {
        script.setAttribute('data-session-timeout', sessionTimeout);
    }

    stub(document, 'currentScript', script);
    jest.isolateModules(() => require(SCRIPT));
}

const stubbed = [];

/**
 * Shadows a (prototype) property of a browser object for the current test
 */
function stub(object, property, value) {
    Object.defineProperty(object, property, {value, configurable: true});
    stubbed.push([object, property]);
}

function visit(url, referrer = '') {
    window.history.replaceState(null, '', url);
    stub(document, 'referrer', referrer);
}

function lastSeen(minutesAgo) {
    window.localStorage.setItem(STORAGE_KEY, String(NOW - minutesAgo * 60 * 1000));
}

function postedPayload() {
    expect(fetch).toHaveBeenCalledTimes(1);

    return JSON.parse(fetch.mock.calls[0][1].body);
}

beforeEach(() => {
    window.localStorage.clear();
    visit('/');
    jest.spyOn(Date, 'now').mockReturnValue(NOW);
    global.fetch = jest.fn(() => Promise.resolve());
});

afterEach(() => {
    stubbed.splice(0).forEach(([object, property]) => delete object[property]);
    delete window.Cypress;
    jest.restoreAllMocks();
});

describe('a page view', () => {
    test('is posted to the endpoint', () => {
        visit('/product/t-shirt', 'https://google.com/search');

        run();

        expect(fetch).toHaveBeenCalledWith('/track', {
            method: 'POST',
            credentials: 'same-origin',
            keepalive: true,
            headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
            body: JSON.stringify({page: 'https://shop.example/product/t-shirt', referrer: 'https://google.com/search'}),
        });
    });

    test('is posted without a referrer when there is none', () => {
        run();

        expect(postedPayload()).toEqual({page: 'https://shop.example/'});
    });

    test('updates the last seen timestamp', () => {
        run();

        expect(window.localStorage.getItem(STORAGE_KEY)).toBe(String(NOW));
    });

    test('swallows a failed request', () => {
        // offline or blocked by an extension: no unhandled rejection in the console
        const request = {catch: jest.fn()};
        fetch.mockReturnValue(request);

        run();

        expect(request.catch).toHaveBeenCalledWith(expect.any(Function));
        expect(() => request.catch.mock.calls[0][0](new Error('offline'))).not.toThrow();
    });
});

describe('the session throttle', () => {
    test('skips a visitor seen within the session timeout, but extends the session', () => {
        lastSeen(29);

        run();

        expect(fetch).not.toHaveBeenCalled();
        expect(window.localStorage.getItem(STORAGE_KEY)).toBe(String(NOW));
    });

    test('tracks a visitor again once the session timed out', () => {
        lastSeen(30);

        run();

        expect(fetch).toHaveBeenCalledTimes(1);
    });

    test.each([
        ['a campaign', '/?utm_source=newsletter&utm_medium=email', ''],
        ['an ad click', '/product?gclid=abc', ''],
        ['pagination, sorting and search', '/taxons/t-shirts?page=2&sorting[price]=asc&criteria[search][value]=red', ''],
        ['returning from a payment gateway', '/en_US/order/thank-you?token=EC-123&PayerID=ABC', 'https://www.paypal.com/'],
        ['another site', '/', 'https://facebook.com/'],
        ['the shop itself', '/cart', 'https://shop.example/product/t-shirt'],
    ])('is not bypassed by a page view from %s', (_, url, referrer) => {
        lastSeen(1);
        visit(url, referrer);

        run();

        expect(fetch).not.toHaveBeenCalled();
    });

    test('tracks the first page view of a session with its referrer', () => {
        visit('/', 'https://facebook.com/');

        run();

        expect(postedPayload()).toEqual({page: 'https://shop.example/', referrer: 'https://facebook.com/'});
    });

    test.each([
        ['missing', null],
        ['not a number', 'soon'],
    ])('tracks every page view when the session timeout is %s', (_, sessionTimeout) => {
        lastSeen(1);

        run({sessionTimeout});

        expect(fetch).toHaveBeenCalledTimes(1);
    });

    test('starts a new session when the stored timestamp is unreadable', () => {
        window.localStorage.setItem(STORAGE_KEY, 'not a timestamp');

        run();

        expect(fetch).toHaveBeenCalledTimes(1);
        expect(window.localStorage.getItem(STORAGE_KEY)).toBe(String(NOW));
    });

    test('tracks every page view when storage is unavailable', () => {
        lastSeen(1);
        jest.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
            throw new DOMException('The operation is insecure.', 'SecurityError');
        });

        run();

        expect(fetch).toHaveBeenCalledTimes(1);
    });
});

describe('nothing is tracked', () => {
    test('in a browser controlled through WebDriver (Selenium, Playwright, Puppeteer, ...)', () => {
        stub(window.navigator, 'webdriver', true);

        run();

        expect(fetch).not.toHaveBeenCalled();
        expect(window.localStorage.getItem(STORAGE_KEY)).toBeNull();
    });

    test('in a browser controlled by Cypress', () => {
        window.Cypress = {};

        run();

        expect(fetch).not.toHaveBeenCalled();
    });

    test('without an endpoint', () => {
        run({endpoint: null});

        expect(fetch).not.toHaveBeenCalled();
    });

    test('when the script cannot find its own tag', () => {
        stub(document, 'currentScript', null);
        jest.isolateModules(() => require(SCRIPT));

        expect(fetch).not.toHaveBeenCalled();
    });
});

describe('without document.currentScript', () => {
    test('the script finds its tag by its id', () => {
        // e.g. when Cloudflare Rocket Loader runs the script
        const script = document.createElement('script');
        script.id = 'setono-sylius-conversion-attribution';
        script.setAttribute('data-endpoint', '/track');
        script.setAttribute('data-session-timeout', String(SESSION_TIMEOUT));
        document.head.appendChild(script);

        try {
            stub(document, 'currentScript', null);
            jest.isolateModules(() => require(SCRIPT));

            expect(fetch).toHaveBeenCalledWith('/track', expect.objectContaining({method: 'POST'}));
        } finally {
            script.remove();
        }
    });
});

describe('a prerendered page', () => {
    test('is tracked once it is shown, and only once', () => {
        stub(document, 'prerendering', true);

        // no session throttle, so it can't hide a second POST
        run({sessionTimeout: '0'});

        expect(fetch).not.toHaveBeenCalled();
        expect(window.localStorage.getItem(STORAGE_KEY)).toBeNull();

        document.dispatchEvent(new Event('prerenderingchange'));
        document.dispatchEvent(new Event('prerenderingchange'));

        expect(fetch).toHaveBeenCalledTimes(1);
    });
});
