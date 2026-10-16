<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? __('app.title') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
    <nav class="bg-white border-b border-slate-200 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
            <a href="{{ route('home') }}" class="flex items-center gap-2 font-bold text-lg text-indigo-700">
                <span aria-hidden="true">◆</span> {{ __('app.title') }}
            </a>
            <div class="flex items-center gap-2 text-sm">
                <span class="text-slate-400">{{ __('app.language') }}</span>
                <a href="{{ route('lang.switch', 'en') }}"
                   class="px-2 py-1 rounded {{ app()->getLocale() === 'en' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100' }}">EN</a>
                <a href="{{ route('lang.switch', 'id') }}"
                   class="px-2 py-1 rounded {{ app()->getLocale() === 'id' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100' }}">ID</a>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-6">
        {{ $slot }}
    </main>

    <footer class="max-w-7xl mx-auto px-4 py-6 text-xs text-slate-400">
        {{ __('app.footer') }}
    </footer>
</body>
</html>
