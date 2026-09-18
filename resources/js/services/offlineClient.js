import { ref } from 'vue';

const DATABASE_NAME = 'spendo-offline';
const DATABASE_VERSION = 1;
const RECORDS = 'records';
const MUTATIONS = 'mutations';
const STORE = 'store';

const userId = ref(null);
const syncState = ref({ status: navigator.onLine ? 'synced' : 'offline', pending: 0, failed: 0, failedMessages: [] });
let databasePromise;

const offlineError = () => Object.assign(new Error('Estos datos todavía no se descargaron en este dispositivo.'), { offlineUnavailable: true });
const uuid = () => globalThis.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random()}`;
const cloneForStorage = (value) => value === undefined ? undefined : JSON.parse(JSON.stringify(value));
const recordKey = (type, id) => `${userId.value}:${type}:${id}`;
const periodKey = (period) => `${userId.value}:transactions-period:${period}`;
const placesKey = () => `${userId.value}:transaction-places`;
const preferenceKey = () => `${userId.value}:visualization-preferences`;
const mappingKey = (temporaryId) => `${userId.value}:mapping:${temporaryId}`;
const activeUserKey = 'spendo-offline:active-user';

const openDatabase = () => {
    if (! databasePromise) {
        databasePromise = new Promise((resolve, reject) => {
            const request = indexedDB.open(DATABASE_NAME, DATABASE_VERSION);

            request.onupgradeneeded = () => {
                request.result.createObjectStore(STORE);
                request.result.createObjectStore(MUTATIONS, { keyPath: 'id' });
            };
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    return databasePromise;
};

const transaction = async (storeName, mode, callback) => {
    const database = await openDatabase();

    return new Promise((resolve, reject) => {
        const tx = database.transaction(storeName, mode);
        const store = tx.objectStore(storeName);
        let result;

        tx.oncomplete = () => resolve(result);
        tx.onerror = () => reject(tx.error);
        tx.onabort = () => reject(tx.error);
        result = callback(store);
    });
};

const getValue = async (key) => {
    const database = await openDatabase();

    return requestResult(database.transaction(STORE, 'readonly').objectStore(STORE).get(key));
};
const setValue = async (key, value) => transaction(STORE, 'readwrite', (store) => store.put(cloneForStorage(value), key));
const deleteValue = async (key) => transaction(STORE, 'readwrite', (store) => store.delete(key));

const requestResult = (request) => new Promise((resolve, reject) => {
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
});

const valuesForPrefix = async (prefix) => {
    const database = await openDatabase();

    return new Promise((resolve, reject) => {
        const values = [];
        const cursorRequest = database.transaction(STORE, 'readonly').objectStore(STORE).openCursor();

        cursorRequest.onsuccess = () => {
            const cursor = cursorRequest.result;

            if (! cursor) {
                resolve(values);
                return;
            }

            if (String(cursor.key).startsWith(prefix)) {
                values.push(cursor.value);
            }

            cursor.continue();
        };
        cursorRequest.onerror = () => reject(cursorRequest.error);
    });
};

const mutationValues = async () => {
    const database = await openDatabase();
    const all = await requestResult(database.transaction(MUTATIONS, 'readonly').objectStore(MUTATIONS).getAll());

    return all.filter((mutation) => mutation.userId === userId.value).sort((left, right) => left.createdAt.localeCompare(right.createdAt));
};

const putMutation = async (mutation) => transaction(MUTATIONS, 'readwrite', (store) => store.put(cloneForStorage(mutation)));
const deleteMutation = async (id) => transaction(MUTATIONS, 'readwrite', (store) => store.delete(id));

const clearAllOfflineData = async () => {
    const database = await openDatabase();

    await new Promise((resolve, reject) => {
        const tx = database.transaction([STORE, MUTATIONS], 'readwrite');
        tx.objectStore(STORE).clear();
        tx.objectStore(MUTATIONS).clear();
        tx.oncomplete = resolve;
        tx.onerror = () => reject(tx.error);
        tx.onabort = () => reject(tx.error);
    });
};

const routeInfo = (url) => {
    const path = String(url).split('?')[0];
    const nestedCycle = path.match(/^\/cards\/([^/]+)\/billing-cycles(?:\/([^/]+))?$/);

    if (nestedCycle) {
        return { type: 'billing-cycles', id: nestedCycle[2] ?? null, parentId: nestedCycle[1], path };
    }

    if (path === '/transactions/places') {
        return { type: 'transaction-places', id: 'all', path };
    }

    const resource = path.match(/^\/(categories|tags|cards|transactions)(?:\/([^/]+))?$/);

    if (resource) {
        return { type: resource[1], id: resource[2] ?? null, path };
    }

    if (path === '/visualization-preferences') {
        return { type: 'visualization-preferences', id: 'current', path };
    }

    return { type: null, id: null, path };
};

const collection = async (type) => valuesForPrefix(`${userId.value}:${type}:`);

const writeRecord = async (type, item) => {
    if (item?.id === undefined || item?.id === null) {
        return;
    }

    await setValue(recordKey(type, item.id), item);
};

const removeRecord = async (type, id) => deleteValue(recordKey(type, id));

const writeTransactionPeriod = async (period, items) => {
    await Promise.all(items.map((item) => writeRecord('transactions', item)));
    await setValue(periodKey(period), items.map((item) => item.id));
};

const transactionPeriod = async (period) => {
    const ids = await getValue(periodKey(period)) ?? [];
    const queuedMutations = (await mutationValues())
        .filter((mutation) => {
            const route = routeInfo(mutation.url);
            const transactionDate = String(mutation.payload?.purchase_date ?? '').slice(0, 7);

            return mutation.status === 'pending'
                && mutation.method === 'post'
                && route.type === 'transactions'
                && route.id === null
                && transactionDate === period;
        })
        .reverse();
    const queuedTransactions = (await Promise.all(queuedMutations.map(async (mutation) => {
        const transaction = await getValue(recordKey('transactions', mutation.localId));

        return transaction ? { ...transaction, is_pending: true, queued_at: mutation.createdAt } : null;
    }))).filter(Boolean);

    if (ids.length === 0 && queuedTransactions.length === 0) {
        return null;
    }

    const items = await Promise.all(ids.map((id) => getValue(recordKey('transactions', id))));
    const queuedIds = new Set(queuedTransactions.map((transaction) => String(transaction.id)));

    return [
        ...queuedTransactions,
        ...items.filter((item) => item && !queuedIds.has(String(item.id))),
    ];
};

const cacheResponse = async (url, data, params = {}) => {
    const route = routeInfo(url);

    if (route.type === 'transactions' && ! route.id) {
        await writeTransactionPeriod(params.period, data);
        return;
    }

    if (route.type === 'transaction-places') {
        await setValue(placesKey(), data);
        return;
    }

    if (['categories', 'tags', 'cards'].includes(route.type) && ! route.id) {
        await Promise.all(data.map((item) => writeRecord(route.type, item)));
        return;
    }

    if (route.type === 'visualization-preferences') {
        await setValue(preferenceKey(), data);
        return;
    }

    if (route.type) {
        await writeRecord(route.type, data);
    }
};

const cachedResponse = async (url, params = {}) => {
    const route = routeInfo(url);

    if (route.type === 'transactions' && ! route.id) {
        return transactionPeriod(params.period);
    }

    if (route.type === 'transaction-places') {
        return getValue(placesKey());
    }

    if (['categories', 'tags', 'cards'].includes(route.type) && ! route.id) {
        return collection(route.type);
    }

    if (route.type === 'visualization-preferences') {
        return getValue(preferenceKey());
    }

    return route.type ? getValue(recordKey(route.type, route.id)) : null;
};

const enrichTransaction = async (payload, id) => {
    const [category, card, tags] = await Promise.all([
        payload.category_id ? getValue(recordKey('categories', payload.category_id)) : null,
        payload.card_id ? getValue(recordKey('cards', payload.card_id)) : null,
        Promise.all((payload.tag_ids ?? []).map((tagId) => getValue(recordKey('tags', tagId)))),
    ]);

    return {
        ...payload,
        id,
        category,
        card,
        tags: tags.filter(Boolean),
        payment_date: payload.purchase_date,
        created_at: new Date().toISOString(),
    };
};

const compareTransactions = (left, right) => {
    const leftDate = left.payment_date ?? left.purchase_date ?? '';
    const rightDate = right.payment_date ?? right.purchase_date ?? '';
    const dateOrder = String(rightDate).localeCompare(String(leftDate));

    if (dateOrder !== 0) {
        return dateOrder;
    }

    return String(right.created_at ?? '').localeCompare(String(left.created_at ?? ''));
};

const sortedTransactionIds = async (ids) => {
    const items = await Promise.all(ids.map((id) => getValue(recordKey('transactions', id))));

    return items
        .filter(Boolean)
        .sort(compareTransactions)
        .map((item) => item.id);
};

const updateCachedTransactionReferences = async (item, remove = false, replacedId = null) => {
    const database = await openDatabase();
    const keys = await new Promise((resolve, reject) => {
        const found = [];
        const cursorRequest = database.transaction(STORE, 'readonly').objectStore(STORE).openCursor();
        cursorRequest.onsuccess = () => {
            const cursor = cursorRequest.result;
            if (! cursor) {
                resolve(found);
                return;
            }
            if (String(cursor.key).startsWith(`${userId.value}:transactions-period:`)) {
                found.push(cursor.key);
            }
            cursor.continue();
        };
        cursorRequest.onerror = () => reject(cursorRequest.error);
    });

    const itemPeriod = String(item.payment_date ?? item.purchase_date ?? '').slice(0, 7);
    const itemPeriodKey = periodKey(itemPeriod);
    const replacedIds = new Set([String(item.id)]);

    if (replacedId !== null) {
        replacedIds.add(String(replacedId));
    }

    await Promise.all(keys.map(async (key) => {
        const ids = await getValue(key) ?? [];
        const nextIds = ids.filter((id) => !replacedIds.has(String(id)));

        if (! remove && key === itemPeriodKey) {
            nextIds.push(item.id);
        }

        await setValue(key, await sortedTransactionIds(nextIds));
    }));

    if (! remove && itemPeriod && !keys.includes(itemPeriodKey)) {
        const ids = await getValue(periodKey(itemPeriod)) ?? [];
        const nextIds = ids.filter((id) => !replacedIds.has(String(id)));

        nextIds.push(item.id);
        await setValue(itemPeriodKey, await sortedTransactionIds(nextIds));
    }
};

const updateCachedCardCycles = async (cardId, cycle, remove = false) => {
    const resolvedCardId = await resolveReferences(cardId);
    const card = await getValue(recordKey('cards', resolvedCardId));

    if (! card) {
        return;
    }

    const cycles = (card.billing_cycles ?? []).filter((item) => String(item.id) !== String(cycle.id));
    await writeRecord('cards', {
        ...card,
        billing_cycles: remove ? cycles : [...cycles, cycle].sort((left, right) => String(left.closing_date).localeCompare(String(right.closing_date))),
    });
};

const applyOptimisticMutation = async (method, url, payload) => {
    const route = routeInfo(url);

    if (route.type === 'visualization-preferences') {
        const item = { expense_list: payload.expense_list };
        await setValue(preferenceKey(), item);
        return { item, localId: 'current' };
    }

    if (! route.type) {
        return { item: null, localId: null };
    }

    const id = route.id ?? `local-${uuid()}`;
    const existing = route.id ? await getValue(recordKey(route.type, route.id)) : null;
    let item;

    if (method === 'delete') {
        if (existing) {
            await removeRecord(route.type, route.id);
            if (route.type === 'transactions') {
                await updateCachedTransactionReferences(existing, true);
            }
        }
        if (route.type === 'billing-cycles' && existing) {
            await updateCachedCardCycles(route.parentId, existing, true);
        }
        return { item: existing, localId: route.id };
    }

    if (route.type === 'transactions') {
        item = await enrichTransaction({ ...(existing ?? {}), ...payload }, id);
    } else if (route.type === 'billing-cycles') {
        item = { ...(existing ?? {}), ...payload, id, card_id: route.parentId };
    } else {
        item = { ...(existing ?? {}), ...payload, id };
        if (route.type === 'cards' && ! item.billing_cycles) {
            item.billing_cycles = [];
        }
    }

    await writeRecord(route.type, item);
    if (route.type === 'transactions') {
        await updateCachedTransactionReferences(item);
    }
    if (route.type === 'billing-cycles') {
        await updateCachedCardCycles(route.parentId, item);
    }

    return { item, localId: id };
};

const compactMutation = async (mutation) => {
    const queued = await mutationValues();
    const create = queued.find((item) => item.method === 'post' && item.localId === mutation.localId && item.status === 'pending');

    if (create && mutation.method === 'put') {
        create.payload = { ...create.payload, ...mutation.payload };
        await putMutation(create);
        return true;
    }

    if (create && mutation.method === 'delete') {
        await deleteMutation(create.id);
        return true;
    }

    return false;
};

const resolveReferences = async (value) => {
    if (typeof value === 'string') {
        return (await getValue(mappingKey(value))) ?? value;
    }

    if (Array.isArray(value)) {
        return Promise.all(value.map(resolveReferences));
    }

    if (value && typeof value === 'object') {
        return Object.fromEntries(await Promise.all(Object.entries(value).map(async ([key, nested]) => [key, await resolveReferences(nested)])));
    }

    return value;
};

const resolveUrl = async (url) => {
    const temporaryIds = String(url).match(/local-[^/]+/g) ?? [];
    let resolvedUrl = url;

    for (const temporaryId of temporaryIds) {
        const serverId = await getValue(mappingKey(temporaryId));

        if (serverId !== undefined) {
            resolvedUrl = resolvedUrl.replace(temporaryId, serverId);
        }
    }

    return resolvedUrl;
};

const updateSyncState = async (status = navigator.onLine ? 'synced' : 'offline') => {
    const mutations = await mutationValues();
    syncState.value = {
        status,
        pending: mutations.filter((mutation) => mutation.status === 'pending').length,
        failed: mutations.filter((mutation) => mutation.status === 'failed').length,
        failedMessages: mutations
            .filter((mutation) => mutation.status === 'failed')
            .map((mutation) => mutation.errorMessage),
    };
};

const reconcile = async (mutation, data) => {
    const route = routeInfo(mutation.url);
    if (route.type === 'visualization-preferences') {
        await setValue(preferenceKey(), data);
        return;
    }

    if (! route.type || mutation.method === 'delete') {
        return;
    }

    const localItem = await getValue(recordKey(route.type, mutation.localId ?? route.id));
    const item = route.type === 'cards' ? { ...localItem, ...data } : data;
    const replacedId = mutation.method === 'post' && mutation.localId !== item.id
        ? mutation.localId
        : null;

    if (replacedId !== null) {
        await setValue(mappingKey(mutation.localId), item.id);
        await removeRecord(route.type, mutation.localId);
        if (route.type === 'billing-cycles') {
            await updateCachedCardCycles(route.parentId, { id: mutation.localId }, true);
        }
    }

    await writeRecord(route.type, item);
    if (route.type === 'transactions') {
        await updateCachedTransactionReferences(item, false, replacedId);
    }
    if (route.type === 'billing-cycles') {
        await updateCachedCardCycles(route.parentId, item);
    }
};

const sync = async () => {
    if (! userId.value || ! navigator.onLine) {
        await updateSyncState('offline');
        return;
    }

    let queued = await mutationValues();
    if (queued.length === 0) {
        await updateSyncState('synced');
        return;
    }

    syncState.value = { ...syncState.value, status: 'syncing' };

    for (const mutation of queued) {
        if (mutation.status === 'failed') {
            continue;
        }

        try {
            const [url, payload] = await Promise.all([
                resolveUrl(mutation.url),
                resolveReferences(mutation.payload),
            ]);
            const response = await window.axios.request({
                url,
                method: mutation.method,
                data: payload,
                headers: { 'Idempotency-Key': mutation.idempotencyKey },
            });

            await reconcile(mutation, response.data);
            await deleteMutation(mutation.id);
        } catch (error) {
            if (! error.response) {
                break;
            }

            mutation.status = 'failed';
            mutation.errorMessage = error.response?.data?.message ?? 'La sincronización fue rechazada por el servidor.';
            await putMutation(mutation);
        }
    }

    await updateSyncState(navigator.onLine ? 'synced' : 'offline');
};

const retry = async () => {
    const mutations = await mutationValues();
    await Promise.all(mutations
        .filter((mutation) => mutation.status === 'failed')
        .map((mutation) => putMutation({ ...mutation, status: 'pending', errorMessage: undefined })));
    await sync();
};

const refresh = async (url, config) => {
    try {
        const response = await window.axios.get(url, config);
        await cacheResponse(url, response.data, config?.params);
    } catch {
        // The local copy remains available when the refresh cannot reach Laravel.
    }
};

const get = async (url, config = {}) => {
    const { fresh = false, ...requestConfig } = config;
    const cached = await cachedResponse(url, requestConfig.params);

    if (! fresh && cached !== null && cached !== undefined) {
        if (navigator.onLine) {
            void refresh(url, requestConfig);
        }
        return { data: cached };
    }

    if (! navigator.onLine) {
        throw offlineError();
    }

    const response = await window.axios.get(url, requestConfig);
    await cacheResponse(url, response.data, requestConfig.params);
    return response;
};

const mutate = async (method, url, payload = undefined) => {
    const idempotencyKey = uuid();
    const storedPayload = cloneForStorage(payload);

    try {
        const optimistic = await applyOptimisticMutation(method, url, storedPayload ?? {});
        const mutation = {
            id: uuid(),
            idempotencyKey,
            userId: userId.value,
            method,
            url,
            payload: storedPayload,
            localId: optimistic.localId,
            status: 'pending',
            createdAt: new Date().toISOString(),
        };

        if (! await compactMutation(mutation)) {
            await putMutation(mutation);
        }

        await updateSyncState(navigator.onLine ? 'pending' : 'offline');
        void sync();

        return {
            data: optimistic.item,
            isPending: true,
            queuedAt: mutation.createdAt,
        };
    } catch (error) {
        if (! navigator.onLine) {
            throw error;
        }

        console.error('No fue posible persistir el cambio offline; se guardará directamente en el servidor.', error);

        return window.axios.request({
            url,
            method,
            data: storedPayload,
            headers: { 'Idempotency-Key': idempotencyKey },
        });
    }
};

const initialize = async (id) => {
    const nextUserId = String(id);
    const previousUserId = await getValue(activeUserKey);
    const changedUser = previousUserId !== undefined && previousUserId !== nextUserId;

    if (changedUser) {
        await clearAllOfflineData();
    }

    userId.value = nextUserId;
    await setValue(activeUserKey, nextUserId);
    await updateSyncState();
    void sync();

    return changedUser;
};

const clear = async () => {
    if (! userId.value) {
        return;
    }

    const prefix = `${userId.value}:`;
    const database = await openDatabase();
    await new Promise((resolve, reject) => {
        const tx = database.transaction([STORE, MUTATIONS], 'readwrite');
        const records = tx.objectStore(STORE);
        const cursor = records.openCursor();
        cursor.onsuccess = () => {
            const item = cursor.result;
            if (item) {
                if (String(item.key).startsWith(prefix)) {
                    item.delete();
                }
                item.continue();
            }
        };
        const mutations = tx.objectStore(MUTATIONS);
        const mutationCursor = mutations.openCursor();
        mutationCursor.onsuccess = () => {
            const item = mutationCursor.result;
            if (item) {
                if (item.value.userId === userId.value) {
                    item.delete();
                }
                item.continue();
            }
        };
        tx.oncomplete = resolve;
        tx.onerror = () => reject(tx.error);
    });
    userId.value = null;
    syncState.value = { status: 'synced', pending: 0, failed: 0, failedMessages: [] };
};

export const offlineClient = { clear, get, initialize, mutate, retry, sync, syncState };
