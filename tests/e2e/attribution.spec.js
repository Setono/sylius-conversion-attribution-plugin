const {test, expect, tracked, endSession} = require('./support/test');

test('an order is credited to the campaign the customer came from, not to a payment gateway or a later visit', async ({page, trackRequests, state}) => {
    // landing from a newsletter
    let response = tracked(page);
    await page.goto('/en_US/?utm_source=newsletter&utm_medium=email&utm_campaign=fall');
    await response;

    // browsing to a product and checking out as a guest
    await page.locator('a[href*="/products/"]').first().click();
    await page.locator('#sylius-product-adding-to-cart button[type=submit]').click();
    await page.waitForURL(/\/cart\/$/);

    await page.goto('/en_US/checkout/address');
    await page.fill('#sylius_checkout_address_customer_email', 'e2e@example.com');
    await page.fill('#sylius_checkout_address_billingAddress_firstName', 'Ada');
    await page.fill('#sylius_checkout_address_billingAddress_lastName', 'Lovelace');
    await page.fill('#sylius_checkout_address_billingAddress_street', '1 Main Street');
    await page.selectOption('#sylius_checkout_address_billingAddress_countryCode', 'FR');
    await page.fill('#sylius_checkout_address_billingAddress_city', 'Paris');
    await page.fill('#sylius_checkout_address_billingAddress_postcode', '75001');
    await page.click('#next-step');
    // Sylius' fixtures are random, so a method isn't always preselected: pick the first one
    await page.waitForURL(/\/checkout\/select-shipping$/);
    await page.locator('input[type=radio][name^="sylius_checkout_select_shipping"]').first().check({force: true});
    await page.click('#next-step');
    await page.waitForURL(/\/checkout\/select-payment$/);
    await page.locator('input[type=radio][name^="sylius_checkout_select_payment"]').first().check({force: true});
    await page.click('#next-step');
    await page.waitForURL(/\/checkout\/complete$/);
    await page.getByRole('button', {name: 'Place order'}).click();
    await page.waitForURL(/\/order\/thank-you$/);

    const {orders} = await state();
    expect(orders).toHaveLength(1);

    // Sylius completes the checkout before the payment, so the customer returns from the payment gateway
    // afterwards, within the session. That must not be recorded as a referral
    await page.goto('/en_US/order/thank-you?token=EC-123&PayerID=ABC', {referer: 'https://www.paypal.com/checkoutnow'});
    await page.waitForLoadState('networkidle');
    expect(await page.evaluate(() => document.referrer)).toMatch(/^https:\/\/www\.paypal\.com\//);
    expect(trackRequests).toHaveLength(1); // the landing page view only

    // later, in a new session, the customer comes back through an ad. The database stores times with
    // second precision, so make sure that visit isn't in the same second as the checkout (a real one never is)
    await endSession(page);
    await page.waitForTimeout(1000);
    response = tracked(page);
    await page.goto('/en_US/?utm_source=later&utm_medium=cpc');
    await response;

    expect((await state()).sources.map((source) => source.source)).toEqual(['newsletter', 'later']);

    await page.goto('/admin/login');
    await page.fill('#_username', 'sylius');
    await page.fill('#_password', 'sylius');
    await page.getByRole('button', {name: 'Login'}).click();
    await page.waitForURL(/\/admin\/$/);

    await page.goto(`/admin/orders/${orders[0].id}`);
    const attribution = page.locator('h4', {hasText: 'Attribution'}).locator('xpath=..');
    await expect(attribution).toContainText('newsletter');
    await expect(attribution).toContainText('email');
    await expect(attribution).toContainText('fall');
    await expect(attribution).not.toContainText('later');
});
