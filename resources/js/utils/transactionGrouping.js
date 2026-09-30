const transactionDay = (transaction) => String(transaction.purchase_date ?? '').slice(0, 10);
const isRecentlyCreated = (transaction) => transaction.is_pending || transaction.is_recently_created;
const recentCreationTimestamp = (transaction) => transaction.queued_at
    ?? transaction.recently_created_at
    ?? transaction.created_at
    ?? '';

const paymentGroup = (transaction) => {
    if (transaction.payment_method !== 'credit') {
        return { key: 'payment-cash', label: 'Efectivo' };
    }

    const cardIdentity = transaction.card_id ?? transaction.card?.id ?? transaction.card?.name ?? 'unknown';

    return {
        key: `payment-card-${cardIdentity}`,
        label: transaction.card?.name ?? 'Crédito sin tarjeta',
    };
};

export const groupTransactions = (transactions, grouping) => {
    const transactionsByGroup = new Map();

    [...transactions]
        .sort((left, right) => {
            if (grouping === 'day') {
                const purchaseDateOrder = transactionDay(right).localeCompare(transactionDay(left));

                if (purchaseDateOrder !== 0) {
                    return purchaseDateOrder;
                }
            }

            if (isRecentlyCreated(left) !== isRecentlyCreated(right)) {
                return isRecentlyCreated(left) ? -1 : 1;
            }

            if (isRecentlyCreated(left) && isRecentlyCreated(right)) {
                return String(recentCreationTimestamp(right)).localeCompare(String(recentCreationTimestamp(left)));
            }

            if (grouping === 'category') {
                const categoryOrder = (left.category?.name ?? 'Sin categoría')
                    .localeCompare(right.category?.name ?? 'Sin categoría', 'es');

                if (categoryOrder !== 0) {
                    return categoryOrder;
                }
            }

            const purchaseDateOrder = String(right.purchase_date).localeCompare(String(left.purchase_date));

            if (purchaseDateOrder !== 0) {
                return purchaseDateOrder;
            }

            return String(right.created_at ?? '').localeCompare(String(left.created_at ?? ''));
        })
        .forEach((transaction) => {
            const categoryName = transaction.category?.name ?? 'Sin categoría';
            const groupIdentity = grouping === 'category'
                ? { key: `category-${transaction.category?.id ?? 'uncategorized'}`, label: categoryName }
                : grouping === 'payment_method'
                    ? paymentGroup(transaction)
                    : { key: transactionDay(transaction), label: null };
            const group = transactionsByGroup.get(groupIdentity.key) ?? {
                ...groupIdentity,
                totals: { ARS: 0, USD: 0 },
                transactions: [],
            };

            const currency = ['ARS', 'USD'].includes(transaction.currency) ? transaction.currency : 'ARS';
            group.totals[currency] += Number.parseFloat(transaction.amount ?? 0) || 0;
            group.transactions.push(transaction);
            transactionsByGroup.set(groupIdentity.key, group);
        });

    const groups = [...transactionsByGroup.values()];

    if (grouping === 'payment_method') {
        groups.sort((left, right) => {
            if (left.key === 'payment-cash' || right.key === 'payment-cash') {
                return left.key === 'payment-cash' ? -1 : 1;
            }

            return left.label.localeCompare(right.label, 'es');
        });
    }

    return groups;
};
