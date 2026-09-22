export const normalizeSearchValue = (value) => String(value ?? '')
    .normalize('NFD')
    .replace(/\p{Diacritic}/gu, '')
    .toLocaleLowerCase('es-AR');

const tagNames = (transaction) => (transaction.tags ?? [])
    .map((tag) => tag.name)
    .join(' | ');

export const transactionMatchesSearch = (transaction, searchQuery, searchFields, formatCurrencyAmount) => {
    const normalizedSearchQuery = normalizeSearchValue(searchQuery);

    if (normalizedSearchQuery === '') {
        return true;
    }

    const searchableValues = {
        amount: [transaction.amount, formatCurrencyAmount(transaction.currency, transaction.amount)],
        category: [transaction.category?.name],
        description: [transaction.description],
        place: [transaction.place],
        tags: [tagNames(transaction)],
        notes: [transaction.notes],
    };

    return searchFields.some((field) => searchableValues[field]
        ?.some((value) => normalizeSearchValue(value).includes(normalizedSearchQuery)));
};
