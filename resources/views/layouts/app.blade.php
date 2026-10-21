@php
    // Page metadata. Each page sets these with @section; the defaults keep every
    // page complete for search engines even when a page forgets one.
    $base = rtrim(config('app.url'), '/');
    $pageTitle = trim($__env->yieldContent('title', 'Saltmarsh Hotel - test site'));
    $pageDescription = trim($__env->yieldContent('description', 'The Saltmarsh Hotel is a test hotel and restaurant website with a rules-based chatbot, used for the VML Yonder Full Circle Agent pilot.'));
    $canonical = $base.'/'.ltrim(trim($__env->yieldContent('path', request()->path())), '/');
    $canonical = rtrim($canonical, '/') ?: $base;
    $logoSvg = trim(view('partials.logo-svg')->render());
    $nav = [
        ['home', 'Home'],
        ['rooms', 'Rooms'],
        ['restaurant', 'Restaurant'],
        ['contact', 'Contact'],
        ['channel', 'Channel'],
        ['board', 'Board'],
    ];
@endphp
<!DOCTYPE html>
<html lang="en-ZA" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>{!! \App\Http\Middleware\SecurityHeaders::THEME_SCRIPT !!}</script>
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $pageDescription }}">
    <meta name="keywords" content="@yield('keywords', 'Saltmarsh Hotel, test hotel website, hotel chatbot, restaurant booking, Full Circle Agent, VML Yonder, Claude Code')">
    <meta name="author" content="VML Yonder">
    <meta name="robots" content="index, follow">
    <meta name="theme-color" content="#0b1418">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="canonical" href="{{ $canonical }}">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,{{ rawurlencode($logoSvg) }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">

    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:site_name" content="Saltmarsh Hotel">
    <meta property="og:image" content="{{ $base }}/images/hero-salt-marsh.webp">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $pageTitle }}">
    <meta name="twitter:description" content="{{ $pageDescription }}">
    <meta name="twitter:site" content="@TerminalVelocityAI">

    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => 'Saltmarsh Hotel (test site)',
        'url' => $base.'/',
        'description' => $pageDescription,
        'inLanguage' => 'en-ZA',
        'about' => ['@type' => 'Hotel', 'name' => 'Saltmarsh Hotel', 'description' => 'A fictional hotel and restaurant used to test Full Circle Agent.'],
        'publisher' => ['@type' => 'Organization', 'name' => 'VML Yonder'],
    ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>

    <link rel="stylesheet" href="{{ asset('css/site.css') }}?v={{ filemtime(public_path('css/site.css')) }}">
    <script src="{{ asset('js/site.js') }}?v={{ filemtime(public_path('js/site.js')) }}" defer></script>
    @stack('scripts')
</head>
<body class="@yield('body_class')">
<a class="skip-link" href="#main">Skip to content</a>

<header class="navbar">
    <div class="navbar__inner">
        <a class="brand" href="{{ route('home') }}" aria-label="Saltmarsh Hotel, home">
            <span class="brand__logo" aria-hidden="true">{!! $logoSvg !!}</span>
            <span class="brand__name">Saltmarsh Hotel</span>
        </a>

        <nav id="site-nav" class="site-nav" aria-label="Main">
            <ul>
                @foreach ($nav as [$name, $label])
                    <li><a href="{{ route($name) }}" @if (request()->routeIs($name)) aria-current="page" @endif>{{ $label }}</a></li>
                @endforeach
                <li class="site-nav__account">
                    @auth
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="link-button">Sign out <span class="muted">({{ auth()->user()->name }})</span></button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" @if (request()->routeIs('login')) aria-current="page" @endif>Sign in</a>
                    @endauth
                </li>
            </ul>
        </nav>

        <div class="navbar__tools">
            <button type="button" class="theme-switch" role="switch" aria-checked="false" aria-label="Light mode" title="Switch light mode on or off">
                <span class="theme-switch__track" aria-hidden="true"><span class="theme-switch__knob"></span></span>
            </button>
            <button type="button" class="nav-toggle" aria-expanded="false" aria-controls="site-nav" aria-label="Open menu">
                <span class="nav-toggle__bar" aria-hidden="true"></span>
                <span class="nav-toggle__bar" aria-hidden="true"></span>
                <span class="nav-toggle__bar" aria-hidden="true"></span>
            </button>
        </div>
    </div>
</header>

<main id="main" tabindex="-1">
    @yield('content')
</main>

<footer class="site-footer">
    <div class="site-footer__inner">
        <div class="site-footer__brand">
            <span class="brand__logo brand__logo--small" aria-hidden="true">{!! $logoSvg !!}</span>
            <div>
                <p class="site-footer__name">Saltmarsh Hotel</p>
                <p class="muted">A test site for the VML Yonder Full Circle Agent pilot. Not a real hotel: nothing here is booked or charged.</p>
            </div>
        </div>
        <nav class="site-footer__links" aria-label="Footer">
            <a href="{{ route('rooms') }}">Rooms</a>
            <a href="{{ route('restaurant') }}">Restaurant</a>
            <a href="{{ route('contact') }}">Contact</a>
            <a href="{{ route('channel') }}">Channel</a>
            <a href="{{ route('board') }}">Board</a>
            <a href="{{ route('sitemap') }}">Sitemap</a>
        </nav>
        <div class="site-footer__legal">
            <p>&copy; 2026 VML Yonder. All rights reserved.</p>
        </div>
    </div>
</footer>

@unless (request()->routeIs('login'))
    @include('partials.chat-widget')
@endunless
</body>
</html>
