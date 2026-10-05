import assert from 'node:assert/strict';
import test from 'node:test';
import { filterTransactionsByPaymentAndCurrency } from '../../resources/js/utils/transactionFilters.js';

const transactions = [
    { id: 1, payment_method: 'cash', currency: 'ARS' },
    { id: 2, payment_method: 'cash', currency: 'USD' },
    { id: 3, payment_method: 'credit', currency: 'ARS' },
    { id: 4, payment_method: 'credit', currency: 'USD' },
];

test('combines payment method and currency checkbox selections', () => {
    assert.deepEqual(filterTransactionsByPaymentAndCurrency(transactions, ['cash', 'credit'], ['ARS', 'USD']).map((item) => item.id), [1, 2, 3, 4]);
    assert.deepEqual(filterTransactionsByPaymentAndCurrency(transactions, ['cash'], ['USD']).map((item) => item.id), [2]);
    assert.deepEqual(filterTransactionsByPaymentAndCurrency(transactions, ['credit'], ['ARS', 'USD']).map((item) => item.id), [3, 4]);
});

test('shows no movements when either checkbox group has no selection', () => {
    assert.deepEqual(filterTransactionsByPaymentAndCurrency(transactions, [], ['ARS', 'USD']), []);
    assert.deepEqual(filterTransactionsByPaymentAndCurrency(transactions, ['cash', 'credit'], []), []);
});
