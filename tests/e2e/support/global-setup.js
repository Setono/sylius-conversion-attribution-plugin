const fs = require('fs');
const path = require('path');
const app = require('./app');

/**
 * Installs the test application from scratch: database (E2E_DATABASE_URL's, which is dropped first, see
 * app.js), fixtures, bundle assets (the tracking script) and a warm prod cache. Set E2E_SKIP_SETUP=1 to
 * rerun the tests against an installed application
 */
module.exports = () => {
    if (process.env.E2E_SKIP_SETUP) {
        return;
    }

    // Without debug, Symfony never checks whether a cached container is stale, so a prod cache compiled
    // against other dependencies (or another version of the plugin) would be used as is
    fs.rmSync(path.join(app.APP_DIR, 'var/cache/prod'), {recursive: true, force: true});

    app.console('doctrine:database:drop', '--force', '--if-exists');
    app.console('doctrine:database:create');
    app.console('doctrine:schema:create');
    app.console('sylius:fixtures:load', 'default');
    app.console('assets:install', 'public');
    app.console('cache:clear');
};
