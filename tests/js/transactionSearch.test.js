import assert from 'node:assert/strict';
import test from 'node:test';
import { transactionMatchesSearch } from '../../resources/js/utils/transactionSearch.js';

const transaction = {
    amount: '1234.50',
    category: { name: 'Alimentación' },
    currency: 'ARS',
    description: 'Compra semanal',
    notes: 'Usar cupón',
    place: 'Mercado Central',
    tags: [{ name: 'Hogar' }, { name: 'Oferta' }],
};

const formatCurrencyAmount = (currency, amount) => `${currency} 1.234,50 (${amount})`;

test('matches every supported transaction field', () => {
    const cases = [
        ['1234', ['amount']],
        ['1.234,50', ['amount']],
        ['alimentacion', ['category']],
        ['SEMANAL', ['description']],
        ['central', ['place']],
        ['oferta', ['tags']],
        ['cupon', ['notes']],
    ];

    for (const [searchQuery, searchFields] of cases) {
        assert.equal(transactionMatchesSearch(transaction, searchQuery, searchFields, formatCurrencyAmount), true, searchQuery);
    }
});

test('only searches the selected fields', () => {
    assert.equal(transactionMatchesSearch(transaction, 'mercado', ['description'], formatCurrencyAmount), false);
    assert.equal(transactionMatchesSearch(transaction, 'mercado', ['place'], formatCurrencyAmount), true);
});

test('returns all transactions for an empty search and none when no fields are selected', () => {
    assert.equal(transactionMatchesSearch(transaction, '', ['description'], formatCurrencyAmount), true);
    assert.equal(transactionMatchesSearch(transaction, 'compra', [], formatCurrencyAmount), false);
});
