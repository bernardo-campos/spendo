import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';
import { computed, effectScope, ref, watch } from 'vue';
import { parse } from '@babel/parser';
import { parse as parseVue } from '@vue/compiler-sfc';

const { descriptor } = parseVue(readFileSync('resources/js/pages/TransactionListPage.vue', 'utf8'));
const source = descriptor.scriptSetup.content;
const script = parse(source, { sourceType: 'module' }).program.body
    .filter((node) => node.type !== 'ImportDeclaration')
    .map((node) => source.slice(node.start, node.end)).join('\n');
const storage = () => {
    const values = new Map();
    return { getItem: (key) => values.get(key) ?? null, setItem: (key, value) => values.set(key, value) };
};
const mount = (localStorage, expense = true) => {
    const scope = effectScope();
    const context = {
        localStorage, computed, ref, watch,
        defineProps: () => ({ showPaymentMethodFilter: expense, transactions: [] }),
        defineEmits: () => () => {},
        onMounted: () => {}, onBeforeUnmount: () => {}, formatLocalDate: () => '2026-11-01',
    };
    scope.run(() => runInNewContext(`${script}
        globalThis.subject = { paymentMethodFilter, currencyFilter, searchQuery, searchFields,
            unconfirmedOnly, selectedPaymentMethodFilter, selectedCurrencyFilter, selectedSearchQuery,
            selectedSearchFields, applySearch, applyPaymentMethodFilter, openSearchDialog,
            openPaymentMethodFilterDialog };`, context));
    return { state: context.subject, unmount: () => scope.stop() };
};

test('applied movement search and filters survive navigation and page reload', () => {
    const saved = storage();
    const first = mount(saved);
    first.state.selectedSearchQuery.value = 'Supermercado';
    first.state.selectedSearchFields.value = ['description', 'place'];
    first.state.applySearch();
    first.state.selectedPaymentMethodFilter.value = ['credit'];
    first.state.selectedCurrencyFilter.value = ['USD'];
    first.state.applyPaymentMethodFilter();
    first.state.unconfirmedOnly.value = true;
    first.unmount();

    for (let visit = 0; visit < 2; visit++) {
        const page = mount(saved);
        assert.equal(page.state.searchQuery.value, 'Supermercado');
        assert.deepEqual([...page.state.searchFields.value], ['description', 'place']);
        assert.deepEqual([...page.state.paymentMethodFilter.value], ['credit']);
        assert.deepEqual([...page.state.currencyFilter.value], ['USD']);
        assert.equal(page.state.unconfirmedOnly.value, true);
        page.state.openSearchDialog();
        page.state.openPaymentMethodFilterDialog();
        assert.equal(page.state.selectedSearchQuery.value, 'Supermercado');
        assert.deepEqual([...page.state.selectedCurrencyFilter.value], ['USD']);
        page.unmount();
    }
});

test('unapplied dialog edits do not replace saved search and filters', () => {
    const saved = storage();
    const first = mount(saved);
    first.state.selectedSearchQuery.value = 'Borrador';
    first.state.selectedCurrencyFilter.value = [];
    first.unmount();
    const next = mount(saved);
    assert.equal(next.state.searchQuery.value, '');
    assert.deepEqual([...next.state.currencyFilter.value], ['ARS', 'USD']);
    next.unmount();
});

test('income and expense selections are saved independently including cleared filters', () => {
    const saved = storage();
    const expense = mount(saved);
    expense.state.searchQuery.value = 'Gasto';
    expense.state.currencyFilter.value = [];
    expense.unmount();
    const income = mount(saved, false);
    assert.equal(income.state.searchQuery.value, '');
    income.state.searchQuery.value = 'Sueldo';
    income.unmount();
    const nextExpense = mount(saved);
    assert.equal(nextExpense.state.searchQuery.value, 'Gasto');
    assert.deepEqual([...nextExpense.state.currencyFilter.value], []);
    nextExpense.state.searchQuery.value = '';
    nextExpense.unmount();
    const cleared = mount(saved);
    assert.equal(cleared.state.searchQuery.value, '');
    cleared.unmount();
});

test('invalid or unavailable storage does not prevent using movement filters', () => {
    for (const stored of ['broken JSON', 'null', '{"currencies":null,"searchQuery":42}']) {
        const page = mount({ getItem: () => stored, setItem: () => {} });
        assert.equal(page.state.searchQuery.value, '');
        assert.deepEqual([...page.state.currencyFilter.value], ['ARS', 'USD']);
        page.unmount();
    }
    const page = mount({
        getItem: () => { throw new Error('unavailable'); },
        setItem: () => { throw new Error('unavailable'); },
    });
    page.state.selectedSearchQuery.value = 'Compra';
    assert.doesNotThrow(() => page.state.applySearch());
    assert.equal(page.state.searchQuery.value, 'Compra');
    page.unmount();
});
