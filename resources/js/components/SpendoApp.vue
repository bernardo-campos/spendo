<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

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
const savingTransaction = ref(false);
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

const categories = ref([]);
const tags = ref([]);
const cards = ref([]);
const transactions = ref([]);

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

const categoryOptions = computed(() => {
    if (form.value.type === 'income') {
        return categories.value.filter((category) => category.scope === 'income' || category.scope === 'both');
    }

    return categories.value.filter((category) => category.scope === 'expense' || category.scope === 'both');
});

const isCreditPayment = computed(() => form.value.type === 'expense' && form.value.payment_method === 'credit');

const selectedCard = computed(() => cards.value.find((card) => Number(card.id) === Number(form.value.card_id)) ?? null);

const showInstallments = computed(() => isCreditPayment.value && Number(form.value.installments_count) > 1);

const toDateParts = (value) => {
    const [year, month, day] = String(value).split('-').map((part) => Number.parseInt(part, 10));

    if (!Number.isInteger(year) || !Number.isInteger(month) || !Number.isInteger(day)) {
        return null;
    }

    return { year, month, day };
};

const formatDateParts = ({ year, month, day }) => `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

const addMonthNoOverflow = ({ year, month }) => {
    if (month === 12) {
        return { year: year + 1, month: 1 };
    }

    return { year, month: month + 1 };
};

const daysInMonth = (year, month) => new Date(year, month, 0).getDate();

const calculateFirstInstallmentPaymentDate = (purchaseDate, card) => {
    const purchase = toDateParts(purchaseDate);

    if (purchase === null || card === null) {
        return null;
    }

    const purchaseDateValue = formatDateParts(purchase);
    const billingCycles = Array.isArray(card.billing_cycles)
        ? [...card.billing_cycles].sort((left, right) => String(left.closing_date).localeCompare(String(right.closing_date)))
        : [];

    const matchedCycle = billingCycles.find((cycle) => String(cycle.closing_date) >= purchaseDateValue);

    if (matchedCycle?.due_date) {
        return matchedCycle.due_date;
    }

    const closingDay = Number(card.closing_day) || 1;
    const dueDay = Number(card.due_day) || closingDay;

    const statementMonth = purchase.day <= closingDay
        ? { year: purchase.year, month: purchase.month }
        : addMonthNoOverflow({ year: purchase.year, month: purchase.month });

    const dueMonth = addMonthNoOverflow(statementMonth);
    const safeDueDay = Math.min(dueDay, daysInMonth(dueMonth.year, dueMonth.month));

    return formatDateParts({
        year: dueMonth.year,
        month: dueMonth.month,
        day: safeDueDay,
    });
};

const hasRealCycleForPurchaseDate = (purchaseDate, card) => {
    const purchase = toDateParts(purchaseDate);

    if (purchase === null || card === null) {
        return false;
    }

    const purchaseDateValue = formatDateParts(purchase);
    const billingCycles = Array.isArray(card.billing_cycles)
        ? card.billing_cycles
        : [];

    return billingCycles.some((cycle) => String(cycle.closing_date) >= purchaseDateValue);
};

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

const isInSelectedPeriod = (dateValue) => {
    if (!dateValue) {
        return false;
    }

    return String(dateValue).slice(0, 7) === selectedPeriod.value;
};

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
                    description: `${transaction.description} · Cuota ${installment.installment_number}/${totalInstallments}`,
                    purchase_date: installment.due_date,
                    payment_method: transaction.payment_method,
                    amount: installment.amount,
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

const cardsSummary = computed(() => [
    { title: 'Ingresos', value: `${currencySymbol}${formatAmount(incomeTotal.value)}` },
    { title: 'Gastos', value: `${currencySymbol}${formatAmount(expenseTotal.value)}` },
    { title: 'Saldo', value: `${currencySymbol}${formatAmount(incomeTotal.value - expenseTotal.value)}` },
]);

const transactionFormTitle = computed(() => {
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
    forcedTransactionType.value = type;
    form.value.type = type;
    activeScreen.value = 'transaction-form';
};

const openGenericTransactionForm = () => {
    resetTransactionForm();
    forcedTransactionType.value = null;
    activeScreen.value = 'transaction-form';
};

const toggleUserMenu = () => {
    userMenuOpen.value = !userMenuOpen.value;
};

const closeUserMenu = () => {
    userMenuOpen.value = false;
};

const setActiveScreenFromMenu = (screen) => {
    activeScreen.value = screen;
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

onMounted(() => {
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

const loadTransactions = async () => {
    const response = await window.axios.get('/transactions');
    transactions.value = response.data;
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

        await window.axios.post('/transactions', payload);

        const registeredType = form.value.type;

        successMessage.value = 'Transacción guardada correctamente.';
        resetTransactionForm();
        forcedTransactionType.value = null;
        activeScreen.value = registeredType === 'income' ? 'income-list' : 'expense-list';
    } catch (error) {
        errorMessage.value = error?.response?.data?.message ?? 'No fue posible guardar la transacción.';
    } finally {
        savingTransaction.value = false;
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
    <div class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
        <main class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-6">
            <header class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-2 dark:border-slate-800 dark:bg-slate-900">
                <nav class="flex flex-wrap items-center gap-2">
                    <button type="button" class="rounded-md px-3 py-2 text-sm font-medium" :class="activePrimaryTab === 'dashboard' ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900' : 'text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800'" @click="activeScreen = 'dashboard'">Dashboard</button>
                    <button type="button" class="rounded-md px-3 py-2 text-sm font-medium" :class="activePrimaryTab === 'income-list' ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900' : 'text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800'" @click="activeScreen = 'income-list'">Ingresos</button>
                    <button type="button" class="rounded-md px-3 py-2 text-sm font-medium" :class="activePrimaryTab === 'expense-list' ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900' : 'text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800'" @click="activeScreen = 'expense-list'">Egresos</button>
                </nav>

                <div ref="userMenuRef" class="relative">
                    <button type="button" class="cursor-pointer rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800" @click="toggleUserMenu">
                        {{ userName }}
                    </button>
                    <div v-if="userMenuOpen" class="absolute right-0 z-20 mt-2 w-56 rounded-md border border-slate-200 bg-white p-1 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <button type="button" class="w-full rounded-md px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800" @click="setActiveScreenFromMenu('cards')">Tarjetas</button>
                        <button type="button" class="w-full rounded-md px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800" @click="setActiveScreenFromMenu('categories')">Categorías</button>
                        <button type="button" class="w-full rounded-md px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800" @click="setActiveScreenFromMenu('tags')">Tags</button>
                        <div class="my-1 border-t border-slate-200 dark:border-slate-700"></div>
                        <form method="POST" action="/logout" class="w-full">
                            <input type="hidden" name="_token" :value="csrfToken">
                            <button type="submit" class="w-full rounded-md px-3 py-2 text-left text-sm text-red-700 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-950/40">
                                Cerrar sesión
                            </button>
                        </form>
                    </div>
                </div>
            </header>

            <section v-if="activeScreen === 'income-list'" class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="text-base font-semibold">Listado de ingresos</h2>
                    <input v-model="selectedPeriod" type="month" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                </div>

                <p v-if="loading" class="text-sm text-slate-500 dark:text-slate-400">Cargando...</p>
                <p v-else-if="incomeTransactions.length === 0" class="text-sm text-slate-500 dark:text-slate-400">No hay ingresos registrados.</p>
                <ul v-else class="space-y-2">
                    <li v-for="transaction in incomeTransactions" :key="transaction.id" class="flex items-center justify-between rounded-md border border-slate-200 px-3 py-2 text-sm dark:border-slate-800">
                        <div>
                            <p class="font-medium">{{ transaction.description }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ formatDate(transaction.purchase_date) }}</p>
                        </div>
                        <span class="font-semibold">{{ currencySymbol }}{{ formatAmount(transaction.amount) }}</span>
                    </li>
                </ul>

                <div class="sticky bottom-4 mt-4 flex justify-end">
                    <button type="button" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200" @click="openTransactionForm('income')">
                        Registrar ingreso
                    </button>
                </div>
            </section>

            <section v-if="activeScreen === 'expense-list'" class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="text-base font-semibold">Listado de egresos</h2>
                    <input v-model="selectedPeriod" type="month" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                </div>

                <p v-if="loading" class="text-sm text-slate-500 dark:text-slate-400">Cargando...</p>
                <p v-else-if="expenseTransactions.length === 0" class="text-sm text-slate-500 dark:text-slate-400">No hay egresos registrados.</p>
                <ul v-else class="space-y-2">
                    <li v-for="transaction in expenseTransactions" :key="transaction.id" class="flex items-center justify-between rounded-md border border-slate-200 px-3 py-2 text-sm dark:border-slate-800">
                        <div>
                            <p class="font-medium">{{ transaction.description }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ formatDate(transaction.purchase_date) }} · {{ transaction.payment_method === 'credit' ? 'Crédito' : 'Efectivo' }}</p>
                        </div>
                        <span class="font-semibold">{{ currencySymbol }}{{ formatAmount(transaction.amount) }}</span>
                    </li>
                </ul>

                <div class="sticky bottom-4 mt-4 flex justify-end">
                    <button type="button" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200" @click="openTransactionForm('expense')">
                        Registrar egreso
                    </button>
                </div>
            </section>

            <section v-if="activeScreen === 'dashboard'" class="grid gap-4 md:grid-cols-3">
                <article class="md:col-span-3 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <label class="space-y-1 text-sm">
                        <span class="font-medium">Período</span>
                        <input v-model="selectedPeriod" type="month" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950">
                    </label>
                </article>
                <article v-for="card in cardsSummary" :key="card.title" class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ card.title }}</h2>
                    <p class="mt-2 text-2xl font-semibold">{{ card.value }}</p>
                </article>
            </section>

            <section v-if="activeScreen === 'dashboard'" class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="mb-4 text-base font-semibold">Últimos movimientos</h2>
                <p v-if="loading" class="text-sm text-slate-500 dark:text-slate-400">Cargando...</p>
                <p v-else-if="dashboardRecentTransactions.length === 0" class="text-sm text-slate-500 dark:text-slate-400">Aún no hay transacciones.</p>
                <ul v-else class="space-y-2">
                    <li v-for="transaction in dashboardRecentTransactions" :key="transaction.id" class="flex items-center justify-between rounded-md border border-slate-200 px-3 py-2 text-sm dark:border-slate-800">
                        <div>
                            <p class="font-medium">{{ transaction.description }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ transaction.type === 'expense' ? 'Gasto' : 'Ingreso' }} · {{ formatDate(transaction.purchase_date) }}</p>
                        </div>
                        <span class="font-semibold">{{ currencySymbol }}{{ formatAmount(transaction.amount) }}</span>
                    </li>
                </ul>
            </section>

            <section v-if="activeScreen === 'transaction-form'" class="grid gap-6">
                <article class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="mb-4 text-base font-semibold">{{ transactionFormTitle }}</h2>
                    <form class="space-y-3" @submit.prevent="submitTransaction">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label v-if="!forcedTransactionType" class="space-y-1 text-sm">
                                <span class="font-medium">Tipo</span>
                                <select v-model="form.type" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950">
                                    <option value="expense">Gasto</option>
                                    <option value="income">Ingreso</option>
                                </select>
                            </label>
                            <label class="space-y-1 text-sm">
                                <span class="font-medium">Monto</span>
                                <input v-model="form.amount" type="number" min="0" step="0.01" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950">
                            </label>
                        </div>

                        <label class="space-y-1 text-sm">
                            <span class="font-medium">Descripción</span>
                            <input v-model="form.description" type="text" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950">
                        </label>

                        <label class="space-y-1 text-sm">
                            <span class="font-medium">Categoría</span>
                            <select v-model="form.category_id" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950">
                                <option value="">Sin categoría</option>
                                <option v-for="category in categoryOptions" :key="category.id" :value="category.id">{{ category.name }}</option>
                            </select>
                        </label>

                        <label class="space-y-1 text-sm">
                            <span class="font-medium">Tags</span>
                            <select v-model="form.tag_ids" multiple class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950">
                                <option v-for="tag in tags" :key="tag.id" :value="tag.id">{{ tag.name }}</option>
                            </select>
                            <span class="text-xs text-slate-500 dark:text-slate-400">Mantén Ctrl/Cmd para seleccionar varios.</span>
                        </label>

                        <label class="space-y-1 text-sm">
                            <span class="font-medium">Fecha</span>
                            <input v-model="form.purchase_date" type="date" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950">
                        </label>

                        <label v-if="form.type === 'expense'" class="space-y-1 text-sm">
                            <span class="font-medium">Forma de pago</span>
                            <select v-model="form.payment_method" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950">
                                <option v-for="paymentMethod in PAYMENT_METHODS" :key="paymentMethod.value" :value="paymentMethod.value">{{ paymentMethod.label }}</option>
                            </select>
                        </label>

                        <label v-if="isCreditPayment" class="space-y-1 text-sm">
                            <span class="font-medium">Tarjeta</span>
                            <select v-model="form.card_id" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950">
                                <option value="" disabled>Selecciona una tarjeta</option>
                                <option v-for="card in cards" :key="card.id" :value="card.id">{{ card.name }} · ****{{ card.last_four_digits }}</option>
                            </select>
                        </label>

                        <p v-if="firstInstallmentPaymentDate" class="text-xs text-slate-500 dark:text-slate-400">
                            La primera cuota se pagará el {{ formatDate(firstInstallmentPaymentDate) }}
                            {{ firstInstallmentPaymentDateIsEstimated ? '(fecha estimada)' : '(fecha real por ciclo cargado)' }}.
                        </p>

                        <label v-if="isCreditPayment" class="space-y-1 text-sm">
                            <span class="font-medium">Cuotas</span>
                            <input v-model.number="form.installments_count" type="number" min="1" max="120" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950">
                        </label>

                        <p v-if="showInstallments" class="text-xs text-slate-500 dark:text-slate-400">
                            La fecha de pago se calcula automáticamente según cierre/vencimiento de la tarjeta.
                        </p>

                        <label class="space-y-1 text-sm">
                            <span class="font-medium">Notas</span>
                            <textarea v-model="form.notes" rows="3" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950"></textarea>
                        </label>

                        <button type="submit" :disabled="savingTransaction" class="w-full rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200">
                            {{ savingTransaction ? 'Guardando...' : 'Guardar transacción' }}
                        </button>
                    </form>
                </article>
            </section>

            <section v-if="activeScreen === 'cards'" class="grid gap-6">
                <article class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="mb-4 text-base font-semibold">Tarjetas</h2>
                    <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">Las formas de pago son fijas en el sistema: Efectivo y Crédito.</p>

                    <form class="grid gap-3 sm:grid-cols-2" @submit.prevent="submitCard">
                        <label class="space-y-1 text-sm">
                            <span class="font-medium">Nombre</span>
                            <input v-model="cardForm.name" type="text" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950">
                        </label>

                        <label class="space-y-1 text-sm">
                            <span class="font-medium">Últimos 4 dígitos</span>
                            <input v-model="cardForm.last_four_digits" type="text" maxlength="4" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950">
                        </label>

                        <label class="space-y-1 text-sm">
                            <span class="font-medium">Día de cierre</span>
                            <input v-model="cardForm.closing_day" type="number" min="1" max="31" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950">
                        </label>
                        <label class="space-y-1 text-sm">
                            <span class="font-medium">Día de vencimiento</span>
                            <input v-model="cardForm.due_day" type="number" min="1" max="31" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950">
                        </label>

                        <div class="sm:col-span-2 flex gap-2">
                            <button type="submit" :disabled="savingCard" class="flex-1 rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200">{{ cardForm.id === null ? 'Crear' : 'Actualizar' }}</button>
                            <button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700" @click="resetCardForm">Limpiar</button>
                        </div>
                    </form>

                    <ul class="mt-4 space-y-2">
                        <li v-for="card in cards" :key="card.id" class="rounded-md border border-slate-200 px-3 py-3 text-sm dark:border-slate-800">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="font-medium">{{ card.name }}</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">****{{ card.last_four_digits }} · Cierre estimado {{ card.closing_day }} · Vence estimado {{ card.due_day }}</p>
                                </div>
                                <div class="flex gap-2">
                                    <button type="button" class="rounded-md border border-slate-300 px-2 py-1 text-xs dark:border-slate-700" @click="editCard(card)">Editar</button>
                                    <button type="button" class="rounded-md border border-red-300 px-2 py-1 text-xs text-red-700" @click="removeCard(card.id)">Eliminar</button>
                                </div>
                            </div>

                            <div class="mt-3 rounded-md border border-slate-200 p-3 dark:border-slate-800">
                                <p class="mb-2 text-xs font-medium text-slate-600 dark:text-slate-300">Ciclos de facturación (reales)</p>

                                <p v-if="!Array.isArray(card.billing_cycles) || card.billing_cycles.length === 0" class="mb-2 text-xs text-slate-500 dark:text-slate-400">
                                    No hay ciclos cargados para esta tarjeta.
                                </p>

                                <ul v-else class="mb-3 space-y-1">
                                    <li v-for="cycle in card.billing_cycles" :key="cycle.id" class="flex items-center justify-between rounded-md border border-slate-200 px-2 py-1 text-xs dark:border-slate-700">
                                        <span>Cierre {{ formatDate(cycle.closing_date) }} · Vence {{ formatDate(cycle.due_date) }}</span>
                                        <button type="button" class="rounded-md border border-slate-300 px-2 py-1 text-xs dark:border-slate-700" @click="editBillingCycle(card.id, cycle)">Editar ciclo</button>
                                    </li>
                                </ul>

                                <form class="grid gap-2 sm:grid-cols-3" @submit.prevent="submitBillingCycle(card.id)">
                                    <input v-model="getBillingCycleForm(card.id).closing_date" type="date" required class="rounded-md border border-slate-300 bg-white px-3 py-2 text-xs dark:border-slate-700 dark:bg-slate-950">
                                    <input v-model="getBillingCycleForm(card.id).due_date" type="date" required class="rounded-md border border-slate-300 bg-white px-3 py-2 text-xs dark:border-slate-700 dark:bg-slate-950">
                                    <div class="flex gap-2">
                                        <button type="submit" :disabled="savingBillingCycle" class="flex-1 rounded-md bg-slate-900 px-3 py-2 text-xs font-medium text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200">
                                            {{ getBillingCycleForm(card.id).id === null ? 'Agregar ciclo' : 'Actualizar ciclo' }}
                                        </button>
                                        <button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-xs dark:border-slate-700" @click="resetBillingCycleForm(card.id)">Limpiar</button>
                                    </div>
                                </form>
                            </div>
                        </li>
                    </ul>
                </article>
            </section>

            <section v-if="activeScreen === 'categories'" class="grid gap-6">
                <article class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="mb-4 text-base font-semibold">Categorías</h2>
                    <form class="grid gap-3 sm:grid-cols-3" @submit.prevent="submitCategory">
                        <input v-model="categoryForm.name" type="text" required placeholder="Nombre" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                        <select v-model="categoryForm.scope" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                            <option value="income">Ingreso</option>
                            <option value="expense">Gasto</option>
                            <option value="both">Ambos</option>
                        </select>
                        <div class="flex gap-2">
                            <button type="submit" :disabled="savingCategory" class="flex-1 rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200">{{ categoryForm.id === null ? 'Crear' : 'Actualizar' }}</button>
                            <button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700" @click="resetCategoryForm">Limpiar</button>
                        </div>
                    </form>
                    <ul class="mt-4 space-y-2">
                        <li v-for="category in categories" :key="category.id" class="flex items-center justify-between rounded-md border border-slate-200 px-3 py-2 text-sm dark:border-slate-800">
                            <div>
                                <p class="font-medium">{{ category.name }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ category.scope }}</p>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" class="rounded-md border border-slate-300 px-2 py-1 text-xs dark:border-slate-700" @click="editCategory(category)">Editar</button>
                                <button type="button" class="rounded-md border border-red-300 px-2 py-1 text-xs text-red-700" @click="removeCategory(category.id)">Eliminar</button>
                            </div>
                        </li>
                    </ul>
                </article>
            </section>

            <section v-if="activeScreen === 'tags'" class="grid gap-6">
                <article class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="mb-4 text-base font-semibold">Tags</h2>
                    <form class="grid gap-3 sm:grid-cols-3" @submit.prevent="submitTag">
                        <input v-model="tagForm.name" type="text" required placeholder="Nombre" class="sm:col-span-2 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                        <div class="flex gap-2">
                            <button type="submit" :disabled="savingTag" class="flex-1 rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200">{{ tagForm.id === null ? 'Crear' : 'Actualizar' }}</button>
                            <button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700" @click="resetTagForm">Limpiar</button>
                        </div>
                    </form>
                    <ul class="mt-4 space-y-2">
                        <li v-for="tag in tags" :key="tag.id" class="flex items-center justify-between rounded-md border border-slate-200 px-3 py-2 text-sm dark:border-slate-800">
                            <p class="font-medium">{{ tag.name }}</p>
                            <div class="flex gap-2">
                                <button type="button" class="rounded-md border border-slate-300 px-2 py-1 text-xs dark:border-slate-700" @click="editTag(tag)">Editar</button>
                                <button type="button" class="rounded-md border border-red-300 px-2 py-1 text-xs text-red-700" @click="removeTag(tag.id)">Eliminar</button>
                            </div>
                        </li>
                    </ul>
                </article>
            </section>

            <p v-if="errorMessage" class="rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">{{ errorMessage }}</p>
            <p v-if="successMessage" class="rounded-md border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ successMessage }}</p>
        </main>
    </div>
</template>
