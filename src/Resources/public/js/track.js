/*
 * Tracking script of setono/sylius-conversion-attribution-plugin.
 *
 * Static by design: it is identical for every visitor (and so is the <script> tag that loads it), so
 * full page caches can store pages carrying it. Everything per-visitor is resolved server side when
 * the page view is POSTed: the client id from the setono_client_id cookie riding on the request, and
 * the traffic source from the posted page URL and referrer.
 *
 * Configured through attributes on its own <script> tag:
 * - data-endpoint: the URL to POST page views to
 * - data-session-timeout: seconds within which a returning visitor isn't tracked again
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'setono_conversion_attribution_last_seen';

    var script = document.currentScript;

    // Automated browsers (Selenium, Puppeteer, Playwright, Cypress, headless Chrome, ...) execute
    // JavaScript, so they sail past the "bots don't run JS" filter, and many of them send a real
    // browser User-Agent that the server side bot detection cannot flag. They do expose themselves
    // through the standardized automation flag (real browsers leave it false or undefined), or in
    // Cypress' case through its global. This also keeps the shop's own end-to-end tests out of the data
    if (!script || navigator.webdriver || window.Cypress) {
        return;
    }

    var endpoint = script.getAttribute('data-endpoint');
    if (!endpoint) {
        return;
    }

    var sessionTimeout = parseInt(script.getAttribute('data-session-timeout'), 10) * 1000;

    function hasCampaignHint() {
        // A query string (utm_*, gclid, fbclid, ...) or a cross-host referrer may carry a new traffic
        // source; the server side source matcher makes the definitive call
        if (window.location.search !== '') {
            return true;
        }

        if (document.referrer === '') {
            return false;
        }

        try {
            return new URL(document.referrer).host !== window.location.host;
        } catch (e) {
            return true;
        }
    }

    function track() {
        var now = Date.now();
        var last = NaN;

        // A rolling "last seen" timestamp: every page view extends the session
        try {
            last = parseInt(window.localStorage.getItem(STORAGE_KEY), 10);
            window.localStorage.setItem(STORAGE_KEY, String(now));
        } catch (e) {
            // storage unavailable (disabled, sandboxed iframe, ...): track every page view
        }

        if (!hasCampaignHint() && now - last < sessionTimeout) {
            return;
        }

        var payload = {page: window.location.href};
        if (document.referrer !== '') {
            payload.referrer = document.referrer;
        }

        fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            keepalive: true,
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        }).catch(function () {
            // offline or blocked: nothing to do, and no unhandled rejection in the console
        });
    }

    // A prerendered page (speculation rules, browser address bar predictions) runs its scripts before
    // the visitor ever sees it, and maybe never. Wait until it is actually shown
    if (document.prerendering) {
        document.addEventListener('prerenderingchange', track, {once: true});
    } else {
        track();
    }
})();
