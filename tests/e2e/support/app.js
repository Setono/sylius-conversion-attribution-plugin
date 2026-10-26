const {execFileSync} = require('child_process');
const path = require('path');

const APP_DIR = path.resolve(__dirname, '../../Application');

// The end-to-end tests run the test application in the prod environment: the test environment's mock
// session storage doesn't send cookies, so a real browser couldn't keep a cart or log in
const APP_ENV = {APP_ENV: 'prod', APP_DEBUG: '0'};

const PHP = process.env.PHP_BINARY || 'php';

function php(args, options = {}) {
    return execFileSync(PHP, args, {cwd: APP_DIR, env: {...process.env, ...APP_ENV}, ...options});
}

module.exports = {
    APP_DIR,
    APP_ENV,
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
