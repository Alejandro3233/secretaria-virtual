<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="/favicon-grid.svg?v=1">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Secretary365')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    @if (request()->is('consola'))
        <script src="https://cdn.jsdelivr.net/npm/@twilio/voice-sdk@2.12.1/dist/twilio.min.js"></script>
    @endif
    <script>
        if (localStorage.getItem('sv-sidebar') === 'collapsed') {
            document.documentElement.classList.add('sidebar-collapsed');
        }
    </script>
    <style>
        :root {
            --ink: #181216;
            --muted: #70646b;
            --line: #eadfe5;
            --soft: #fbf7f9;
            --brand: #c0265a;
            --brand-dark: #8f1840;
            --green: #15803d;
            --blue: #1d4ed8;
            --amber: #b45309;
            --panel: #24151d;
            --white: #ffffff;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            grid-template-columns: 236px 1fr;
            font-family: Roboto, Arial, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", "Helvetica Neue", sans-serif;
            font-size: 14px;
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
            color: var(--ink);
            background: var(--soft);
        }
        html.sidebar-collapsed body { grid-template-columns: 66px 1fr; }
        a { color: inherit; text-decoration: none; }
        button { font: inherit; }
        .sidebar {
            position: relative;
            min-height: 100vh;
            background: var(--panel);
            color: #f7edf2;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            transition: padding .18s ease;
        }
        .sidebar-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 7px;
            margin-left: 4px;
            min-width: 0;
            color: #eadfe5;
        }
        .app-launcher {
            width: 30px;
            height: 30px;
            flex: 0 0 30px;
            display: grid;
            grid-template-columns: repeat(3, 4px);
            grid-template-rows: repeat(3, 4px);
            place-content: center;
            gap: 3px;
            border-radius: 6px;
            transition: background .16s ease;
        }
        .brand:hover .app-launcher { background: rgba(255,255,255,.1); }
        .app-launcher span {
            width: 4px;
            height: 4px;
            border-radius: 50%;
            background: currentColor;
            animation: launcherDotWave .72s ease-in-out 1 both;
        }
        .app-launcher span:nth-child(1) { animation-delay: .04s; }
        .app-launcher span:nth-child(2) { animation-delay: .09s; }
        .app-launcher span:nth-child(3) { animation-delay: .14s; }
        .app-launcher span:nth-child(4) { animation-delay: .09s; }
        .app-launcher span:nth-child(5) { animation-delay: .14s; }
        .app-launcher span:nth-child(6) { animation-delay: .19s; }
        .app-launcher span:nth-child(7) { animation-delay: .14s; }
        .app-launcher span:nth-child(8) { animation-delay: .19s; }
        .app-launcher span:nth-child(9) { animation-delay: .24s; }
        @keyframes launcherDotWave {
            0%, 100% { transform: translateY(0); }
            32% { transform: translateY(-4px); }
            68% { transform: translateY(3px); }
        }
        @media (prefers-reduced-motion: reduce) {
            .app-launcher span { animation: none; }
        }
        .brand-text {
            overflow: hidden;
            color: #eadfe5;
            font-size: 14px;
            font-weight: 800;
            letter-spacing: 0;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .mark {
            width: 30px;
            height: 30px;
            flex: 0 0 30px;
            border-radius: 8px;
            display: grid;
            place-items: center;
            background: var(--brand);
            color: white;
        }
        .sidebar-toggle {
            width: 30px;
            height: 30px;
            flex: 0 0 30px;
            display: grid;
            place-items: center;
            border: 1px solid rgba(255,255,255,.16);
            border-radius: 8px;
            background: transparent;
            color: #eadfe5;
            cursor: pointer;
        }
        .sidebar-toggle:hover { background: rgba(255,255,255,.1); color: white; }
        .nav { display: grid; gap: 5px; }
        .nav a {
            min-height: 38px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-radius: 6px;
            padding: 0 10px;
            color: #eadfe5;
            font-weight: 800;
        }
        .nav-label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .nav a.active,
        .nav a:hover { background: rgba(255,255,255,.1); color: white; }
        .nav-group { display: grid; gap: 6px; }
        .nav-sub {
            display: grid;
            gap: 6px;
            padding-left: 32px;
        }
        .nav-sub a {
            min-height: 32px;
            font-size: 13px;
            color: #d8c9d1;
        }
        .icon {
            width: 18px;
            height: 18px;
            flex: 0 0 18px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        .console-nav-link.call-live { background: rgba(192, 38, 90, .28); color: white; box-shadow: inset 0 0 0 1px rgba(249, 168, 212, .3); }
        .nav-console-phone { display: none; color: #f9a8d4; animation: sidebarCallRing 1.15s ease-in-out infinite; }
        .console-nav-link.call-live .nav-console-dashboard { display: none; }
        .console-nav-link.call-live .nav-console-phone { display: block; }
        .nav-call-live { display: none; align-items: center; gap: 5px; margin-left: auto; padding: 3px 6px; border-radius: 999px; background: var(--brand); color: white; font-size: 9px; font-weight: 900; letter-spacing: .04em; text-transform: uppercase; }
        .console-nav-link.call-live .nav-call-live { display: inline-flex; }
        .nav-call-live::before { content: ""; width: 5px; height: 5px; border-radius: 50%; background: white; animation: sidebarCallPulse 1.15s ease-in-out infinite; }
        @keyframes sidebarCallRing { 0%, 100% { transform: rotate(-7deg) scale(.96); } 50% { transform: rotate(7deg) scale(1.08); } }
        @keyframes sidebarCallPulse { 0%, 100% { opacity: .45; } 50% { opacity: 1; } }
        .logout {
            width: 100%;
            min-height: 36px;
            border: 0;
            border-radius: 6px;
            background: #111827;
            color: white;
            font-weight: 900;
            cursor: pointer;
        }
        .logout-label { white-space: nowrap; }
        .plan {
            margin-top: auto;
            border: 1px solid rgba(255,255,255,.16);
            border-radius: 8px;
            padding: 12px;
            background: rgba(255,255,255,.06);
        }
        .plan b { display: block; margin-bottom: 6px; }
        .plan span { color: #eadfe5; font-size: 12px; line-height: 1.35; }
        .progress {
            height: 9px;
            border-radius: 999px;
            background: rgba(255,255,255,.16);
            overflow: hidden;
            margin-top: 10px;
        }
        .bar { height: 100%; background: var(--brand); width: 62%; }
        html.sidebar-collapsed .sidebar { padding: 16px 10px; align-items: center; }
        html.sidebar-collapsed .sidebar-top { width: 100%; justify-content: center; }
        html.sidebar-collapsed .brand { justify-content: center; margin-left: 0; }
        html.sidebar-collapsed .app-launcher { width: 38px; height: 38px; flex-basis: 38px; }
        html.sidebar-collapsed .brand-text,
        html.sidebar-collapsed .nav-label,
        html.sidebar-collapsed .nav-sub,
        html.sidebar-collapsed .logout-label,
        html.sidebar-collapsed .plan { display: none; }
        html.sidebar-collapsed .sidebar-toggle { position: absolute; top: 16px; left: 46px; background: var(--panel); box-shadow: 0 8px 18px rgba(0,0,0,.18); }
        html.sidebar-collapsed .nav { width: 100%; }
        html.sidebar-collapsed .nav a { justify-content: center; padding: 0; }
        html.sidebar-collapsed .nav-group { width: 100%; }
        html.sidebar-collapsed .logout { width: 38px; padding: 0; }
        html.sidebar-collapsed .logout::before { content: "X"; font-size: 15px; }
        .main {
            min-width: 0;
            width: 100%;
            margin: 0;
        }
        .topbar {
            min-height: 58px;
            display: grid;
            grid-template-columns: minmax(180px, 1fr) minmax(220px, 288px) minmax(240px, 1fr);
            align-items: center;
            gap: 14px;
            padding: 0 22px;
            background: white;
            border-bottom: 1px solid var(--line);
        }
        .topbar-title { min-width: 0; }
        .topbar h1 { margin: 0; font-size: 22px; letter-spacing: 0; }
        .subtitle { color: var(--muted); }
        .topbar-search {
            min-height: 36px;
            display: grid;
            grid-template-columns: 20px 1fr;
            align-items: center;
            gap: 10px;
            border: 1px solid #b8c0d4;
            border-radius: 8px;
            padding: 0 12px;
            background: white;
        }
        .topbar-search:focus-within { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(29,78,216,.1); }
        .topbar-search svg { width: 18px; height: 18px; color: #344054; }
        .topbar-search input {
            width: 100%;
            min-height: 34px;
            border: 0;
            padding: 0;
            outline: 0;
            color: var(--ink);
            font-weight: 700;
        }
        .topbar-search input::placeholder { color: #7d8798; font-style: italic; }
        .topbar-right { display: flex; justify-content: flex-end; align-items: center; gap: 8px; min-width: 0; }
        .actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .topbar-icon-btn,
        .topbar-menu summary {
            position: relative;
            width: 34px;
            height: 34px;
            display: grid;
            place-items: center;
            border: 1px solid transparent;
            border-radius: 8px;
            background: white;
            color: #475467;
            cursor: pointer;
            list-style: none;
        }
        .topbar-icon-btn:hover,
        .topbar-menu summary:hover { border-color: var(--line); background: #fffafd; color: var(--ink); }
        .topbar-menu { position: relative; }
        .topbar-menu summary::-webkit-details-marker { display: none; }
        .topbar-dropdown {
            position: absolute;
            top: 40px;
            right: 0;
            z-index: 20;
            width: 260px;
            display: grid;
            gap: 6px;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 10px;
            background: white;
            box-shadow: 0 18px 40px rgba(24,18,22,.16);
        }
        .topbar-dropdown .dropdown-head { padding: 8px; border-bottom: 1px solid #f2eaf0; }
        .topbar-dropdown b,
        .topbar-dropdown span { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .topbar-dropdown span { margin-top: 3px; color: var(--muted); font-size: 13px; }
        .topbar-dropdown .dropdown-head span {
            white-space: normal;
            overflow-wrap: anywhere;
            line-height: 1.35;
        }
        .topbar-dropdown a,
        .topbar-dropdown button {
            width: 100%;
            min-height: 34px;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 0;
            border-radius: 6px;
            padding: 0 10px;
            background: white;
            color: var(--ink);
            font-weight: 800;
            text-align: left;
            cursor: pointer;
        }
        .topbar-dropdown a:hover,
        .topbar-dropdown button:hover { background: #fffafd; }
        .avatar {
            width: 30px;
            height: 30px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: #f3e8ff;
            color: var(--brand-dark);
            font-weight: 900;
        }
        .notification-dot {
            position: absolute;
            top: 7px;
            right: 7px;
            width: 8px;
            height: 8px;
            border: 2px solid white;
            border-radius: 50%;
            background: var(--brand);
        }
        .btn {
            min-height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            padding: 0 12px;
            border: 1px solid var(--line);
            background: white;
            font-weight: 800;
        }
        .btn.primary { background: #111827; color: white; border-color: #111827; }
        .btn.primary:hover { background: #000; border-color: #000; }
        .content { padding: 18px 22px 36px; }
        .card {
            background: white;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 14px;
        }
        .grid-2 { display: grid; grid-template-columns: 1.4fr .9fr; gap: 14px; align-items: start; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
        .grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
        h2 { margin: 0; font-size: 18px; letter-spacing: 0; }
        .section-title { display: flex; justify-content: space-between; gap: 10px; align-items: center; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px 8px; border-bottom: 1px solid #f2eaf0; text-align: left; vertical-align: top; }
        th { color: var(--muted); font-size: 11px; text-transform: uppercase; background: #fffafd; }
        tr:last-child td { border-bottom: 0; }
        .status { display: inline-flex; align-items: center; border-radius: 999px; padding: 5px 10px; font-size: 12px; font-weight: 900; }
        .integration-status-bar .item { align-items: flex-start; }
        .integration-status-bar .integration-status {
            flex: 0 0 auto;
            min-width: 86px;
            min-height: 28px;
            justify-content: center;
            padding: 6px 10px;
            border-radius: 6px;
            text-align: center;
            line-height: 1;
        }
        .ok { background: #dcfce7; color: var(--green); }
        .wait { background: #fef3c7; color: var(--amber); }
        .info { background: #dbeafe; color: var(--blue); }
        .danger { background: #fee2e2; color: #991b1b; }
        .cancelled-status { background: #e5e7eb; color: #374151; }
        input, select {
            width: 100%;
            border: 1px solid #dbcbd4;
            border-radius: 6px;
            min-height: 36px;
            padding: 0 10px;
            background: white;
        }
        label { display: block; color: var(--muted); font-weight: 800; margin-bottom: 7px; }
        .toolbar { display: grid; grid-template-columns: 1fr 170px 170px; gap: 10px; margin-bottom: 14px; }
        .metric-label { color: var(--muted); font-size: 14px; font-weight: 800; }
        .metric { font-size: 28px; font-weight: 900; margin-top: 6px; }
        .trend { margin-top: 8px; font-size: 13px; color: var(--muted); }
        .item { display: flex; justify-content: space-between; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f2eaf0; }
        .item:last-child { border-bottom: 0; padding-bottom: 0; }
        .item b { display: block; margin-bottom: 4px; }
        .item span { color: var(--muted); font-size: 14px; }
        .google-calendar-layout { display: grid; grid-template-columns: 230px minmax(0, 1fr); gap: 14px; align-items: start; }
        .google-calendar-sidebar { position: sticky; top: 14px; display: grid; gap: 16px; }
        .google-calendar-main { min-width: 0; max-width: 100%; overflow: hidden; }
        .calendar-create-btn { width: fit-content; min-height: 42px; display: inline-flex; align-items: center; gap: 10px; border: 1px solid var(--line); border-radius: 8px; padding: 0 16px 0 14px; background: white; box-shadow: 0 2px 7px rgba(24,18,22,.12); font-weight: 900; }
        .calendar-create-btn span { width: 24px; height: 24px; display: grid; place-items: center; color: var(--brand); font-size: 28px; font-weight: 500; line-height: 1; }
        .mini-calendar, .stylist-calendar-list { display: grid; gap: 9px; }
        .mini-calendar-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        .mini-calendar-head b { font-size: 15px; }
        .mini-calendar-head div { display: flex; gap: 6px; }
        .mini-calendar-head a { width: 28px; height: 28px; display: grid; place-items: center; border-radius: 50%; color: var(--muted); font-size: 18px; font-weight: 900; }
        .mini-calendar-head a:hover { background: #f3eef2; color: var(--ink); }
        .mini-calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; align-items: center; }
        .mini-calendar-grid span, .mini-calendar-grid a { height: 24px; display: grid; place-items: center; border-radius: 50%; font-size: 11px; font-weight: 900; }
        .mini-calendar-grid a:hover { background: #f3eef2; }
        .mini-weekday { color: var(--muted); }
        .mini-calendar-grid a.is-muted { color: #a99da4; }
        .mini-calendar-grid a.is-selected-week { background: #f3eef2; border-radius: 6px; }
        .mini-calendar-grid a.is-today { background: #1a73e8; color: white; border-radius: 50%; }
        .sidebar-section-title { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 2px; }
        .sidebar-section-title b { font-size: 14px; }
        .sidebar-section-title span { color: var(--muted); font-size: 12px; font-weight: 900; }
        .stylist-filter { display: grid; grid-template-columns: 16px 1fr; gap: 8px; align-items: center; margin: 0; color: var(--ink); cursor: pointer; }
        .stylist-filter input { position: absolute; opacity: 0; pointer-events: none; }
        .stylist-filter > span:last-child { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 700; }
        .stylist-check { width: 16px; height: 16px; display: grid; place-items: center; border: 2px solid #8ab4f8; border-radius: 3px; }
        .stylist-filter input:checked + .stylist-check::after { content: ""; width: 9px; height: 5px; border-left: 2px solid white; border-bottom: 2px solid white; transform: rotate(-45deg) translate(1px, -1px); }
        .stylist-filter input:checked + .stylist-check { background: #1a73e8; border-color: #1a73e8; }
        .stylist-check.stylist-color-2 { border-color: #16a34a; }
        .stylist-filter input:checked + .stylist-color-2 { background: #16a34a; border-color: #16a34a; }
        .stylist-check.stylist-color-3 { border-color: #f59e0b; }
        .stylist-filter input:checked + .stylist-color-3 { background: #f59e0b; border-color: #f59e0b; }
        .stylist-check.stylist-color-4 { border-color: #c0265a; }
        .stylist-filter input:checked + .stylist-color-4 { background: #c0265a; border-color: #c0265a; }
        .stylist-check.stylist-color-5 { border-color: #7c3aed; }
        .stylist-filter input:checked + .stylist-color-5 { background: #7c3aed; border-color: #7c3aed; }
        .stylist-check.stylist-color-6 { border-color: #0891b2; }
        .stylist-filter input:checked + .stylist-color-6 { background: #0891b2; border-color: #0891b2; }
        .calendar-sidebar-empty { color: var(--muted); font-size: 14px; }
        .calendar-shell { max-width: 100%; border: 1px solid var(--line); border-radius: 8px; background: white; overflow-x: auto; overflow-y: hidden; }
        .calendar-toolbar { min-height: 56px; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 14px; border-bottom: 1px solid var(--line); }
        .calendar-kicker { margin-bottom: 3px; color: var(--muted); font-size: 12px; font-weight: 900; text-transform: uppercase; }
        .calendar-view-tabs { display: inline-flex; align-items: center; border: 1px solid var(--line); border-radius: 6px; overflow: hidden; background: #fffafd; }
        .calendar-view-tabs a { min-width: 62px; padding: 7px 10px; border-right: 1px solid var(--line); color: var(--muted); font-size: 12px; font-weight: 900; text-align: center; }
        .calendar-view-tabs a:last-child { border-right: 0; }
        .calendar-view-tabs .active { background: #111827; color: white; }
        .calendar-empty { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 9px 14px; border-bottom: 1px solid var(--line); background: #f8fbff; }
        .calendar-empty b, .calendar-empty span { display: block; }
        .calendar-empty span { margin-top: 2px; color: var(--muted); font-size: 13px; }
        .calendar-week { min-width: 820px; display: grid; grid-template-columns: 62px repeat(7, minmax(104px, 1fr)); }
        .calendar-timezone { min-height: 58px; display: flex; align-items: end; justify-content: center; padding: 0 6px 9px; border-right: 1px solid var(--line); border-bottom: 1px solid var(--line); color: var(--muted); font-size: 10px; font-weight: 800; }
        .calendar-day-head { min-height: 58px; display: grid; place-items: center; align-content: center; gap: 3px; border-right: 1px solid var(--line); border-bottom: 1px solid var(--line); color: var(--muted); }
        .calendar-day-head:last-of-type { border-right: 0; }
        .calendar-day-head span { font-size: 11px; font-weight: 900; text-transform: uppercase; }
        .calendar-day-head b { width: 28px; height: 28px; display: grid; place-items: center; border-radius: 50%; color: var(--ink); font-size: 16px; }
        .calendar-day-head small { max-width: 100%; overflow: hidden; color: var(--muted); font-size: 11px; font-weight: 800; text-overflow: ellipsis; white-space: nowrap; }
        .calendar-day-head.is-today b { background: var(--brand); color: white; }
        .calendar-time-gutter { position: relative; border-right: 1px solid var(--line); background: white; }
        .calendar-hour-label { position: absolute; right: 8px; transform: translateY(-8px); color: var(--muted); font-size: 10px; font-weight: 800; white-space: nowrap; }
        .calendar-day-column { position: relative; border-right: 1px solid var(--line); background: white; }
        .calendar-day-column:last-child { border-right: 0; }
        .calendar-hour-line { position: absolute; left: 0; right: 0; height: 1px; background: #edf0f5; }
        .calendar-quarter-line { position: absolute; left: 0; right: 0; height: 1px; background: #f4f6fa; }
        .calendar-quarter-line.is-half { background: #eef2f7; }
        .calendar-event { position: absolute; left: 5px; right: 5px; z-index: 1; display: grid; align-content: start; gap: 2px; overflow: hidden; border-left: 3px solid var(--brand); border-radius: 6px; padding: 6px 7px; background: #fff1f6; color: var(--ink); font-size: 11px; line-height: 1.2; box-shadow: 0 1px 2px rgba(24,18,22,.08); }
        .calendar-event:hover { cursor: grab; filter: brightness(.98); }
        .calendar-event.is-dragging { opacity: .62; cursor: grabbing; }
        .calendar-event.is-saving { opacity: .74; pointer-events: none; }
        .calendar-event b, .calendar-event span, .calendar-event small { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .calendar-event b { font-size: 12px; }
        .calendar-event span { color: #4f3743; font-weight: 800; }
        .calendar-event small { color: var(--muted); font-size: 10px; }
        .calendar-week:not(.calendar-day-view) .calendar-event.is-compact { gap: 1px; padding: 4px 6px; line-height: 1.05; }
        .calendar-week:not(.calendar-day-view) .calendar-event.is-compact b { font-size: 11px; }
        .calendar-week:not(.calendar-day-view) .calendar-event.is-compact span { font-size: 10px; }
        .calendar-week:not(.calendar-day-view) .calendar-event.is-compact small { display: none; }
        .calendar-day-view .calendar-event.is-compact { gap: 1px; padding: 4px 28px 4px 7px; line-height: 1.05; }
        .calendar-day-view .calendar-event.is-compact b { font-size: 11px; }
        .calendar-day-view .calendar-event.is-compact span { font-size: 10px; }
        .calendar-day-view .calendar-event.is-compact small { display: none; }
        .calendar-day-view .calendar-event.is-very-compact { display: flex; align-items: center; gap: 8px; }
        .calendar-day-view .calendar-event.is-very-compact b,
        .calendar-day-view .calendar-event.is-very-compact span { flex: 0 1 auto; max-width: 46%; }
        .calendar-event.is-micro { display: flex; align-items: center; gap: 7px; padding: 1px 27px 1px 6px; line-height: 1; }
        .calendar-event.is-micro b,
        .calendar-event.is-micro span { flex: 0 1 auto; max-width: 48%; font-size: 9px; }
        .calendar-event.is-micro small { display: none; }
        .calendar-event.is-micro > .appointment-delivery,
        .calendar-event.is-micro > .appointment-cancel-mark { right: 6px; bottom: 1px; font-size: 10px; }
        .calendar-event.from-google { border-left-color: #1a73e8; background: #e8f0fe; }
        .calendar-event.from-twilio { border-left-color: var(--green); background: #ecfdf3; }
        .calendar-event.from-web { border-left-color: var(--amber); background: #fff7ed; }
        .calendar-event.appointment-confirmed,
        .calendar-month-day .calendar-month-event.appointment-confirmed { border-color: #15803d; background: #dcfce7; color: #14532d; }
        .calendar-event.appointment-cancelled,
        .calendar-month-day .calendar-month-event.appointment-cancelled { border-color: #374151; background: #e5e7eb; color: #374151; text-decoration: line-through; opacity: .86; }
        .calendar-event.appointment-pending,
        .calendar-month-day .calendar-month-event.appointment-pending { border-color: #ca8a04; background: #fef9c3; color: #713f12; }
        .calendar-event.appointment-urgent-light,
        .calendar-month-day .calendar-month-event.appointment-urgent-light { border-color: #f87171; background: #fee2e2; color: #991b1b; }
        .calendar-event.appointment-urgent-medium,
        .calendar-month-day .calendar-month-event.appointment-urgent-medium { border-color: #dc2626; background: #fca5a5; color: #7f1d1d; }
        .calendar-event.appointment-urgent-high,
        .calendar-month-day .calendar-month-event.appointment-urgent-high { border-color: #7f1d1d; background: #b91c1c; color: white; }
        .calendar-event.appointment-confirmed span,
        .calendar-event.appointment-confirmed small,
        .calendar-event.appointment-pending span,
        .calendar-event.appointment-pending small,
        .calendar-event.appointment-urgent-light span,
        .calendar-event.appointment-urgent-light small,
        .calendar-event.appointment-urgent-medium span,
        .calendar-event.appointment-urgent-medium small { color: inherit; }
        .calendar-event.appointment-urgent-high span,
        .calendar-event.appointment-urgent-high small { color: white; }
        .calendar-week:not(.calendar-day-view) .calendar-event.is-past { border-left-color: #111827; background: #fff; color: #374151; }
        .calendar-week:not(.calendar-day-view) .calendar-event.is-past span,
        .calendar-week:not(.calendar-day-view) .calendar-event.is-past small { color: #4b5563; }
        .calendar-month-day .calendar-month-event.is-past { background: #fff; color: #4b5563; }
        .calendar-month-event { display: block; border-left: 3px solid transparent; border-radius: 4px; padding: 3px 5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .appointment-delivery { margin-left: auto; font-style: normal; font-size: 12px; font-weight: 900; letter-spacing: -3px; }
        .calendar-event > .appointment-delivery,
        .calendar-event > .appointment-cancel-mark { position: absolute; right: 7px; bottom: 5px; margin: 0; }
        .appointment-delivery.sent { color: #2563eb; }
        .appointment-delivery.responded { color: #15803d; }
        .status .appointment-delivery { margin-left: 7px; }
        .appointment-cancel-mark { margin-left: auto; color: #374151; font-style: normal; font-size: 15px; font-weight: 900; line-height: 1; text-decoration: none; }
        .status .appointment-cancel-mark { margin-left: 7px; }
        .calendar-month-event .appointment-cancel-mark { float: right; margin-left: 5px; }
        .appointment-google-badge { width: 16px; height: 16px; display: inline-block; margin-right: 4px; object-fit: contain; vertical-align: middle; }
        .calendar-event.appointment-urgent-high .appointment-delivery { color: white; }
        .calendar-month-event .appointment-delivery { float: right; margin-left: 5px; }
        .calendar-day-column.is-drag-over { background: #f8fbff; box-shadow: inset 0 0 0 2px rgba(26,115,232,.2); }
        .calendar-drop-preview { position: absolute; left: 5px; right: 5px; z-index: 2; display: flex; align-items: flex-start; justify-content: flex-end; border: 1px dashed #1a73e8; border-left: 3px solid #1a73e8; border-radius: 6px; padding: 4px 6px; background: rgba(232, 240, 254, .78); color: #174ea6; font-size: 11px; font-weight: 900; pointer-events: none; box-shadow: 0 4px 12px rgba(26,115,232,.12); }
        .calendar-drop-preview[hidden] { display: none; }
        .calendar-drag-status { margin: 10px 14px 14px; border-radius: 6px; padding: 9px 11px; background: #dbeafe; color: var(--blue); font-size: 13px; font-weight: 900; }
        .calendar-drag-status[hidden] { display: none; }
        .calendar-drag-status[data-type="ok"] { background: #dcfce7; color: var(--green); }
        .calendar-drag-status[data-type="danger"] { background: #fee2e2; color: #991b1b; }
        .calendar-day-view { min-width: 700px; }
        .calendar-month { display: grid; grid-template-columns: repeat(7, minmax(96px, 1fr)); background: white; }
        .calendar-month-weekday { min-height: 32px; display: flex; align-items: center; padding: 0 8px; border-right: 1px solid var(--line); border-bottom: 1px solid var(--line); color: var(--muted); font-size: 11px; font-weight: 900; text-transform: uppercase; }
        .calendar-month-weekday:nth-child(7n) { border-right: 0; }
        .calendar-month-day { min-height: 96px; display: grid; align-content: start; gap: 4px; padding: 7px; border-right: 1px solid var(--line); border-bottom: 1px solid var(--line); color: var(--ink); }
        .calendar-month-day:nth-child(7n) { border-right: 0; }
        .calendar-month-day b { width: 22px; height: 22px; display: grid; place-items: center; border-radius: 50%; font-size: 12px; }
        .calendar-month-day.is-muted { background: #fcfafb; color: #a99da4; }
        .calendar-month-day.is-today b { background: var(--brand); color: white; }
        .calendar-month-day span { overflow: hidden; border-radius: 4px; padding: 2px 5px; background: #e8f0fe; color: #174ea6; font-size: 11px; font-weight: 800; text-overflow: ellipsis; white-space: nowrap; }
        .calendar-month-day small { color: var(--muted); font-size: 11px; font-weight: 900; }
        @media (max-width: 980px) {
            body, html.sidebar-collapsed body { grid-template-columns: 1fr; }
            .sidebar { min-height: auto; }
            html.sidebar-collapsed .sidebar { align-items: stretch; }
            html.sidebar-collapsed .brand-text,
            html.sidebar-collapsed .nav-label,
            html.sidebar-collapsed .logout-label { display: inline; }
            html.sidebar-collapsed .nav-sub,
            html.sidebar-collapsed .plan { display: grid; }
            html.sidebar-collapsed .sidebar-toggle { position: static; box-shadow: none; }
            html.sidebar-collapsed .nav a { justify-content: flex-start; padding: 0 12px; }
            html.sidebar-collapsed .logout { width: 100%; }
            html.sidebar-collapsed .logout::before { content: none; }
            .nav { grid-template-columns: repeat(2, 1fr); }
            .plan { margin-top: 0; }
            .grid-2, .grid-3, .grid-4, .toolbar { grid-template-columns: 1fr; }
            .google-calendar-layout { grid-template-columns: 1fr; }
            .google-calendar-sidebar { position: static; }
            .topbar { grid-template-columns: 1fr; align-items: stretch; padding: 16px; }
            .topbar-right { justify-content: flex-start; }
            .content { padding: 16px; }
            .calendar-toolbar, .calendar-empty { align-items: flex-start; flex-direction: column; }
            .calendar-week { min-width: 760px; }
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-top">
            <a class="brand" href="/consola" title="Secretary365">
                <span class="app-launcher" aria-hidden="true">
                    <span></span><span></span><span></span>
                    <span></span><span></span><span></span>
                    <span></span><span></span><span></span>
                </span>
                <span class="brand-text">Secretary 365</span>
            </a>
            <button class="sidebar-toggle" type="button" aria-label="Cerrar menu" aria-expanded="true" title="Abrir o cerrar menu">
                <svg class="icon" viewBox="0 0 24 24"><rect x="4" y="5" width="16" height="14" rx="3"/><path d="M10 5v14"/></svg>
            </button>
        </div>

        <nav class="nav" aria-label="Navegacion principal">
            <a class="{{ request()->is('agenda') ? 'active' : '' }}" href="/agenda" title="Agenda">
                <svg class="icon" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                <span class="nav-label">Agenda</span>
            </a>
            <a class="{{ request()->is('citas') ? 'active' : '' }}" href="/citas" title="Citas">
                <svg class="icon" viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                <span class="nav-label">Citas</span>
            </a>
            <a class="{{ request()->is('clientes*') ? 'active' : '' }}" href="/clientes" title="Clientes">
                <svg class="icon" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><path d="M20 8v6M23 11h-6"/></svg>
                <span class="nav-label">Clientes</span>
            </a>
            <a class="{{ request()->is('campanas*') ? 'active' : '' }}" href="/campanas" title="Campañas">
                <svg class="icon" viewBox="0 0 24 24"><path d="M3 11v2a2 2 0 0 0 2 2h2l4 4V5L7 9H5a2 2 0 0 0-2 2z"/><path d="M15 9a4 4 0 0 1 0 6"/><path d="M18 6a8 8 0 0 1 0 12"/></svg>
                <span class="nav-label">Campañas</span>
            </a>
            <a class="{{ request()->is('personal') ? 'active' : '' }}" href="/personal" title="Personal">
                <svg class="icon" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span class="nav-label">Personal</span>
            </a>
            <a class="{{ request()->is('personal/servicios') ? 'active' : '' }}" href="/personal/servicios" title="Servicios">
                <svg class="icon" viewBox="0 0 24 24"><path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/><circle cx="8" cy="7" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="16" cy="17" r="1"/></svg>
                <span class="nav-label">Servicios</span>
            </a>
            <a class="console-nav-link {{ request()->is('consola') || request()->is('dashboard') ? 'active' : '' }}" href="/consola" title="Consola" data-console-call-indicator>
                <svg class="icon nav-console-dashboard" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
                <svg class="icon nav-console-phone" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.69 2.8a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.33 1.84.56 2.8.69A2 2 0 0 1 22 16.92z"/></svg>
                <span class="nav-label">Consola</span>
                <span class="nav-call-live nav-label">En vivo</span>
            </a>
            <a class="{{ request()->is('manager*') ? 'active' : '' }}" href="/manager" title="Manager">
                <svg class="icon" viewBox="0 0 24 24"><path d="M4 19V9"/><path d="M10 19V5"/><path d="M16 19v-7"/><path d="M22 19V3"/><path d="M2 19h22"/></svg>
                <span class="nav-label">Manager</span>
            </a>
            <a class="{{ request()->is('ajustes') || request()->is('facturacion*') ? 'active' : '' }}" href="/ajustes" title="Ajustes">
                <svg class="icon" viewBox="0 0 24 24"><path d="M12 15.5A3.5 3.5 0 1 0 12 8a3.5 3.5 0 0 0 0 7.5z"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.1V21a2 2 0 1 1-4 0v-.1A1.7 1.7 0 0 0 8 19.4a1.7 1.7 0 0 0-1.88.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1.1-.4H3a2 2 0 1 1 0-4h.1A1.7 1.7 0 0 0 4.6 8a1.7 1.7 0 0 0-.34-1.88l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1.1V3a2 2 0 1 1 4 0v.1A1.7 1.7 0 0 0 16 4.6a1.7 1.7 0 0 0 1.88-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.7 1.7 0 0 0 19.4 9c.2.38.52.7.9.9.34.18.72.28 1.1.28h.1a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-2 1z"/></svg>
                <span class="nav-label">Ajustes</span>
            </a>
            @if (auth()->user()?->is_super_admin)
                <a class="{{ request()->is('costos-salones*') ? 'active' : '' }}" href="/costos-salones" title="Costos por salon">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M12 2v20"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7H14a3.5 3.5 0 0 1 0 7H6"/><path d="M4 19h16"/></svg>
                    <span class="nav-label">Costos salones</span>
                </a>
                <a class="{{ request()->is('gestion-usuarios*') ? 'active' : '' }}" href="/gestion-usuarios" title="Gestion de usuarios">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>
                    <span class="nav-label">Gestion de usuarios</span>
                </a>
                <a class="{{ request()->is('base-de-datos*') ? 'active' : '' }}" href="/base-de-datos" title="Base de datos">
                    <svg class="icon" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/></svg>
                    <span class="nav-label">Base de datos</span>
                </a>
            @endif
        </nav>

        @php
            $sidebarClinic = auth()->user()?->primaryClinic();
            $sidebarPlan = $sidebarClinic?->plan;
            $sidebarPlanActive = in_array($sidebarClinic?->subscription_status, ['active', 'trial'], true);
        @endphp
        <a class="plan" href="/facturacion" title="Ver facturacion">
            <b>{{ $sidebarPlan ? 'Plan '.$sidebarPlan->name : 'Plan pendiente' }}</b>
            <span>{{ $sidebarPlan ? $sidebarPlan->sidebarDescription() : 'Elige un plan para activar facturacion y limites del salon.' }}</span>
            <div class="progress"><div class="bar" style="width: {{ $sidebarPlanActive ? '100%' : '18%' }};"></div></div>
        </a>
    </aside>

    <div class="main">
        @php
            $topbarUser = auth()->user();
            $topbarName = $topbarUser?->name ?? 'Usuario';
            $topbarInitial = strtoupper(substr($topbarName, 0, 1));
        @endphp
        <header class="topbar">
            <div class="topbar-title"></div>

            <form class="topbar-search" method="GET" action="/buscar" role="search">
                <svg class="icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
                <input name="q" value="{{ request('q') }}" placeholder="Buscar citas, clientes, servicios...">
            </form>

            <div class="topbar-right">
                <div class="actions">
                    @yield('page_actions')
                </div>

                <details class="topbar-menu">
                    <summary title="Notificaciones">
                        <svg class="icon" viewBox="0 0 24 24"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg>
                        <span class="notification-dot" aria-hidden="true"></span>
                    </summary>
                    <div class="topbar-dropdown">
                        <div class="dropdown-head">
                            <b>Notificaciones</b>
                            <span>Centro para avisos de clientes, citas y sincronizaciones.</span>
                        </div>
                        <a href="/citas">Ver citas pendientes</a>
                        <a href="/agenda">Abrir agenda</a>
                        <a href="/ajustes">Preferencias de avisos</a>
                    </div>
                </details>

                <details class="topbar-menu">
                    <summary title="{{ $topbarName }}">
                        <span class="avatar">{{ $topbarInitial }}</span>
                    </summary>
                    <div class="topbar-dropdown">
                        <div class="dropdown-head">
                            <b>{{ $topbarName }}</b>
                            <span>{{ $topbarUser?->email }}</span>
                        </div>
                        <a href="/ajustes">Ajustes de cuenta</a>
                        <a href="/google-calendar/connect">Google Calendar</a>
                        <a href="{{ route('activity-export') }}">Descargar informe Excel</a>
                        @if ($topbarUser?->is_super_admin)
                            <a href="/gestion-usuarios">Gestion de usuarios</a>
                            <a href="/base-de-datos">Base de datos</a>
                        @endif
                        <form method="POST" action="/logout">
                            @csrf
                            <button type="submit">Cerrar sesion</button>
                        </form>
                    </div>
                </details>
            </div>
        </header>

        <main class="content">
            @yield('content')
        </main>
    </div>
    <script>
        const sidebarToggle = document.querySelector('.sidebar-toggle');
        const setSidebarState = (collapsed) => {
            document.documentElement.classList.toggle('sidebar-collapsed', collapsed);
            sidebarToggle?.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            sidebarToggle?.setAttribute('aria-label', collapsed ? 'Abrir menu' : 'Cerrar menu');
            localStorage.setItem('sv-sidebar', collapsed ? 'collapsed' : 'expanded');
        };

        setSidebarState(localStorage.getItem('sv-sidebar') === 'collapsed');
        sidebarToggle?.addEventListener('click', () => {
            setSidebarState(! document.documentElement.classList.contains('sidebar-collapsed'));
        });

        @auth
        const svNoraReminderDueUrl = @json(route('nora-reminders.due'));
        const svNoraReminderRequest = async () => {
            const response = await fetch(svNoraReminderDueUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    Accept: 'application/json',
                },
                cache: 'no-store',
            });

            if (! response.ok) return { reminders: [] };

            return response.json();
        };

        const svSpeakNoraReminder = (message) => {
            if (! ('speechSynthesis' in window)) return;

            window.speechSynthesis.cancel();
            const utterance = new SpeechSynthesisUtterance(message);
            utterance.lang = 'es-ES';
            const voices = window.speechSynthesis?.getVoices?.() || [];
            const spanishVoices = voices.filter((voice) => /^es([-_]|$)/i.test(voice.lang || ''));
            const femaleNames = ['helena', 'monica', 'paulina', 'sabina', 'luciana', 'elvira', 'google espanol', 'laura', 'maria', 'paula', 'zira', 'aria', 'jenny', 'susan', 'samantha', 'victoria', 'karen', 'moira', 'fiona', 'lucia', 'sofia', 'soledad', 'paloma', 'camila', 'mia', 'lupe'];
            const maleNames = ['jorge', 'diego', 'pablo', 'miguel', 'carlos', 'raul', 'juan', 'alvaro', 'enrique', 'david', 'mark', 'paul', 'daniel', 'alex'];
            const hasName = (voice, names) => names.some((name) => voice.name.toLowerCase().includes(name));
            const preferredVoice = spanishVoices.find((candidate) => hasName(candidate, femaleNames));
            const anyLanguagePreferredVoice = voices.find((candidate) => hasName(candidate, femaleNames));
            const neutralVoice = spanishVoices.find((candidate) => ! hasName(candidate, maleNames));
            const anyLanguageNeutralVoice = voices.find((candidate) => ! hasName(candidate, maleNames));
            const voice = preferredVoice
                || anyLanguagePreferredVoice
                || neutralVoice
                || anyLanguageNeutralVoice
                || spanishVoices[0]
                || voices[0]
                || null;
            utterance.rate = 0.99;
            utterance.pitch = 1.18;
            if (voice) utterance.voice = voice;
            window.speechSynthesis.speak(utterance);
        };

        const svPlayNoraReminderTone = () => {
            const AudioContextApi = window.AudioContext || window.webkitAudioContext;
            if (! AudioContextApi) return;

            try {
                const context = new AudioContextApi();
                const gain = context.createGain();
                gain.gain.setValueAtTime(0.0001, context.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.18, context.currentTime + 0.03);
                gain.gain.exponentialRampToValueAtTime(0.0001, context.currentTime + 1.15);
                gain.connect(context.destination);

                [0, 0.24, 0.48].forEach((offset) => {
                    const oscillator = context.createOscillator();
                    oscillator.type = 'sine';
                    oscillator.frequency.setValueAtTime(880, context.currentTime + offset);
                    oscillator.connect(gain);
                    oscillator.start(context.currentTime + offset);
                    oscillator.stop(context.currentTime + offset + 0.15);
                });

                window.setTimeout(() => context.close(), 1400);
            } catch (error) {
                // Algunos navegadores bloquean audio hasta que el usuario interactua con la pagina.
            }
        };

        const svCheckNoraReminders = async () => {
            try {
                const payload = await svNoraReminderRequest();
                (payload.reminders || []).forEach((reminder) => {
                    const message = 'Recordatorio: '.concat(reminder.message || 'tu recordatorio', '.');
                    svPlayNoraReminderTone();
                    svSpeakNoraReminder(message);
                    window.setTimeout(() => window.alert(message), 450);
                });
            } catch (error) {
                // El backend conserva el recordatorio para el siguiente intento.
            }
        };

        svCheckNoraReminders();
        window.setInterval(svCheckNoraReminders, 30000);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') svCheckNoraReminders();
        });

        (() => {
            if (document.querySelector('[data-nora-listening]')) return;

            const preferenceKey = 'secretary365-nora-listening';
            const helpKey = 'secretary365-nora-help-shown-date';
            const SpeechRecognitionApi = window.SpeechRecognition || window.webkitSpeechRecognition;
            const routes = {
                reminders: {
                    index: @json(route('nora-reminders.index')),
                    store: @json(route('nora-reminders.store')),
                    cancel: @json(route('nora-reminders.cancel')),
                    due: @json(route('nora-reminders.due')),
                },
                clientCall: @json(route('nora-client-calls.store')),
                clientAppointmentReminder: @json(route('nora-client-appointment-reminders.store')),
                clientNote: @json(route('nora-client-notes.store')),
                nextAppointmentDelay: @json(route('nora-next-appointment-delay.store')),
                staffVacations: @json(route('staff.vacations.index')),
                paymentsToday: @json(route('nora-payments.today')),
            };

            if (! SpeechRecognitionApi) return;

            let enabled = window.localStorage.getItem(preferenceKey) === '1';
            let recognition = null;
            let recognitionRunning = false;
            let speaking = false;
            let restartTimer = null;
            let awaitingCommandUntil = 0;

            const normalizedVoiceText = (text) => text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
            const noraWakeWordMatch = (transcript) => transcript.match(/\b(?:n+\s*o+\s*r+\s*a+|n+\s*o+\s*h+\s*r+\s*a+|n+\s*o+\s*u+\s*r+\s*a+|n+\s*o+\s*l+\s*a+|l+\s*o+\s*r+\s*a+|h+\s*o+\s*r+\s*a+|m+\s*i+\s*a+|m+\s*i+\s*j+\s*a+|m+\s*i+\s*l+\s*a+)\b/);
            const request = async (url, options = {}) => {
                const response = await fetch(url, {
                    method: options.method || 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        Accept: 'application/json',
                    },
                    body: options.body ? JSON.stringify(options.body) : undefined,
                    cache: 'no-store',
                });

                if (! response.ok) {
                    const error = new Error('No se pudo completar la solicitud.');
                    try { error.payload = await response.json(); } catch (parseError) {}
                    throw error;
                }

                return response.json();
            };

            const stop = () => {
                window.clearTimeout(restartTimer);
                if (! recognition || ! recognitionRunning) return;
                try { recognition.abort(); } catch (error) {}
            };

            const start = () => {
                if (! enabled || speaking || ! recognition || recognitionRunning || document.visibilityState !== 'visible') return;
                try { recognition.start(); } catch (error) {
                    restartTimer = window.setTimeout(start, 900);
                }
            };

            const speak = async (message) => {
                if (! message || ! ('speechSynthesis' in window)) return;

                speaking = true;
                stop();
                window.speechSynthesis.cancel();
                const utterance = new SpeechSynthesisUtterance(message);
                utterance.lang = 'es-ES';
                utterance.rate = 0.99;
                utterance.pitch = 1.18;
                const voices = window.speechSynthesis?.getVoices?.() || [];
                const spanishVoices = voices.filter((voice) => /^es([-_]|$)/i.test(voice.lang || ''));
                utterance.voice = spanishVoices.find((voice) => /female|helena|monica|paulina|sabina|luciana|elvira|laura|maria|paula|lucia|sofia|camila|mia/i.test(voice.name || ''))
                    || spanishVoices[0]
                    || voices[0]
                    || null;
                utterance.onend = () => {
                    speaking = false;
                    restartTimer = window.setTimeout(start, 500);
                };
                utterance.onerror = utterance.onend;
                window.speechSynthesis.speak(utterance);
            };

            const parseSpokenNumber = (value) => {
                if (/^\d{1,3}$/.test(value)) return Number(value);
                const numbers = {
                    un: 1, una: 1, uno: 1, dos: 2, tres: 3, cuatro: 4, cinco: 5,
                    seis: 6, siete: 7, ocho: 8, nueve: 9, diez: 10, once: 11,
                    doce: 12, trece: 13, catorce: 14, quince: 15, veinte: 20,
                    treinta: 30, cuarenta: 40, cincuenta: 50, sesenta: 60,
                };
                return numbers[value.replace(/\s+y\s+/g, ' ')] || 0;
            };

            const parseRelativeReminderTime = (command) => {
                const timeMatch = command.match(/\b(?:dentro de|en)\s+([a-z0-9]+)\s*(minutos|minuto|mins|min|horas|hora|segundos|segundo)\b/)
                    || command.match(/\b([a-z0-9]+)\s*(minutos|minuto|mins|min|horas|hora|segundos|segundo)\b/);
                if (! timeMatch) return null;

                const amount = parseSpokenNumber(timeMatch[1]);
                const unit = timeMatch[2];
                const unitMs = unit.startsWith('hora') ? 60 * 60 * 1000 : (unit.startsWith('segundo') ? 1000 : 60 * 1000);
                const delayMs = amount * unitMs;
                if (! amount || delayMs < 1000 || delayMs > 24 * 60 * 60 * 1000) return { error: 'range' };

                return { dueAt: Date.now() + delayMs, phrase: timeMatch[0] };
            };

            const reminderLabel = (reminder) => reminder.message && reminder.message !== 'recordatorio' ? reminder.message : 'tu recordatorio';
            const reminderDueLabel = (dueAt) => {
                const reminderDate = new Date(dueAt);
                const time = reminderDate.toLocaleTimeString('es-ES', { hour: 'numeric', minute: '2-digit' });
                return 'hoy a las '.concat(time);
            };

            const parseReminderCommand = (command) => {
                const wantsReminder = /\b(recuerdame|recuerdalo|avisame|avisa|alerta|alarma|recordatorio)\b/.test(command);
                if (! wantsReminder) return null;

                const time = parseRelativeReminderTime(command);
                if (! time) return { error: 'time' };
                if (time.error) return time;

                const message = command
                    .replace(/\b(dame|pon|crea|programa|un|una|el|la|por favor|recordatorio|recuerdame|recuerdalo|avisame|avisa|alerta|alarma)\b/g, ' ')
                    .replace(time.phrase, ' ')
                    .replace(/\s+/g, ' ')
                    .trim()
                    .replace(/^(en|de|que|para)\s+/, '')
                    .trim();

                return { dueAt: time.dueAt, message: message || 'recordatorio', timePhrase: time.phrase };
            };

            const parseClientCallCommand = (command) => {
                const match = command.match(/\b(?:llama|llamar|llamale|marca|marcar)\s+(?:a\s+)?(.+)$/);
                if (! match) return null;
                const name = match[1].replace(/\b(?:por telefono|al telefono|ahora|por favor|cliente|clienta)\b/g, ' ').replace(/\s+/g, ' ').trim();
                return name.length >= 2 ? { name } : { error: 'name' };
            };

            const parseClientAppointmentReminderCommand = (command) => {
                const match = command.match(/\b(?:recuerdale|recordale|recuerdales|recordales|avisa|avisale|avisales)\s+(?:la\s+|su\s+)?cita\s+(?:a\s+)?(.+)$/)
                    || command.match(/\b(?:recuerda|recordatorio)\s+(?:la\s+|su\s+)?cita\s+(?:a\s+)?(.+)$/);
                if (! match) return null;
                const name = match[1].replace(/\b(?:por telefono|al telefono|ahora|por favor|cliente|clienta)\b/g, ' ').replace(/\s+/g, ' ').trim();
                return name.length >= 2 ? { name } : { error: 'name' };
            };

            const parseStaffVacationCommand = (command) => {
                const match = command.match(/\b(?:dime|lee|consulta|ver|muestrame|cuales son)?\s*(?:las\s+)?vacaciones\s+(?:de\s+)?(.+)$/)
                    || command.match(/\bcuando\s+(?:sale|esta|estara)\s+(.+?)\s+(?:de\s+)?vacaciones\b/);
                if (! match) return null;
                const name = match[1].replace(/\b(?:por favor|especialista|estilista|empleado|empleada)\b/g, ' ').replace(/\s+/g, ' ').trim();
                return name.length >= 2 ? { name } : { error: 'name' };
            };

            const parseDelayMinutes = (command) => {
                if (/\bmedia\s+hora\b/.test(command)) return 30;
                if (/\b(?:un\s+)?cuarto\s+(?:de\s+)?hora\b/.test(command)) return 15;
                if (/\b(?:una|un)\s+hora\b/.test(command)) return 60;

                const minuteMatch = command.match(/\b([a-z0-9]+)\s*(minutos|minuto|mins|min)\b/);
                if (minuteMatch) return parseSpokenNumber(minuteMatch[1]);

                const hourMatch = command.match(/\b([a-z0-9]+)\s*(horas|hora)\b/);
                if (hourMatch) return parseSpokenNumber(hourMatch[1]) * 60;

                return 0;
            };

            const parseNextAppointmentDelayCommand = (command) => {
                const mentionsNextAppointment = /\b(proxima|siguiente)\b/.test(command)
                    && /\b(cita|cliente|persona)\b/.test(command);
                const mentionsDelay = /\b(retrasad|retraso|tarde|demora|demorados|demorad|atrasad|atraso)\b/.test(command);
                const wantsNotify = /\b(llama|llamar|llamale|marca|marcar|avisa|avisar|informa|informar|dile|decirle|comunica|notifica)\b/.test(command);
                if (! mentionsNextAppointment || ! mentionsDelay || ! wantsNotify) return null;

                const minutes = parseDelayMinutes(command);
                if (! minutes) return { error: 'minutes' };
                if (minutes < 1 || minutes > 240) return { error: 'range' };

                return { minutes };
            };

            const parseClientNoteCommand = (command) => {
                const match = command.match(/\b(?:guarda|guardar|anota|anotar|agrega|agregar|añade|anade|poner|pon)\s+(?:una\s+)?(?:nota\s+)?(?:en|al|para)\s+(?:el\s+|la\s+)?(?:cliente|clienta)\s+(.+?)\s+(?:que|porque|con nota|nota|dice|es|tiene|esta|está)\s+(.+)$/)
                    || command.match(/\b(?:guarda|guardar|anota|anotar|agrega|agregar|añade|anade|poner|pon)\s+(?:en|al|para)\s+(.+?)\s+(?:que|porque|con nota|nota|dice|es|tiene|esta|está)\s+(.+)$/);
                if (! match) return null;
                const name = match[1].replace(/\b(?:cliente|clienta|por favor)\b/g, ' ').replace(/\s+/g, ' ').trim();
                const note = match[2].replace(/\b(?:por favor)\b/g, ' ').replace(/\s+/g, ' ').trim();
                if (name.length < 2) return { error: 'name' };
                if (note.length < 2) return { error: 'note', name };
                return { name, note };
            };

            const saveClientNote = async ({ name, note }) => {
                try {
                    speak('De acuerdo. Guardo esa nota en el cliente '.concat(name, '.'));
                    const payload = await request(routes.clientNote, { method: 'POST', body: { name, note } });
                    speak(payload.message || 'Listo. Guarde la nota del cliente.');
                } catch (error) {
                    speak(error.payload?.message || 'No pude guardar la nota ahora mismo.');
                }
            };

            const callClient = async ({ name }) => {
                try {
                    speak('De acuerdo. Busco a '.concat(name, ' y preparo la llamada.'));
                    const payload = await request(routes.clientCall, { method: 'POST', body: { name } });
                    speak(payload.message || 'Listo. Estoy iniciando la llamada.');
                } catch (error) {
                    speak(error.payload?.message || 'No pude iniciar la llamada ahora mismo.');
                }
            };

            const remindClientAppointment = async ({ name }) => {
                try {
                    speak('De acuerdo. Busco la proxima cita de '.concat(name, ' y preparo el recordatorio.'));
                    const payload = await request(routes.clientAppointmentReminder, { method: 'POST', body: { name } });
                    speak(payload.message || 'Listo. Estoy recordandole su cita.');
                } catch (error) {
                    speak(error.payload?.message || 'No pude iniciar el recordatorio de cita ahora mismo.');
                }
            };

            const describeStaffVacations = async ({ name }) => {
                try {
                    const query = new URLSearchParams({ name });
                    const payload = await request(routes.staffVacations.concat('?').concat(query.toString()));
                    speak(payload.message || 'No encontre vacaciones proximas registradas.');
                } catch (error) {
                    speak(error.payload?.message || 'No pude consultar las vacaciones ahora mismo.');
                }
            };

            const describePaymentsToday = async () => {
                try {
                    const payload = await request(routes.paymentsToday);
                    speak(payload.message || 'No pude consultar los cobros de hoy.');
                } catch (error) {
                    speak(error.payload?.message || 'No pude consultar los cobros de hoy ahora mismo.');
                }
            };

            const notifyNextAppointmentDelay = async ({ minutes }) => {
                try {
                    speak('De acuerdo. Aviso a la proxima cita que vamos retrasados '.concat(minutes, ' minutos.'));
                    const payload = await request(routes.nextAppointmentDelay, { method: 'POST', body: { minutes } });
                    speak(payload.message || 'Listo. Estoy avisando a la proxima cita.');
                } catch (error) {
                    speak(error.payload?.message || 'No pude avisar a la proxima cita ahora mismo.');
                }
            };

            const createReminder = async (reminder) => {
                try {
                    const payload = await request(routes.reminders.store, {
                        method: 'POST',
                        body: { message: reminderLabel(reminder), due_at: new Date(reminder.dueAt).toISOString() },
                    });
                    const saved = payload.reminder || reminder;
                    speak('Listo. Te aviso sobre '.concat(reminderLabel(saved), ' ', reminderDueLabel(saved.due_at || saved.dueAt || reminder.dueAt), '.'));
                } catch (error) {
                    speak('No pude guardar el recordatorio ahora mismo.');
                }
            };

            const answer = (rawTranscript) => {
                const transcript = normalizedVoiceText(rawTranscript);
                const wakeWord = noraWakeWordMatch(transcript);
                const hasKnownIntent = ['llama', 'llamar', 'llamale', 'marca', 'marcar', 'avisa', 'avisar', 'informa', 'informar', 'dile', 'retraso', 'retrasad', 'tarde', 'demora', 'atraso', 'guarda', 'guardar', 'anota', 'agrega', 'cliente', 'clienta', 'nota', 'recordatorio', 'recuerdame', 'avisame', 'alerta', 'alarma', 'vacaciones', 'cobrado', 'cobros', 'caja', 'dinero', 'pagado', 'pagos', 'ayuda']
                    .some((word) => transcript.includes(word));
                if (! wakeWord && ! hasKnownIntent && Date.now() >= awaitingCommandUntil) return false;

                const command = wakeWord ? transcript.slice((wakeWord.index || 0) + wakeWord[0].length).trim() : transcript;
                if (! command) {
                    awaitingCommandUntil = Date.now() + 10000;
                    speak('Te escucho. ¿Qué quieres hacer?');
                    return true;
                }

                awaitingCommandUntil = 0;
                const nextDelay = parseNextAppointmentDelayCommand(command);
                const appointmentReminder = nextDelay ? null : parseClientAppointmentReminderCommand(command);
                const staffVacation = (nextDelay || appointmentReminder) ? null : parseStaffVacationCommand(command);
                const clientNote = (nextDelay || appointmentReminder || staffVacation) ? null : parseClientNoteCommand(command);
                const reminder = (nextDelay || appointmentReminder || staffVacation || clientNote) ? null : parseReminderCommand(command);
                const clientCall = (nextDelay || appointmentReminder || staffVacation || clientNote || reminder) ? null : parseClientCallCommand(command);

                if (nextDelay?.error === 'minutes') speak('Claro. Dime cuantos minutos de retraso debo avisar a la proxima cita.');
                else if (nextDelay?.error === 'range') speak('Puedo avisar retrasos entre uno y doscientos cuarenta minutos.');
                else if (nextDelay) notifyNextAppointmentDelay(nextDelay);
                else if (appointmentReminder?.error === 'name') speak('Claro. Dime a que cliente quieres recordarle la cita.');
                else if (appointmentReminder) remindClientAppointment(appointmentReminder);
                else if (staffVacation?.error === 'name') speak('Claro. Dime de que especialista quieres consultar vacaciones.');
                else if (staffVacation) describeStaffVacations(staffVacation);
                else if (command.includes('cobrado') || command.includes('cobros') || command.includes('caja') || command.includes('dinero') || command.includes('pagado') || command.includes('pagos')) describePaymentsToday();
                else if (clientNote?.error === 'name') speak('Claro. Dime en que cliente quieres guardar la nota.');
                else if (clientNote?.error === 'note') speak('Claro. Dime que nota quieres guardar para '.concat(clientNote.name, '.'));
                else if (clientNote) saveClientNote(clientNote);
                else if (clientCall?.error === 'name') speak('Claro. Dime a que cliente quieres que llame.');
                else if (clientCall) callClient(clientCall);
                else if (reminder?.error === 'time') speak('Claro. Dime en cuanto tiempo quieres el recordatorio.');
                else if (reminder?.error === 'range') speak('Puedo crear recordatorios entre un segundo y veinticuatro horas.');
                else if (reminder) createReminder(reminder);
                else if (command.includes('ayuda') || command.includes('que puedes')) speak('Puedes pedirme que llame a un cliente, avise retrasos a la proxima cita, guarde una nota interna o cree un recordatorio.');
                else {
                    const today = new Date().toISOString().slice(0, 10);
                    if (window.localStorage.getItem(helpKey) !== today) {
                        window.localStorage.setItem(helpKey, today);
                        speak('No he entendido esa peticion. Puedes pedirme que llame a un cliente, avise retrasos a la proxima cita, guarde una nota interna o cree un recordatorio.');
                    }
                }

                return true;
            };

            recognition = new SpeechRecognitionApi();
            recognition.lang = 'es-ES';
            recognition.continuous = true;
            recognition.interimResults = false;
            recognition.maxAlternatives = 1;
            recognition.onstart = () => { recognitionRunning = true; };
            recognition.onresult = (event) => {
                for (let index = event.resultIndex; index < event.results.length; index += 1) {
                    if (! event.results[index].isFinal) continue;
                    answer((event.results[index][0].transcript || '').trim());
                }
            };
            recognition.onerror = (event) => {
                if (event.error === 'not-allowed' || event.error === 'service-not-allowed') {
                    enabled = false;
                    window.localStorage.removeItem(preferenceKey);
                }
            };
            recognition.onend = () => {
                recognitionRunning = false;
                if (enabled && ! speaking) restartTimer = window.setTimeout(start, 800);
            };

            window.addEventListener('storage', (event) => {
                if (event.key !== preferenceKey) return;
                enabled = event.newValue === '1';
                if (enabled) start();
                else stop();
            });
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') start();
                else stop();
            });
            if (enabled) window.setTimeout(start, 600);
            window.addEventListener('beforeunload', stop);
        })();
        @endauth

        const consoleCallIndicator = document.querySelector('[data-console-call-indicator]');
        const consoleHotCallAlert = document.querySelector('[data-hot-call-alert]');
        const consoleHotCallTitle = document.querySelector('[data-hot-call-title]');
        const consoleHotCallClient = document.querySelector('[data-hot-call-client]');
        const callAnswerButton = document.querySelector('[data-call-answer]');
        const callAssistantButton = document.querySelector('[data-call-assistant]');
        const callEndButton = document.querySelector('[data-call-end]');
        let globalCallActive = consoleHotCallAlert ? !consoleHotCallAlert.hidden : false;
        let inboundCallExpiryTimer = null;
        let voiceDevice = null;
        let pendingBrowserCall = null;
        let activeBrowserCall = null;

        const setCallButtons = (state) => {
            if (!callAnswerButton || !callAssistantButton || !callEndButton) return;
            const incoming = state === 'incoming';
            const active = state === 'active';
            callAnswerButton.hidden = !incoming;
            callAssistantButton.hidden = !incoming;
            callEndButton.hidden = !active;
            callAnswerButton.disabled = !incoming;
            callAssistantButton.disabled = !incoming;
        };

        const finishBrowserCall = () => {
            pendingBrowserCall = null;
            activeBrowserCall = null;
            setCallButtons('idle');
            monitorActiveCall();
        };

        const showIncomingBrowserCall = (call) => {
            pendingBrowserCall = call;
            const name = call.customParameters?.get('callerName');
            const phone = call.customParameters?.get('callerPhone') || call.parameters?.From;
            if (consoleHotCallAlert) consoleHotCallAlert.hidden = false;
            if (consoleHotCallTitle) consoleHotCallTitle.textContent = 'Llamada entrante · esperando respuesta';
            if (consoleHotCallClient) consoleHotCallClient.textContent = name && phone ? `${name} · ${phone}` : (name || phone || 'Número desconocido');
            setCallButtons('incoming');

            call.on('accept', () => {
                activeBrowserCall = call;
                pendingBrowserCall = null;
                if (consoleHotCallTitle) consoleHotCallTitle.textContent = 'Llamada atendida desde la consola';
                setCallButtons('active');
            });
            call.on('disconnect', finishBrowserCall);
            call.on('cancel', finishBrowserCall);
            call.on('reject', finishBrowserCall);
            call.on('error', finishBrowserCall);
        };

        const fetchVoiceToken = async () => {
            const response = await fetch('/twilio/voice/browser/token', {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
            });
            if (!response.ok) throw new Error('La telefonía del navegador no está configurada.');
            return response.json();
        };

        const initializeBrowserVoice = async () => {
            if (!callAnswerButton || !window.Twilio?.Device) return;
            try {
                const credentials = await fetchVoiceToken();
                voiceDevice = new window.Twilio.Device(credentials.token, {
                    codecPreferences: ['opus', 'pcmu'],
                    logLevel: 1,
                });
                voiceDevice.on('incoming', showIncomingBrowserCall);
                voiceDevice.on('tokenWillExpire', async () => {
                    try {
                        const refreshed = await fetchVoiceToken();
                        voiceDevice.updateToken(refreshed.token);
                    } catch (error) {
                        // El registro existente sigue válido hasta que expire.
                    }
                });
                await voiceDevice.register();
            } catch (error) {
                setCallButtons('idle');
            }
        };

        callAnswerButton?.addEventListener('click', () => {
            if (!pendingBrowserCall) return;
            callAnswerButton.disabled = true;
            callAssistantButton.disabled = true;
            pendingBrowserCall.accept();
        });
        callAssistantButton?.addEventListener('click', () => {
            if (!pendingBrowserCall) return;
            callAnswerButton.disabled = true;
            callAssistantButton.disabled = true;
            pendingBrowserCall.reject();
            if (consoleHotCallTitle) consoleHotCallTitle.textContent = 'Pasando la llamada a Nora…';
        });
        callEndButton?.addEventListener('click', () => activeBrowserCall?.disconnect());

        const monitorActiveCall = async () => {
            try {
                const response = await fetch('/consola/llamada-activa', {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store',
                });
                if (!response.ok) return;
                const call = await response.json();
                if (inboundCallExpiryTimer) {
                    window.clearTimeout(inboundCallExpiryTimer);
                    inboundCallExpiryTimer = null;
                }
                consoleCallIndicator?.classList.toggle('call-live', Boolean(call.active));
                consoleCallIndicator?.setAttribute('title', call.active
                    ? `${call.status_label || 'Llamada en curso'} · ${call.client || call.phone || 'Cliente'}`
                    : 'Consola');

                if (call.active && consoleHotCallAlert) {
                    consoleHotCallAlert.hidden = false;
                    consoleHotCallTitle.textContent = call.status_label || 'Llamada detectada en tiempo real';
                    consoleHotCallClient.textContent = call.phone && call.client !== call.phone
                        ? `${call.client} · ${call.phone}`
                        : (call.client || call.phone || 'Número desconocido');
                }

                if (!call.active && consoleHotCallAlert && !pendingBrowserCall && !activeBrowserCall) {
                    consoleHotCallAlert.hidden = true;
                    setCallButtons('idle');
                }
                globalCallActive = call.active;

                if (call.active && call.direction === 'inbound' && call.expires_in_ms !== null) {
                    inboundCallExpiryTimer = window.setTimeout(
                        monitorActiveCall,
                        Math.max(100, Number(call.expires_in_ms) + 100),
                    );
                }
            } catch (error) {
                // Una comprobación fallida no interrumpe el resto del panel.
            }
        };

        let globalCallTimer = null;
        const startGlobalCallMonitoring = () => {
            if (globalCallTimer || document.hidden) return;
            monitorActiveCall();
            globalCallTimer = window.setInterval(monitorActiveCall, 10000);
        };
        const stopGlobalCallMonitoring = () => {
            if (!globalCallTimer) return;
            window.clearInterval(globalCallTimer);
            globalCallTimer = null;
        };
        document.addEventListener('visibilitychange', () => {
            document.hidden ? stopGlobalCallMonitoring() : startGlobalCallMonitoring();
        });
        startGlobalCallMonitoring();
        initializeBrowserVoice();

    </script>
</body>
</html>
