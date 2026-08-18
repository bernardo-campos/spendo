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
    components/
      SpendoApp.vue                    State, HTTP, forms, active screen flow
      admin/
        AdminLayout.vue                Shell and slot composition
        AdminSidebar.vue               Responsive navigation
        AdminHeader.vue                Theme and user-menu trigger
    pages/
      DashboardPage.vue                Summary cards and recent transactions
      TransactionListPage.vue          Reusable income/expense list
```

## UI conventions

- `AdminLayout` owns the sidebar, mobile overlay, header, main width, and slots.
- `SpendoApp` owns `activeScreen`, the global `selectedPeriod`, color mode
  persistence, sidebar visibility, user-menu state, data loading, mutation
  handlers, and error/success messages.
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
  period is required and changing it reloads dashboard, income, and expense
  data. Navigation among those screens reuses the already loaded result for the
  active period; saving a transaction invalidates it. The versioned API at
  `/api/v1` is a separate contract.
