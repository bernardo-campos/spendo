---
name: spendo-admin
description: Build, extend, and refactor Spendo's Vue 3 administrative frontend. Use when creating or modifying admin screens, dashboard or transaction views, navigation, the responsive sidebar/header, theme behavior, reusable Vue components, or Tailwind styling under resources/js and resources/css.
---

# Spendo Admin

Maintain Spendo's Laravel + Vite + Vue 3 frontend. Reuse the existing admin shell
and visual tokens; do not introduce the original shadcn-vue-admin template's
router, Pinia, TypeScript, or package structure unless the user explicitly asks.

## Read first

Read [references/frontend-map.md](references/frontend-map.md) before modifying
the frontend. Inspect the target component and a sibling before adding code.

## Workflow

1. Keep `resources/js/app.js` as the Vue entry point and `SpendoApp.vue` as the
   current state and HTTP coordinator.
2. Put reusable layout and navigation elements in `resources/js/components/admin/`.
   Reuse `AdminLayout`, `AdminSidebar`, and `AdminHeader`; do not duplicate their
   sidebar, responsive overlay, user menu, or theme controls inside a page.
3. Put screen-specific presentational components in `resources/js/pages/`. Pass
   data through props and communicate user actions with emitted events. Keep
   loading, HTTP calls, and cross-screen state in `SpendoApp.vue` until a
   dedicated state architecture is deliberately introduced.
4. Add a sidebar item only after its screen exists and it can be reached through
   the current `activeScreen` flow. Preserve existing screen names and behavior
   unless the task changes navigation intentionally.
5. Use Tailwind CSS v4 utility classes and the tokens defined in
   `resources/css/app.css`. Support the existing `.dark` mode for new UI.
6. Prefer `@lucide/vue` icons already installed. Do not add UI dependencies,
   Vue Router, Pinia, or a component library without explicit approval.
7. Keep the existing web frontend endpoints unchanged. Do not migrate its calls
   to `/api/v1` unless the task explicitly includes that change.

## Page pattern

For a new admin screen:

1. Create `resources/js/pages/<Name>Page.vue` with `script setup`.
2. Define explicit props for displayed data and `defineEmits` for user actions.
3. Render it inside `AdminLayout` from `SpendoApp.vue` (or its future page
   coordinator) using the existing `activeScreen` convention.
4. Add its navigation item in `AdminSidebar.vue` only when applicable.
5. Keep cards, inputs, borders, spacing, and dark mode consistent with the
   existing pages.

## Verification

- Run `npm run build` after frontend code changes.
- Run the affected Laravel tests when the change affects requests, authentication,
  or server-rendered integration.
- Summarize changed files and commands run.
