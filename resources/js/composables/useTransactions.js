import { computed, ref } from 'vue';
import { offlineClient } from '../services/offlineClient';

const CURRENCIES = ['ARS', 'USD'];

export const useTransactions = (selectedPeriod) => {
    const transactions = ref([]);
    const transactionsLoading = ref(false);
    const loadedTransactionsPeriod = ref(null);

    const isInSelectedPeriod = (dateValue) => String(dateValue ?? '').slice(0, 7) === selectedPeriod.value;
    const parseAmount = (value) => Number.parseFloat(value ?? 0) || 0;
    const emptyCurrencyTotals = () => Object.fromEntries(CURRENCIES.map((currency) => [currency, 0]));
    const totalsByCurrency = (items) => items.reduce((totals, item) => {
        const currency = CURRENCIES.includes(item.currency) ? item.currency : 'ARS';

        totals[currency] += parseAmount(item.amount);

        return totals;
    }, emptyCurrencyTotals());

    const incomeTransactions = computed(() => transactions.value
        .filter((transaction) => transaction.type === 'income')
        .filter((transaction) => isInSelectedPeriod(transaction.purchase_date)));

    const expenseTransactions = computed(() => transactions.value
        .filter((transaction) => transaction.type === 'expense')
        .flatMap((transaction) => {
            const installments = transaction.installment_plan?.installments ?? [];

            if (installments.length > 0) {
                const totalInstallments = installments.length;

                return installments
                    .filter((installment) => isInSelectedPeriod(installment.due_date))
                    .map((installment) => ({
                        id: `${transaction.id}-installment-${installment.id ?? installment.installment_number}`,
                        transaction_id: transaction.id,
                        category: transaction.category,
                        description: transaction.description,
                        notes: transaction.notes,
                        purchase_date: installment.due_date,
                        payment_method: transaction.payment_method,
                        amount: installment.amount,
                        currency: transaction.currency,
                        installment_number: installment.installment_number,
                        tags: transaction.tags,
                        total_installments: totalInstallments,
                        created_at: transaction.created_at,
                        type: 'expense',
                    }));
            }

            const expenseDate = transaction.payment_date ?? transaction.purchase_date;

            if (!isInSelectedPeriod(expenseDate)) {
                return [];
            }

            return [{
                ...transaction,
                purchase_date: expenseDate,
            }];
        }));

    const dashboardRecentTransactions = computed(() => [...incomeTransactions.value, ...expenseTransactions.value]
        .sort((left, right) => String(right.purchase_date).localeCompare(String(left.purchase_date)))
        .slice(0, 10));

    const incomeTotals = computed(() => totalsByCurrency(incomeTransactions.value));

    const expenseTotals = computed(() => totalsByCurrency(expenseTransactions.value));

    const loadTransactions = async ({ force = false } = {}) => {
        const period = selectedPeriod.value;

        if (! force && loadedTransactionsPeriod.value === period) {
            transactionsLoading.value = false;

            return;
        }

        transactionsLoading.value = true;

        try {
            const response = await offlineClient.get('/transactions', {
                params: { period },
                fresh: force,
            });

            if (selectedPeriod.value !== period) {
                return;
            }

            transactions.value = response.data;
            loadedTransactionsPeriod.value = period;
        } finally {
            if (selectedPeriod.value === period) {
                transactionsLoading.value = false;
            }
        }
    };

    const invalidateTransactions = () => {
        loadedTransactionsPeriod.value = null;
    };

    const addTransaction = (transaction, { isPending = false, queuedAt = null } = {}) => {
        const listedTransaction = isPending
            ? { ...transaction, is_pending: true, queued_at: queuedAt }
            : transaction;
        const transactionDate = listedTransaction.type === 'expense'
            ? listedTransaction.payment_date ?? listedTransaction.purchase_date
            : listedTransaction.purchase_date;

        if (! isInSelectedPeriod(transactionDate)) {
            invalidateTransactions();

            return;
        }

        transactions.value = [
            listedTransaction,
            ...transactions.value.filter((item) => String(item.id) !== String(listedTransaction.id)),
        ];
        loadedTransactionsPeriod.value = selectedPeriod.value;
    };

    return {
        addTransaction,
        dashboardRecentTransactions,
        expenseTotals,
        expenseTransactions,
        incomeTotals,
        incomeTransactions,
        invalidateTransactions,
        loadTransactions,
        transactions,
        transactionsLoading,
    };
};
