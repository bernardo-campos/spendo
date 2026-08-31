<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Recuperar contraseña · {{ config('app.name', 'Spendo') }}</title>
        @vite(['resources/css/app.css'])
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
        <main class="mx-auto flex min-h-screen w-full max-w-md items-center px-6">
            <section class="w-full rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <header class="mb-6 space-y-1">
                    <h1 class="text-xl font-semibold">Recuperar contraseña</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Te enviaremos un enlace para elegir una nueva contraseña.</p>
                </header>

                @if (session('status'))
                    <p class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">
                        {{ session('status') }}
                    </p>
                @endif

                <form method="POST" action="{{ route('password.email') }}" class="space-y-4" data-password-reset-form>
                    @csrf

                    <div class="space-y-1">
                        <label for="email" class="text-sm font-medium">Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none ring-0 focus:border-slate-400 dark:border-slate-700 dark:bg-slate-950">
                        @error('email')
                            <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200" data-password-reset-submit>
                        <span class="hidden size-4 animate-spin rounded-full border-2 border-current border-t-transparent" data-password-reset-spinner aria-hidden="true"></span>
                        <span data-password-reset-label>Enviar enlace</span>
                    </button>
                </form>

                <p class="mt-4 text-sm text-slate-600 dark:text-slate-300">
                    <a href="{{ route('login') }}" class="font-medium underline">Volver a iniciar sesión</a>
                </p>
            </section>
        </main>

        <script>
            const passwordResetForm = document.querySelector('[data-password-reset-form]');
            const passwordResetButton = document.querySelector('[data-password-reset-submit]');
            const passwordResetSpinner = document.querySelector('[data-password-reset-spinner]');
            const passwordResetLabel = document.querySelector('[data-password-reset-label]');

            const resetPasswordResetButton = () => {
                passwordResetForm?.removeAttribute('data-submitting');
                passwordResetButton?.removeAttribute('disabled');
                passwordResetButton?.removeAttribute('aria-busy');
                passwordResetSpinner?.classList.add('hidden');

                if (passwordResetLabel) {
                    passwordResetLabel.textContent = 'Enviar enlace';
                }
            };

            passwordResetForm?.addEventListener('submit', () => {
                if (!passwordResetForm.checkValidity() || passwordResetForm.dataset.submitting === 'true') {
                    return;
                }

                passwordResetForm.dataset.submitting = 'true';
                passwordResetButton?.setAttribute('disabled', 'disabled');
                passwordResetButton?.setAttribute('aria-busy', 'true');
                passwordResetSpinner?.classList.remove('hidden');

                if (passwordResetLabel) {
                    passwordResetLabel.textContent = 'Enviando...';
                }
            });

            window.addEventListener('pageshow', resetPasswordResetButton);
        </script>
    </body>
</html>
<div>
    <!-- No surplus words or unnecessary actions. - Marcus Aurelius -->
</div>
