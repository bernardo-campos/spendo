import { computed, ref } from 'vue';

export const useTransactions = (selectedPeriod) => {
    const transactions = ref([]);
    const transactionsLoading = ref(false);
    const loadedTransactionsPeriod = ref(null);

    const isInSelectedPeriod = (dateValue) => String(dateValue ?? '').slice(0, 7) === selectedPeriod.value;
    const parseAmount = (value) => Number.parseFloat(value ?? 0) || 0;

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
                        purchase_date: installment.due_date,
                        payment_method: transaction.payment_method,
                        amount: installment.amount,
                        installment_number: installment.installment_number,
                        tags: transaction.tags,
                        total_installments: totalInstallments,
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

    const incomeTotal = computed(() => incomeTransactions.value
        .reduce((sum, transaction) => sum + parseAmount(transaction.amount), 0));

    const expenseTotal = computed(() => expenseTransactions.value
        .reduce((sum, transaction) => sum + parseAmount(transaction.amount), 0));

    const loadTransactions = async () => {
        const period = selectedPeriod.value;

        if (loadedTransactionsPeriod.value === period) {
            transactionsLoading.value = false;

            return;
        }

        transactionsLoading.value = true;

        try {
            const response = await window.axios.get('/transactions', {
                params: { period },
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

    return {
        dashboardRecentTransactions,
        expenseTotal,
        expenseTransactions,
        incomeTotal,
        incomeTransactions,
        invalidateTransactions,
        loadTransactions,
        transactions,
        transactionsLoading,
    };
};
