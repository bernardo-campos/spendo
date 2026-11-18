import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { createPushNotifications, disablePushNotifications, unsubscribeBrowserPush } from '../../resources/js/services/pushNotifications.js';

const setup = () => {
    const calls = [];
    let subscription = null;
    const created = {
        endpoint: 'https://web.push.apple.com/device',
        toJSON: () => ({ endpoint: created.endpoint, keys: { p256dh: 'key', auth: 'auth' } }),
        unsubscribe: async () => { calls.push('unsubscribe'); subscription = null; return true; },
    };
    const worker = {
        active: {},
        pushManager: {
            getSubscription: async () => subscription,
            subscribe: async (options) => { calls.push(['subscribe', options]); subscription = created; return created; },
        },
    };
    const browser = {
        navigator: { serviceWorker: { getRegistration: async () => worker } },
        PushManager: {},
        Notification: {
            permission: 'granted',
            requestPermission: () => { calls.push('permission'); return Promise.resolve('granted'); },
        },
    };
    const http = {
        get: async () => ({ data: { configured: true, public_key: 'BAEC' } }),
        post: async (...args) => { calls.push(['post', ...args]); },
        delete: async (...args) => { calls.push(['delete', ...args]); },
    };

    return { calls, created, browser, http, worker, service: createPushNotifications(http, browser), setSubscription: (value) => { subscription = value; } };
};

test('unsupported browsers do not make subscription requests', async () => {
    const service = createPushNotifications({ get: () => { throw new Error('unexpected request'); } }, {});
    assert.deepEqual(await service.initialize(), { supported: false, configured: false, enabled: false });
});

test('permission is requested immediately and a new subscription is persisted', async () => {
    const { calls, service, created } = setup();
    const enabling = service.enable('BAEC');
    assert.deepEqual(calls, ['permission']);
    await enabling;
    assert.equal(calls[1][0], 'subscribe');
    assert.deepEqual([...calls[1][1].applicationServerKey], [4, 1, 2]);
    assert.equal(calls[1][1].userVisibleOnly, true);
    assert.deepEqual(calls[2], ['post', '/push-subscriptions', created.toJSON()]);
});

test('denying permission does not subscribe or persist anything', async () => {
    const { browser, service, calls } = setup();
    browser.Notification.requestPermission = async () => 'denied';
    await assert.rejects(service.enable('BAEC'), /Permití las notificaciones/);
    assert.deepEqual(calls, []);
});

test('an existing subscription is synchronized without requesting permission again', async () => {
    const { service, created, setSubscription, calls } = setup();
    setSubscription(created);
    assert.equal((await service.initialize()).enabled, true);
    assert.deepEqual(calls, [['post', '/push-subscriptions', created.toJSON()]]);
});

test('a new browser subscription is removed if saving it fails', async () => {
    const { service, http, calls } = setup();
    http.post = async () => { throw new Error('server unavailable'); };
    await assert.rejects(service.enable('BAEC'), /server unavailable/);
    assert.equal(calls.at(-1), 'unsubscribe');
});

test('disabled server configuration does not register existing subscriptions', async () => {
    const { service, http, created, setSubscription, calls } = setup();
    http.get = async () => ({ data: { configured: false, public_key: null } });
    setSubscription(created);
    assert.equal((await service.initialize()).enabled, false);
    assert.deepEqual(calls, []);
});

test('disabling removes the server subscription and unsubscribes the browser', async () => {
    const { service, created, setSubscription, calls } = setup();
    setSubscription(created);
    await service.disable();
    assert.deepEqual(calls, [['delete', '/push-subscriptions', { data: { endpoint: created.endpoint } }], 'unsubscribe']);
});

test('the browser unsubscribes even when the server cannot be reached during logout', async () => {
    const { http, browser, created, setSubscription, calls } = setup();
    setSubscription(created);
    http.delete = async () => { throw new Error('offline'); };
    await assert.rejects(disablePushNotifications(http, browser.navigator.serviceWorker), /offline/);
    assert.deepEqual(calls, ['unsubscribe']);
});

test('changing accounts unsubscribes the previous browser subscription', async () => {
    const { browser, created, setSubscription, calls } = setup();
    setSubscription(created);
    await unsubscribeBrowserPush(browser.navigator.serviceWorker);
    assert.deepEqual(calls, ['unsubscribe']);
});

const loadWorker = () => {
    const handlers = {};
    const notifications = [];
    const opened = [];
    const self = {
        addEventListener: (name, handler) => { handlers[name] = handler; },
        location: { origin: 'https://spendo.test' },
        registration: { showNotification: async (...args) => { notifications.push(args); } },
        clients: { matchAll: async () => [], openWindow: async (url) => { opened.push(url); } },
    };
    runInNewContext(readFileSync('public/service-worker.js', 'utf8'), { self, URL });
    return { handlers, notifications, opened, self };
};

test('the existing service worker displays the push payload', async () => {
    const { handlers, notifications } = loadWorker();
    let completed;
    handlers.push({
        data: { json: () => ({ title: 'Gasto registrado', body: 'Uber: ARS 100', tag: 'expense-1', data: { url: '/app/transactions/1/edit' } }) },
        waitUntil: (promise) => { completed = promise; },
    });
    await completed;
    assert.equal(notifications[0][0], 'Gasto registrado');
    assert.equal(notifications[0][1].body, 'Uber: ARS 100');
    assert.equal(notifications[0][1].data.url, '/app/transactions/1/edit');
});

test('notification clicks open the expense and reject external destinations', async () => {
    for (const [destination, expected] of [
        ['/app/transactions/1/edit', 'https://spendo.test/app/transactions/1/edit'],
        ['https://attacker.test/app/expenses', 'https://spendo.test/app'],
    ]) {
        const { handlers, opened } = loadWorker();
        let completed;
        handlers.notificationclick({ notification: { close() {}, data: { url: destination } }, waitUntil: (promise) => { completed = promise; } });
        await completed;
        assert.deepEqual(opened, [expected]);
    }
});

test('notification clicks navigate and focus an existing Spendo window', async () => {
    const { handlers, self } = loadWorker();
    const calls = [];
    const client = {
        url: 'https://spendo.test/app',
        navigate: async (url) => { calls.push(url); return client; },
        focus: async () => { calls.push('focus'); },
    };
    self.clients.matchAll = async () => [client];
    let completed;
    handlers.notificationclick({ notification: { close() {}, data: { url: '/app/transactions/3/edit' } }, waitUntil: (promise) => { completed = promise; } });
    await completed;
    assert.deepEqual(calls, ['https://spendo.test/app/transactions/3/edit', 'focus']);
});
