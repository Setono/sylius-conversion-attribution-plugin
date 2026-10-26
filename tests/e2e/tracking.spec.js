const {test, expect, tracked, endSession} = require('./support/test');

const LANDING_PAGE = '/en_US/?utm_source=newsletter&utm_medium=email&utm_campaign=fall';

test('a landing page view is tracked and stored', async ({page, request, state}) => {
    const response = tracked(page);
    await page.goto(LANDING_PAGE);
    expect((await response).status()).toBe(204);

    const tag = page.locator('script#setono-sylius-conversion-attribution');
    await expect(tag).toHaveAttribute('src', /^\/bundles\/setonosyliusconversionattributionplugin\/js\/track\.js\?v=[0-9a-f]{12}$/);
    await expect(tag).toHaveAttribute('defer', '');
    await expect(tag).toHaveAttribute('data-endpoint', '/track');
    await expect(tag).toHaveAttribute('data-session-timeout', '1800');

    // assets:install put the script in place
    const script = await request.get(await tag.getAttribute('src'));
    expect(script.status()).toBe(200);
    expect(await script.text()).toContain('setono_conversion_attribution_last_seen');

    // stored for the client id in the visitor's cookie, with the traffic source matched server side
    expect((await state()).sources).toEqual([{
        source: 'newsletter',
        medium: 'email',
        campaign: 'fall',
        page: page.url(),
        referrer: null,
        ip: '127.0.0.1',
        userAgent: await page.evaluate(() => navigator.userAgent),
    }]);
});

test('every visitor gets the same tag, so full page caches can store the page', async ({browser}) => {
    const tags = [];
    for (const options of [{}, {userAgent: 'Mozilla/5.0 (X11; Linux x86_64; rv:131.0) Gecko/20100101 Firefox/131.0', extraHTTPHeaders: {Referer: 'https://www.google.com/'}}]) {
        const context = await browser.newContext(options);
        const page = await context.newPage();
        await page.goto('/en_US/');
        tags.push(await page.locator('script#setono-sylius-conversion-attribution').evaluate((tag) => tag.outerHTML));
        await context.close();
    }

    expect(tags[0]).toBe(tags[1]);
});

test('only the first page view of a session is tracked', async ({page, trackRequests, state}) => {
    const visit = async (navigate) => {
        const before = trackRequests.length;
        await navigate();
        await page.waitForLoadState('networkidle');

        return trackRequests.length - before;
    };

    expect(await visit(() => page.goto(LANDING_PAGE))).toBe(1);

    // browsing the shop
    expect(await visit(() => page.locator('a[href*="/products/"]').first().click())).toBe(0);
    const productPage = page.url();

    // pagination, sorting and search query strings
    const taxonPage = await page.locator('a[href*="/taxons/"]').first().getAttribute('href');
    expect(await visit(() => page.goto(`${taxonPage}?page=1&limit=9&sorting[price]=asc&criteria[search][value]=a`))).toBe(0);

    // an ad click, still within the session
    expect(await visit(() => page.goto(`${productPage}?gclid=abc`))).toBe(0);

    // the same ad click after the session timed out
    await endSession(page);
    expect(await visit(() => page.goto(`${productPage}?gclid=abc`))).toBe(1);

    expect((await state()).sources).toEqual([
        expect.objectContaining({source: 'newsletter', medium: 'email', campaign: 'fall'}),
        expect.objectContaining({source: 'google', medium: 'cpc', page: `${productPage}?gclid=abc`}),
    ]);
});

test.describe('in an automated browser', () => {
    test.use({webdriver: true});

    test('nothing is tracked', async ({page, trackRequests, state}) => {
        await page.goto(LANDING_PAGE);
        await page.waitForLoadState('networkidle');

        expect(trackRequests).toHaveLength(0);
        expect((await state()).sources).toEqual([]);
    });
});

test.describe('a bot running JavaScript', () => {
    test.use({userAgent: 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'});

    test('is dropped server side', async ({page, state}) => {
        const response = tracked(page);
        await page.goto(LANDING_PAGE);
        expect((await response).status()).toBe(204);

        expect((await state()).sources).toEqual([]);
    });
});
