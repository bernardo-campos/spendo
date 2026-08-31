# Especificacion Tecnica del Proyecto Spendo

## 1. Resumen tecnico
- Framework backend: Laravel 13.
- Lenguaje backend: PHP 8.3.
- Autenticacion: Laravel Fortify 1.
- Frontend: Vue 3 + Axios + Vite 7.
- Estilos: Tailwind CSS v4.
- Base de datos: MariaDB.
- Testing: Pest 4 + PHPUnit 12.

Arquitectura aplicada:
- Backend monolito Laravel con autenticacion web por sesion.
- API JSON bajo `/api` protegida por middleware `auth`.
- Frontend tipo SPA ligera montada en una vista Blade (`SpendoApp.vue`).

## 2. Arquitectura y capas

### 2.1 Capa de presentacion
- Vistas Blade para auth y shell de app:
  - `resources/views/auth/login.blade.php`
  - `resources/views/auth/register.blade.php`
  - `resources/views/auth/forgot-password.blade.php`
  - `resources/views/auth/reset-password.blade.php`
  - `resources/views/auth/confirm-password.blade.php`
  - `resources/views/auth/verify-email.blade.php`
  - `resources/views/app.blade.php`
- Componente principal Vue:
  - `resources/js/components/SpendoApp.vue`
- Entrada frontend:
  - `resources/js/app.js`
  - `resources/js/bootstrap.js`

### 2.2 Capa HTTP/API
Controladores REST + controlador invocable para dashboard:
- `DashboardController`
- `CategoryController`
- `TagController`
- `CardController`
- `CardBillingCycleController`
- `TransactionController`
- `InstallmentPlanController`

Fortify registra las rutas y controladores de autenticacion. `FortifyServiceProvider`
asocia las vistas y acciones propias; `CreateNewUser` valida los datos de alta y
Turnstile antes de persistir al usuario. El resto de entradas usa Form Requests
dedicados (excepto update de installment plan, que valida inline en controlador).

### 2.3 Capa de dominio/negocio
Servicios de negocio:
- `CardPaymentDateService`: resuelve fecha de pago real/estimada para consumos con tarjeta.
- `InstallmentPlanService`: construye cuotas y sincroniza estado de plan.
- `InstallmentDueDateSyncService`: recalcula vencimientos de cuotas con ciclos reales de tarjeta.

### 2.4 Capa de datos
- Eloquent ORM con modelos y relaciones.
- Migraciones versionadas para esquema.
- Integridad por foreign keys, indices y unicos compuestos.

## 3. Routing y seguridad

## 3.1 Rutas web
Las rutas de aplicacion se definen en `routes/web.php` y las de autenticacion
son registradas por Fortify:
- `/` redirige a `app` o `login` segun sesion.
- Rutas publicas/guest de Fortify:
  - `GET /login`
  - `POST /login`
  - `GET /register`
  - `POST /register`
  - `GET|POST /forgot-password`
  - `GET|POST /reset-password`
- Rutas autenticadas de Fortify:
  - `POST /logout`
  - `GET /email/verify`
  - `GET /email/verify/{id}/{hash}`
  - `POST /email/verification-notification`
  - `GET|POST /user/confirm-password`
- Grupo `auth` + `verified` de la aplicacion:
  - `GET /app` (vista app)

## 3.2 Rutas API autenticadas
Bajo prefijo `/api` y middleware `auth`:
- `GET /api/dashboard`
- `apiResource /api/categories`
- `apiResource /api/tags`
- `apiResource /api/cards`
- `apiResource /api/cards/{card}/billing-cycles`
- `apiResource /api/transactions`
- `apiResource /api/installment-plans`

## 3.3 Mecanismos de seguridad
- Autenticacion por sesion Laravel.
- Laravel Fortify como unico backend de registro, login, logout, recuperacion,
  restablecimiento, confirmacion de contrasena y verificacion de email.
- CSRF token en formularios Blade y cabecera Axios (`X-CSRF-TOKEN`).
- Registro protegido con Cloudflare Turnstile Managed visible. La validacion
  server-side exige `success=true`, `action=register` y coincidencia de hostname.
- Turnstile utiliza timeout de conexion de 2 segundos, timeout total de 5
  segundos y reintentos breves solo ante errores de conexion o respuestas 5xx.
- La politica es fail-closed y los logs omiten tokens, secretos y credenciales.
- Aislamiento por usuario en controladores con checks de `user_id` + `abort_unless`.
- Validaciones de pertenencia de recursos con reglas `exists(...)->where(user_id)`.

## 4. Modelo de datos

## 4.1 Entidades principales
- `users`
- `categories`
- `tags`
- `cards`
- `card_billing_cycles`
- `transactions`
- `transaction_tag` (pivot)
- `installment_plans`
- `installments`

## 4.2 Relacionamiento
- User 1:N Category
- User 1:N Tag
- User 1:N Card
- User 1:N Transaction
- User 1:N InstallmentPlan
- Card 1:N CardBillingCycle
- Card 1:N Transaction
- Card 1:N InstallmentPlan
- Category 1:N Transaction
- Transaction N:M Tag (pivot `transaction_tag`)
- Transaction 1:1 InstallmentPlan (unico por `transaction_id`)
- InstallmentPlan 1:N Installment

## 4.3 Campos y restricciones clave
- `categories`: unique (`user_id`, `slug`), `scope` (`income|expense|both` a nivel validacion), `is_active`.
- `tags`: unique (`user_id`, `slug`).
- `cards`: `last_four_digits` de 4 chars, `closing_day`, `due_day`, `is_active`.
- `card_billing_cycles`: unique (`card_id`, `closing_date`), indice (`card_id`, `due_date`).
- `transactions`:
  - `payment_method` nullable.
  - `card_id` nullable.
  - indices (`user_id`,`type`,`purchase_date`) y (`user_id`,`payment_method`).
- `installment_plans`:
  - unique `transaction_id`.
  - indice (`user_id`,`status`).
- `installments`:
  - unique (`installment_plan_id`,`installment_number`).
  - indice (`due_date`,`status`).
  - flag `due_date_is_estimated`.

## 4.4 Casts relevantes en modelos
- Monetarios: `decimal:2` en `Transaction.amount`, `InstallmentPlan.total_amount`, `Installment.amount`.
- Fechas: `purchase_date`, `payment_date`, `first_due_date`, `due_date`, `paid_at`, `closing_date`.
- Booleanos: `Category.is_active`, `Card.is_active`, `Installment.due_date_is_estimated`.

## 5. Enumeraciones y valores de dominio
- Enum `PaymentMethodType`:
  - `Cash = cash`
  - `Credit = credit`

Valores operativos adicionales:
- Tipo de transaccion: `income`, `expense`.
- Scope de categoria: `income`, `expense`, `both`.
- Estado de plan/cuota: `pending`, `paid`, `completed` (plan completo cuando no quedan pendientes).

## 6. Validaciones y contratos de entrada

## 6.1 Auth
- Fortify procesa login con `email`, `password` y `remember?`; la opcion de
  recordar se procesa separada de las credenciales.
- `CreateNewUser`: `name`, `email` unico, `password` confirmada y
  `cf-turnstile-response` obligatorio (`string`, maximo 2048).
- `ValidTurnstile` delega en `TurnstileVerifier` la comprobacion remota contra
  Siteverify antes de crear o autenticar al usuario.
- El email debe verificarse para acceder a las rutas de la aplicacion.

## 6.2 Catalogos
- Category:
  - `name` requerido.
  - `slug` requerido y unico por usuario.
  - `scope` requerido en conjunto permitido.
- Tag:
  - `name` requerido.
  - `slug` requerido y unico por usuario.
- Card:
  - `name` requerido.
  - `last_four_digits` 4 digitos.
  - `closing_day` y `due_day` entre 1 y 31.

## 6.3 Billing cycles
- Store/Update:
  - `closing_date` unico por tarjeta.
  - `due_date` posterior a cierre.
- En update se completan campos faltantes con valores previos antes de validar consistencia.

## 6.4 Transacciones
- Store:
  - `type` obligatorio (`income|expense`).
  - `amount` numerico > 0.
  - `purchase_date` obligatoria.
  - `category_id`, `tag_ids`, `card_id` deben pertenecer al usuario.
  - `payment_method` requerido si `type=expense`.
  - `card_id` requerido si `expense + credit`.
  - `installments_count` requerido si `expense + credit` (1..120).
- Update:
  - campos opcionales, preservando pertenencia de recursos.

## 6.5 Plan de cuotas manual
- StoreInstallmentPlanRequest:
  - `transaction_id`, `card_id` pertenecientes al usuario.
  - `installments_count` entre 2 y 120.
  - `first_due_date` obligatoria.

## 7. Logica de negocio implementada

## 7.1 Calculo de fecha de pago (`CardPaymentDateService`)
Algoritmo:
1. Si no es `credit` o no hay tarjeta: usa fecha de compra.
2. Busca primer ciclo real con `closing_date >= purchase_date`.
3. Si existe ciclo: usa `due_date` real (`is_estimated=false`).
4. Si no existe:
   - define mes de resumen segun `closing_day`.
   - mueve al mes de vencimiento.
   - aplica `due_day` con control de desborde de fin de mes.
   - retorna fecha estimada (`is_estimated=true`).

## 7.2 Generacion de cuotas (`InstallmentPlanService::buildInstallments`)
- Convierte total a centavos para evitar error de flotantes.
- Reparte base por cuota con `intdiv`.
- Asigna residuo a la ultima cuota.
- Genera vencimientos mensuales consecutivos (`addMonthsNoOverflow`).
- Inicializa cada cuota como `pending` y `due_date_is_estimated=true`.

## 7.3 Sincronizacion de cuotas con ciclos reales (`InstallmentDueDateSyncService`)
- `syncCard`: recorre planes pendientes de la tarjeta.
- `syncPlan`:
  - obtiene transaccion, tarjeta, ciclos reales futuros y cuotas ordenadas.
  - para cada cuota pendiente, mapea ciclo por indice y actualiza `due_date` real.
  - marca cuota como no estimada.
  - sincroniza `first_due_date` del plan.
  - sincroniza `payment_date` de la transaccion al nuevo primer vencimiento.

## 7.4 Reglas en controladores
- `TransactionController@store`:
  - crea transaccion en transaccion DB.
  - sincroniza tags.
  - si aplica credito en >1 cuota, crea `installment_plan` + cuotas + sync real.
- `TransactionController@update`:
  - recalcula `payment_date` si cambia compra/metodo/tarjeta en credito.
  - para efectivo, `payment_date = purchase_date` si cambia fecha.
  - sincroniza plan si existe.
- `CardController@destroy`:
  - impide borrar tarjeta con transacciones asociadas (422).
- `CardBillingCycleController`:
  - al crear/editar/eliminar ciclo dispara sincronizacion de cuotas.
- `InstallmentPlanController@update`:
  - marca cuota como pagada (`status=paid`, `paid_at`).
  - recalcula estado global del plan.

## 8. Frontend: estructura y comportamiento

## 8.1 Componente unico principal
`SpendoApp.vue` concentra:
- Estado de navegacion (`activeScreen`).
- Estado de formularios (transaccion, categoria, tag, tarjeta, ciclos).
- Carga de datos via Axios (`/api/*`).
- Derivados con `computed` para resumen, filtros y rendering.

## 8.2 Helpers/funciones especificas en frontend
- `normalizeSlug`: limpia acentos y normaliza a slug URL-safe.
- `calculateFirstInstallmentPaymentDate`: replica logica de fecha de primera cuota para feedback inmediato en formulario.
- `hasRealCycleForPurchaseDate`: detecta si la fecha mostrada es real o estimada.
- `formatDate`: muestra fechas en `dd/mm/yyyy`.
- `formatAmount`: locale `es-AR`.
- `runWithLoading`: wrapper de carga/errores para llamadas async.

## 8.3 Comportamientos UX
- Watchers que ajustan formulario cuando cambia tipo/metodo de pago.
- Carga diferida por pantalla activa.
- Mensajes de error/exito por accion.
- Selector de periodo mensual compartido para vistas de movimientos y dashboard.

## 9. Configuracion y ejecucion

## 9.1 Config propia
`config/spendo.php`:
- `currency` (env `SPENDO_CURRENCY`, default `USD`).
- `currency_symbol` (env `SPENDO_CURRENCY_SYMBOL`, default `$`).

`config/services.php`:
- `turnstile.site_key` (env `TURNSTILE_SITE_KEY`): clave publica renderizada en el widget.
- `turnstile.secret_key` (env `TURNSTILE_SECRET_KEY`): secreto exclusivo del servidor.
- `turnstile.expected_hostname` (env `TURNSTILE_EXPECTED_HOSTNAME`, fallback al host de `APP_URL`).
- `turnstile.action`: `register`.
- `turnstile.verify_url`: endpoint Siteverify oficial.

## 9.2 Build/runtime
- `composer run dev`: server + queue listener + vite en paralelo.
- `npm run dev`: vite dev server.
- `npm run build`: build produccion assets.

## 9.3 Bootstrap Laravel 13
- `bootstrap/app.php` configura routing web/console y endpoint health (`/up`).
- Middleware y excepciones sin customizaciones extra (defaults).

## 10. Pruebas y cobertura actual
Pruebas Pest implementadas para:
- Flujo Fortify: registro, login, remember, logout, recuperacion/restablecimiento,
  confirmacion de contrasena y verificacion de email.
- Turnstile: renderizado seguro del widget, payload de Siteverify, respuesta
  exitosa, token ausente/rechazado/reutilizado, action y hostname incorrectos,
  claves faltantes, fallos de conexion/timeouts y respuestas 5xx.
- Restriccion de borrado de tarjeta con transacciones asociadas.
- Sincronizacion de cuotas estimadas a reales al cargar ciclos de facturacion.
- Logica de reparto de montos en cuotas (incluyendo residuo).

Observacion:
- No hay una suite completa para todos los endpoints CRUD ni para toda la UI; la cobertura es parcial y enfocada en reglas de negocio criticas.

## 11. Deuda tecnica / mejoras sugeridas
- Separar `SpendoApp.vue` en componentes menores por modulo para mejorar mantenibilidad.
- Homogeneizar validacion en `InstallmentPlanController@update` usando FormRequest dedicado.
- Incorporar politicas (`Policies`) para autorizacion por recurso en lugar de checks repetidos en controladores.
- Aumentar cobertura de tests para CRUD completo de categorias/tags/tarjetas/transacciones.
- Exponer dashboard backend o remover endpoint no utilizado para evitar duplicidad de logica.
