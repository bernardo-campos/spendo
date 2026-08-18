# Spendo admin frontend map

## Entry and mounting

- `resources/views/app.blade.php` provides `#spendo-app`, the authenticated user
  name, currency symbol, CSRF token, and Vite entry points.
- `resources/js/app.js` mounts `SpendoApp.vue` only when `#spendo-app` exists.
- `resources/js/bootstrap.js` configures Axios for the existing web endpoints.

## Current structure

```text
resources/
  css/app.css                         Tailwind v4 imports and theme tokens
    js/
    app.js                             Vue entry point
    composables/
      useTransactions.js               Consulta, caché y proyecciones por período
    components/
      SpendoApp.vue                    State, HTTP, forms, active screen flow
      admin/
        AdminLayout.vue                Shell and slot composition
        AdminSidebar.vue               Responsive navigation
        AdminHeader.vue                Theme and user-menu trigger
      ui/tags-input/                   Primitivas Tags Input de shadcn/vue
      ui/combobox/                     Primitivas Combobox de shadcn/vue
    lib/
      utils.js                         Utilidad de composición de clases de shadcn/vue
    pages/
      CardsPage.vue                    Tarjetas y ciclos de facturación
      CategoriesPage.vue               Administración de categorías
      DashboardPage.vue                Summary cards and recent transactions
      TagsPage.vue                     Administración de etiquetas
      TransactionFormPage.vue          Formulario de ingresos y egresos
      TransactionListPage.vue          Reusable income/expense list
    utils/
      cardPaymentDates.js              Cálculos puros de fechas de cuotas
```

## UI conventions

- `AdminLayout` owns the sidebar, mobile overlay, header, main width, and slots.
- `SpendoApp` owns `activeScreen`, the global `selectedPeriod`, color mode
  persistence, sidebar visibility, user-menu state, data loading, mutation
  handlers, and error/success messages. Resource screens receive state through
  props and emit user actions back to this coordinator.
- `useTransactions` encapsulates `/transactions?period=YYYY-MM`, its cache del
  período activo, loading state, normalización de cuotas y totales. Las fechas
  de pago estimadas y el uso de ciclos reales viven en `cardPaymentDates.js`.
- Los ítems de ingresos y egresos abren el formulario de edición. Una cuota
  proyectada conserva el identificador de su transacción padre, por lo que se
  edita o elimina la compra original; la cantidad de cuotas queda fija después
  de crearla.
- El formulario de transacciones usa `TagsInput` de shadcn/vue para seleccionar
  tags existentes por nombre. Sus chips visuales se traducen siempre a
  `form.tag_ids`, que es el contrato de Laravel.
- El formulario usa `Combobox` de shadcn/vue para categorías (con búsqueda),
  forma de pago y tarjeta (sin búsqueda). Los modelos de objeto del componente
  se traducen a los valores escalares existentes del formulario.
- `AdminHeader` renders the single month selector. Dashboard, income, and expense
  pages consume the filtered data and must not render a second period selector.
- Existing pages receive data via props and emit actions such as
  `update:selected-period` and `create`.
- Use semantic Tailwind tokens for shell UI: `bg-background`, `text-foreground`,
  `bg-sidebar`, `border-border`, `bg-primary`, and `bg-accent`.
- Existing resource forms still use the established Slate utility classes; match
  their surrounding UI and include equivalent `dark:` variants.
- Use `@lucide/vue` for navigation and control icons.

## Boundaries

- This is JavaScript Vue 3 with Composition API, not TypeScript.
- There is no Vue Router or Pinia. Navigation is an in-component `activeScreen`
  flow.
- The admin runs inside Laravel's authenticated `/app` experience. Do not create
  a second SPA entry or independent frontend repository for ordinary screens.
- The current web frontend calls `/transactions?period=YYYY-MM`. The selected
  period is required and changing it always reloads its data, including when the
  active screen is outside dashboard, income, or expense. Navigation among those
  three screens reuses the already loaded result for the active period; saving a
  transaction invalidates it. The sidebar receives the calculated income and
  expense totals for that period and displays a loading placeholder while the
  transaction request is in progress. The versioned API at `/api/v1` is a
  separate contract.
