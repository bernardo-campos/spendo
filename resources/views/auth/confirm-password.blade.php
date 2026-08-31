<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Confirmar contraseña · {{ config('app.name', 'Spendo') }}</title>
        @include('partials.favicons')
        @vite(['resources/css/app.css'])
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
        <main class="mx-auto flex min-h-screen w-full max-w-md items-center px-6">
            <section class="w-full rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <header class="mb-6 space-y-1">
                    <h1 class="text-xl font-semibold">Confirmar contraseña</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Esta es un área protegida. Confirmá tu contraseña para continuar.</p>
                </header>

                <form method="POST" action="{{ route('password.confirm.store') }}" class="space-y-4">
                    @csrf

                    <div class="space-y-1">
                        <label for="password" class="text-sm font-medium">Contraseña</label>
                        <input id="password" name="password" type="password" required autofocus autocomplete="current-password" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none ring-0 focus:border-slate-400 dark:border-slate-700 dark:bg-slate-950">
                        @error('password')
                            <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="w-full rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200">
                        Confirmar
                    </button>
                </form>
            </section>
        </main>
    </body>
</html>
<div>
    <!-- It is quality rather than quantity that matters. - Lucius Annaeus Seneca -->
</div>
