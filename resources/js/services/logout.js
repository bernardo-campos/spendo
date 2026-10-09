export const submitLogout = async (event, offlineClient, cacheStorage = globalThis.caches, serviceWorker = navigator.serviceWorker) => {
    const form = event.currentTarget;
    event.preventDefault();

    try {
        await offlineClient.clear();
    } catch (error) {
        console.error('No fue posible borrar los datos offline al cerrar sesión.', error);
    }

    try {
        if (cacheStorage) {
            const cacheNames = await cacheStorage.keys();
            await Promise.all(cacheNames
                .filter((name) => name.startsWith('spendo-private-shell-'))
                .map((name) => cacheStorage.delete(name)));
        }
    } catch (error) {
        console.error('No fue posible borrar la caché privada al cerrar sesión.', error);
    } finally {
        try {
            serviceWorker?.controller?.postMessage({ type: 'CLEAR_PRIVATE_OFFLINE_DATA' });
        } finally {
            form.submit();
        }
    }
};
