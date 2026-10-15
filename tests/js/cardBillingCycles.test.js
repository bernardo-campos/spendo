import assert from 'node:assert/strict';
import test from 'node:test';
import { billingCyclesForCard } from '../../resources/js/utils/billingCycles.js';
import { buildInstallmentPreview, calculateFirstInstallmentPaymentDate, hasRealCycleForPurchaseDate } from '../../resources/js/utils/cardPaymentDates.js';

const card = {
    closing_day: 20,
    due_day: 3,
    billing_cycles: [{ id: 1, closing_date: '2026-10-24', due_date: '2026-11-05' }],
};

test('a manual cycle replaces the estimated cycle for its statement month', () => {
    const today = new Date();
    const statementMonth = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}`;
    const manualCycle = {
        id: 2,
        closing_date: `${statementMonth}-24`,
        due_date: `${statementMonth}-28`,
    };
    const cycles = billingCyclesForCard({ ...card, billing_cycles: [manualCycle] }, 1)
        .filter((cycle) => cycle.closing_date.startsWith(statementMonth));

    assert.deepEqual(cycles, [{ ...manualCycle, is_estimated: false }]);
});

test('the manual closing day determines which purchases belong to the cycle', () => {
    assert.equal(calculateFirstInstallmentPaymentDate('2026-10-19', card), '2026-11-05');
    assert.equal(calculateFirstInstallmentPaymentDate('2026-10-22', card), '2026-11-05');
    assert.equal(calculateFirstInstallmentPaymentDate('2026-10-25', card), '2026-12-03');
    assert.equal(hasRealCycleForPurchaseDate('2026-10-22', card), true);
    assert.equal(hasRealCycleForPurchaseDate('2026-10-25', card), false);
});

test('installment previews use the usual due day after a manual cycle', () => {
    const installments = buildInstallmentPreview(2000, 2, '2026-10-22', card);

    assert.deepEqual(installments.map((installment) => installment.due_date), ['2026-11-05', '2026-12-03']);
    assert.deepEqual(installments.map((installment) => installment.due_date_is_estimated), [false, true]);
});
