<!-- resources/views/partials/header.blade.php -->
<header>
    <p class="logo">
        <a href="{{ url('/') }}">
            Apache Helper
        </a>
    </p>
    <nav class="menu">
        <ul>
            <li><a href="{{ url('/') }}">Overview</a></li>
            <li><a href="{{ url('/lookup') }}">Lookup</a></li>
            <li><a href="{{ url('/testing') }}">Testing</a></li>
        </ul>
    </nav>
</header>

