import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';
import { parse } from '@babel/parser';

const source = readFileSync('resources/js/services/offlineClient.js', 'utf8');
const declarations = parse(source, { sourceType: 'module' }).program.body;
const loadFunction = (name, dependencies = {}) => {
    const declaration = declarations.find((node) => node.type === 'VariableDeclaration'
        && node.declarations.some((item) => item.id.name === name));
    const context = { ...dependencies };
    assert.ok(declaration, `Missing ${name}`);
    runInNewContext(`${source.slice(declaration.start, declaration.end)}\nglobalThis.subject = ${name};`, context);
    return context.subject;
};

const routeInfo = loadFunction('routeInfo');
const isMissingTransaction = loadFunction('isMissingTransaction', { routeInfo });
const legacyMessage = 'No query results for model [App\\Models\\Transaction] 287';

test('only missing transaction edits and deletions can be discarded', () => {
    for (const method of ['put', 'patch', 'delete']) {
        assert.equal(isMissingTransaction({ method, url: '/transactions/287' }, 404), true);
        assert.equal(isMissingTransaction({ method, url: '/transactions/287' }, undefined, legacyMessage), true);
    }
    for (const mutation of [
        { method: 'post', url: '/transactions' },
        { method: 'put', url: '/cards/287' },
        { method: 'put', url: '/transactions' },
        { method: 'put', url: '/transactions/places' },
        { method: 'put', url: '/transactions/local-unsynced' },
    ]) {
        assert.equal(isMissingTransaction(mutation, 404, legacyMessage), false);
    }
    for (const status of [401, 403, 422, 500, 503]) {
        assert.equal(isMissingTransaction({ method: 'put', url: '/transactions/287' }, status, 'Error'), false);
    }
});

const syncQueue = async (mutations, request) => {
    const deleted = [];
    const stored = [];
    const removed = [];
    const references = [];
    const discardMissingTransaction = loadFunction('discardMissingTransaction', {
        routeInfo,
        resolveUrl: async (url) => url,
        updateCachedTransactionReferences: async (item, remove) => references.push([item.id, remove]),
        removeRecord: async (...args) => removed.push(args),
        deleteMutation: async (id) => deleted.push(id),
    });
    const state = { value: {} };
    const processSync = loadFunction('processSync', {
        userId: { value: '1' }, navigator: { onLine: true }, syncState: state,
        mutationValues: async () => mutations,
        resolveUrl: async (url) => url, resolveReferences: async (value) => value,
        window: { axios: { request } }, routeInfo, isMissingTransaction, discardMissingTransaction,
        putMutation: async (mutation) => stored.push(mutation),
        reconcile: async () => {}, deleteMutation: async (id) => deleted.push(id),
        updateSyncState: async (status) => { state.value.status = status; },
    });
    await processSync();
    return { deleted, stored, removed, references, state: state.value };
};

test('previously persisted errors are cleaned on synchronization while other failures remain', async () => {
    const result = await syncQueue([
        { id: 'legacy', method: 'put', url: '/transactions/287', status: 'failed', errorMessage: legacyMessage },
        { id: 'missing', method: 'delete', url: '/transactions/288', status: 'failed', statusCode: 404 },
        { id: 'validation', method: 'put', url: '/transactions/289', status: 'failed', statusCode: 422 },
    ], async () => assert.fail('Failed changes should not be sent again'));
    assert.deepEqual(result.deleted, ['legacy', 'missing']);
    assert.deepEqual(result.removed, [['transactions', '287'], ['transactions', '288']]);
    assert.deepEqual(result.references, [['287', true], ['288', true]]);
    assert.equal(result.state.status, 'synced');
});

test('a new 404 removes the stale transaction and allows subsequent changes to synchronize', async () => {
    const result = await syncQueue([
        { id: 'missing', method: 'put', url: '/transactions/287', status: 'pending' },
        { id: 'valid', method: 'put', url: '/transactions/288', status: 'pending' },
    ], async ({ url }) => {
        if (url === '/transactions/287') {
            throw { response: { status: 404, data: { message: legacyMessage } } };
        }
        return { data: { id: 288 } };
    });
    assert.deepEqual(result.deleted, ['missing', 'valid']);
    assert.equal(result.stored.length, 0);
});

test('server and validation errors keep their changes available for retry', async () => {
    for (const status of [422, 500]) {
        const result = await syncQueue([
            { id: 'failed', method: 'put', url: '/transactions/287', status: 'pending' },
        ], async () => { throw { response: { status, data: { message: 'Error' } } }; });
        assert.deepEqual(result.deleted, []);
        assert.equal(result.stored[0].status, 'failed');
        assert.equal(result.stored[0].statusCode, status);
    }
});

test('a network failure leaves the queued change pending', async () => {
    const mutation = { id: 'pending', method: 'put', url: '/transactions/287', status: 'pending' };
    const result = await syncQueue([mutation], async () => { throw new Error('offline'); });
    assert.deepEqual(result.deleted, []);
    assert.deepEqual(result.stored, []);
    assert.equal(mutation.status, 'pending');
});
