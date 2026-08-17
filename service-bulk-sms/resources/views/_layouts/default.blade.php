<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ isset($pageTitle) ? $pageTitle . ' - SMS Service' : 'SMS Service' }}</title>
        <link rel="stylesheet" type="text/css" href="{{ asset('/css/app.css') }}">
        <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    </head>
    <body class="bg-gray-100 text-gray-700 mb-8">
        <div id="app">
            @auth
                <div class="w-full bg-blue-900 py-3 mb-4">
                    <div class="container mx-auto flex justify-between">
                        <div>
                            <a href="{{ url('/practices') }}" class="inline-block border border-gray-500 text-white font-light rounded px-2 px-2">SMS Service</a>
                        </div>
                        <div class="text-gray-400 text-sm">
                            <ul class="list-none flex">
                                <li class="mx-3"><a href="{{ route('practices') }}" class="font-semibold text-white hover:underline">{{ __('Practices') }}</a></li>
                                <li class="mx-3"><a href="{{ route('messages') }}" class="font-semibold text-white hover:underline">{{ __('Sent Messages') }}</a></li>
                            </ul>
                        </div>
                        <div class="text-gray-400 text-sm">
                            {{ auth()->user()->email }} |
                            <a href="{{ route('logout') }}" class="font-semibold text-white hover:underline" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                {{ __('Logout') }}
                            </a>
                            <form id="logout-form" action="{{ route('logout') }}" method="post" class="hidden">
                                @csrf
                            </form>
                        </div>
                    </div>
                </div>
            @endauth
            @yield('content')
        </div>
    <script src="{{ asset('/js/app.js') .'?v=1.0.1' }}"></script>
    </body>
</html>
