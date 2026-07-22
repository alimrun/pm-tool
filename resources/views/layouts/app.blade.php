<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Release Planner') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-full font-sans">
        <div class="flex min-h-screen flex-col">
            {{-- Sticky top bar --}}
            <div class="sticky top-0 z-40 border-b border-slate-200 bg-white/90 backdrop-blur-md">
                @include('layouts.navigation')
            </div>

            {{-- Page sub-header --}}
            @isset($header)
                <header class="border-b border-slate-200 bg-white">
                    <div class="app-container py-4 sm:py-5">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            {{-- Flash messages --}}
            <div class="app-container">
                @include('layouts.flash')
            </div>

            {{-- Page content --}}
            <main class="flex-1">
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
