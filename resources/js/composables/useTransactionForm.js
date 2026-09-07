import { computed, ref, watch } from 'vue';
import { calculateFirstInstallmentPaymentDate, hasRealCycleForPurchaseDate } from '../utils/cardPaymentDates';

const PAYMENT_METHODS = [
    { value: 'cash', label: 'Efectivo' },
    { value: 'credit', label: 'Crédito' },
];

export const useTransactionForm = ({ categories, cards, forcedTransactionType, editingTransactionId, form: providedForm = null }) => {
    const form = providedForm ?? ref({
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

    const categoryOptions = computed(() => {
        const scope = form.value.type === 'income' ? 'income' : 'expense';

        return categories.value.filter((category) => category.scope === scope || category.scope === 'both');
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

    watch(() => form.value.type, (type) => {
        if (type !== 'expense') {
            form.value.payment_method = 'cash';
            form.value.card_id = '';
            form.value.installments_count = 1;
        }
    });

    watch(() => form.value.payment_method, () => {
        if (form.value.payment_method !== 'credit') {
            form.value.card_id = '';
            form.value.installments_count = 1;
        }
    });

    return {
        categoryOptions,
        firstInstallmentPaymentDate,
        firstInstallmentPaymentDateIsEstimated,
        form,
        isCreditPayment,
        PAYMENT_METHODS,
        resetTransactionForm,
        selectedCard,
        showInstallments,
        transactionFormTitle,
    };
};
