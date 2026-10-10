export const filterTransactionsByPaymentAndCurrency = (transactions, paymentMethods, currencies) => transactions.filter(
    (transaction) => {
        const paymentMethod = transaction.payment_method
            ?? (transaction.exchange_id && transaction.type === 'expense' ? 'cash' : null);

        return paymentMethods.includes(paymentMethod) && currencies.includes(transaction.currency);
    },
);
