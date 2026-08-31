<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Registro · {{ config('app.name', 'Spendo') }}</title>
        @include('partials.favicons')
        @vite(['resources/css/app.css'])
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
        <main class="mx-auto flex min-h-screen w-full max-w-md items-center px-6">
            <section class="w-full rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <header class="mb-6 space-y-1">
                    <h1 class="text-xl font-semibold">Crear cuenta</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Empieza a registrar tus gastos e ingresos.</p>
                </header>

                <form method="POST" action="{{ route('register.store') }}" class="space-y-4">
                    @csrf

                    <div class="space-y-1">
                        <label for="name" class="text-sm font-medium">Nombre</label>
                        <input id="name" name="name" type="text" value="{{ old('name') }}" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none ring-0 focus:border-slate-400 dark:border-slate-700 dark:bg-slate-950">
                        @error('name')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-1">
                        <label for="email" class="text-sm font-medium">Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none ring-0 focus:border-slate-400 dark:border-slate-700 dark:bg-slate-950">
                        @error('email')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-1">
                        <label for="password" class="text-sm font-medium">Contraseña</label>
                        <input id="password" name="password" type="password" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none ring-0 focus:border-slate-400 dark:border-slate-700 dark:bg-slate-950">
                        @error('password')
                            <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-1">
                        <label for="password_confirmation" class="text-sm font-medium">Confirmar contraseña</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none ring-0 focus:border-slate-400 dark:border-slate-700 dark:bg-slate-950">
                        @error('password_confirmation')
                            <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="w-full rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200">
                        Crear cuenta
                    </button>
                </form>

                <p class="mt-4 text-sm text-slate-600 dark:text-slate-300">
                    ¿Ya tienes cuenta?
                    <a href="{{ route('login') }}" class="font-medium underline">Inicia sesión</a>
                </p>
            </section>
        </main>
    </body>
</html>
