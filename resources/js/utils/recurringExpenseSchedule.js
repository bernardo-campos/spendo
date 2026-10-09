const daysInMonth = (year, month) => {
    const date = new Date(0);
    date.setUTCFullYear(year, month, 0);

    return date.getUTCDate();
};

const dateParts = (value) => {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value ?? ''));
    if (!match) {
        return null;
    }

    const [, year, month, day] = match.map(Number);
    const valid = month >= 1 && month <= 12 && day >= 1 && day <= daysInMonth(year, month);

    return valid ? { year, month, day } : null;
};

const monthIndex = ({ year, month }) => (year * 12) + month;
const monthParts = (index) => ({ year: Math.floor((index - 1) / 12), month: ((index - 1) % 12) + 1 });
const chargeDate = (index, dayOfMonth) => {
    const { year, month } = monthParts(index);
    const day = Math.min(dayOfMonth, daysInMonth(year, month));

    return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
};

export const recurringExpenseSchedule = (rule, visibleLimit = 60) => {
    const start = dateParts(rule.starts_on);
    const end = dateParts(rule.ends_on);
    const dayOfMonth = Number(rule.day_of_month);

    if (!start || !end || !Number.isInteger(dayOfMonth) || dayOfMonth < 1 || dayOfMonth > 31 || rule.ends_on < rule.starts_on) {
        return null;
    }

    let firstMonth = monthIndex(start);
    let lastMonth = monthIndex(end);
    if (chargeDate(firstMonth, dayOfMonth) < rule.starts_on) {
        firstMonth += 1;
    }
    if (chargeDate(lastMonth, dayOfMonth) > rule.ends_on) {
        lastMonth -= 1;
    }

    const total = Math.max(0, lastMonth - firstMonth + 1);
    const baseNote = String(rule.notes ?? '').trim();
    const items = Array.from({ length: Math.min(total, visibleLimit) }, (_, index) => {
        const number = index + 1;
        const numbering = rule.number_occurrences_in_notes ? `${number} de ${total}` : '';

        return {
            number,
            date: chargeDate(firstMonth + index, dayOfMonth),
            notes: [baseNote, numbering].filter(Boolean).join('\n'),
        };
    });

    return { total, items, hiddenCount: total - items.length };
};
