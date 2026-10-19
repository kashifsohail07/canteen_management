<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>@yield('title') – Canteen Hisab</title>
    <style>
        @page { margin: 28px 30px; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111827; }
        .head { background: #1a56db; color: #fff; padding: 14px 16px; border-radius: 6px; margin-bottom: 14px; }
        .head h1 { margin: 0; font-size: 17px; }
        .head .sub { margin-top: 3px; font-size: 10px; opacity: .9; }
        h2 { margin: 16px 0 6px; font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: #1a56db; border-bottom: 1.5px solid #1a56db; padding-bottom: 3px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th { background: #e8f0fe; color: #1e429f; text-align: left; font-size: 8.5px; text-transform: uppercase; padding: 5px 6px; border-bottom: 1px solid #c7d9f8; }
        td { padding: 5px 6px; border-bottom: 1px solid #e5e9f2; }
        .num { text-align: right; white-space: nowrap; }
        tfoot td, .strong { font-weight: bold; background: #f3f6fc; }
        .in { color: #057a55; } .out { color: #e02424; } .owed { color: #d97706; }
        .kv td:first-child { color: #6b7280; width: 60%; }
        .kv td:last-child { text-align: right; font-weight: bold; }
        .foot { margin-top: 18px; font-size: 8px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <div class="head">
        <h1>Canteen Hisab — @yield('title')</h1>
        <div class="sub">@yield('subtitle')</div>
    </div>
    @yield('body')
    <div class="foot">Generated {{ now()->format('d M Y, h:i A') }} · Canteen Hisab</div>
</body>
</html>
