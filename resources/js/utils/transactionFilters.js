export const filterTransactionsByPaymentAndCurrency = (transactions, paymentMethods, currencies) => transactions.filter(
    (transaction) => paymentMethods.includes(transaction.payment_method) && currencies.includes(transaction.currency),
);
