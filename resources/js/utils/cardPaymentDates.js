const toDateParts = (value) => {
    const [year, month, day] = String(value).split('-').map(Number);

    return Number.isInteger(year) && Number.isInteger(month) && Number.isInteger(day)
        ? { year, month, day }
        : null;
};

const formatDateParts = ({ year, month, day }) => `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
const daysInMonth = (year, month) => new Date(year, month, 0).getDate();
const monthAtOffset = ({ year, month }, offset) => {
    const date = new Date(year, month - 1 + offset, 1);

    return { year: date.getFullYear(), month: date.getMonth() + 1 };
};
const monthKey = ({ year, month }) => `${year}-${String(month).padStart(2, '0')}`;

export const buildBillingCycleSchedule = (card, startDate, monthsCount) => {
    const start = toDateParts(startDate);

    if (!card || !start) {
        return [];
    }

    const realCycles = (Array.isArray(card.billing_cycles) ? card.billing_cycles : [])
        .map((cycle) => ({ ...cycle, is_estimated: false }))
        .sort((left, right) => String(left.closing_date).localeCompare(String(right.closing_date)));
    const coveredIntervals = realCycles.slice(1).flatMap((cycle, index) => {
        const previous = realCycles[index];
        const previousDue = toDateParts(previous.due_date);
        const nextDue = toDateParts(cycle.due_date);
        const monthDifference = (nextDue.year - previousDue.year) * 12 + nextDue.month - previousDue.month;

        return monthDifference >= 0 && monthDifference <= 1 ? [[previous.closing_date, cycle.closing_date]] : [];
    });
    const closingDay = Number(card.closing_day) || 1;
    const dueDay = Number(card.due_day) || closingDay;
    const replacedMonths = new Set(realCycles.map((cycle) => {
        const closing = toDateParts(cycle.closing_date);
        const timestamp = Date.UTC(closing.year, closing.month - 1, closing.day);
        const candidates = [-1, 0, 1].map((offset) => {
            const month = monthAtOffset(closing, offset);
            const day = Math.min(closingDay, daysInMonth(month.year, month.month));

            return { month, distance: Math.abs(Date.UTC(month.year, month.month - 1, day) - timestamp) };
        });

        return monthKey(candidates.sort((left, right) => left.distance - right.distance)[0].month);
    }));
    const cycles = [...realCycles];

    for (let offset = 0; offset < monthsCount; offset++) {
        const closingMonth = monthAtOffset(start, offset);
        const dueMonth = monthAtOffset(start, offset + (closingDay === dueDay ? 0 : 1));

        const closingDate = formatDateParts({
            ...closingMonth, day: Math.min(closingDay, daysInMonth(closingMonth.year, closingMonth.month)),
        });

        if (replacedMonths.has(monthKey(closingMonth))
            || coveredIntervals.some(([previous, next]) => closingDate > previous && closingDate < next)) {
            continue;
        }
        cycles.push({
            id: `estimated-${closingDate}`,
            closing_date: closingDate,
            due_date: formatDateParts({
                ...dueMonth, day: Math.min(dueDay, daysInMonth(dueMonth.year, dueMonth.month)),
            }),
            is_estimated: true,
        });
    }

    return cycles.sort((left, right) => String(left.closing_date).localeCompare(String(right.closing_date)));
};

const scheduleForPurchase = (purchaseDate, card, count) => {
    if (!card || !toDateParts(purchaseDate)) {
        return [];
    }

    let monthsCount = count + 2;
    let cycles;

    do {
        cycles = buildBillingCycleSchedule(card, purchaseDate, monthsCount)
            .filter((cycle) => String(cycle.closing_date) >= purchaseDate);
        monthsCount += count + 2;
    } while (cycles.length < count);

    return cycles.slice(0, count);
};

export const calculateFirstInstallmentPaymentDate = (purchaseDate, card) => (
    scheduleForPurchase(purchaseDate, card, 1)[0]?.due_date ?? null
);

export const hasRealCycleForPurchaseDate = (purchaseDate, card) => (
    scheduleForPurchase(purchaseDate, card, 1)[0]?.is_estimated === false
);

export const billingCycleForPurchaseDate = (purchaseDate, card) => {
    const cycle = scheduleForPurchase(purchaseDate, card, 1)[0];

    return cycle ? {
        closing_date: cycle.closing_date,
        due_date: cycle.due_date,
        is_estimated: cycle.is_estimated,
    } : null;
};

export const buildInstallmentPreview = (totalAmount, installmentsCount, purchaseDate, card) => {
    const count = Number(installmentsCount);
    const totalCents = Math.round((Number(totalAmount) || 0) * 100);

    if (!toDateParts(purchaseDate) || !card || String(totalAmount ?? '').trim() === '' || !Number.isInteger(count) || count < 2 || totalCents < 0) {
        return [];
    }

    const baseCents = Math.floor(totalCents / count);
    let remainingCents = totalCents;

    return scheduleForPurchase(purchaseDate, card, count).map((cycle, index) => {
        const amountCents = index === count - 1 ? remainingCents : baseCents;
        remainingCents -= amountCents;

        return {
            amount: amountCents / 100,
            due_date: cycle.due_date,
            due_date_is_estimated: cycle.is_estimated,
            installment_number: index + 1,
        };
    });
};
