export const supportsPushNotifications = (browser = globalThis) => Boolean(
    browser.navigator?.serviceWorker && browser.PushManager && browser.Notification,
);

export const applicationServerKey = (value) => {
    const padding = '='.repeat((4 - value.length % 4) % 4);
    const decoded = atob((value + padding).replace(/-/g, '+').replace(/_/g, '/'));

    return Uint8Array.from(decoded, (character) => character.charCodeAt(0));
};

export const createPushNotifications = (http, browser = globalThis) => {
    const registration = () => browser.navigator.serviceWorker.getRegistration('/');

    const save = async (subscription) => {
        await http.post('/push-subscriptions', subscription.toJSON());
    };

    return {
        async initialize() {
            if (!supportsPushNotifications(browser)) {
                return { supported: false, configured: false, enabled: false };
            }

            const { data } = await http.get('/push-subscriptions');
            const worker = await registration();
            const subscription = await worker?.pushManager.getSubscription();

            if (data.configured && subscription && browser.Notification.permission === 'granted') {
                await save(subscription);
            }

            return {
                supported: true,
                configured: data.configured,
                enabled: Boolean(data.configured && subscription && browser.Notification.permission === 'granted'),
                publicKey: data.public_key,
            };
        },

        async enable(publicKey) {
            if (!supportsPushNotifications(browser) || !publicKey) {
                throw new Error('Las notificaciones no están disponibles en este dispositivo.');
            }

            // Request permission directly from the click, before any network request.
            const permission = await browser.Notification.requestPermission();

            if (permission !== 'granted') {
                throw new Error('Permití las notificaciones en la configuración del navegador para activarlas.');
            }

            const worker = await registration();

            if (!worker?.active) {
                throw new Error('Recargá Spendo y volvé a activar las notificaciones.');
            }

            const existing = await worker.pushManager.getSubscription();
            const subscription = existing ?? await worker.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: applicationServerKey(publicKey),
            });

            try {
                await save(subscription);
            } catch (error) {
                if (!existing) {
                    await subscription.unsubscribe();
                }

                throw error;
            }
        },

        async disable() {
            await disablePushNotifications(http, browser.navigator?.serviceWorker);
        },
    };
};

export const disablePushNotifications = async (http, serviceWorker) => {
    const worker = await serviceWorker?.getRegistration?.('/');
    const subscription = await worker?.pushManager.getSubscription();

    if (!subscription) {
        return;
    }

    try {
        await http.delete('/push-subscriptions', { data: { endpoint: subscription.endpoint } });
    } finally {
        // Unsubscribe even when offline so the previous account cannot receive push.
        if (!await subscription.unsubscribe()) {
            throw new Error('No fue posible desactivar la suscripción del navegador. Intentá nuevamente.');
        }
    }
};

export const unsubscribeBrowserPush = async (serviceWorker) => {
    const worker = await serviceWorker?.getRegistration?.('/');
    const subscription = await worker?.pushManager.getSubscription();

    if (subscription && !await subscription.unsubscribe()) {
        throw new Error('No fue posible desvincular las notificaciones del dispositivo.');
    }
};
