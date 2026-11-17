import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';
import { parse } from '@babel/parser';
import { parse as parseVue } from '@vue/compiler-sfc';
import { computed, effectScope, nextTick, reactive, ref, watch } from 'vue';

const { descriptor } = parseVue(readFileSync('resources/js/components/SpendoApp.vue', 'utf8'));
const source = descriptor.scriptSetup.content;
const names = ['transactionSearchForm', 'transactionSearchResults', 'transactionSearchLoading', 'transactionSearchError', 'appliedTransactionSearch', 'searchTransactions'];
const script = parse(source, { sourceType: 'module' }).program.body
    .filter((node) => node.type === 'VariableDeclaration' && node.declarations.some((item) => names.includes(item.id.name)))
    .map((node) => source.slice(node.start, node.end)).join('\n');
const setup = (get) => {
    const context = { ref, selectedPeriod: ref('2026-11'), window: { axios: { get } } };
    runInNewContext(`${script}\nglobalThis.subject = { ${names.join(', ')} };`, context);
    return context.subject;
};

test('filters collapse on search, preserve values and reopen on an error', async () => {
    const { descriptor: page } = parseVue(readFileSync('resources/js/pages/TransactionSearchPage.vue', 'utf8'));
    const source = page.scriptSetup.content;
    const script = parse(source, { sourceType: 'module' }).program.body
        .filter((node) => node.type !== 'ImportDeclaration')
        .map((node) => source.slice(node.start, node.end)).join('\n');
    const props = reactive({ form: { type: 'expense', description: 'Supermercado', payment_method: '' }, results: null, error: '' });
    const events = [];
    const context = {
        computed, ref, watch, defineProps: () => props, defineEmits: () => (event) => events.push(event),
    };
    const scope = effectScope();
    try {
        scope.run(() => runInNewContext(`${script}\nglobalThis.subject = { filtersExpanded, submitSearch };`, context));
        const { filtersExpanded, submitSearch } = context.subject;
        assert.equal(filtersExpanded.value, true);
        submitSearch();
        assert.equal(filtersExpanded.value, false);
        assert.deepEqual(events, ['search']);
        filtersExpanded.value = true;
        assert.equal(props.form.description, 'Supermercado');
        submitSearch();
        props.error = 'No fue posible realizar la búsqueda.';
        await nextTick();
        assert.equal(filtersExpanded.value, true);
        assert.equal(props.form.description, 'Supermercado');
    } finally {
        scope.stop();
    }
});

test('search uses the server independently of the loaded month and paginates the applied filters', async () => {
    const calls = [];
    const subject = setup(async (url, config) => {
        calls.push({ url, params: config.params });
        return { data: { data: [{ id: 1 }], meta: { total: 30 } } };
    });
    subject.transactionSearchForm.value.date_scope = 'all';
    subject.transactionSearchForm.value.description = 'Compra';
    subject.transactionSearchForm.value.tag_ids = [3];
    await subject.searchTransactions();
    assert.equal(calls[0].url, '/transaction-search');
    assert.equal(calls[0].params.date_scope, 'all');
    assert.equal(calls[0].params.description, 'Compra');
    assert.equal('amount_min' in calls[0].params, false);
    subject.transactionSearchForm.value.description = 'Borrador';
    subject.transactionSearchForm.value.tag_ids.push(4);
    await subject.searchTransactions(2);
    await subject.searchTransactions(1);
    for (const call of calls.slice(1)) {
        assert.equal(call.params.description, 'Compra');
        assert.deepEqual([...call.params.tag_ids], [3]);
    }
    assert.equal(calls[2].params.page, 1);
    assert.equal(subject.transactionSearchResults.value.meta.total, 30);
    assert.equal(subject.transactionSearchLoading.value, false);
});

test('search sends inclusive date and amount bounds, including zero', async () => {
    const calls = [];
    const subject = setup(async (url, config) => { calls.push(config.params); return { data: {} }; });
    Object.assign(subject.transactionSearchForm.value, {
        date_scope: 'range', date_from: '2025-01-01', date_to: '2026-12-31', amount_min: 0, amount_max: 100,
    });
    await subject.searchTransactions();
    assert.equal(calls[0].date_from, '2025-01-01');
    assert.equal(calls[0].date_to, '2026-12-31');
    assert.equal(calls[0].amount_min, 0);
    assert.equal(calls[0].amount_max, 100);
});

test('failed searches display validation errors and release the loading state', async () => {
    const subject = setup(async () => { throw { response: { data: { errors: { date_to: ['Intervalo inválido.'] } } } }; });
    await subject.searchTransactions();
    assert.equal(subject.transactionSearchError.value, 'Intervalo inválido.');
    assert.equal(subject.transactionSearchLoading.value, false);
    assert.equal(subject.transactionSearchResults.value, null);
});

test('the search screen supports menu navigation and direct page loads', () => {
    const navigation = readFileSync('resources/js/composables/useNavigation.js', 'utf8');
    const body = parse(navigation, { sourceType: 'module' }).program.body
        .filter((node) => node.type !== 'ImportDeclaration')
        .map((node) => navigation.slice(node.start, node.end).replace(/^export /, '')).join('\n');
    for (const path of ['/app', '/app/search']) {
        const paths = [];
        const context = {
            computed, ref, onBeforeUnmount: () => {}, URLSearchParams,
            window: {
                location: { pathname: path, search: '' },
                history: { pushState: (state, title, nextPath) => paths.push(nextPath) },
                addEventListener: () => {}, removeEventListener: () => {},
            },
        };
        runInNewContext(`${body}\nglobalThis.nav = useNavigation({ form: { value: {} } });`, context);
        assert.equal(context.nav.activeScreen.value, path === '/app/search' ? 'search' : 'dashboard');
        context.nav.setActiveScreenFromMenu('search');
        assert.equal(context.nav.activeScreen.value, 'search');
        if (path === '/app') {
            assert.deepEqual(paths, ['/app/search']);
        }
    }
});
