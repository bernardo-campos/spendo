<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'Spendo') }}</title>
        @include('partials.favicons')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div
            id="spendo-app"
            data-user-name="{{ auth()->user()->name }}"
            data-currency-symbol="{{ config('spendo.currency_symbol', '$') }}"
        ></div>
    </body>
</html>
