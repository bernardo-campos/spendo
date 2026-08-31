<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Verificar email · {{ config('app.name', 'Spendo') }}</title>
        @include('partials.favicons')
        @vite(['resources/css/app.css'])
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
        <main class="mx-auto flex min-h-screen w-full max-w-md items-center px-6">
            <section class="w-full rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <header class="space-y-1">
                    <h1 class="text-xl font-semibold">Verificá tu email</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Te enviamos un enlace de verificación a <strong class="font-medium text-slate-700 dark:text-slate-200">{{ auth()->user()->email }}</strong>.
                    </p>
                </header>

                @if (session('status') === Laravel\Fortify\Fortify::VERIFICATION_LINK_SENT)
                    <p class="mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">
                        Te enviamos un nuevo enlace de verificación.
                    </p>
                @endif

                <form method="POST" action="{{ route('verification.send') }}" class="mt-6">
                    @csrf
                    <button type="submit" class="w-full rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white transition hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200">
                        Reenviar email de verificación
                    </button>
                </form>

                <hr class="my-4 border-slate-200 dark:border-slate-800">

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm font-medium text-red-700 underline dark:text-red-300">
                        Cerrar sesión
                    </button>
                </form>
            </section>
        </main>
    </body>
</html>
<div>
    <!-- No surplus words or unnecessary actions. - Marcus Aurelius -->
</div>
