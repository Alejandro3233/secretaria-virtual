<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Secretaria Virtual')</title>
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
            grid-template-columns: 264px 1fr;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: var(--ink);
            background: var(--soft);
        }
        html.sidebar-collapsed body { grid-template-columns: 78px 1fr; }
        a { color: inherit; text-decoration: none; }
        button { font: inherit; }
        .sidebar {
            position: relative;
            min-height: 100vh;
            background: var(--panel);
            color: #f7edf2;
            padding: 22px;
            display: flex;
            flex-direction: column;
            gap: 24px;
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
            gap: 10px;
            font-size: 19px;
            font-weight: 900;
            min-width: 0;
        }
        .brand-text { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .mark {
            width: 34px;
            height: 34px;
            flex: 0 0 34px;
            border-radius: 8px;
            display: grid;
            place-items: center;
            background: var(--brand);
            color: white;
        }
        .sidebar-toggle {
            width: 34px;
            height: 34px;
            flex: 0 0 34px;
            display: grid;
            place-items: center;
            border: 1px solid rgba(255,255,255,.16);
            border-radius: 8px;
            background: transparent;
            color: #eadfe5;
            cursor: pointer;
        }
        .sidebar-toggle:hover { background: rgba(255,255,255,.1); color: white; }
        .nav { display: grid; gap: 8px; }
        .nav a {
            min-height: 44px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-radius: 6px;
            padding: 0 12px;
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
            min-height: 36px;
            font-size: 14px;
            color: #d8c9d1;
        }
        .icon {
            width: 20px;
            height: 20px;
            flex: 0 0 20px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        .logout {
            width: 100%;
            min-height: 42px;
            border: 0;
            border-radius: 6px;
            background: var(--brand);
            color: white;
            font-weight: 900;
            cursor: pointer;
        }
        .logout-label { white-space: nowrap; }
        .plan {
            margin-top: auto;
            border: 1px solid rgba(255,255,255,.16);
            border-radius: 8px;
            padding: 16px;
            background: rgba(255,255,255,.06);
        }
        .plan b { display: block; margin-bottom: 6px; }
        .plan span { color: #eadfe5; font-size: 14px; line-height: 1.4; }
        .progress {
            height: 9px;
            border-radius: 999px;
            background: rgba(255,255,255,.16);
            overflow: hidden;
            margin-top: 10px;
        }
        .bar { height: 100%; background: var(--brand); width: 62%; }
        html.sidebar-collapsed .sidebar { padding: 22px 14px; align-items: center; }
        html.sidebar-collapsed .sidebar-top { width: 100%; justify-content: center; }
        html.sidebar-collapsed .brand { justify-content: center; }
        html.sidebar-collapsed .brand-text,
        html.sidebar-collapsed .nav-label,
        html.sidebar-collapsed .nav-sub,
        html.sidebar-collapsed .logout-label,
        html.sidebar-collapsed .plan { display: none; }
        html.sidebar-collapsed .sidebar-toggle { position: absolute; top: 22px; left: 56px; background: var(--panel); box-shadow: 0 8px 18px rgba(0,0,0,.18); }
        html.sidebar-collapsed .nav { width: 100%; }
        html.sidebar-collapsed .nav a { justify-content: center; padding: 0; }
        html.sidebar-collapsed .nav-group { width: 100%; }
        html.sidebar-collapsed .logout { width: 44px; padding: 0; }
        html.sidebar-collapsed .logout::before { content: "X"; font-size: 15px; }
        .main { min-width: 0; }
        .topbar {
            min-height: 74px;
            display: grid;
            grid-template-columns: minmax(180px, 1fr) minmax(220px, 288px) minmax(240px, 1fr);
            align-items: center;
            gap: 18px;
            padding: 0 30px;
            background: white;
            border-bottom: 1px solid var(--line);
        }
        .topbar-title { min-width: 0; }
        .topbar h1 { margin: 0; font-size: 28px; letter-spacing: 0; }
        .subtitle { color: var(--muted); }
        .topbar-search {
            min-height: 42px;
            display: grid;
            grid-template-columns: 20px 1fr;
            align-items: center;
            gap: 10px;
            border: 1px solid #b8c0d4;
            border-radius: 8px;
            padding: 0 14px;
            background: white;
        }
        .topbar-search:focus-within { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(29,78,216,.1); }
        .topbar-search svg { width: 18px; height: 18px; color: #344054; }
        .topbar-search input {
            width: 100%;
            min-height: 38px;
            border: 0;
            padding: 0;
            outline: 0;
            color: var(--ink);
            font-weight: 700;
        }
        .topbar-search input::placeholder { color: #7d8798; font-style: italic; }
        .topbar-right { display: flex; justify-content: flex-end; align-items: center; gap: 10px; min-width: 0; }
        .actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .topbar-icon-btn,
        .topbar-menu summary {
            position: relative;
            width: 38px;
            height: 38px;
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
            top: 46px;
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
        .topbar-dropdown a,
        .topbar-dropdown button {
            width: 100%;
            min-height: 38px;
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
            width: 34px;
            height: 34px;
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
            min-height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            padding: 0 14px;
            border: 1px solid var(--line);
            background: white;
            font-weight: 800;
        }
        .btn.primary { background: var(--brand); color: white; border-color: var(--brand); }
        .content { padding: 28px 30px 48px; }
        .card {
            background: white;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 18px;
        }
        .grid-2 { display: grid; grid-template-columns: 1.4fr .9fr; gap: 18px; align-items: start; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
        .grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
        h2 { margin: 0; font-size: 20px; letter-spacing: 0; }
        .section-title { display: flex; justify-content: space-between; gap: 12px; align-items: center; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 13px 10px; border-bottom: 1px solid #f2eaf0; text-align: left; vertical-align: top; }
        th { color: var(--muted); font-size: 12px; text-transform: uppercase; background: #fffafd; }
        tr:last-child td { border-bottom: 0; }
        .status { display: inline-flex; align-items: center; border-radius: 999px; padding: 5px 10px; font-size: 12px; font-weight: 900; }
        .integration-status-bar .integration-status {
            width: 92px;
            height: 68px;
            flex: 0 0 92px;
            justify-content: center;
            padding: 0;
            text-align: center;
        }
        .ok { background: #dcfce7; color: var(--green); }
        .wait { background: #fef3c7; color: var(--amber); }
        .info { background: #dbeafe; color: var(--blue); }
        .danger { background: #fee2e2; color: #991b1b; }
        input, select {
            width: 100%;
            border: 1px solid #dbcbd4;
            border-radius: 6px;
            min-height: 42px;
            padding: 0 12px;
            background: white;
        }
        label { display: block; color: var(--muted); font-weight: 800; margin-bottom: 7px; }
        .toolbar { display: grid; grid-template-columns: 1fr 180px 180px; gap: 12px; margin-bottom: 18px; }
        .metric-label { color: var(--muted); font-size: 14px; font-weight: 800; }
        .metric { font-size: 34px; font-weight: 900; margin-top: 8px; }
        .trend { margin-top: 8px; font-size: 13px; color: var(--muted); }
        .item { display: flex; justify-content: space-between; gap: 14px; padding: 14px 0; border-bottom: 1px solid #f2eaf0; }
        .item:last-child { border-bottom: 0; padding-bottom: 0; }
        .item b { display: block; margin-bottom: 4px; }
        .item span { color: var(--muted); font-size: 14px; }
        .google-calendar-layout { display: grid; grid-template-columns: 250px minmax(0, 1fr); gap: 18px; align-items: start; }
        .google-calendar-sidebar { position: sticky; top: 18px; display: grid; gap: 22px; }
        .google-calendar-main { min-width: 0; }
        .calendar-create-btn { width: fit-content; min-height: 52px; display: inline-flex; align-items: center; gap: 12px; border: 1px solid var(--line); border-radius: 8px; padding: 0 22px 0 18px; background: white; box-shadow: 0 2px 7px rgba(24,18,22,.12); font-weight: 900; }
        .calendar-create-btn span { width: 24px; height: 24px; display: grid; place-items: center; color: var(--brand); font-size: 28px; font-weight: 500; line-height: 1; }
        .mini-calendar, .stylist-calendar-list { display: grid; gap: 12px; }
        .mini-calendar-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        .mini-calendar-head b { font-size: 15px; }
        .mini-calendar-head div { display: flex; gap: 6px; }
        .mini-calendar-head a { width: 28px; height: 28px; display: grid; place-items: center; border-radius: 50%; color: var(--muted); font-size: 18px; font-weight: 900; }
        .mini-calendar-head a:hover { background: #f3eef2; color: var(--ink); }
        .mini-calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; align-items: center; }
        .mini-calendar-grid span, .mini-calendar-grid a { height: 28px; display: grid; place-items: center; border-radius: 50%; font-size: 12px; font-weight: 900; }
        .mini-calendar-grid a:hover { background: #f3eef2; }
        .mini-weekday { color: var(--muted); }
        .mini-calendar-grid a.is-muted { color: #a99da4; }
        .mini-calendar-grid a.is-selected-week { background: #f3eef2; border-radius: 6px; }
        .mini-calendar-grid a.is-today { background: #1a73e8; color: white; border-radius: 50%; }
        .sidebar-section-title { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 2px; }
        .sidebar-section-title b { font-size: 15px; }
        .sidebar-section-title span { color: var(--muted); font-size: 12px; font-weight: 900; }
        .stylist-filter { display: grid; grid-template-columns: 18px 1fr; gap: 10px; align-items: center; margin: 0; color: var(--ink); cursor: pointer; }
        .stylist-filter input { position: absolute; opacity: 0; pointer-events: none; }
        .stylist-filter > span:last-child { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 700; }
        .stylist-check { width: 18px; height: 18px; display: grid; place-items: center; border: 2px solid #8ab4f8; border-radius: 3px; }
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
        .calendar-shell { border: 1px solid var(--line); border-radius: 8px; background: white; overflow: hidden; }
        .calendar-toolbar { min-height: 70px; display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 14px 18px; border-bottom: 1px solid var(--line); }
        .calendar-kicker { margin-bottom: 3px; color: var(--muted); font-size: 12px; font-weight: 900; text-transform: uppercase; }
        .calendar-view-tabs { display: inline-flex; align-items: center; border: 1px solid var(--line); border-radius: 6px; overflow: hidden; background: #fffafd; }
        .calendar-view-tabs a { min-width: 68px; padding: 8px 12px; border-right: 1px solid var(--line); color: var(--muted); font-size: 13px; font-weight: 900; text-align: center; }
        .calendar-view-tabs a:last-child { border-right: 0; }
        .calendar-view-tabs .active { background: var(--brand); color: white; }
        .calendar-empty { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 18px; border-bottom: 1px solid var(--line); background: #f8fbff; }
        .calendar-empty b, .calendar-empty span { display: block; }
        .calendar-empty span { margin-top: 3px; color: var(--muted); font-size: 14px; }
        .calendar-week { min-width: 920px; display: grid; grid-template-columns: 74px repeat(7, minmax(118px, 1fr)); overflow: auto; }
        .calendar-timezone { min-height: 74px; display: flex; align-items: end; justify-content: center; padding: 0 8px 12px; border-right: 1px solid var(--line); border-bottom: 1px solid var(--line); color: var(--muted); font-size: 11px; font-weight: 800; }
        .calendar-day-head { min-height: 74px; display: grid; place-items: center; align-content: center; gap: 5px; border-right: 1px solid var(--line); border-bottom: 1px solid var(--line); color: var(--muted); }
        .calendar-day-head:last-of-type { border-right: 0; }
        .calendar-day-head span { font-size: 12px; font-weight: 900; text-transform: uppercase; }
        .calendar-day-head b { width: 34px; height: 34px; display: grid; place-items: center; border-radius: 50%; color: var(--ink); font-size: 18px; }
        .calendar-day-head small { max-width: 100%; overflow: hidden; color: var(--muted); font-size: 12px; font-weight: 800; text-overflow: ellipsis; white-space: nowrap; }
        .calendar-day-head.is-today b { background: var(--brand); color: white; }
        .calendar-time-gutter { position: relative; border-right: 1px solid var(--line); background: white; }
        .calendar-hour-label { position: absolute; right: 10px; transform: translateY(-8px); color: var(--muted); font-size: 11px; font-weight: 800; white-space: nowrap; }
        .calendar-day-column { position: relative; border-right: 1px solid var(--line); background: white; }
        .calendar-day-column:last-child { border-right: 0; }
        .calendar-hour-line { position: absolute; left: 0; right: 0; height: 1px; background: #edf0f5; }
        .calendar-event { position: absolute; left: 6px; right: 6px; z-index: 1; display: grid; align-content: start; gap: 2px; overflow: hidden; border-left: 4px solid var(--brand); border-radius: 6px; padding: 7px 8px; background: #fff1f6; color: var(--ink); font-size: 12px; line-height: 1.2; box-shadow: 0 1px 2px rgba(24,18,22,.08); }
        .calendar-event:hover { filter: brightness(.98); }
        .calendar-event b, .calendar-event span, .calendar-event small { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .calendar-event b { font-size: 13px; }
        .calendar-event span { color: #4f3743; font-weight: 800; }
        .calendar-event small { color: var(--muted); font-size: 11px; }
        .calendar-event.from-google { border-left-color: #1a73e8; background: #e8f0fe; }
        .calendar-event.from-twilio { border-left-color: var(--green); background: #ecfdf3; }
        .calendar-event.from-web { border-left-color: var(--amber); background: #fff7ed; }
        .calendar-day-view { min-width: 760px; }
        .calendar-month { display: grid; grid-template-columns: repeat(7, minmax(110px, 1fr)); background: white; }
        .calendar-month-weekday { min-height: 38px; display: flex; align-items: center; padding: 0 10px; border-right: 1px solid var(--line); border-bottom: 1px solid var(--line); color: var(--muted); font-size: 12px; font-weight: 900; text-transform: uppercase; }
        .calendar-month-weekday:nth-child(7n) { border-right: 0; }
        .calendar-month-day { min-height: 116px; display: grid; align-content: start; gap: 5px; padding: 9px; border-right: 1px solid var(--line); border-bottom: 1px solid var(--line); color: var(--ink); }
        .calendar-month-day:nth-child(7n) { border-right: 0; }
        .calendar-month-day b { width: 26px; height: 26px; display: grid; place-items: center; border-radius: 50%; font-size: 13px; }
        .calendar-month-day.is-muted { background: #fcfafb; color: #a99da4; }
        .calendar-month-day.is-today b { background: var(--brand); color: white; }
        .calendar-month-day span { overflow: hidden; border-radius: 4px; padding: 3px 5px; background: #e8f0fe; color: #174ea6; font-size: 12px; font-weight: 800; text-overflow: ellipsis; white-space: nowrap; }
        .calendar-month-day small { color: var(--muted); font-size: 12px; font-weight: 900; }
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
            .topbar { grid-template-columns: 1fr; align-items: stretch; padding: 20px; }
            .topbar-right { justify-content: flex-start; }
            .content { padding: 20px; }
            .calendar-toolbar, .calendar-empty { align-items: flex-start; flex-direction: column; }
            .calendar-shell { overflow-x: auto; }
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-top">
            <a class="brand" href="/consola" title="Secretaria Virtual">
                <span class="mark">SV</span>
                <span class="brand-text">Secretaria Virtual</span>
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
            <a class="{{ request()->is('personal') ? 'active' : '' }}" href="/personal" title="Personal">
                <svg class="icon" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span class="nav-label">Personal</span>
            </a>
            <a class="{{ request()->is('personal/servicios') ? 'active' : '' }}" href="/personal/servicios" title="Servicios">
                <svg class="icon" viewBox="0 0 24 24"><path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/><circle cx="8" cy="7" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="16" cy="17" r="1"/></svg>
                <span class="nav-label">Servicios</span>
            </a>
            <a class="{{ request()->is('consola') || request()->is('dashboard') ? 'active' : '' }}" href="/consola" title="Consola">
                <svg class="icon" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
                <span class="nav-label">Consola</span>
            </a>
            <a class="{{ request()->is('ajustes') ? 'active' : '' }}" href="/ajustes" title="Ajustes">
                <svg class="icon" viewBox="0 0 24 24"><path d="M12 15.5A3.5 3.5 0 1 0 12 8a3.5 3.5 0 0 0 0 7.5z"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.1V21a2 2 0 1 1-4 0v-.1A1.7 1.7 0 0 0 8 19.4a1.7 1.7 0 0 0-1.88.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1.1-.4H3a2 2 0 1 1 0-4h.1A1.7 1.7 0 0 0 4.6 8a1.7 1.7 0 0 0-.34-1.88l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1.1V3a2 2 0 1 1 4 0v.1A1.7 1.7 0 0 0 16 4.6a1.7 1.7 0 0 0 1.88-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.7 1.7 0 0 0 19.4 9c.2.38.52.7.9.9.34.18.72.28 1.1.28h.1a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-2 1z"/></svg>
                <span class="nav-label">Ajustes</span>
            </a>
            @if (auth()->user()?->is_super_admin)
                <a class="{{ request()->is('base-de-datos*') ? 'active' : '' }}" href="/base-de-datos" title="Base de datos">
                    <svg class="icon" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/></svg>
                    <span class="nav-label">Base de datos</span>
                </a>
            @endif
        </nav>

        <form method="POST" action="/logout">
            @csrf
            <button class="logout" type="submit" title="Cerrar sesion"><span class="logout-label">Cerrar sesion</span></button>
        </form>

        <div class="plan">
            <b>Plan Profesional</b>
            <span>600 minutos de voz, 500 SMS y Google Calendar activo.</span>
            <div class="progress"><div class="bar"></div></div>
        </div>
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
                        @if ($topbarUser?->is_super_admin)
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
    </script>
</body>
</html>
