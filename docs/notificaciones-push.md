# Notificaciones Web Push

## Estado y tareas pendientes

La integración está implementada y el paquete `laravel-notification-channels/webpush`
está instalado en el entorno de desarrollo. El 18 de noviembre de 2026 se confirmó
manualmente el funcionamiento en localhost desde la computadora del desarrollador,
simulando un correo con Tinker. La apertura del gasto al tocar el aviso y la
recepción en dispositivos móviles deben verificarse por separado.

| Parte | Estado de esta implementación |
| --- | --- |
| Canal de Laravel y vinculación con `User` | Implementado |
| Registro y eliminación de suscripciones por dispositivo | Implementado |
| Activación desde **Configuración → Importar recibos** | Implementado |
| Recepción mediante el service worker existente | Implementado |
| Aviso al crear un gasto desde un recibo reconocido | Implementado |
| Migración `create_push_subscriptions_table` | Disponible; entorno local operativo en la prueba manual. Aplicar en cada entorno nuevo |
| Claves VAPID y contacto del responsable | Generación de claves y envío local comprobados; configurar por entorno |
| Worker de colas permanente en el servidor | Debe comprobarse o configurarse en el entorno destino |
| Permiso del usuario y suscripción en su navegador | Activación local comprobada; repetir por dispositivo |
| Prueba de recepción en un dispositivo real | Confirmada en la computadora local; pendiente en móviles |

La puesta en marcha específica de Android y la configuración de HTTPS quedan para
un trabajo separado, según el alcance acordado. Las restricciones del navegador
siguen vigentes: posponer esa configuración no habilita Web Push en un origen que
el navegador considere inseguro. Para iPhone/iPad, la aplicación debe abrirse desde
la pantalla de inicio y contar con soporte Web Push.

## Qué es VAPID y para qué sirve

VAPID significa **Voluntary Application Server Identification**. Es el mecanismo
estándar que identifica al servidor que envía las notificaciones ante el servicio
push del navegador. Spendo firma las solicitudes con una clave privada; el servicio
comprueba esa identidad con la clave pública. El contacto identifica al responsable
del servidor. La definición está en el [RFC 8292](https://datatracker.ietf.org/doc/html/rfc8292).

Estas credenciales son de la aplicación, no de cada usuario. La suscripción de cada
dispositivo tiene su propio endpoint y claves de cifrado. No hay que obtener las
claves VAPID de una cuenta de correo ni contratar un servicio para generarlas.

El recorrido es:

```text
Recibo reconocido → gasto guardado → notificación en la cola de Laravel
→ servicio push del navegador → dispositivo → service worker → aviso
```

El envío utiliza los servicios push de los proveedores del navegador. El paquete
gestiona el protocolo y el cifrado; VAPID identifica al emisor. No es necesario
configurar un servidor de WebSockets o Laravel Reverb para este flujo.

### Valores esperados en `.env`

| Variable | Valor esperado | Cómo se obtiene |
| --- | --- | --- |
| `VAPID_SUBJECT` | Un contacto válido, preferentemente `mailto:persona@tu-dominio.com`. También se admite una URL HTTPS válida del responsable. | Lo define quien administra Spendo. Usar un correo real que pueda recibir comunicaciones. |
| `VAPID_PUBLIC_KEY` | Clave pública P-256 codificada en Base64 URL-safe, normalmente de 87 caracteres. | La genera `php artisan webpush:vapid`. El frontend la recibe para crear la suscripción. |
| `VAPID_PRIVATE_KEY` | Clave privada correspondiente, codificada en Base64 URL-safe, normalmente de 43 caracteres. | La genera el mismo comando. Se conserva únicamente en el servidor. |

`VAPID_SUBJECT` **no es el destinatario ni el remitente de un email**: las push no
se envían por correo. Tampoco debe contener la dirección privada de importación de
recibos. Un `mailto:` es un identificador de contacto, con ese prefijo incluido.

Las claves pública y privada deben pertenecer al mismo par. Se generan una vez por
entorno y se conservan entre despliegues y reinicios. Si cambia el par, los
dispositivos deben renovar sus suscripciones. No incluir la clave privada en Git,
variables `VITE_*`, capturas o respuestas de la API. El endpoint de configuración
solo expone la clave pública.

El paquete documenta la generación de claves y los requisitos del contacto para
Safari en su [guía de instalación](https://github.com/laravel-notification-channels/webpush#installation).

## Activación paso a paso

Ejecutar los comandos desde la raíz del proyecto, en el entorno donde se quiera
habilitar el envío.

1. En otro servidor o checkout, instalar las dependencias versionadas:

   ```bash
   composer install --no-interaction
   ```

   No es necesario volver a ejecutar `composer require`: el paquete ya está en
   `composer.json` y `composer.lock`.

2. Aplicar las migraciones:

   ```bash
   php artisan migrate --no-interaction
   ```

   En producción, el despliegue debe usar `--force` si corresponde. La migración
   nueva crea `push_subscriptions`. No hace falta publicar las migraciones del
   paquete: ya se agregó una propia al proyecto.

3. Si este entorno todavía no tiene claves VAPID, generarlas una sola vez:

   ```bash
   php artisan webpush:vapid --no-interaction
   ```

   El comando pertenece al paquete `laravel-notification-channels/webpush`.
   Escribe `VAPID_PUBLIC_KEY` y `VAPID_PRIVATE_KEY` en `.env` y no
   configura `VAPID_SUBJECT`. No incluirlo en una rutina que se ejecute en cada
   despliegue: puede reemplazar claves existentes. Si el entorno ya tiene claves,
   conservarlas. Usar `--force` en producción únicamente al generar o reemplazar
   el par de forma deliberada.

4. Definir el contacto y comprobar la conexión de colas en `.env`:

   ```dotenv
   # Reemplazar por un contacto real del responsable de la aplicación.
   VAPID_SUBJECT=mailto:persona@tu-dominio.com
   QUEUE_CONNECTION=database
   ```

   Mantener las dos claves escritas por el comando. No copiar las palabras
   «clave pública» o «clave privada» como valores. El código considera habilitado
   el servicio cuando los tres campos VAPID no están vacíos; esa comprobación no
   garantiza que las credenciales sean válidas ni que un proveedor acepte el envío.

5. Actualizar configuración y assets:

   ```bash
   php artisan config:clear --no-interaction
   npm run build
   ```

   Si el despliegue usa configuración cacheada, ejecutar después
   `php artisan config:cache --no-interaction`.

6. Mantener un worker de colas activo:

   ```bash
   php artisan queue:work --no-interaction
   ```

   `composer run dev` ya inicia un listener para desarrollo. No hace falta iniciar
   otro si ese listener está funcionando. En producción, usar el supervisor de
   procesos del servidor y reiniciar los workers tras desplegar cambios con
   `php artisan queue:restart --no-interaction`. El proceso que importa recibos y
   el worker deben usar la misma configuración y base de datos. El servidor
   necesita conexión saliente al servicio push.

7. Recargar Spendo con conexión para actualizar el service worker. Entrar con una
   cuenta verificada, abrir **Configuración → Importar recibos**, pulsar
   **Activar notificaciones** y aceptar el permiso. Repetirlo en cada dispositivo.

8. Importar un recibo **nuevo** y reconocido. Verificar que se crea el gasto,
   llega el aviso y al tocarlo se abre la edición del gasto. Reenviar un recibo
   ya importado no sirve para esta prueba: la deduplicación evita crear otro gasto
   y no se genera otro aviso.

## Prueba local sin recibir un correo real

Los navegadores consideran localhost un contexto seguro para desarrollo, por lo
que se puede probar desde el navegador de la misma computadora usando el puerto
8000 sin configurar HTTPS. Esto no se extiende a una IP de la red local usada
desde un celular: ese acceso necesita HTTPS confiable. Ver
[contextos seguros y service workers](https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API).

Primero completar la configuración, migración y compilación de assets de los
pasos anteriores. Mantener el servidor y el worker activos en terminales separadas:

```bash
php artisan serve --host=localhost --port=8000
```

```bash
php artisan queue:work --no-interaction
```

Si `composer run dev` ya mantiene el listener activo, no iniciar otro worker.
Activar las notificaciones desde la cuenta verificada que se usará para la prueba.
Se necesita Internet para comunicarse con el servicio push del navegador; el
servidor local no necesita estar publicado para enviar el aviso.

Abrir `php artisan tinker` y pegar el siguiente bloque, reemplazando el email por
el de esa cuenta. Se utiliza el servicio real de importación, incluyendo el parser,
la creación del gasto y el envío posterior al commit; no requiere un servidor de
correo ni Postfix:

```php
$user = App\Models\User::where('email', 'tu-correo@example.com')->firstOrFail();
$service = app(App\Services\ReceiptImportService::class);
$address = $service->addressFor($user);
$id = (string) Illuminate\Support\Str::uuid();

$html = '<html><body>'
    . '<div data-testid="total_fare_amount">ARS 1234.56</div>'
    . '<div data-testid="address_point_0_address">Origen de prueba 123</div>'
    . '<div data-testid="address_point_1_address">Destino de prueba 456</div>'
    . '<a href="https://example.com/recibos/'.$id.'">Descargar PDF</a>'
    . '</body></html>';

$message = "From: prueba@example.com\r\n"
    . "To: {$address}\r\n"
    . "Date: ".date(DATE_RFC2822)."\r\n"
    . "Subject: Recibo Uber de prueba\r\n"
    . "Message-ID: <{$id}@local.test>\r\n"
    . "MIME-Version: 1.0\r\n"
    . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
    . $html;

$input = fopen('php://memory', 'r+');
fwrite($input, $message);
rewind($input);
$path = $service->ingest($address, $input);
fclose($input);

Illuminate\Support\Facades\DB::table('inbound_receipts')->where('storage_path', $path)->first(['id', 'status', 'transaction_id']);
```

El resultado esperado es `status: "imported"` con un `transaction_id`, seguido del
aviso en el dispositivo suscrito. Comprobar además que al tocarlo abre la edición
del gasto. El enlace del PDF solo aporta un identificador al parser; no se descarga.

Cada ejecución crea un gasto real de prueba de ARS 1.234,56 en la base local y un
correo en el almacenamiento local. El UUID evita que la deduplicación descarte una
nueva ejecución. Se pueden eliminar los gastos de prueba desde la interfaz.

## Comportamiento implementado

- El aviso se prepara después de confirmar la transacción de base de datos.
  Un rollback no envía la notificación.
- Solo se dispara al crear un gasto nuevo mediante la importación de recibos.
  Crear gastos manualmente o editar gastos existentes no dispara este aviso.
- Si faltan credenciales o el usuario no tiene dispositivos suscritos, el gasto
  se guarda sin encolar el aviso. Activar las push más tarde no recupera esos
  avisos históricos.
- Una falla al encolar el aviso se reporta en los logs sin deshacer el gasto.
  No se implementó una bandeja persistente para recuperar automáticamente ese
  aviso perdido.
- El título es «Gasto registrado desde un recibo». El cuerpo muestra lugar,
  moneda e importe. Esos datos pueden verse en la pantalla de notificaciones del
  dispositivo según su configuración.
- El clic abre `/app/transactions/{id}/edit`; el acceso sigue requiriendo la
  sesión y autorización normales de Spendo.
- Cada gasto usa una etiqueta estable. La retención solicitada al proveedor
  (`TTL`) es de una hora; no garantiza una entrega inmediata ni un plazo exacto.
- Los dispositivos se administran por usuario. El paquete elimina las
  suscripciones que el proveedor identifica como vencidas. El cliente intenta
  desvincular las push al cerrar sesión y al detectar un cambio de cuenta.
- No se agregó un historial de notificaciones en base de datos, una campanita,
  avisos por email, ni preferencias independientes por tipo de evento.
- La notificación tiene tres intentos ante excepciones del job. No todas las
  respuestas HTTP fallidas del proveedor se convierten en excepciones: el paquete
  también emite su evento `NotificationFailed`. No hay un reintento propio para
  cada una de esas respuestas ni una garantía de entrega exactamente una vez.

## Archivos y contratos

| Archivo | Responsabilidad |
| --- | --- |
| [config/webpush.php](../config/webpush.php) | Credenciales, modelo, timeouts y hosts push admitidos. |
| [WebPushConfiguration](../app/Services/WebPushConfiguration.php) | Determina si están presentes los tres campos de configuración. |
| [User](../app/Models/User.php) | Agrega `HasPushSubscriptions` del paquete al modelo notificable. |
| [PushSubscriptionController](../app/Http/Controllers/PushSubscriptionController.php) | Configuración pública y altas/bajas del dispositivo de la cuenta autenticada. |
| [ExpenseCreatedFromEmail](../app/Notifications/ExpenseCreatedFromEmail.php) | Notificación Laravel encolada: canal, contenido y destino del clic. |
| [ReceiptExpenseService](../app/Services/ReceiptExpenseService.php) | Dispara el aviso tras crear y confirmar el gasto. |
| [pushNotifications.js](../resources/js/services/pushNotifications.js) | Permisos, suscripción del navegador y sincronización con el backend. |
| [service-worker.js](../public/service-worker.js) | Recibe `push`, muestra el aviso y procesa `notificationclick`; conserva el modo offline existente. |
| [ReceiptImportPage](../resources/js/pages/ReceiptImportPage.vue) | Botón y estado de activación por dispositivo. |

Las rutas web usan la sesión autenticada, email verificado y protección CSRF:

| Ruta | Entrada / salida |
| --- | --- |
| `GET /push-subscriptions` | Devuelve `configured` y `public_key`, con `Cache-Control: private, no-store`. |
| `POST /push-subscriptions` | Recibe `endpoint` y `keys: {p256dh, auth}` de `PushSubscription.toJSON()`. Crea o actualiza la suscripción propia. |
| `DELETE /push-subscriptions` | Recibe `{endpoint}` en el cuerpo y elimina solo la suscripción de la cuenta actual. |

El backend valida claves y permite únicamente endpoints de los hosts configurados
en `webpush.allowed_hosts`. Rechaza con `409` el intento de registrar un endpoint
que pertenece a otra cuenta. Estas rutas pertenecen al frontend web, no a `/api/v1`.

Para agregar otra ocasión de aviso, crear otra clase `Notification` que use
`WebPushChannel::class` en `via()` y construya su contenido en `toWebPush()`.
La clase `ExpenseCreatedFromEmail` muestra el patrón de `ShouldQueue`,
`afterCommit()` y comprobación de suscripciones. La infraestructura del navegador
se reutiliza sin pedir un permiso distinto por tipo de evento.

## Diagnóstico y pruebas

| Síntoma | Comprobación |
| --- | --- |
| «Todavía no están habilitadas» | Revisar los tres campos VAPID y la caché de configuración. |
| Error de tabla inexistente | Aplicar la migración de `push_subscriptions`. |
| Navegador no compatible | Comprobar soporte de Push API y, en iPhone/iPad, abrir desde la pantalla de inicio. |
| Permiso denegado | Permitir notificaciones desde la configuración del navegador; el sitio no puede concedérselas solo. |
| Se crea el gasto pero no llega el aviso | Verificar suscripción, que el recibo sea nuevo, worker activo y conexión saliente. Consultar `storage/logs/laravel.log` y `php artisan queue:failed --no-interaction`. |
| `BadJwtToken` de Apple | Revisar contacto VAPID válido, par de claves correcto y reloj del servidor. |
| `Unable to create the key` en Windows | Comprobar OpenSSL y la ruta a su `openssl.cnf`, tanto para generar claves como para ejecutar las pruebas. |

Los fallos que el paquete devuelve como reportes, sin lanzar una excepción, no
necesariamente aparecen en `queue:failed`. No se agregó un panel de reportes de
entrega. Las pruebas automatizadas simulan el servicio push; la recepción real
requiere la prueba manual del paso 8.

Pruebas nuevas:

```bash
php artisan test --compact tests/Feature/PushSubscriptionTest.php tests/Feature/ReceiptPushNotificationTest.php
node --test tests/js/pushNotifications.test.js tests/js/logout.test.js tests/js/receiptImportRefresh.test.js
```

En Windows, si PHP no encuentra su configuración OpenSSL, puede establecerse solo
para la terminal actual antes de ejecutar los comandos:

```powershell
$phpDirectory = Split-Path (Get-Command php).Source
$env:OPENSSL_CONF = Join-Path $phpDirectory 'extras/ssl/openssl.cnf'
```

La ruta debe existir en esa instalación de PHP. No hace falta guardar una ruta
específica de Windows en el código de la aplicación.

`openssl.cafile` en `php.ini` apunta al conjunto de certificados `cacert.pem` para
verificar conexiones HTTPS; no indica dónde está `openssl.cnf`. PHP no tiene una
directiva de `php.ini` para esa ubicación. La variable `OPENSSL_CONF` permite
indicarla para las operaciones de generación de claves. Ver el
[manual de instalación de OpenSSL en PHP](https://www.php.net/manual/es/openssl.installation.php).

Para conservarla entre terminales, se puede guardar como variable de entorno del
usuario de Windows (con la ruta de la instalación de PHP correspondiente):

```powershell
$phpDirectory = Split-Path (Get-Command php).Source
[Environment]::SetEnvironmentVariable('OPENSSL_CONF', (Join-Path $phpDirectory 'extras/ssl/openssl.cnf'), 'User')
```

Después abrir terminales nuevas y reiniciar el editor si se usa su terminal
integrada. El worker también debe recibir esa variable. Al cambiar de instalación
de PHP, revisar que la ruta siga siendo válida.
