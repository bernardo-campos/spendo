import assert from 'node:assert/strict';
import test from 'node:test';
import { groupTransactions, partitionPlannedDayGroups, plannedPeriodLabel } from '../../resources/js/utils/transactionGrouping.js';

const transactions = [
    { id: 1, amount: '100.00', currency: 'ARS', purchase_date: '2026-09-03', payment_method: 'credit', card_id: 2, card: { id: 2, name: 'Visa' } },
    { id: 2, amount: '50.00', currency: 'ARS', purchase_date: '2026-09-02', payment_method: 'cash' },
    { id: 3, amount: '75.00', currency: 'ARS', purchase_date: '2026-09-01', payment_method: 'credit', card_id: 1, card: { id: 1, name: 'Mastercard' } },
    { id: 4, amount: '25.00', currency: 'USD', purchase_date: '2026-09-04', payment_method: 'credit', card_id: 2, card: { id: 2, name: 'Visa' } },
    { id: 5, amount: '10.00', currency: 'ARS', purchase_date: '2026-09-05', payment_method: 'cash' },
];

test('groups expenses by cash and individual card with separate currency totals', () => {
    const groups = groupTransactions(transactions, 'payment_method');

    assert.deepEqual(groups.map(({ key, label, totals, transactions: items }) => ({
        key,
        label,
        totals,
        ids: items.map((item) => item.id),
    })), [
        { key: 'payment-cash', label: 'Efectivo', totals: { ARS: 60, USD: 0 }, ids: [5, 2] },
        { key: 'payment-card-1', label: 'Mastercard', totals: { ARS: 75, USD: 0 }, ids: [3] },
        { key: 'payment-card-2', label: 'Visa', totals: { ARS: 100, USD: 25 }, ids: [4, 1] },
    ]);
});

test('keeps day and category grouping available', () => {
    assert.deepEqual(groupTransactions(transactions, 'day').map((group) => group.key), [
        '2026-09-05', '2026-09-04', '2026-09-03', '2026-09-02', '2026-09-01',
    ]);

    assert.deepEqual(groupTransactions([
        { ...transactions[0], category: { id: 1, name: 'Comida' } },
        { ...transactions[1], category: { id: 1, name: 'Comida' } },
    ], 'category')[0].totals, { ARS: 150, USD: 0 });
});

test('places only future dates in the current month under planned with combined totals', () => {
    const groups = groupTransactions([
        { id: 1, amount: '10.00', currency: 'ARS', purchase_date: '2026-10-04' },
        { id: 2, amount: '20.00', currency: 'ARS', purchase_date: '2026-10-05' },
        { id: 3, amount: '30.00', currency: 'ARS', purchase_date: '2026-10-06' },
        { id: 4, amount: '5.00', currency: 'USD', purchase_date: '2026-10-07' },
        { id: 5, amount: '40.00', currency: 'ARS', purchase_date: '2026-11-01' },
    ], 'day');

    const { planned, other, totals } = partitionPlannedDayGroups(groups, '2026-10-05', '2026-10');

    assert.deepEqual(planned.map((group) => group.key), ['2026-10-07', '2026-10-06']);
    assert.deepEqual(other.map((group) => group.key), ['2026-11-01', '2026-10-05', '2026-10-04']);
    assert.deepEqual(totals, { ARS: 30, USD: 5 });
});

test('groups every day of a future selected period as planned and names its month', () => {
    const groups = groupTransactions([
        { id: 1, amount: '40.00', currency: 'ARS', purchase_date: '2026-11-01' },
        { id: 2, amount: '15.00', currency: 'USD', purchase_date: '2026-11-20' },
    ], 'day');

    const { planned, other, totals } = partitionPlannedDayGroups(groups, '2026-10-05', '2026-11');

    assert.deepEqual(planned.map((group) => group.key), ['2026-11-20', '2026-11-01']);
    assert.deepEqual(other, []);
    assert.deepEqual(totals, { ARS: 40, USD: 15 });
    assert.equal(plannedPeriodLabel('2026-11'), 'Planificados en noviembre');
});
