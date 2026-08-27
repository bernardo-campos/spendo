const toDateParts = (value) => {
    const [year, month, day] = String(value).slice(0, 10).split('-').map((part) => Number.parseInt(part, 10));

    if (!Number.isInteger(year) || !Number.isInteger(month) || !Number.isInteger(day)) {
        return null;
    }

    return { year, month, day };
};

const formatDateParts = ({ year, month, day }) => `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

const addMonth = ({ year, month }) => (month === 12
    ? { year: year + 1, month: 1 }
    : { year, month: month + 1 });

const daysInMonth = (year, month) => new Date(year, month, 0).getDate();

const buildEstimatedCycle = (card, referenceDate) => {
    const closingDay = Number(card.closing_day);
    const dueDay = Number(card.due_day);

    if (!Number.isInteger(closingDay) || !Number.isInteger(dueDay)) {
        return null;
    }

    const statementMonth = {
        year: referenceDate.getFullYear(),
        month: referenceDate.getMonth() + 1,
    };
    const dueMonth = addMonth(statementMonth);

    return {
        id: `estimated-${formatDateParts({
            ...statementMonth,
            day: Math.min(closingDay, daysInMonth(statementMonth.year, statementMonth.month)),
        })}`,
        closing_date: formatDateParts({
            ...statementMonth,
            day: Math.min(closingDay, daysInMonth(statementMonth.year, statementMonth.month)),
        }),
        due_date: formatDateParts({
            ...dueMonth,
            day: Math.min(dueDay, daysInMonth(dueMonth.year, dueMonth.month)),
        }),
        is_estimated: true,
    };
};

const todayAsDate = () => {
    const today = new Date();

    return new Date(today.getFullYear(), today.getMonth(), today.getDate());
};

export const billingCyclesForCard = (card, monthsAroundToday = 12) => {
    const estimatedCycles = Array.from({ length: (monthsAroundToday * 2) + 1 }, (_, index) => {
        const offset = index - monthsAroundToday;
        const referenceDate = new Date();
        referenceDate.setMonth(referenceDate.getMonth() + offset, 1);

        return buildEstimatedCycle(card, referenceDate);
    }).filter(Boolean);
    const cyclesByClosingDate = new Map(estimatedCycles.map((cycle) => [cycle.closing_date, cycle]));

    (Array.isArray(card.billing_cycles) ? card.billing_cycles : []).forEach((cycle) => {
        cyclesByClosingDate.set(String(cycle.closing_date).slice(0, 10), {
            ...cycle,
            is_estimated: false,
        });
    });

    return [...cyclesByClosingDate.values()]
        .sort((left, right) => String(left.closing_date).localeCompare(String(right.closing_date)));
};

export const billingCyclesAroundToday = (card) => {
    const cycles = billingCyclesForCard(card);
    const today = formatDateParts(toDateParts(todayAsDate().toISOString().slice(0, 10)));
    const cyclesByDueDate = [...cycles]
        .sort((left, right) => String(left.due_date).localeCompare(String(right.due_date)));
    const currentCycle = cyclesByDueDate.find((cycle) => String(cycle.due_date) >= today) ?? null;

    if (currentCycle === null) {
        return [];
    }

    const previousCycle = cyclesByDueDate
        .filter((cycle) => cycle.id !== currentCycle.id && String(cycle.due_date) <= today)
        .at(-1) ?? null;
    const nextCycle = cycles
        .filter((cycle) => String(cycle.closing_date) > String(currentCycle.due_date))
        .at(0) ?? null;

    return [
        { cycle: previousCycle, label: 'Anterior' },
        { cycle: currentCycle, label: 'Actual' },
        { cycle: nextCycle, label: 'Próximo' },
    ].filter((entry) => entry.cycle !== null);
};
