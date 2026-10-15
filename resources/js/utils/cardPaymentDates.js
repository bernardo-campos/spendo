const toDateParts = (value) => {
    const [year, month, day] = String(value).split('-').map((part) => Number.parseInt(part, 10));

    if (!Number.isInteger(year) || !Number.isInteger(month) || !Number.isInteger(day)) {
        return null;
    }

    return { year, month, day };
};

const formatDateParts = ({ year, month, day }) => `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

const addMonthNoOverflow = ({ year, month }) => (month === 12
    ? { year: year + 1, month: 1 }
    : { year, month: month + 1 });

const addMonthsNoOverflow = (date, months) => {
    let result = { ...date };

    for (let index = 0; index < months; index++) {
        const nextMonth = addMonthNoOverflow(result);
        result = {
            ...nextMonth,
            day: Math.min(result.day, daysInMonth(nextMonth.year, nextMonth.month)),
        };
    }

    return result;
};

const daysInMonth = (year, month) => new Date(year, month, 0).getDate();

const monthKey = ({ year, month }) => `${year}-${String(month).padStart(2, '0')}`;

const cycleForStatementMonth = (billingCycles, statementMonth) => billingCycles.find((cycle) => (
    String(cycle.closing_date).slice(0, 7) === monthKey(statementMonth)
));

const statementMonthForPurchase = (purchase, closingDay, billingCycles) => {
    const purchaseMonth = { year: purchase.year, month: purchase.month };
    const realClosingDay = toDateParts(cycleForStatementMonth(billingCycles, purchaseMonth)?.closing_date)?.day;

    return purchase.day <= (realClosingDay ?? closingDay)
        ? purchaseMonth
        : addMonthNoOverflow(purchaseMonth);
};

export const calculateFirstInstallmentPaymentDate = (purchaseDate, card) => {
    const purchase = toDateParts(purchaseDate);

    if (purchase === null || card === null) {
        return null;
    }

    const billingCycles = Array.isArray(card.billing_cycles)
        ? [...card.billing_cycles].sort((left, right) => String(left.closing_date).localeCompare(String(right.closing_date)))
        : [];
    const closingDay = Number(card.closing_day) || 1;
    const statementMonth = statementMonthForPurchase(purchase, closingDay, billingCycles);
    const matchedCycle = cycleForStatementMonth(billingCycles, statementMonth);

    if (matchedCycle?.due_date) {
        return matchedCycle.due_date;
    }

    const dueDay = Number(card.due_day) || closingDay;
    const dueMonth = closingDay === dueDay ? statementMonth : addMonthNoOverflow(statementMonth);

    return formatDateParts({
        year: dueMonth.year,
        month: dueMonth.month,
        day: Math.min(dueDay, daysInMonth(dueMonth.year, dueMonth.month)),
    });
};

export const hasRealCycleForPurchaseDate = (purchaseDate, card) => {
    const purchase = toDateParts(purchaseDate);

    if (purchase === null || card === null) {
        return false;
    }

    const closingDay = Number(card.closing_day) || 1;
    const statementMonth = statementMonthForPurchase(purchase, closingDay, card.billing_cycles ?? []);

    return Array.isArray(card.billing_cycles)
        && cycleForStatementMonth(card.billing_cycles, statementMonth) !== undefined;
};

export const buildInstallmentPreview = (totalAmount, installmentsCount, purchaseDate, card) => {
    const purchase = toDateParts(purchaseDate);
    const count = Number(installmentsCount);
    const totalCents = Math.round((Number(totalAmount) || 0) * 100);

    if (purchase === null || card === null || String(totalAmount ?? '').trim() === '' || !Number.isInteger(count) || count < 2 || totalCents < 0) {
        return [];
    }

    const billingCycles = Array.isArray(card.billing_cycles) ? [...card.billing_cycles] : [];
    const closingDay = Number(card.closing_day) || 1;
    const dueDay = Number(card.due_day) || closingDay;
    const statementMonth = statementMonthForPurchase(purchase, closingDay, billingCycles);
    const baseCents = Math.floor(totalCents / count);
    let remainingCents = totalCents;

    return Array.from({ length: count }, (_, index) => {
        const amountCents = index === count - 1 ? remainingCents : baseCents;
        const installmentStatementMonth = addMonthsNoOverflow({ ...statementMonth, day: 1 }, index);
        const cycle = cycleForStatementMonth(
            billingCycles,
            installmentStatementMonth,
        );
        const dueMonth = closingDay === dueDay
            ? installmentStatementMonth
            : addMonthNoOverflow(installmentStatementMonth);
        const dueDate = cycle?.due_date ?? formatDateParts({
            ...dueMonth,
            day: Math.min(dueDay, daysInMonth(dueMonth.year, dueMonth.month)),
        });

        remainingCents -= amountCents;

        return {
            amount: amountCents / 100,
            due_date: dueDate,
            due_date_is_estimated: !cycle?.due_date,
            installment_number: index + 1,
        };
    });
};
