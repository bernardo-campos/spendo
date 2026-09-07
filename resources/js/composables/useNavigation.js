import { computed, ref } from 'vue';

export const useNavigation = ({ form }) => {
    const activeScreen = ref('dashboard');
    const forcedTransactionType = ref(null);
    const editingTransactionId = ref(null);

    const resetTransactionForm = () => {
        form.value.description = '';
        form.value.amount = '';
        form.value.category_id = '';
        form.value.purchase_date = new Date().toISOString().slice(0, 10);
        form.value.payment_method = 'cash';
        form.value.card_id = '';
        form.value.installments_count = 1;
        form.value.notes = '';
        form.value.tag_ids = [];
    };

    const activePrimaryTab = computed(() => {
        if (activeScreen.value === 'dashboard') {
            return 'dashboard';
        }

        if (activeScreen.value === 'income-list') {
            return 'income-list';
        }

        if (activeScreen.value === 'expense-list') {
            return 'expense-list';
        }

        if (activeScreen.value === 'transaction-form' && forcedTransactionType.value === 'income') {
            return 'income-list';
        }

        if (activeScreen.value === 'transaction-form' && forcedTransactionType.value === 'expense') {
            return 'expense-list';
        }

        return '';
    });

    const openTransactionForm = (type) => {
        resetTransactionForm();
        editingTransactionId.value = null;
        forcedTransactionType.value = type;
        form.value.type = type;
        activeScreen.value = 'transaction-form';
    };

    const openGenericTransactionForm = () => {
        resetTransactionForm();
        editingTransactionId.value = null;
        forcedTransactionType.value = null;
        activeScreen.value = 'transaction-form';
    };

    const returnToTransactionList = () => {
        const transactionType = form.value.type;

        resetTransactionForm();
        editingTransactionId.value = null;
        forcedTransactionType.value = null;
        activeScreen.value = transactionType === 'income' ? 'income-list' : 'expense-list';
    };

    const setActiveScreenFromMenu = (screen) => {
        activeScreen.value = screen;
    };

    return {
        activePrimaryTab,
        activeScreen,
        editingTransactionId,
        forcedTransactionType,
        openGenericTransactionForm,
        openTransactionForm,
        returnToTransactionList,
        setActiveScreenFromMenu,
    };
};
