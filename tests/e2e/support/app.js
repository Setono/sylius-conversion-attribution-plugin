const {execFileSync} = require('child_process');
const path = require('path');

const APP_DIR = path.resolve(__dirname, '../../Application');

// The global setup drops and recreates this database, so it comes from its own variable, never from an
// exported DATABASE_URL (which may point at another project's database), and its name must end in _e2e
const DATABASE_URL = process.env.E2E_DATABASE_URL;
if (!DATABASE_URL) {
    throw new Error('Set E2E_DATABASE_URL to a database the end-to-end tests may drop and recreate, e.g. mysql://root@127.0.0.1:3306/setono_sylius_conversion_attribution_e2e?serverVersion=8.0');
}

const DATABASE = decodeURIComponent(new URL(DATABASE_URL).pathname.replace(/^\//, ''));
if (!DATABASE.endsWith('_e2e')) {
    throw new Error(`The end-to-end tests drop and recreate their database, so its name must end in _e2e, but E2E_DATABASE_URL names "${DATABASE}"`);
}

// The end-to-end tests run the test application in the prod environment: the test environment's mock
// session storage doesn't send cookies, so a real browser couldn't keep a cart or log in
const APP_ENV = {APP_ENV: 'prod', APP_DEBUG: '0', DATABASE_URL};

const PHP = process.env.PHP_BINARY || 'php';

function php(args, options = {}) {
    return execFileSync(PHP, args, {cwd: APP_DIR, env: {...process.env, ...APP_ENV}, ...options});
}

module.exports = {
    APP_DIR,
    APP_ENV,
    DATABASE,
    PHP,

    console(...args) {
        php(['bin/console', ...args, '--no-interaction'], {stdio: 'inherit'});
    },

    /**
     * The sources tracked for a client id and the client's completed orders, as stored in the database
     */
    state(clientId) {
        return JSON.parse(php([path.resolve(__dirname, 'state.php'), clientId]).toString());
    },
};
