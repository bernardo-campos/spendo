<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useTransactions } from '../composables/useTransactions';
import { calculateFirstInstallmentPaymentDate, hasRealCycleForPurchaseDate } from '../utils/cardPaymentDates';
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

const loading = ref(false);
const activeScreen = ref('dashboard');
const forcedTransactionType = ref(null);
const selectedPeriod = ref(new Date().toISOString().slice(0, 7));
const userMenuRef = ref(null);
const userMenuOpen = ref(false);
const sidebarOpen = ref(false);
const isDarkMode = ref(false);
const savingTransaction = ref(false);
const deletingTransaction = ref(false);
const editingTransactionId = ref(null);
const savingCategory = ref(false);
const savingTag = ref(false);
const savingCard = ref(false);
const savingBillingCycle = ref(false);
const errorMessage = ref('');
const successMessage = ref('');

const PAYMENT_METHODS = [
    { value: 'cash', label: 'Efectivo' },
    { value: 'credit', label: 'Crédito' },
];

const CATEGORY_SCOPE_LABELS = {
    both: 'Ambos',
    expense: 'Gasto',
    income: 'Ingreso',
};

const categories = ref([]);
const tags = ref([]);
const cards = ref([]);
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

const categoryForm = ref({
    id: null,
    name: '',
    scope: 'both',
});

const tagForm = ref({
    id: null,
    name: '',
});

const cardForm = ref({
    id: null,
    name: '',
    last_four_digits: '',
    closing_day: '',
    due_day: '',
    is_active: true,
});

const billingCycleForms = ref({});

const normalizeSlug = (value) => value
    .trim()
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');

const categoryScopeLabel = (scope) => CATEGORY_SCOPE_LABELS[scope] ?? scope;

const categoryOptions = computed(() => {
    if (form.value.type === 'income') {
        return categories.value.filter((category) => category.scope === 'income' || category.scope === 'both');
    }

    return categories.value.filter((category) => category.scope === 'expense' || category.scope === 'both');
});

const isCreditPayment = computed(() => form.value.type === 'expense' && form.value.payment_method === 'credit');

const selectedCard = computed(() => cards.value.find((card) => Number(card.id) === Number(form.value.card_id)) ?? null);

const showInstallments = computed(() => isCreditPayment.value && Number(form.value.installments_count) > 1);

const firstInstallmentPaymentDate = computed(() => {
    if (!isCreditPayment.value || selectedCard.value === null) {
        return null;
    }

    return calculateFirstInstallmentPaymentDate(form.value.purchase_date, selectedCard.value);
});

const firstInstallmentPaymentDateIsEstimated = computed(() => {
    if (!isCreditPayment.value || selectedCard.value === null) {
        return false;
    }

    return !hasRealCycleForPurchaseDate(form.value.purchase_date, selectedCard.value);
});

const cardsSummary = computed(() => [
    { title: 'Ingresos', value: `${currencySymbol}${formatAmount(incomeTotal.value)}` },
    { title: 'Gastos', value: `${currencySymbol}${formatAmount(expenseTotal.value)}` },
    { title: 'Saldo', value: `${currencySymbol}${formatAmount(incomeTotal.value - expenseTotal.value)}` },
]);

const transactionFormTitle = computed(() => {
    if (editingTransactionId.value !== null) {
        return form.value.type === 'income' ? 'Editar ingreso' : 'Editar egreso';
    }

    if (forcedTransactionType.value === 'income') {
        return 'Registrar ingreso';
    }

    if (forcedTransactionType.value === 'expense') {
        return 'Registrar egreso';
    }

    return 'Nueva transacción';
});

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

const returnToTransactionList = () => {
    const transactionType = form.value.type;

    resetTransactionForm();
    editingTransactionId.value = null;
    forcedTransactionType.value = null;
    activeScreen.value = transactionType === 'income' ? 'income-list' : 'expense-list';
};

const toggleUserMenu = () => {
    userMenuOpen.value = !userMenuOpen.value;
};

const closeUserMenu = () => {
    userMenuOpen.value = false;
};

const setActiveScreenFromMenu = (screen) => {
    activeScreen.value = screen;
    sidebarOpen.value = false;
    closeUserMenu();
};

const applyColorMode = (isDark) => {
    isDarkMode.value = isDark;
    document.documentElement.classList.toggle('dark', isDark);
    localStorage.setItem('spendo-color-mode', isDark ? 'dark' : 'light');
};

const toggleColorMode = () => {
    applyColorMode(!isDarkMode.value);
};

const onDocumentPointerDown = (event) => {
    if (!userMenuOpen.value) {
        return;
    }

    if (userMenuRef.value && !userMenuRef.value.contains(event.target)) {
        closeUserMenu();
    }
};

onMounted(() => {
    const storedColorMode = localStorage.getItem('spendo-color-mode');
    const prefersDarkMode = window.matchMedia('(prefers-color-scheme: dark)').matches;

    applyColorMode(storedColorMode ? storedColorMode === 'dark' : prefersDarkMode);
    document.addEventListener('pointerdown', onDocumentPointerDown);
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', onDocumentPointerDown);
});

const runWithLoading = async (handler, fallbackMessage) => {
    loading.value = true;
    errorMessage.value = '';

    try {
        await handler();
    } catch (error) {
        errorMessage.value = error?.response?.data?.message ?? fallbackMessage;
    } finally {
        loading.value = false;
    }
};

const loadCategories = async () => {
    const response = await window.axios.get('/categories');
    categories.value = response.data;
};

const loadTags = async () => {
    const response = await window.axios.get('/tags');
    tags.value = response.data;
};

const loadCards = async () => {
    const response = await window.axios.get('/cards');
    cards.value = response.data;
};

const ensureTransactionFormData = async () => {
    await Promise.all([
        categories.value.length === 0 ? loadCategories() : Promise.resolve(),
        tags.value.length === 0 ? loadTags() : Promise.resolve(),
        cards.value.length === 0 ? loadCards() : Promise.resolve(),
    ]);
};

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
    () => form.value.type,
    (type) => {
        if (type !== 'expense') {
            form.value.payment_method = 'cash';
            form.value.card_id = '';
            form.value.installments_count = 1;
        }
    }
);

watch(
    () => form.value.payment_method,
    () => {
        if (form.value.payment_method !== 'credit') {
            form.value.card_id = '';
            form.value.installments_count = 1;
        }
    }
);

watch(
    () => activeScreen.value,
    () => {
        successMessage.value = '';
        closeUserMenu();
    }
);

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

const resetCategoryForm = () => {
    categoryForm.value.id = null;
    categoryForm.value.name = '';
    categoryForm.value.scope = 'both';
};

const resetTagForm = () => {
    tagForm.value.id = null;
    tagForm.value.name = '';
};

const resetCardForm = () => {
    cardForm.value.id = null;
    cardForm.value.name = '';
    cardForm.value.last_four_digits = '';
    cardForm.value.closing_day = '';
    cardForm.value.due_day = '';
    cardForm.value.is_active = true;
};

const getBillingCycleForm = (cardId) => {
    if (!billingCycleForms.value[cardId]) {
        billingCycleForms.value[cardId] = {
            id: null,
            closing_date: '',
            due_date: '',
        };
    }

    return billingCycleForms.value[cardId];
};

const resetBillingCycleForm = (cardId) => {
    const cycleForm = getBillingCycleForm(cardId);
    cycleForm.id = null;
    cycleForm.closing_date = '';
    cycleForm.due_date = '';
};

const toInputDateValue = (value) => {
    if (!value) {
        return '';
    }

    return String(value).slice(0, 10);
};

const editBillingCycle = (cardId, cycle) => {
    const cycleForm = getBillingCycleForm(cardId);
    cycleForm.id = cycle.id;
    cycleForm.closing_date = toInputDateValue(cycle.closing_date);
    cycleForm.due_date = toInputDateValue(cycle.due_date);
};

const submitBillingCycle = async (cardId) => {
    savingBillingCycle.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        const cycleForm = getBillingCycleForm(cardId);
        const payload = {
            closing_date: cycleForm.closing_date,
            due_date: cycleForm.due_date,
        };

        if (cycleForm.id === null) {
            await window.axios.post(`/cards/${cardId}/billing-cycles`, payload);
            successMessage.value = 'Ciclo de facturación creado correctamente.';
        } else {
            await window.axios.put(`/cards/${cardId}/billing-cycles/${cycleForm.id}`, payload);
            successMessage.value = 'Ciclo de facturación actualizado correctamente.';
        }

        resetBillingCycleForm(cardId);
        await runWithLoading(loadCards, 'No fue posible cargar las tarjetas.');
    } catch (error) {
        errorMessage.value = error?.response?.data?.message ?? 'No fue posible guardar el ciclo de facturación.';
    } finally {
        savingBillingCycle.value = false;
    }
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

const editCategory = (category) => {
    categoryForm.value.id = category.id;
    categoryForm.value.name = category.name;
    categoryForm.value.scope = category.scope;
};

const submitCategory = async () => {
    savingCategory.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        const payload = {
            name: categoryForm.value.name,
            slug: normalizeSlug(categoryForm.value.name),
            scope: categoryForm.value.scope,
        };

        if (categoryForm.value.id === null) {
            await window.axios.post('/categories', payload);
            successMessage.value = 'Categoría creada correctamente.';
        } else {
            await window.axios.put(`/categories/${categoryForm.value.id}`, payload);
            successMessage.value = 'Categoría actualizada correctamente.';
        }

        resetCategoryForm();
        await runWithLoading(loadCategories, 'No fue posible cargar las categorías.');
    } catch (error) {
        errorMessage.value = error?.response?.data?.message ?? 'No fue posible guardar la categoría.';
    } finally {
        savingCategory.value = false;
    }
};

const removeCategory = async (categoryId) => {
    errorMessage.value = '';
    successMessage.value = '';

    try {
        await window.axios.delete(`/categories/${categoryId}`);
        successMessage.value = 'Categoría eliminada correctamente.';

        if (Number(form.value.category_id) === categoryId) {
            form.value.category_id = '';
        }

        await runWithLoading(loadCategories, 'No fue posible cargar las categorías.');
    } catch (error) {
        errorMessage.value = error?.response?.data?.message ?? 'No fue posible eliminar la categoría.';
    }
};

const editTag = (tag) => {
    tagForm.value.id = tag.id;
    tagForm.value.name = tag.name;
};

const submitTag = async () => {
    savingTag.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        const payload = {
            name: tagForm.value.name,
            slug: normalizeSlug(tagForm.value.name),
        };

        if (tagForm.value.id === null) {
            await window.axios.post('/tags', payload);
            successMessage.value = 'Tag creado correctamente.';
        } else {
            await window.axios.put(`/tags/${tagForm.value.id}`, payload);
            successMessage.value = 'Tag actualizado correctamente.';
        }

        resetTagForm();
        await runWithLoading(loadTags, 'No fue posible cargar los tags.');
    } catch (error) {
        errorMessage.value = error?.response?.data?.message ?? 'No fue posible guardar el tag.';
    } finally {
        savingTag.value = false;
    }
};

const removeTag = async (tagId) => {
    errorMessage.value = '';
    successMessage.value = '';

    try {
        await window.axios.delete(`/tags/${tagId}`);
        successMessage.value = 'Tag eliminado correctamente.';
        form.value.tag_ids = form.value.tag_ids.filter((value) => Number(value) !== tagId);
        await runWithLoading(loadTags, 'No fue posible cargar los tags.');
    } catch (error) {
        errorMessage.value = error?.response?.data?.message ?? 'No fue posible eliminar el tag.';
    }
};

const editCard = (card) => {
    cardForm.value.id = card.id;
    cardForm.value.name = card.name;
    cardForm.value.last_four_digits = card.last_four_digits;
    cardForm.value.closing_day = card.closing_day ?? '';
    cardForm.value.due_day = card.due_day ?? '';
    cardForm.value.is_active = !!card.is_active;
};

const submitCard = async () => {
    savingCard.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        const payload = {
            name: cardForm.value.name,
            last_four_digits: cardForm.value.last_four_digits,
            closing_day: Number(cardForm.value.closing_day),
            due_day: Number(cardForm.value.due_day),
            is_active: cardForm.value.is_active,
        };

        if (cardForm.value.id === null) {
            await window.axios.post('/cards', payload);
            successMessage.value = 'Tarjeta creada correctamente.';
        } else {
            await window.axios.put(`/cards/${cardForm.value.id}`, payload);
            successMessage.value = 'Tarjeta actualizada correctamente.';
        }

        resetCardForm();
        await runWithLoading(loadCards, 'No fue posible cargar las tarjetas.');
    } catch (error) {
        errorMessage.value = error?.response?.data?.message ?? 'No fue posible guardar la tarjeta.';
    } finally {
        savingCard.value = false;
    }
};

const removeCard = async (cardId) => {
    errorMessage.value = '';
    successMessage.value = '';

    try {
        await window.axios.delete(`/cards/${cardId}`);
        successMessage.value = 'Tarjeta eliminada correctamente.';

        if (Number(form.value.card_id) === cardId) {
            form.value.card_id = '';
        }

        await runWithLoading(loadCards, 'No fue posible cargar las tarjetas.');
    } catch (error) {
        errorMessage.value = error?.response?.data?.message ?? 'No fue posible eliminar la tarjeta.';
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

        <TransactionListPage v-if="activeScreen === 'income-list'" :currency-symbol="currencySymbol" empty-message="No hay ingresos registrados." :format-amount="formatAmount" :loading="loading" title="Ingresos" :transactions="incomeTransactions" @create="openTransactionForm('income')" @edit="openTransactionEdit" />

        <TransactionListPage v-if="activeScreen === 'expense-list'" :currency-symbol="currencySymbol" empty-message="No hay egresos registrados." :format-amount="formatAmount" :loading="loading" title="Egresos" :transactions="expenseTransactions" @create="openTransactionForm('expense')" @edit="openTransactionEdit" />

        <DashboardPage v-if="activeScreen === 'dashboard'" :cards-summary="cardsSummary" :currency-symbol="currencySymbol" :format-amount="formatAmount" :format-date="formatDate" :loading="loading" :recent-transactions="dashboardRecentTransactions" />

        <TransactionFormPage v-if="activeScreen === 'transaction-form'" :cards="cards" :categories="categories" :category-options="categoryOptions" :deleting="deletingTransaction" :editing="editingTransactionId !== null" :first-installment-payment-date="firstInstallmentPaymentDate" :first-installment-payment-date-is-estimated="firstInstallmentPaymentDateIsEstimated" :forced-transaction-type="forcedTransactionType" :form="form" :format-date="formatDate" :is-credit-payment="isCreditPayment" :payment-methods="PAYMENT_METHODS" :saving="savingTransaction" :show-installments="showInstallments" :tags="tags" :title="transactionFormTitle" @back="returnToTransactionList" @delete="deleteTransaction" @submit="submitTransaction" />

        <CardsPage v-if="activeScreen === 'cards'" :billing-cycle-forms="billingCycleForms" :card-form="cardForm" :cards="cards" :format-date="formatDate" :get-billing-cycle-form="getBillingCycleForm" :saving-billing-cycle="savingBillingCycle" :saving-card="savingCard" @edit-billing-cycle="editBillingCycle" @edit-card="editCard" @remove-card="removeCard" @reset-billing-cycle="resetBillingCycleForm" @reset-card="resetCardForm" @submit-billing-cycle="submitBillingCycle" @submit-card="submitCard" />

        <CategoriesPage v-if="activeScreen === 'categories'" :categories="categories" :form="categoryForm" :saving="savingCategory" :scope-label="categoryScopeLabel" @edit="editCategory" @remove="removeCategory" @reset="resetCategoryForm" @submit="submitCategory" />

        <TagsPage v-if="activeScreen === 'tags'" :form="tagForm" :saving="savingTag" :tags="tags" @edit="editTag" @remove="removeTag" @reset="resetTagForm" @submit="submitTag" />

            <p v-if="errorMessage" class="rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">{{ errorMessage }}</p>
            <p v-if="successMessage" class="rounded-md border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ successMessage }}</p>
    </AdminLayout>
</template>
