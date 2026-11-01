import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { computed, ref } from 'vue';
import { parse } from '@babel/parser';
import { parse as parseVue } from '@vue/compiler-sfc';
import { billingCycleForPurchaseDate } from '../../resources/js/utils/cardPaymentDates.js';

test('the list preserves the original subscription period after a date correction', () => {
    const source = readFileSync('resources/js/composables/useTransactions.js', 'utf8');
    const declaration = parse(source, { sourceType: 'module' }).program.body
        .find((node) => node.type === 'ExportNamedDeclaration');
    const context = { computed, ref };
    runInNewContext(`${source.slice(declaration.declaration.start, declaration.declaration.end)}
        globalThis.subject = useTransactions;`, context);
    const subject = context.subject(ref('2026-12'), ref([{
        id: 1, period: '2026-10', charge_date: '2026-11-11', payment_date: '2026-12-04',
        status: 'confirmed', amount: 1000,
    }]));
    const item = subject.expenseTransactions.value[0];
    assert.equal(item.recurring_period, '2026-10');
    assert.equal(item.id, 'recurring-1-2026-10');
    assert.equal(item.charge_date, '2026-11-11');
});

test('the modal submits the corrected date with the original subscription period', () => {
    const { descriptor } = parseVue(readFileSync('resources/js/pages/TransactionListPage.vue', 'utf8'));
    const source = descriptor.scriptSetup.content;
    const names = ['recurringDialogOpen', 'editingRecurring', 'recurringAmount', 'recurringDescription',
        'recurringChargeDate', 'confirmingRecurringDelete', 'openTransaction', 'saveRecurring'];
    const statements = parse(source, { sourceType: 'module' }).program.body.filter((node) => (
        node.type === 'VariableDeclaration' && node.declarations.some((item) => names.includes(item.id.name))
    ));
    const events = [];
    const context = { ref, emit: (...args) => events.push(args) };
    runInNewContext(`${statements.map((node) => source.slice(node.start, node.end)).join('\n')}
        openTransaction({ recurring_expense_id: 1, recurring_period: '2026-10',
            charge_date: '2026-10-15', amount: 1000, description: 'Suscripción' });
        recurringChargeDate.value = '2026-11-11';
        saveRecurring('confirmed');`, context);
    assert.equal(events[0][0], 'save-recurring');
    assert.equal(events[0][1].period, '2026-10');
    assert.equal(events[0][1].charge_date, '2026-11-11');
});

test('the modal cycle changes when the charge crosses a real closing date', () => {
    const card = {
        closing_day: 10, due_day: 20,
        billing_cycles: [{ closing_date: '2026-10-12', due_date: '2026-11-04' }],
    };
    assert.deepEqual(billingCycleForPurchaseDate('2026-10-12', card), {
        closing_date: '2026-10-12', due_date: '2026-11-04', is_estimated: false,
    });
    assert.deepEqual(billingCycleForPurchaseDate('2026-10-13', card), {
        closing_date: '2026-11-10', due_date: '2026-12-20', is_estimated: true,
    });
});

test('estimated closing dates respect short months and missing cards', () => {
    assert.deepEqual(billingCycleForPurchaseDate('2026-02-28', { closing_day: 31, due_day: 31 }), {
        closing_date: '2026-02-28', due_date: '2026-02-28', is_estimated: true,
    });
    assert.equal(billingCycleForPurchaseDate('', { closing_day: 10 }), null);
    assert.equal(billingCycleForPurchaseDate('2026-10-12', null), null);
});
