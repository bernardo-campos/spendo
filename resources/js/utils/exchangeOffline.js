export const optimisticExchange = (payload, id, existing = null) => {
    const createdAt = existing?.created_at ?? new Date().toISOString();
    const shared = {
        exchange_id: id, description: payload.description, purchase_date: payload.purchase_date,
        payment_date: payload.purchase_date, notes: payload.notes ?? null,
        category_id: null, category: null, card_id: null, card: null, tags: [],
        payment_method: null, created_at: createdAt,
    };

    return {
        id, created_at: createdAt,
        expense: {
            ...shared, id: existing?.expense?.id ?? `${id}-expense`, type: 'expense',
            currency: payload.source_currency, amount: payload.source_amount, place: payload.place ?? null,
        },
        income: {
            ...shared, id: existing?.income?.id ?? `${id}-income`, type: 'income',
            currency: payload.target_currency, amount: payload.target_amount, place: null,
        },
    };
};
