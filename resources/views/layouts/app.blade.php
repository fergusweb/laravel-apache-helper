<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>My Laravel App</title>
    @vite(['resources/css/app.scss'])
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
</body>

</html>

