<!doctype html>
<html lang="en">
<head>
    @include('layouts.partials.head')
    @stack('head')
</head>
<body class="app-body" x-data="{ moreOpen: false }" @keydown.escape.window="moreOpen = false">
@php
    $user = auth()->user();
    $topDate = ($topDate ?? null) ?: now();
    $is = fn (...$patterns) => request()->routeIs(...$patterns);
@endphp

<div class="app-shell">
    <header class="topbar">
        <a class="brand" href="{{ route('dashboard') }}">
            <span class="brand-logo"><i class="bi bi-wallet2"></i></span>
            <span>Canteen Hisab</span>
        </a>
        <span class="date-chip"><i class="bi bi-calendar3"></i> {{ $topDate->format('D, d M') }}</span>
    </header>

    <main class="app-main @yield('main_class')">
        @if (session('success'))
            <div class="flash flash-success" role="status"><i class="bi bi-check-circle-fill"></i><div>{{ session('success') }}</div></div>
        @endif
        @if (session('error'))
            <div class="flash flash-error" role="alert"><i class="bi bi-exclamation-triangle-fill"></i><div>{{ session('error') }}</div></div>
        @endif
        @if ($errors->any())
            <div class="flash flash-error" role="alert">
                <i class="bi bi-exclamation-octagon-fill"></i>
                <div>
                    @if ($errors->count() === 1)
                        {{ $errors->first() }}
                    @else
                        <ul>@foreach (array_unique($errors->all()) as $message)<li>{{ $message }}</li>@endforeach</ul>
                    @endif
                </div>
            </div>
        @endif

        @yield('content')
    </main>

    <nav class="bottom-nav" aria-label="Main">
        <a href="{{ route('dashboard') }}" class="nav-item {{ $is('dashboard') ? 'active' : '' }}">
            <i class="bi {{ $is('dashboard') ? 'bi-house-door-fill' : 'bi-house-door' }}"></i>
            <span>Home</span>
        </a>
        <a href="{{ route('sales.create') }}" class="nav-item {{ $is('sales.*') ? 'active' : '' }}">
            <i class="bi {{ $is('sales.*') ? 'bi-cash-stack' : 'bi-cash' }}"></i>
            <span>Sale</span>
        </a>
        <a href="{{ route('purchases.create') }}" class="nav-item nav-primary {{ $is('purchases.*') ? 'active' : '' }}">
            <span class="nav-fab"><i class="bi bi-plus-lg"></i></span>
            <span>Bill</span>
        </a>
        <a href="{{ route('payments.create') }}" class="nav-item {{ $is('payments.*') ? 'active' : '' }}">
            <i class="bi {{ $is('payments.*') ? 'bi-send-fill' : 'bi-send' }}"></i>
            <span>Pay</span>
        </a>
        <button type="button" class="nav-item {{ $is('vendors.*', 'rent.*', 'closing.*', 'reports.*', 'settings.*', 'setup.*', 'segments.*', 'owner.*') ? 'active' : '' }}" @click="moreOpen = true" aria-haspopup="dialog">
            <i class="bi {{ $is('vendors.*', 'rent.*', 'closing.*', 'reports.*', 'settings.*', 'setup.*', 'segments.*', 'owner.*') ? 'bi-grid-fill' : 'bi-grid' }}"></i>
            <span>More</span>
        </button>
    </nav>
</div>

{{-- "More" bottom sheet --}}
<div x-cloak x-show="moreOpen" class="sheet-backdrop" @click="moreOpen = false"
     x-transition:enter="fade-enter" x-transition:enter-start="fade-start" x-transition:enter-end="fade-end"
     x-transition:leave="fade-enter" x-transition:leave-start="fade-end" x-transition:leave-end="fade-start"></div>
<div x-cloak x-show="moreOpen" class="sheet" role="dialog" aria-label="Menu"
     x-transition:enter="sheet-enter" x-transition:enter-start="sheet-enter-start" x-transition:enter-end="sheet-enter-end"
     x-transition:leave="sheet-enter" x-transition:leave-start="sheet-enter-end" x-transition:leave-end="sheet-enter-start">
    <div class="grabber"></div>
    <h3>Menu <small class="text-muted fw-normal fs-6">· {{ $user->name }} ({{ $user->role->label() }})</small></h3>
    <div class="sheet-grid">
        <a class="sheet-link" href="{{ route('vendors.index') }}"><i class="bi bi-people-fill"></i>Vendors</a>
        <a class="sheet-link" href="{{ route('owner.index') }}"><i class="bi bi-person-badge-fill"></i>Owner / Pocket</a>
        <a class="sheet-link" href="{{ route('rent.pay') }}"><i class="bi bi-house-lock-fill"></i>Rent Pay</a>
        <a class="sheet-link" href="{{ route('closing.show') }}"><i class="bi bi-lock-fill"></i>Din Close</a>
        <a class="sheet-link" href="{{ route('reports.index') }}"><i class="bi bi-file-earmark-bar-graph-fill"></i>Reports</a>
        @if ($user->isAdmin())
            <a class="sheet-link" href="{{ route('segments.index') }}"><i class="bi bi-ui-checks-grid"></i>Segments</a>
            <a class="sheet-link" href="{{ route('settings.edit') }}"><i class="bi bi-gear-fill"></i>Settings</a>
        @endif
        <form method="POST" action="{{ route('logout') }}" class="m-0">
            @csrf
            <button type="submit" class="sheet-link danger"><i class="bi bi-box-arrow-right"></i>Logout</button>
        </form>
    </div>
</div>

@stack('scripts')
</body>
</html>
