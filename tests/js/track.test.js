const path = require('path');

const SCRIPT = path.resolve(__dirname, '../../src/Resources/public/js/track.js');
const STORAGE_KEY = 'setono_conversion_attribution_last_seen';
const NOW = 1_700_000_000_000;
const SESSION_TIMEOUT = 1800;

/**
 * Runs the tracking script as if it was loaded by <script data-endpoint=... data-session-timeout=...>
 */
function run({endpoint = '/track', sessionTimeout = String(SESSION_TIMEOUT), campaignParameters = 'utm_source gclid fbclid'} = {}) {
    const script = document.createElement('script');
    if (endpoint !== null) {
        script.setAttribute('data-endpoint', endpoint);
    }
    if (sessionTimeout !== null) {
        script.setAttribute('data-session-timeout', sessionTimeout);
    }
    if (campaignParameters !== null) {
        script.setAttribute('data-campaign-parameters', campaignParameters);
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
            headers: {'Content-Type': 'application/json'},
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
        ['a utm_source', '/?utm_source=newsletter&utm_medium=email'],
        ['a click id', '/product?gclid=abc'],
        ['a campaign parameter after others', '/?page=2&fbclid=abc'],
    ])('is bypassed by %s, which may carry a new traffic source', (_, url) => {
        lastSeen(1);
        visit(url);

        run();

        expect(postedPayload()).toEqual({page: 'https://shop.example' + url});
    });

    test.each([
        ['pagination, sorting and search', '/taxons/t-shirts?page=2&sorting[price]=asc&criteria[search][value]=red'],
        ['an empty campaign parameter', '/?gclid='],
        ['a parameter that merely contains a campaign parameter name', '/?xgclid=abc&utm_source_x=y'],
    ])('is not bypassed by %s', (_, url) => {
        lastSeen(1);
        visit(url);

        run();

        expect(fetch).not.toHaveBeenCalled();
    });

    test.each([
        ['missing', null],
        ['empty', ''],
    ])('is not bypassed by any query parameter when the campaign parameters are %s', (_, campaignParameters) => {
        lastSeen(1);
        visit('/?utm_source=newsletter');

        run({campaignParameters});

        expect(fetch).not.toHaveBeenCalled();
    });

    test('is bypassed by a cross-host referrer', () => {
        lastSeen(1);
        visit('/', 'https://facebook.com/');

        run();

        expect(postedPayload()).toEqual({page: 'https://shop.example/', referrer: 'https://facebook.com/'});
    });

    test('is not bypassed by a same-host referrer', () => {
        lastSeen(1);
        visit('/cart', 'https://shop.example/product/t-shirt');

        run();

        expect(fetch).not.toHaveBeenCalled();
    });

    test('is bypassed by an unparsable referrer', () => {
        lastSeen(1);
        visit('/', 'not a url');

        run();

        expect(fetch).toHaveBeenCalledTimes(1);
    });

    test.each([
        ['missing', null],
        ['not a number', 'soon'],
    ])('tracks every page view when the session timeout is %s', (_, sessionTimeout) => {
        lastSeen(1);

        run({sessionTimeout});

        expect(fetch).toHaveBeenCalledTimes(1);
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
        // document.currentScript is null for module scripts and inside callbacks
        stub(document, 'currentScript', null);
        jest.isolateModules(() => require(SCRIPT));

        expect(fetch).not.toHaveBeenCalled();
    });
});

describe('a prerendered page', () => {
    test('is tracked once it is shown, and only once', () => {
        // a campaign URL, so the session throttle can't hide a second POST
        visit('/?utm_source=newsletter');
        stub(document, 'prerendering', true);

        run();

        expect(fetch).not.toHaveBeenCalled();
        expect(window.localStorage.getItem(STORAGE_KEY)).toBeNull();

        document.dispatchEvent(new Event('prerenderingchange'));
        document.dispatchEvent(new Event('prerenderingchange'));

        expect(fetch).toHaveBeenCalledTimes(1);
    });
});
