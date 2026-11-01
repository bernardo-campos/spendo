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

const bankCard = {
    closing_day: 27,
    due_day: 9,
    billing_cycles: [
        { id: 1, closing_date: '2026-05-28', due_date: '2026-06-10' },
        { id: 2, closing_date: '2026-07-02', due_date: '2026-07-15' },
        { id: 3, closing_date: '2026-07-30', due_date: '2026-08-12' },
        { id: 4, closing_date: '2026-08-27', due_date: '2026-09-09' },
        { id: 5, closing_date: '2026-10-01', due_date: '2026-10-14' },
        { id: 6, closing_date: '2026-10-29', due_date: '2026-11-11' },
    ],
};

test('the history retains all real cycles including two closings in the same month', () => {
    const actual = billingCyclesForCard(bankCard).filter((cycle) => !cycle.is_estimated);
    assert.deepEqual(actual.map((cycle) => cycle.id), [1, 2, 3, 4, 5, 6]);
});

test('real purchases use consecutive closing dates even across months without a closing', () => {
    for (const [purchase, due] of [
        ['2026-05-29', '2026-07-15'], ['2026-06-30', '2026-07-15'],
        ['2026-07-02', '2026-07-15'], ['2026-07-03', '2026-08-12'],
        ['2026-09-30', '2026-10-14'], ['2026-10-01', '2026-10-14'],
        ['2026-10-02', '2026-11-11'], ['2026-10-29', '2026-11-11'],
    ]) {
        assert.equal(calculateFirstInstallmentPaymentDate(purchase, bankCard), due, purchase);
        assert.equal(hasRealCycleForPurchaseDate(purchase, bankCard), true);
    }
    const installments = buildInstallmentPreview(100, 6, '2026-05-20', { ...bankCard, closing_day: 15 });
    assert.deepEqual(installments.map((item) => item.due_date), bankCard.billing_cycles.map((cycle) => cycle.due_date));
    assert.equal(installments.reduce((sum, item) => sum + Math.round(item.amount * 100), 0), 10000);
});

test('sparse real cycles preserve the following estimated installment', () => {
    const partialCard = { ...bankCard, billing_cycles: [bankCard.billing_cycles[1]] };
    const installments = buildInstallmentPreview(300, 3, '2026-06-15', partialCard);
    assert.deepEqual(installments.map((item) => item.due_date), ['2026-07-15', '2026-08-09', '2026-09-09']);
    assert.deepEqual(installments.map((item) => item.due_date_is_estimated), [false, true, true]);
});
