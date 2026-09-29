import assert from 'node:assert/strict';
import test from 'node:test';
import { exchangeReferenceAmounts, suggestedExchangeAmount } from '../../resources/js/utils/exchangeSuggestion.js';

test('suggests either amount using the last exchange in either direction', () => {
    const previous = { expense: { currency: 'USD', amount: '2.00' }, income: { currency: 'ARS', amount: '3200.00' } };
    const amounts = exchangeReferenceAmounts(previous, 'ARS', 'USD');

    assert.deepEqual(amounts, { source: 3200, target: 2 });
    assert.equal(suggestedExchangeAmount('1600', amounts.source, amounts.target), '1.00');
    assert.equal(suggestedExchangeAmount('3', amounts.target, amounts.source), '4800.00');
});

test('does not suggest for invalid or missing amounts', () => {
    assert.equal(suggestedExchangeAmount('0', 1500, 1), null);
    assert.equal(exchangeReferenceAmounts(null, 'ARS', 'USD'), null);
});
