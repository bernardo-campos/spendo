import assert from 'node:assert/strict';
import test from 'node:test';
import { submitLogout } from '../../resources/js/services/logout.js';

test('clears private offline data before submitting logout', async () => {
    const calls = [];
    let finishClear;
    const clear = new Promise((resolve) => {
        finishClear = resolve;
    });
    const form = { submit: () => calls.push('submit') };
    const event = {
        preventDefault: () => calls.push('preventDefault'),
        currentTarget: form,
    };
    const offlineClient = { clear: async () => {
        calls.push('clear-start');
        await clear;
        calls.push('clear-end');
    } };
    const cacheStorage = {
        keys: async () => ['spendo-private-shell-v1', 'other-app-cache', 'spendo-private-shell-v2'],
        delete: async (name) => { calls.push(`delete:${name}`); },
    };
    const serviceWorker = { controller: { postMessage: ({ type }) => calls.push(type) } };

    const logout = submitLogout(event, offlineClient, cacheStorage, serviceWorker);
    await new Promise(setImmediate);
    assert.deepEqual(calls, ['preventDefault', 'clear-start']);

    event.currentTarget = null;
    finishClear();
    await logout;

    assert.deepEqual(calls, [
        'preventDefault',
        'clear-start',
        'clear-end',
        'delete:spendo-private-shell-v1',
        'delete:spendo-private-shell-v2',
        'CLEAR_PRIVATE_OFFLINE_DATA',
        'submit',
    ]);
});

test('logout removes push from the account and browser before submitting the form', async () => {
    const calls = [];
    const previousHttp = globalThis.axios;
    globalThis.axios = { delete: async (path, options) => { calls.push([path, options]); } };

    try {
        await submitLogout({
            preventDefault() {},
            currentTarget: { submit: () => { calls.push('submit'); } },
        }, { clear: async () => {} }, null, {
            getRegistration: async () => ({ pushManager: { getSubscription: async () => ({
                endpoint: 'https://web.push.apple.com/device',
                unsubscribe: async () => { calls.push('unsubscribe'); return true; },
            }) } }),
        });
    } finally {
        globalThis.axios = previousHttp;
    }

    assert.deepEqual(calls, [
        ['/push-subscriptions', { data: { endpoint: 'https://web.push.apple.com/device' } }],
        'unsubscribe',
        'submit',
    ]);
});

test('submits logout and clears the shell cache when IndexedDB cleanup fails', async () => {
    let submitted = false;
    let cacheDeleted = false;
    const originalConsoleError = console.error;
    console.error = () => {};

    try {
        await submitLogout({
            preventDefault: () => {},
            currentTarget: { submit: () => { submitted = true; } },
        }, { clear: async () => { throw new Error('IndexedDB unavailable'); } }, {
            keys: async () => ['spendo-private-shell-v2'],
            delete: async () => { cacheDeleted = true; },
        }, null);
    } finally {
        console.error = originalConsoleError;
    }

    assert.equal(submitted, true);
    assert.equal(cacheDeleted, true);
});
