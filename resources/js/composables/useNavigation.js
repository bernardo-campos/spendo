import { computed, onBeforeUnmount, ref } from 'vue';

export const useNavigation = ({ form }) => {
    const screenPaths = {
        dashboard: '/app',
        'income-list': '/app/incomes',
        'expense-list': '/app/expenses',
        categories: '/app/categories',
        tags: '/app/tags',
        cards: '/app/cards',
        visualization: '/app/visualization',
    };

    const getLocationState = () => {
        const path = window.location.pathname.replace(/\/$/, '') || '/app';
        const transactionFormMatch = path.match(/^\/app\/transactions\/(create|([0-9]+)\/edit)$/);
        const query = new URLSearchParams(window.location.search);

        if (transactionFormMatch) {
            return {
                activeScreen: 'transaction-form',
                editingTransactionId: transactionFormMatch[2] ? Number(transactionFormMatch[2]) : null,
                forcedTransactionType: query.get('type'),
            };
        }

        const activeScreen = Object.entries(screenPaths)
            .find(([, screenPath]) => screenPath === path)?.[0] ?? 'dashboard';

        return {
            activeScreen,
            editingTransactionId: null,
            forcedTransactionType: null,
        };
    };

    const initialLocationState = getLocationState();
    const activeScreen = ref(initialLocationState.activeScreen);
    const forcedTransactionType = ref(initialLocationState.forcedTransactionType);
    const editingTransactionId = ref(initialLocationState.editingTransactionId);

    const getPathForCurrentScreen = () => {
        if (activeScreen.value === 'transaction-form') {
            const path = editingTransactionId.value === null
                ? '/app/transactions/create'
                : `/app/transactions/${editingTransactionId.value}/edit`;
            const query = forcedTransactionType.value ? `?type=${forcedTransactionType.value}` : '';

            return `${path}${query}`;
        }

        return screenPaths[activeScreen.value] ?? screenPaths.dashboard;
    };

    const updateBrowserLocation = () => {
        const nextPath = getPathForCurrentScreen();
        const currentPath = `${window.location.pathname}${window.location.search}`;

        if (currentPath !== nextPath) {
            window.history.pushState({}, '', nextPath);
        }
    };

    const setActiveScreen = (screen) => {
        activeScreen.value = screen;
        updateBrowserLocation();
    };

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
        setActiveScreen('transaction-form');
    };

    const openGenericTransactionForm = () => {
        resetTransactionForm();
        editingTransactionId.value = null;
        forcedTransactionType.value = null;
        setActiveScreen('transaction-form');
    };

    const returnToTransactionList = () => {
        const transactionType = form.value.type;

        resetTransactionForm();
        editingTransactionId.value = null;
        forcedTransactionType.value = null;
        setActiveScreen(transactionType === 'income' ? 'income-list' : 'expense-list');
    };

    const setActiveScreenFromMenu = (screen) => {
        setActiveScreen(screen);
    };

    const handlePopState = () => {
        const locationState = getLocationState();

        activeScreen.value = locationState.activeScreen;
        editingTransactionId.value = locationState.editingTransactionId;
        forcedTransactionType.value = locationState.forcedTransactionType;
    };

    window.addEventListener('popstate', handlePopState);

    onBeforeUnmount(() => {
        window.removeEventListener('popstate', handlePopState);
    });

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
