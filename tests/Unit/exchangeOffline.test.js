import assert from 'node:assert/strict';
import test from 'node:test';
import { optimisticExchange } from '../../resources/js/utils/exchangeOffline.js';

const payload = {
    source_currency: 'ARS', source_amount: '1500.00',
    target_currency: 'USD', target_amount: '1.00',
    purchase_date: '2026-09-15', description: 'Cambio', place: 'Mercado Pago',
};

test('one offline exchange yields both linked legs in the same period', () => {
    const exchange = optimisticExchange(payload, 'local-test');
    assert.equal(exchange.expense.exchange_id, exchange.id);
    assert.equal(exchange.income.exchange_id, exchange.id);
    assert.equal(exchange.expense.type, 'expense');
    assert.equal(exchange.income.type, 'income');
    assert.equal(exchange.expense.payment_date, exchange.income.purchase_date);
});

test('editing an offline exchange retains both leg identities', () => {
    const original = optimisticExchange(payload, 'local-test');
    const changed = optimisticExchange({ ...payload, source_amount: '3000.00', target_amount: '2.00' }, 'local-test', original);
    assert.equal(changed.expense.id, original.expense.id);
    assert.equal(changed.income.id, original.income.id);
    assert.equal(changed.expense.amount, '3000.00');
    assert.equal(changed.income.amount, '2.00');
});
