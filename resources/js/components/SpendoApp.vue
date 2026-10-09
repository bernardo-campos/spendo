<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useAsyncAction } from '../composables/useAsyncAction';
import { useCatalogs } from '../composables/useCatalogs';
import { useCatalogManagement } from '../composables/useCatalogManagement';
import { useColorMode } from '../composables/useColorMode';
import { useNavigation } from '../composables/useNavigation';
import { useTransactionForm } from '../composables/useTransactionForm';
import { useTransactions } from '../composables/useTransactions';
import { offlineClient } from '../services/offlineClient';
import { submitLogout as submitLogoutForm } from '../services/logout';
import { currentLocalPeriod, formatLocalDate } from '../utils/localDate';
import { exchangeReferenceAmounts, suggestedExchangeAmount } from '../utils/exchangeSuggestion';
import AdminLayout from './admin/AdminLayout.vue';
import CardsPage from '../pages/CardsPage.vue';
import CategoriesPage from '../pages/CategoriesPage.vue';
import CurrencyExchangeFormPage from '../pages/CurrencyExchangeFormPage.vue';
import DashboardPage from '../pages/DashboardPage.vue';
import TagsPage from '../pages/TagsPage.vue';
import TransactionFormPage from '../pages/TransactionFormPage.vue';
import TransactionListPage from '../pages/TransactionListPage.vue';
import RecurringExpensesPage from '../pages/RecurringExpensesPage.vue';
import VisualizationPage from '../pages/VisualizationPage.vue';

const rootElement = document.getElementById('spendo-app');
const userName = rootElement?.dataset.userName ?? 'Usuario';
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
const CURRENCY_OPTIONS = [
    { value: 'ARS', label: 'Pesos argentinos (AR$)', symbol: 'AR$' },
    { value: 'USD', label: 'Dólares (USD$)', symbol: 'USD$' },
];
const CURRENCY_SYMBOLS = { ARS: 'AR$', USD: 'USD$' };
const EXPENSE_LIST_DISPLAY_DEFAULTS = {
    show_category: true,
    show_description: true,
    show_cash_payment_method: false,
    show_credit_payment_method: true,
    show_tags: true,
    show_notes: false,
};

const selectedPeriod = ref(currentLocalPeriod());
const collapsedDatesByList = ref({
    income: new Set(),
    expense: new Set(),
});
const userMenuRef = ref(null);
const userMenuOpen = ref(false);
const sidebarOpen = ref(false);
const savingTransaction = ref(false);
const deletingTransaction = ref(false);
const loadingVisualizationPreferences = ref(true);
const savingVisualizationPreferences = ref(false);
const expenseListDisplayPreferences = ref({ ...EXPENSE_LIST_DISPLAY_DEFAULTS });
const offlineSyncState = offlineClient.syncState;
const places = ref([]);
const recurringRules = ref([]);
const recurringPreview = ref([]);
const recurringBusy = ref(false);
const recurringOffline = ref(false);
const recurringPage = ref(null);
const recurringPendingCount = computed(() => recurringPreview.value.filter((item) => item.amount_type === 'variable' && item.status === 'pending' && item.charge_date <= formatLocalDate()).length);
const { errorMessage, loading, runWithLoading, successMessage } = useAsyncAction();
const { isDarkMode, toggleColorMode } = useColorMode();

const {
    cards,
    categories,
    ensureTransactionFormData,
    loadCards,
    loadCategories,
    loadTags,
    tags,
} = useCatalogs();
const {
    addTransaction,
    dashboardRecentTransactions,
    expenseTotals,
    expenseTransactions,
    incomeTotals,
    incomeTransactions,
    invalidateTransactions,
    loadTransactions,
    transactionsLoading,
} = useTransactions(selectedPeriod, recurringPreview);

const userInitials = computed(() => userName
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((name) => name[0])
    .join('')
    .toUpperCase());

const updateCollapsedDates = (list, dates) => {
    collapsedDatesByList.value[list] = dates;
};

const loadVisualizationPreferences = async () => {
    loadingVisualizationPreferences.value = true;

    try {
        const response = await offlineClient.get('/visualization-preferences');

        Object.assign(expenseListDisplayPreferences.value, response.data.expense_list ?? {});
    } catch (error) {
        errorMessage.value = error?.response?.data?.message ?? 'No fue posible cargar las preferencias de visualización.';
    } finally {
        loadingVisualizationPreferences.value = false;
    }
};

const saveVisualizationPreferences = async () => {
    savingVisualizationPreferences.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        const response = await offlineClient.mutate('put', '/visualization-preferences', {
            expense_list: expenseListDisplayPreferences.value,
        });

        Object.assign(expenseListDisplayPreferences.value, response.data.expense_list);
        successMessage.value = 'Preferencias de visualización guardadas correctamente.';
    } catch (error) {
        errorMessage.value = error?.response?.data?.message ?? 'No fue posible guardar las preferencias de visualización.';
    } finally {
        savingVisualizationPreferences.value = false;
    }
};

const form = ref({
    type: 'expense',
    description: '',
    place: '',
    amount: '',
    currency: 'ARS',
    category_id: '',
    purchase_date: formatLocalDate(),
    payment_method: 'cash',
    card_id: '',
    installments_count: 1,
    notes: '',
    tag_ids: [],
});
const exchangeForm = ref({
    source_currency: 'ARS', source_amount: '', target_currency: 'USD', target_amount: '',
    purchase_date: formatLocalDate(), description: 'Cambio de moneda', place: '', notes: '',
    category_id: '', tag_ids: [],
});
const exchangeReturnScreen = ref('expense-list');
const openExchangeForm = () => {
    exchangeReturnScreen.value = 'expense-list';
    exchangeForm.value = {
        source_currency: 'ARS', source_amount: '', target_currency: 'USD', target_amount: '',
        purchase_date: formatLocalDate(), description: 'Cambio de moneda', place: '', notes: '',
        category_id: '', tag_ids: [],
    };
    openTransactionForm('exchange');
};
const changeExchangeType = (type) => {
    form.value.type = type;
    forcedTransactionType.value = null;
    navigateToScreen('transaction-form');
};
const returnFromExchange = () => {
    editingTransactionId.value = null;
    forcedTransactionType.value = null;
    navigateToScreen(exchangeReturnScreen.value);
};
const latestExchange = ref(null);
const hasExchangeSuggestion = computed(() => latestExchange.value !== null);
const suggestExchangeAmount = (editedSide) => {
    const amounts = exchangeReferenceAmounts(latestExchange.value, exchangeForm.value.source_currency, exchangeForm.value.target_currency);
    if (!amounts) {
        return;
    }
    if (editedSide === 'source' && !exchangeForm.value.target_amount && Number(exchangeForm.value.source_amount) > 0) {
        exchangeForm.value.target_amount = suggestedExchangeAmount(exchangeForm.value.source_amount, amounts.source, amounts.target);
    } else if (editedSide === 'target' && !exchangeForm.value.source_amount && Number(exchangeForm.value.target_amount) > 0) {
        exchangeForm.value.source_amount = suggestedExchangeAmount(exchangeForm.value.target_amount, amounts.target, amounts.source);
    }
};
const loadExchangeSuggestion = async () => {
    latestExchange.value = null;
    const sourceCurrency = exchangeForm.value.source_currency;
    const targetCurrency = exchangeForm.value.target_currency;
    if (sourceCurrency === targetCurrency) {
        return;
    }
    try {
        const response = await offlineClient.get('/currency-exchanges/latest', {
            params: { source_currency: sourceCurrency, target_currency: targetCurrency },
            fresh: true,
        });
        if (exchangeForm.value.source_currency === sourceCurrency && exchangeForm.value.target_currency === targetCurrency) {
            latestExchange.value = response.data;
        }
    } catch (error) {
        if (error.offlineUnavailable) {
            try {
                const response = await offlineClient.get('/currency-exchanges/latest', {
                    params: { source_currency: sourceCurrency, target_currency: targetCurrency },
                });
                if (exchangeForm.value.source_currency === sourceCurrency && exchangeForm.value.target_currency === targetCurrency) {
                    latestExchange.value = response.data;
                }
            } catch {
                latestExchange.value = null;
            }
        } else {
            console.error('No fue posible cargar la última cotización.', error);
        }
    }
};
watch(() => [form.value.type, exchangeForm.value.source_currency, exchangeForm.value.target_currency], ([type]) => {
    if (type === 'exchange') {
        void loadExchangeSuggestion();
    }
});

const {
    activePrimaryTab,
    activeScreen,
    editingTransactionId,
    forcedTransactionType,
    openGenericTransactionForm,
    openTransactionForm,
    returnToTransactionList,
    setActiveScreenFromMenu: navigateToScreen,
} = useNavigation({ form });

const {
    categoryOptions,
    firstInstallmentPaymentDate,
    firstInstallmentPaymentDateIsEstimated,
    isCreditPayment,
    installmentPreview,
    PAYMENT_METHODS,
    resetTransactionForm,
    showInstallments,
    transactionFormTitle,
} = useTransactionForm({
    cards,
    categories,
    editingTransactionId,
    forcedTransactionType,
    form,
});

const {
    billingCycleForms,
    cardForm,
    categoryForm,
    categoryScopeLabel,
    editBillingCycle,
    editCard,
    editCategory,
    editTag,
    getBillingCycleForm,
    removeCard,
    removeBillingCycle,
    removeCategory,
    removeTag,
    resetBillingCycleForm,
    resetCardForm,
    resetCategoryForm,
    resetTagForm,
    savingBillingCycle,
    savingCard,
    savingCategory,
    savingTag,
    tagForm,
    submitBillingCycle,
    submitCard,
    submitCategory,
    submitTag,
} = useCatalogManagement({
    cards,
    categories,
    errorMessage,
    form,
    loadCards,
    loadCategories,
    loadTags,
    runWithLoading,
    successMessage,
    tags,
});

const netTotals = computed(() => Object.fromEntries(
    Object.keys(incomeTotals.value).map((currency) => [
        currency,
        incomeTotals.value[currency] - expenseTotals.value[currency],
    ]),
));

const cardsSummary = computed(() => [
    { amounts: incomeTotals.value, colorClass: 'text-emerald-600 dark:text-emerald-400', target: 'income-list', title: 'Ingresos' },
    { amounts: expenseTotals.value, colorClass: 'text-rose-400', target: 'expense-list', title: 'Gastos' },
    { amounts: netTotals.value, colorClass: 'text-slate-900 dark:text-slate-100', title: 'Saldo' },
]);

const formatDate = (value) => {

    if (!value) {
        return '';
    }

    const [year, month, day] = String(value).split('-');

    if (year?.length === 4 && month?.length === 2 && day?.length === 2) {
        return `${day}/${month}/${year}`;
    }

    const parsedDate = new Date(value);

    if (Number.isNaN(parsedDate.getTime())) {
        return value;
    }

    return parsedDate.toLocaleDateString('es-AR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
};

const formatAmount = (value) => Number(value ?? 0).toLocaleString('es-AR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const loadPlaces = async () => {
    let response;

    try {
        response = await offlineClient.get('/transactions/places', { fresh: true });
    } catch (error) {
        if (! error.offlineUnavailable) {
            throw error;
        }

        response = await offlineClient.get('/transactions/places');
    }

    places.value = response.data;
};

const loadRecurringPreview = async () => {
    const period = selectedPeriod.value;
    recurringPreview.value = [];
    try {
        const response = await offlineClient.get('/recurring-expenses/preview', { params: { period }, fresh: true });
        if (selectedPeriod.value === period) {
            recurringPreview.value = response.data;
            recurringOffline.value = false;
        }
    } catch (error) {
        if (!error.offlineUnavailable) {
            throw error;
        }
        recurringPreview.value = [];
        recurringOffline.value = true;
    }
};

const loadRecurringRules = async () => {
    try {
        const response = await offlineClient.get('/recurring-expenses', { fresh: true });
        recurringRules.value = response.data;
        recurringOffline.value = false;
    } catch (error) {
        if (!error.offlineUnavailable) {
            throw error;
        }
        recurringRules.value = [];
        recurringOffline.value = true;
    }
};

const saveRecurringRule = async ({ id, payload }) => {
    recurringBusy.value = true;
    errorMessage.value = '';
    successMessage.value = '';
    try {
        const url = id ? `/recurring-expenses/${id}` : '/recurring-expenses';
        await window.axios.request({ method: id ? 'put' : 'post', url, data: payload });
        await Promise.all([loadRecurringRules(), loadRecurringPreview()]);
        recurringPage.value?.reset();
        successMessage.value = id ? 'Recurrencia actualizada.' : 'Recurrencia creada.';
    } catch (error) {
        errorMessage.value = error?.response?.data?.message ?? 'No fue posible guardar la recurrencia.';
    } finally {
        recurringBusy.value = false;
    }
};

const saveRecurringOccurrence = async ({ id, status, amount, description }) => {
    recurringBusy.value = true;
    errorMessage.value = '';
    successMessage.value = '';
    try {
        await window.axios.put(`/recurring-expenses/${id}/decisions`, {
            period: selectedPeriod.value,
            status,
            ...(status === 'confirmed' ? { amount, description } : {}),
        });
        invalidateTransactions();
        await Promise.all([loadRecurringPreview(), loadTransactions({ force: true })]);
        successMessage.value = status === 'confirmed' ? 'Gasto de este mes guardado.' : 'Gasto quitado de este mes.';
    } catch (error) {
        errorMessage.value = error?.response?.data?.message ?? 'No fue posible registrar la decisión.';
    } finally {
        recurringBusy.value = false;
    }
};

const openTransactionEdit = async (listedTransaction) => {
    if (listedTransaction.exchange_id) {
        await openExchangeEdit(listedTransaction.exchange_id, listedTransaction.type === 'income' ? 'income-list' : 'expense-list');
        return;
    }
    const transactionId = listedTransaction.transaction_id ?? listedTransaction.id;

    await runWithLoading(async () => {
        const response = await offlineClient.get(`/transactions/${transactionId}`);
        const transaction = response.data;

        form.value.type = transaction.type;
        form.value.description = transaction.description;
        form.value.place = transaction.place ?? '';
        form.value.amount = transaction.amount;
        form.value.currency = transaction.currency ?? 'ARS';
        form.value.category_id = transaction.category_id ?? '';
        form.value.purchase_date = toInputDateValue(transaction.purchase_date);
        form.value.payment_method = transaction.payment_method ?? 'cash';
        form.value.card_id = transaction.card_id ?? '';
        form.value.installments_count = transaction.installment_plan?.installments_count ?? 1;
        form.value.notes = transaction.notes ?? '';
        form.value.tag_ids = (transaction.tags ?? []).map((tag) => tag.id);
        editingTransactionId.value = transaction.id;
        forcedTransactionType.value = transaction.type;
        navigateToScreen('transaction-form');
    }, 'No fue posible cargar la transacción.');
};

const openExchangeEdit = async (exchangeId, returnScreen = 'expense-list') => {
    exchangeReturnScreen.value = returnScreen;
    await runWithLoading(async () => {
        const response = await offlineClient.get(`/currency-exchanges/${exchangeId}`);
        const exchange = response.data;
        exchangeForm.value = {
            source_currency: exchange.expense.currency,
            source_amount: exchange.expense.amount,
            target_currency: exchange.income.currency,
            target_amount: exchange.income.amount,
            purchase_date: toInputDateValue(exchange.expense.purchase_date),
            description: exchange.expense.description,
            place: exchange.expense.place ?? '',
            notes: exchange.expense.notes ?? '',
            category_id: exchange.expense.category_id ?? '',
            tag_ids: (exchange.expense.tags ?? []).map((tag) => tag.id),
        };
        form.value.type = 'exchange';
        editingTransactionId.value = exchange.id;
        forcedTransactionType.value = 'exchange';
        navigateToScreen('transaction-form');
    }, 'No fue posible cargar el cambio de moneda.');
};

onMounted(() => {
    void loadVisualizationPreferences();

    if (activeScreen.value !== 'transaction-form') {
        return;
    }

    if (editingTransactionId.value !== null) {
        if (forcedTransactionType.value === 'exchange') {
            void openExchangeEdit(editingTransactionId.value);
        } else {
            void openTransactionEdit({ id: editingTransactionId.value });
        }

        return;
    }

    if (forcedTransactionType.value !== null) {
        form.value.type = forcedTransactionType.value;
    }
});

const formatCurrencyAmount = (currency, value) => `${CURRENCY_SYMBOLS[currency] ?? currency} ${formatAmount(value)}`;

const toggleUserMenu = () => {
    userMenuOpen.value = !userMenuOpen.value;
};

const closeUserMenu = () => {
    userMenuOpen.value = false;
};

const setActiveScreenFromMenu = (screen) => {
    navigateToScreen(screen);
    sidebarOpen.value = false;
    closeUserMenu();
};

const retryOfflineSync = () => {
    void offlineClient.retry();
};

const submitLogout = (event) => submitLogoutForm(event, offlineClient);

const onDocumentPointerDown = (event) => {
    if (!userMenuOpen.value) {
        return;
    }

    if (userMenuRef.value && !userMenuRef.value.contains(event.target)) {
        closeUserMenu();
    }
};

document.addEventListener('pointerdown', onDocumentPointerDown);

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', onDocumentPointerDown);
});

watch(
    () => activeScreen.value,
    async (screen) => {
        if (screen === 'dashboard' || screen === 'income-list' || screen === 'expense-list') {
            await runWithLoading(loadTransactions, 'No fue posible cargar las transacciones.');
            if (screen === 'expense-list' || screen === 'dashboard') {
                await runWithLoading(loadRecurringPreview, 'No fue posible cargar los gastos recurrentes.');
            }
            return;
        }

        if (screen === 'recurring-expenses') {
            await runWithLoading(() => Promise.all([
                loadRecurringRules(), loadRecurringPreview(), loadCards(), loadCategories(),
                loadPlaces().catch((error) => {
                    if (!error.offlineUnavailable) {
                        throw error;
                    }
                }),
            ]), 'No fue posible cargar las recurrencias.');
            return;
        }

        if (screen === 'categories') {
            await runWithLoading(loadCategories, 'No fue posible cargar las categorías.');
            return;
        }

        if (screen === 'tags') {
            await runWithLoading(loadTags, 'No fue posible cargar los tags.');
            return;
        }

        if (screen === 'cards') {
            await runWithLoading(loadCards, 'No fue posible cargar las tarjetas.');
            return;
        }

        if (screen === 'transaction-form') {
            await runWithLoading(
                () => Promise.all([ensureTransactionFormData(), loadPlaces()]),
                'No fue posible cargar los datos del formulario.'
            );
        }
    },
    { immediate: true }
);

watch(
    () => selectedPeriod.value,
    async () => {
        await runWithLoading(loadTransactions, 'No fue posible cargar las transacciones.');
        if (['dashboard', 'expense-list', 'recurring-expenses'].includes(activeScreen.value)) {
            await runWithLoading(loadRecurringPreview, 'No fue posible cargar los gastos recurrentes.');
        }
    }
);

watch(() => offlineSyncState.value.status, async (status) => {
    if (status === 'synced' && offlineSyncState.value.pending === 0 && offlineSyncState.value.failed === 0
        && ['dashboard', 'income-list', 'expense-list'].includes(activeScreen.value)) {
        invalidateTransactions();
        await runWithLoading(() => loadTransactions({ force: true }), 'No fue posible actualizar las transacciones.');
        if (['dashboard', 'expense-list'].includes(activeScreen.value)) {
            await runWithLoading(loadRecurringPreview, 'No fue posible actualizar los gastos recurrentes.');
        }
    }
    if (status === 'synced' && activeScreen.value === 'recurring-expenses') {
        await runWithLoading(() => Promise.all([loadRecurringRules(), loadRecurringPreview()]), 'No fue posible actualizar las recurrencias.');
    }
});

watch(
    () => activeScreen.value,
    () => {
        successMessage.value = '';
        closeUserMenu();
    }
);

const toInputDateValue = (value) => {
    if (!value) {
        return '';
    }

    return String(value).slice(0, 10);
};

const submitTransaction = async () => {
    if (form.value.type === 'exchange') {
        await submitExchange();
        return;
    }
    savingTransaction.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        const payload = {
            type: form.value.type,
            description: form.value.description,
            place: form.value.type === 'expense' ? form.value.place.trim() || null : null,
            amount: form.value.amount,
            currency: form.value.currency,
            purchase_date: form.value.purchase_date,
            category_id: form.value.category_id || null,
            notes: form.value.notes || null,
            tag_ids: form.value.tag_ids,
            ...(form.value.type === 'expense'
                ? {
                    payment_method: form.value.payment_method,
                    card_id: isCreditPayment.value ? Number(form.value.card_id) : null,
                    installments_count: isCreditPayment.value ? Number(form.value.installments_count) : 1,
                }
                : {}),
        };

        const isEditingTransaction = editingTransactionId.value !== null;

        let createdTransaction;

        if (isEditingTransaction) {
            delete payload.installments_count;
            await offlineClient.mutate('put', `/transactions/${editingTransactionId.value}`, payload);
        } else {
            createdTransaction = await offlineClient.mutate('post', '/transactions', payload);
        }

        const registeredType = form.value.type;

        successMessage.value = isEditingTransaction
            ? 'Transacción actualizada correctamente.'
            : 'Transacción guardada correctamente.';
        if (isEditingTransaction) {
            invalidateTransactions();
        } else {
            addTransaction(createdTransaction.data, {
                isPending: createdTransaction.isPending,
                queuedAt: createdTransaction.queuedAt,
            });
            if (registeredType === 'expense' && createdTransaction.data.place) {
                places.value = [...new Set([...places.value, createdTransaction.data.place])]
                    .sort((left, right) => left.localeCompare(right, 'es-AR', { sensitivity: 'base' }));
            }
        }
        resetTransactionForm();
        editingTransactionId.value = null;
        forcedTransactionType.value = null;
        navigateToScreen(registeredType === 'income' ? 'income-list' : 'expense-list');
    } catch (error) {
        errorMessage.value = error?.response?.data?.message ?? error?.message ?? 'No fue posible guardar la transacción.';
    } finally {
        savingTransaction.value = false;
    }
};

const submitExchange = async () => {
    savingTransaction.value = true;
    errorMessage.value = '';
    successMessage.value = '';
    try {
        const payload = {
            ...exchangeForm.value,
            place: exchangeForm.value.place.trim() || null,
            notes: exchangeForm.value.notes.trim() || null,
            category_id: exchangeForm.value.category_id || null,
            tag_ids: exchangeForm.value.tag_ids,
        };
        const editing = editingTransactionId.value !== null;
        const url = editing ? `/currency-exchanges/${editingTransactionId.value}` : '/currency-exchanges';
        await offlineClient.mutate(editing ? 'put' : 'post', url, payload);
        invalidateTransactions();
        successMessage.value = editing ? 'Cambio de moneda actualizado.' : 'Cambio de moneda guardado.';
        editingTransactionId.value = null;
        forcedTransactionType.value = null;
        navigateToScreen(exchangeReturnScreen.value);
        await loadTransactions();
    } catch (error) {
        errorMessage.value = error?.response?.data?.message ?? error?.message ?? 'No fue posible guardar el cambio de moneda.';
    } finally {
        savingTransaction.value = false;
    }
};

const deleteExchange = async () => {
    if (editingTransactionId.value === null || !window.confirm('¿Eliminar este cambio de moneda y sus dos movimientos?')) {
        return;
    }
    deletingTransaction.value = true;
    try {
        await offlineClient.mutate('delete', `/currency-exchanges/${editingTransactionId.value}`);
        invalidateTransactions();
        editingTransactionId.value = null;
        forcedTransactionType.value = null;
        successMessage.value = 'Cambio de moneda eliminado.';
        navigateToScreen(exchangeReturnScreen.value);
        await loadTransactions();
    } catch (error) {
        errorMessage.value = error?.response?.data?.message ?? error?.message ?? 'No fue posible eliminar el cambio de moneda.';
    } finally {
        deletingTransaction.value = false;
    }
};

const deleteTransaction = async () => {
    if (editingTransactionId.value === null || !window.confirm('¿Eliminar esta transacción? Esta acción no se puede deshacer.')) {
        return;
    }

    deletingTransaction.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        const deletedType = form.value.type;

        await offlineClient.mutate('delete', `/transactions/${editingTransactionId.value}`);

        successMessage.value = 'Transacción eliminada correctamente.';
        invalidateTransactions();
        resetTransactionForm();
        editingTransactionId.value = null;
        forcedTransactionType.value = null;
        navigateToScreen(deletedType === 'income' ? 'income-list' : 'expense-list');
    } catch (error) {
        errorMessage.value = error?.response?.data?.message ?? 'No fue posible eliminar la transacción.';
    } finally {
        deletingTransaction.value = false;
    }
};

</script>

<template>
    <AdminLayout :active-primary-tab="activePrimaryTab" :active-screen="activeScreen" :expense-totals="expenseTotals" :format-currency-amount="formatCurrencyAmount" :income-totals="incomeTotals" :is-dark-mode="isDarkMode" :selected-period="selectedPeriod" :sidebar-open="sidebarOpen" :sync-state="offlineSyncState" :transactions-loading="transactionsLoading" :user-initials="userInitials" :user-menu-open="userMenuOpen" :user-name="userName" @navigate="setActiveScreenFromMenu" @retry-sync="retryOfflineSync" @set-sidebar-open="sidebarOpen = $event" @toggle-color-mode="toggleColorMode" @toggle-user-menu="toggleUserMenu" @update:selected-period="selectedPeriod = $event">
        <template #user-menu="{ open }">
            <div v-if="open" ref="userMenuRef" class="absolute right-0 z-50 mt-2 w-56 rounded-md border border-border bg-popover p-1 shadow-lg">
                <p class="px-3 py-2 text-xs text-muted-foreground">Sesión activa</p>
                <div class="my-1 border-t border-border"></div>
                <button type="button" class="w-full rounded-md px-3 py-2 text-left text-sm hover:bg-accent sm:hidden" @click="toggleColorMode">
                    {{ isDarkMode ? 'Usar tema claro' : 'Usar tema oscuro' }}
                </button>
                <form method="POST" action="/logout" class="w-full" @submit="submitLogout">
                    <input type="hidden" name="_token" :value="csrfToken">
                    <button type="submit" class="w-full rounded-md px-3 py-2 text-left text-sm text-red-700 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-950/40">Cerrar sesión</button>
                </form>
            </div>
        </template>

        <TransactionListPage v-if="activeScreen === 'income-list'" :collapsed-dates="collapsedDatesByList.income" empty-message="No hay ingresos registrados." :format-currency-amount="formatCurrencyAmount" :loading="loading" :selected-period="selectedPeriod" title="Ingresos" :transactions="incomeTransactions" @back="setActiveScreenFromMenu('dashboard')" @create="openTransactionForm('income')" @edit="openTransactionEdit" @update:collapsed-dates="updateCollapsedDates('income', $event)" />

        <TransactionListPage v-if="activeScreen === 'expense-list'" :collapsed-dates="collapsedDatesByList.expense" :display-preferences="expenseListDisplayPreferences" empty-message="No hay egresos registrados." :format-currency-amount="formatCurrencyAmount" :loading="loading" :recurring-busy="recurringBusy" :selected-period="selectedPeriod" :show-payment-method-filter="true" title="Egresos" :transactions="expenseTransactions" @back="setActiveScreenFromMenu('dashboard')" @create="openTransactionForm('expense')" @edit="openTransactionEdit" @save-recurring="saveRecurringOccurrence" @update:collapsed-dates="updateCollapsedDates('expense', $event)" />

        <RecurringExpensesPage v-if="activeScreen === 'recurring-expenses'" ref="recurringPage" :rules="recurringRules" :cards="cards" :categories="categories" :places="places" :selected-period="selectedPeriod" :format-amount="formatAmount" :format-currency-amount="formatCurrencyAmount" :busy="recurringBusy" :offline="recurringOffline" @save="saveRecurringRule" @back="setActiveScreenFromMenu('expense-list')" />

        <DashboardPage v-if="activeScreen === 'dashboard'" :cards-summary="cardsSummary" :format-currency-amount="formatCurrencyAmount" :format-date="formatDate" :loading="loading" :recent-transactions="dashboardRecentTransactions" :recurring-pending-count="recurringPendingCount" @create-expense="openTransactionForm('expense')" @create-exchange="openExchangeForm" @navigate="setActiveScreenFromMenu" />

        <TransactionFormPage v-if="activeScreen === 'transaction-form' && form.type !== 'exchange'" :cards="cards" :categories="categories" :category-options="categoryOptions" :currencies="CURRENCY_OPTIONS" :deleting="deletingTransaction" :editing="editingTransactionId !== null" :first-installment-payment-date="firstInstallmentPaymentDate" :first-installment-payment-date-is-estimated="firstInstallmentPaymentDateIsEstimated" :forced-transaction-type="forcedTransactionType" :form="form" :format-amount="formatAmount" :format-currency-amount="formatCurrencyAmount" :format-date="formatDate" :installment-preview="installmentPreview" :is-credit-payment="isCreditPayment" :payment-methods="PAYMENT_METHODS" :places="places" :saving="savingTransaction" :show-installments="showInstallments" :tags="tags" :title="transactionFormTitle" @back="returnToTransactionList" @delete="deleteTransaction" @submit="submitTransaction" />

        <CurrencyExchangeFormPage v-if="activeScreen === 'transaction-form' && form.type === 'exchange'" :form="exchangeForm" :currencies="CURRENCY_OPTIONS" :categories="categories" :tags="tags" :format-amount="formatAmount" :editing="editingTransactionId !== null" :saving="savingTransaction" :deleting="deletingTransaction" :has-suggestion="hasExchangeSuggestion" @back="returnFromExchange" @submit="submitExchange" @delete="deleteExchange" @change-type="changeExchangeType" @source-input="suggestExchangeAmount('source')" @target-input="suggestExchangeAmount('target')" />

        <CardsPage v-if="activeScreen === 'cards'" :billing-cycle-forms="billingCycleForms" :card-form="cardForm" :cards="cards" :format-date="formatDate" :get-billing-cycle-form="getBillingCycleForm" :saving-billing-cycle="savingBillingCycle" :saving-card="savingCard" @edit-billing-cycle="editBillingCycle" @edit-card="editCard" @remove-billing-cycle="removeBillingCycle" @remove-card="removeCard" @reset-billing-cycle="resetBillingCycleForm" @reset-card="resetCardForm" @submit-billing-cycle="submitBillingCycle" @submit-card="submitCard" />

        <CategoriesPage v-if="activeScreen === 'categories'" :categories="categories" :form="categoryForm" :saving="savingCategory" :scope-label="categoryScopeLabel" @edit="editCategory" @remove="removeCategory" @reset="resetCategoryForm" @submit="submitCategory" />

        <TagsPage v-if="activeScreen === 'tags'" :form="tagForm" :saving="savingTag" :tags="tags" @edit="editTag" @remove="removeTag" @reset="resetTagForm" @submit="submitTag" />

        <VisualizationPage v-if="activeScreen === 'visualization'" :loading="loadingVisualizationPreferences" :preferences="expenseListDisplayPreferences" :saving="savingVisualizationPreferences" @submit="saveVisualizationPreferences" />

            <p v-if="errorMessage" class="rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">{{ errorMessage }}</p>
            <p v-if="successMessage" class="rounded-md border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ successMessage }}</p>
    </AdminLayout>
</template>
