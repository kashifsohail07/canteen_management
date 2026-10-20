<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#1a56db">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Canteen Hisab">
<title>@yield('title', 'Home') · Canteen Hisab</title>
<style>[x-cloak]{display:none!important}</style>
<link rel="manifest" href="{{ asset('manifest.json') }}">
<link rel="icon" type="image/svg+xml" href="{{ asset('icons/icon.svg') }}">
<link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
@vite(['resources/css/app.css', 'resources/js/app.js'])
