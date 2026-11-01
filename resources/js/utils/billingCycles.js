import { formatLocalDate } from './localDate.js';
import { buildBillingCycleSchedule } from './cardPaymentDates.js';

export const billingCyclesForCard = (card, monthsAroundToday = 12) => {
    const start = new Date();
    start.setMonth(start.getMonth() - monthsAroundToday, 1);

    return buildBillingCycleSchedule(card, formatLocalDate(start), (monthsAroundToday * 2) + 1);
};

export const billingCyclesAroundToday = (card) => {
    const cycles = billingCyclesForCard(card);
    const today = formatLocalDate();
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
