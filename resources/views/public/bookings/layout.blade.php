<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Reservar cita - Secretaria Virtual')</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; font-family: "OpenAI Sans", Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", "Helvetica Neue", Arial, sans-serif; color: #10131a; background: #f6f8fb; -webkit-font-smoothing: antialiased; text-rendering: optimizeLegibility; }
        a { color: inherit; text-decoration: none; }
        .wrap { width: min(1080px, calc(100% - 40px)); margin: 0 auto; }
        .topbar { background: white; border-bottom: 1px solid #dde3ea; }
        .nav { min-height: 74px; display: flex; align-items: center; justify-content: space-between; gap: 18px; }
        .brand-logo { width: 170px; max-width: 100%; display: block; border-radius: 8px; }
        .actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .btn { min-height: 42px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid #dde3ea; border-radius: 6px; padding: 0 16px; background: white; font-weight: 900; cursor: pointer; }
        .btn.primary { background: #c0265a; color: white; border-color: #c0265a; }
        .hero { padding: 46px 0 30px; }
        .hero h1 { margin: 0; font-size: clamp(34px, 6vw, 58px); line-height: 1; }
        .hero p { max-width: 680px; color: #647084; font-size: 18px; line-height: 1.55; }
        .card { border: 1px solid #dde3ea; border-radius: 8px; padding: 18px; background: white; }
        .grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
        label { display: block; margin-bottom: 7px; color: #647084; font-weight: 900; }
        input, select, textarea { width: 100%; min-height: 44px; border: 1px solid #ccd5e0; border-radius: 6px; padding: 0 12px; background: white; }
        textarea { min-height: 90px; padding-top: 10px; resize: vertical; }
        .subtitle { color: #647084; }
        .status { display: inline-flex; align-items: center; border-radius: 999px; padding: 5px 10px; background: #fef3c7; color: #92400e; font-size: 12px; font-weight: 900; }
        .notice { margin-bottom: 18px; border-color: #bbf7d0; background: #f0fdf4; color: #166534; font-weight: 900; }
        .error { margin-top: 8px; color: #b91c2a; font-size: 14px; font-weight: 800; }
        .list { display: grid; gap: 12px; }
        .item { display: flex; justify-content: space-between; gap: 16px; align-items: center; border: 1px solid #dde3ea; border-radius: 8px; padding: 16px; background: white; }
        .item b, .item span { display: block; }
        .item span { margin-top: 4px; color: #647084; }
        .slots { display: grid; grid-template-columns: repeat(auto-fill, minmax(112px, 1fr)); gap: 10px; }
        .slot { position: relative; }
        .slot input { position: absolute; opacity: 0; pointer-events: none; }
        .slot span { min-height: 42px; display: grid; place-items: center; border: 1px solid #dde3ea; border-radius: 6px; background: white; font-weight: 900; cursor: pointer; }
        .slot input:checked + span { background: #c0265a; border-color: #c0265a; color: white; }
        section { margin-bottom: 18px; }
        @media (max-width: 760px) {
            .nav { align-items: flex-start; flex-direction: column; padding: 14px 0; }
            .grid-2, .grid-3 { grid-template-columns: 1fr; }
            .item { align-items: flex-start; flex-direction: column; }
            .actions, .actions .btn { width: 100%; }
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="wrap nav">
            <a href="/"><img class="brand-logo" src="/logo.png" alt="Secretaria Virtual"></a>
            <div class="actions">
                <a class="btn" href="/particular">Buscar salon</a>
                <a class="btn" href="/login">Soy salon</a>
            </div>
        </div>
    </header>

    <main class="wrap">
        @yield('content')
    </main>
</body>
</html>
