# Spendo

Aplicación web para gestión de finanzas personales (ingresos, egresos, tarjetas, categorías y tags), construida con Laravel 12 + Vue 3.

## Estado actual (versión implementada)

### Funcionalidades principales
- Autenticación de usuarios (registro, login, logout).
- Dashboard con resumen de:
  - ingresos,
  - egresos,
  - saldo neto.
- Gestión de transacciones:
  - ingresos,
  - egresos,
  - relación con categorías y tags,
  - notas opcionales.
- Gestión de tarjetas:
  - nombre,
  - últimos 4 dígitos,
  - día de cierre,
  - día de vencimiento,
  - estado activo.
- Gestión de categorías (income / expense / both).
- Gestión de tags.

### Flujo UX actual
- Barra superior única con tabs: `Dashboard`, `Ingresos`, `Egresos`.
- Menú de usuario (Tarjetas, Categorías, Tags, Cerrar sesión)
- Pantallas separadas para ingresos y egresos.
- Botones `Registrar ingreso` y `Registrar egreso` fijos abajo.
- Selector de período mensual (`YYYY-MM`) en:
  - dashboard,
  - listado de ingresos,
  - listado de egresos.

### Reglas de negocio implementadas
- Formas de pago para egresos: `cash` y `credit`.
- Para egresos con `credit`:
  - la tarjeta es obligatoria,
  - se calcula `payment_date` según día de cierre/vencimiento,
  - si hay más de 1 cuota, se genera plan de cuotas e ítems por cuota.
- En el formulario de egresos con tarjeta de crédito:
  - se muestra la fecha estimada de pago de la primera cuota al seleccionar tarjeta.
- Filtrado mensual de egresos:
  - si la transacción tiene cuotas, se muestran solo las cuotas cuyo `due_date` corresponde al mes seleccionado,
  - si no tiene cuotas, se usa `payment_date` (fallback: `purchase_date`).
- Restricción de integridad:
  - una tarjeta asociada a transacciones **no puede eliminarse**.

### Formatos de visualización
- Fechas de transacciones en formato `dd/mm/yyyy`.
- Montos en formato argentino (`es-AR`):
  - separador de miles: `.`
  - separador decimal: `,`

## Stack técnico
- PHP `8.3`
- Laravel `12`
- Vue `3`
- Vite `7`
- Tailwind CSS `4`
- Pest `4`
- MariaDB

## Estructura funcional (alto nivel)
- Backend API y auth: `routes/web.php`
- Front principal SPA-like: `resources/js/components/SpendoApp.vue`
- Vista de entrada app: `resources/views/app.blade.php`
- Controladores clave:
  - `DashboardController`
  - `TransactionController`
  - `CardController`
  - `CategoryController`
  - `TagController`

## Configuración
Archivo de configuración propio:
- `config/spendo.php`

Variables de entorno disponibles:
- `SPENDO_CURRENCY` (default: `USD`)
- `SPENDO_CURRENCY_SYMBOL` (default: `$`)

## Instalación y puesta en marcha

### 1) Requisitos
- PHP 8.3+
- Composer
- Node.js + npm
- MariaDB/MySQL

### 2) Instalar dependencias
```bash
composer install
npm install
```

### 3) Configuración inicial
```bash
cp .env.example .env
php artisan key:generate
```

Configurar credenciales de base de datos en `.env`, luego ejecutar:
```bash
php artisan migrate
```

### 4) Desarrollo
Ejecutar backend + cola + vite:
```bash
composer run dev
```

Alternativa separada:
```bash
php artisan serve
npm run dev
```

### 5) Build de frontend
```bash
npm run build
```

## Scripts útiles
- `composer run dev`: servidor Laravel + queue listener + Vite.
- `composer run test`: limpia config y ejecuta tests.
- `php artisan test --compact`: tests compactos.
- `vendor/bin/pint --dirty`: formateo de archivos modificados.

## API (resumen)
Rutas bajo middleware `auth` y prefijo `/api`:
- `GET /api/dashboard`
- `apiResource /api/categories`
- `apiResource /api/tags`
- `apiResource /api/cards`
- `apiResource /api/transactions`
- `apiResource /api/installment-plans`

## Calidad y pruebas
- El proyecto usa Pest para pruebas.
- Incluye cobertura de flujo de autenticación.
- Incluye pruebas para restricción de eliminación de tarjetas asociadas a transacciones.

## Notas
- Si un cambio frontend no se refleja, ejecutar `npm run dev` o `npm run build`.
- El README describe el estado funcional actual del proyecto según la implementación vigente.
