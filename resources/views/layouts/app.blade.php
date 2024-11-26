<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>{{ config('app.name', 'Apache Helper') }}</title>
    @vite(['resources/css/app.scss'])
    @livewireStyles
</head>

<body>
    @include('partials.header')

    <main class="wrapper">
        @yield('content')
    </main>

    @hasSection('commands')
        @yield('commands')
    @endif

    @include('partials.footer')

    @vite(['resources/js/app.js'])
    @livewireScripts
</body>

</html>

