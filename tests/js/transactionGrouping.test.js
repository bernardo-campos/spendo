import assert from 'node:assert/strict';
import test from 'node:test';
import { groupTransactions } from '../../resources/js/utils/transactionGrouping.js';

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
