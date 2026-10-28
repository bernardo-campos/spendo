import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { runInNewContext } from 'node:vm';
import { parse } from '@babel/parser';

const source = readFileSync(resolve('resources/js/services/offlineClient.js'), 'utf8');
const declarations = parse(source, { sourceType: 'module' }).program.body;

const loadFunction = (name, dependencies) => {
    const declaration = declarations.find((node) => node.type === 'VariableDeclaration'
        && node.declarations.some((item) => item.id.name === name));
    const context = { ...dependencies };

    assert.ok(declaration, `Missing ${name}`);
    runInNewContext(`${source.slice(declaration.start, declaration.end)}\nglobalThis.subject = ${name};`, context);

    return context.subject;
};

const routeInfo = (url) => ({ type: url.includes('/billing-cycles') ? 'billing-cycles' : 'transactions' });

test('a duplicate cycle rejected on a prior visit is removed without discarding other failed changes', async () => {
    const rolledBack = [];
    const deleted = [];
    const mutations = [
        { id: 'old-cycle-1', method: 'post', url: '/cards/1/billing-cycles', status: 'failed', errorMessage: 'El valor del campo closing date ya está registrado.' },
        { id: 'old-cycle-2', method: 'post', url: '/cards/1/billing-cycles', status: 'failed', statusCode: 422 },
        { id: 'other-change', method: 'post', url: '/transactions', status: 'failed', statusCode: 422 },
    ];
    const discard = loadFunction('discardPreviousBillingCycleValidationErrors', {
        navigator: { onLine: true },
        mutationValues: async () => mutations,
        routeInfo,
        rollbackBillingCycle: async (mutation) => rolledBack.push(mutation.id),
        deleteMutation: async (id) => deleted.push(id),
    });

    await discard();

    assert.deepEqual(rolledBack, ['old-cycle-1', 'old-cycle-2']);
    assert.deepEqual(deleted, ['old-cycle-1', 'old-cycle-2']);
});

test('a current cycle validation error rolls back its optimistic change and remains visible only in memory', async () => {
    const mutation = { id: 'new-cycle', method: 'post', url: '/cards/1/billing-cycles', status: 'pending' };
    const deleted = [];
    const rolledBack = [];
    const syncState = { value: { status: 'pending', validationError: '' } };
    const processSync = loadFunction('processSync', {
        userId: { value: '1' },
        navigator: { onLine: true },
        mutationValues: async () => [mutation],
        syncState,
        isMissingTransaction: () => false,
        resolveUrl: async (url) => url,
        resolveReferences: async (value) => value,
        window: { axios: { request: async () => {
            throw { response: { status: 422, data: { errors: { closing_date: ['Ya existe un ciclo con esa fecha.'] } } } };
        } } },
        routeInfo,
        rollbackBillingCycle: async (item) => rolledBack.push(item.id),
        deleteMutation: async (id) => deleted.push(id),
        putMutation: async () => assert.fail('A rejected cycle must not remain in the offline queue'),
        updateSyncState: async (status) => { syncState.value = { ...syncState.value, status }; },
    });

    await processSync();

    assert.deepEqual(rolledBack, ['new-cycle']);
    assert.deepEqual(deleted, ['new-cycle']);
    assert.equal(syncState.value.status, 'synced');
    assert.equal(syncState.value.validationError, 'Ya existe un ciclo con esa fecha.');
});

test('rolling back a rejected new cycle removes its cached copy', async () => {
    const removed = [];
    const updated = [];
    const rollback = loadFunction('rollbackBillingCycle', {
        routeInfo: () => ({ type: 'billing-cycles', parentId: '1' }),
        removeRecord: async (...args) => removed.push(args),
        updateCachedCardCycles: async (...args) => updated.push(args),
    });

    await rollback({ method: 'post', url: '/cards/1/billing-cycles', localId: 'local-123' });

    assert.deepEqual(removed, [['billing-cycles', 'local-123']]);
    assert.equal(updated[0][0], '1');
    assert.equal(updated[0][1].id, 'local-123');
    assert.equal(updated[0][2], true);
});

test('an online billing cycle edit waits for Laravel validation before changing the local copy', async () => {
    const rejected = { response: { status: 422, data: { errors: { closing_date: ['Mes ocupado.'] } } } };
    let optimisticWrites = 0;
    const mutate = loadFunction('mutate', {
        uuid: () => 'request-key',
        cloneForStorage: (value) => value,
        routeInfo,
        navigator: { onLine: true },
        clearValidationError: () => {},
        window: { axios: { request: async () => { throw rejected; } } },
        applyOptimisticMutation: async () => { optimisticWrites += 1; },
    });

    await assert.rejects(
        mutate('put', '/cards/1/billing-cycles/5', { closing_date: '2026-10-25', due_date: '2026-11-06' }),
        (error) => error === rejected,
    );
    assert.equal(optimisticWrites, 0);
});

test('a confirmed online cycle edit refreshes the local cycle after the server responds', async () => {
    const calls = [];
    const response = { data: { id: 5, closing_date: '2026-10-24', due_date: '2026-11-06' } };
    const mutate = loadFunction('mutate', {
        uuid: () => 'request-key',
        cloneForStorage: (value) => value,
        routeInfo,
        navigator: { onLine: true },
        clearValidationError: () => {},
        window: { axios: { request: async (request) => { calls.push(request); return response; } } },
        reconcile: async (mutation, data) => { calls.push({ mutation, data }); },
        console: { error: () => assert.fail('The cache should update successfully') },
    });

    assert.equal(await mutate('put', '/cards/1/billing-cycles/5', { closing_date: '2026-10-24', due_date: '2026-11-06' }), response);
    assert.equal(calls.length, 2);
    assert.equal(calls[0].method, 'put');
    assert.equal(calls[1].data.due_date, '2026-11-06');
});
