<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useAsyncAction } from '../composables/useAsyncAction';
import { useCatalogs } from '../composables/useCatalogs';
import { useCatalogManagement } from '../composables/useCatalogManagement';
import { useColorMode } from '../composables/useColorMode';
import { useNavigation } from '../composables/useNavigation';
import { useTransactionForm } from '../composables/useTransactionForm';
import { useTransactions } from '../composables/useTransactions';
import AdminLayout from './admin/AdminLayout.vue';
import CardsPage from '../pages/CardsPage.vue';
import CategoriesPage from '../pages/CategoriesPage.vue';
import DashboardPage from '../pages/DashboardPage.vue';
import TagsPage from '../pages/TagsPage.vue';
import TransactionFormPage from '../pages/TransactionFormPage.vue';
import TransactionListPage from '../pages/TransactionListPage.vue';

const rootElement = document.getElementById('spendo-app');
const userName = rootElement?.dataset.userName ?? 'Usuario';
const currencySymbol = rootElement?.dataset.currencySymbol ?? '$';
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const selectedPeriod = ref(new Date().toISOString().slice(0, 7));
const userMenuRef = ref(null);
const userMenuOpen = ref(false);
const sidebarOpen = ref(false);
const savingTransaction = ref(false);
const deletingTransaction = ref(false);
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
    dashboardRecentTransactions,
    expenseTotal,
    expenseTransactions,
    incomeTotal,
    incomeTransactions,
    invalidateTransactions,
    loadTransactions,
    transactionsLoading,
} = useTransactions(selectedPeriod);

const userInitials = computed(() => userName
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((name) => name[0])
    .join('')
    .toUpperCase());

const form = ref({
    type: 'expense',
    description: '',
    amount: '',
    category_id: '',
    purchase_date: new Date().toISOString().slice(0, 10),
    payment_method: 'cash',
    card_id: '',
    installments_count: 1,
    notes: '',
    tag_ids: [],
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

const cardsSummary = computed(() => [
    { title: 'Ingresos', value: `${currencySymbol}${formatAmount(incomeTotal.value)}` },
    { title: 'Gastos', value: `${currencySymbol}${formatAmount(expenseTotal.value)}` },
    { title: 'Saldo', value: `${currencySymbol}${formatAmount(incomeTotal.value - expenseTotal.value)}` },
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

const openTransactionEdit = async (listedTransaction) => {
    const transactionId = listedTransaction.transaction_id ?? listedTransaction.id;

    await runWithLoading(async () => {
        const response = await window.axios.get(`/transactions/${transactionId}`);
        const transaction = response.data;

        form.value.type = transaction.type;
        form.value.description = transaction.description;
        form.value.amount = transaction.amount;
        form.value.category_id = transaction.category_id ?? '';
        form.value.purchase_date = toInputDateValue(transaction.purchase_date);
        form.value.payment_method = transaction.payment_method ?? 'cash';
        form.value.card_id = transaction.card_id ?? '';
        form.value.installments_count = transaction.installment_plan?.installments_count ?? 1;
        form.value.notes = transaction.notes ?? '';
        form.value.tag_ids = (transaction.tags ?? []).map((tag) => tag.id);
        editingTransactionId.value = transaction.id;
        forcedTransactionType.value = transaction.type;
        activeScreen.value = 'transaction-form';
    }, 'No fue posible cargar la transacción.');
};

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
            await runWithLoading(ensureTransactionFormData, 'No fue posible cargar los datos del formulario.');
        }
    },
    { immediate: true }
);

watch(
    () => selectedPeriod.value,
    async () => {
        await runWithLoading(loadTransactions, 'No fue posible cargar las transacciones.');
    }
);

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
    savingTransaction.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        const payload = {
            type: form.value.type,
            description: form.value.description,
            amount: form.value.amount,
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

        if (isEditingTransaction) {
            delete payload.installments_count;
            await window.axios.put(`/transactions/${editingTransactionId.value}`, payload);
        } else {
            await window.axios.post('/transactions', payload);
        }

        const registeredType = form.value.type;

        successMessage.value = isEditingTransaction
            ? 'Transacción actualizada correctamente.'
            : 'Transacción guardada correctamente.';
        invalidateTransactions();
        resetTransactionForm();
        editingTransactionId.value = null;
        forcedTransactionType.value = null;
        activeScreen.value = registeredType === 'income' ? 'income-list' : 'expense-list';
    } catch (error) {
        errorMessage.value = error?.response?.data?.message ?? 'No fue posible guardar la transacción.';
    } finally {
        savingTransaction.value = false;
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

        await window.axios.delete(`/transactions/${editingTransactionId.value}`);

        successMessage.value = 'Transacción eliminada correctamente.';
        invalidateTransactions();
        resetTransactionForm();
        editingTransactionId.value = null;
        forcedTransactionType.value = null;
        activeScreen.value = deletedType === 'income' ? 'income-list' : 'expense-list';
    } catch (error) {
        errorMessage.value = error?.response?.data?.message ?? 'No fue posible eliminar la transacción.';
    } finally {
        deletingTransaction.value = false;
    }
};

</script>

<template>
    <AdminLayout :active-primary-tab="activePrimaryTab" :active-screen="activeScreen" :currency-symbol="currencySymbol" :expense-total="expenseTotal" :format-amount="formatAmount" :income-total="incomeTotal" :is-dark-mode="isDarkMode" :selected-period="selectedPeriod" :sidebar-open="sidebarOpen" :transactions-loading="transactionsLoading" :user-initials="userInitials" :user-menu-open="userMenuOpen" :user-name="userName" @navigate="setActiveScreenFromMenu" @set-sidebar-open="sidebarOpen = $event" @toggle-color-mode="toggleColorMode" @toggle-user-menu="toggleUserMenu" @update:selected-period="selectedPeriod = $event">
        <template #user-menu="{ open }">
            <div v-if="open" ref="userMenuRef" class="absolute right-0 z-50 mt-2 w-56 rounded-md border border-border bg-popover p-1 shadow-lg">
                <p class="px-3 py-2 text-xs text-muted-foreground">Sesión activa</p>
                <div class="my-1 border-t border-border"></div>
                <button type="button" class="w-full rounded-md px-3 py-2 text-left text-sm hover:bg-accent sm:hidden" @click="toggleColorMode">
                    {{ isDarkMode ? 'Usar tema claro' : 'Usar tema oscuro' }}
                </button>
                <form method="POST" action="/logout" class="w-full">
                    <input type="hidden" name="_token" :value="csrfToken">
                    <button type="submit" class="w-full rounded-md px-3 py-2 text-left text-sm text-red-700 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-950/40">Cerrar sesión</button>
                </form>
            </div>
        </template>

        <TransactionListPage v-if="activeScreen === 'income-list'" :currency-symbol="currencySymbol" empty-message="No hay ingresos registrados." :format-amount="formatAmount" :loading="loading" title="Ingresos" :transactions="incomeTransactions" @back="setActiveScreenFromMenu('dashboard')" @create="openTransactionForm('income')" @edit="openTransactionEdit" />

        <TransactionListPage v-if="activeScreen === 'expense-list'" :currency-symbol="currencySymbol" empty-message="No hay egresos registrados." :format-amount="formatAmount" :loading="loading" title="Egresos" :transactions="expenseTransactions" @back="setActiveScreenFromMenu('dashboard')" @create="openTransactionForm('expense')" @edit="openTransactionEdit" />

        <DashboardPage v-if="activeScreen === 'dashboard'" :cards-summary="cardsSummary" :currency-symbol="currencySymbol" :format-amount="formatAmount" :format-date="formatDate" :loading="loading" :recent-transactions="dashboardRecentTransactions" @create-expense="openTransactionForm('expense')" />

        <TransactionFormPage v-if="activeScreen === 'transaction-form'" :cards="cards" :categories="categories" :category-options="categoryOptions" :currency-symbol="currencySymbol" :deleting="deletingTransaction" :editing="editingTransactionId !== null" :first-installment-payment-date="firstInstallmentPaymentDate" :first-installment-payment-date-is-estimated="firstInstallmentPaymentDateIsEstimated" :forced-transaction-type="forcedTransactionType" :form="form" :format-amount="formatAmount" :format-date="formatDate" :installment-preview="installmentPreview" :is-credit-payment="isCreditPayment" :payment-methods="PAYMENT_METHODS" :saving="savingTransaction" :show-installments="showInstallments" :tags="tags" :title="transactionFormTitle" @back="returnToTransactionList" @delete="deleteTransaction" @submit="submitTransaction" />

        <CardsPage v-if="activeScreen === 'cards'" :billing-cycle-forms="billingCycleForms" :card-form="cardForm" :cards="cards" :format-date="formatDate" :get-billing-cycle-form="getBillingCycleForm" :saving-billing-cycle="savingBillingCycle" :saving-card="savingCard" @edit-billing-cycle="editBillingCycle" @edit-card="editCard" @remove-card="removeCard" @reset-billing-cycle="resetBillingCycleForm" @reset-card="resetCardForm" @submit-billing-cycle="submitBillingCycle" @submit-card="submitCard" />

        <CategoriesPage v-if="activeScreen === 'categories'" :categories="categories" :form="categoryForm" :saving="savingCategory" :scope-label="categoryScopeLabel" @edit="editCategory" @remove="removeCategory" @reset="resetCategoryForm" @submit="submitCategory" />

        <TagsPage v-if="activeScreen === 'tags'" :form="tagForm" :saving="savingTag" :tags="tags" @edit="editTag" @remove="removeTag" @reset="resetTagForm" @submit="submitTag" />

            <p v-if="errorMessage" class="rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">{{ errorMessage }}</p>
            <p v-if="successMessage" class="rounded-md border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ successMessage }}</p>
    </AdminLayout>
</template>
