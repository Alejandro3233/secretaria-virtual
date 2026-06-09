<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Secretaria Virtual para Salones</title>
    <style>
        :root {
            --ink: #181216;
            --muted: #70646b;
            --line: #eadfe5;
            --soft: #fbf7f9;
            --brand: #c0265a;
            --brand-dark: #8f1840;
            --olive: #5d6b3f;
            --gold: #b88945;
            --white: #ffffff;
            --page-max-width: 1440px;
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "OpenAI Sans", Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", "Helvetica Neue", Arial, sans-serif; color: var(--ink); background: var(--white); -webkit-font-smoothing: antialiased; text-rendering: optimizeLegibility; }
        a { color: inherit; text-decoration: none; }
        .wrap { width: min(var(--page-max-width), calc(100% - 40px)); margin: 0 auto; }
        .topbar { border-bottom: 1px solid var(--line); background: rgba(255,255,255,.94); position: sticky; top: 0; z-index: 10; backdrop-filter: blur(10px); }
        .nav { height: 72px; display: flex; align-items: center; justify-content: space-between; gap: 24px; }
        .brand { display: flex; align-items: center; gap: 0; font-weight: 900; font-size: 20px; }
        .brand-logo { width: 172px; max-width: 100%; height: auto; display: block; border-radius: 8px; }
        .mark { width: 34px; height: 34px; border-radius: 8px; background: var(--brand); color: white; display: grid; place-items: center; font-weight: 900; }
        .links { display: flex; align-items: center; gap: 24px; color: var(--muted); font-size: 15px; }
        .actions { display: flex; align-items: center; gap: 12px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; min-height: 42px; border-radius: 6px; padding: 0 18px; font-weight: 800; border: 1px solid var(--line); background: white; }
        .btn.primary { background: var(--brand); color: white; border-color: var(--brand); }
        .btn.primary:hover { background: var(--brand-dark); }
        .audience-menu { position: relative; }
        .audience-menu summary {
            min-height: 42px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            border: 1px solid var(--line);
            border-radius: 6px;
            padding: 0 14px;
            background: white;
            color: var(--ink);
            font-weight: 900;
            cursor: pointer;
            list-style: none;
        }
        .audience-menu summary::-webkit-details-marker { display: none; }
        .audience-menu summary::after { content: "v"; color: var(--muted); font-size: 12px; }
        .audience-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            z-index: 20;
            width: 238px;
            display: grid;
            gap: 6px;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 8px;
            background: white;
            box-shadow: 0 18px 40px rgba(24,18,22,.16);
        }
        .audience-dropdown a {
            display: grid;
            gap: 4px;
            border-radius: 6px;
            padding: 10px;
        }
        .audience-dropdown a:hover { background: #fffafd; }
        .audience-dropdown b { color: var(--ink); font-size: 14px; }
        .audience-dropdown span { color: var(--muted); font-size: 13px; line-height: 1.35; }

        .hero {
            padding: 74px 0 54px;
            border-bottom: 1px solid var(--line);
            background:
                linear-gradient(90deg, rgba(255,255,255,.96) 0%, rgba(255,255,255,.9) 48%, rgba(255,255,255,.56) 100%),
                url("https://images.unsplash.com/photo-1675034743339-0b0747047727?auto=format&fit=crop&w=1800&q=80") center/cover;
        }
        .hero-grid { display: grid; max-width: 820px; }
        .eyebrow { display: inline-flex; align-items: center; gap: 8px; color: var(--brand-dark); font-weight: 900; margin-bottom: 18px; }
        .dot { width: 8px; height: 8px; border-radius: 999px; background: var(--gold); }
        h1 { margin: 0; max-width: 760px; font-size: clamp(42px, 7vw, 76px); line-height: .98; letter-spacing: 0; }
        .lead { margin: 24px 0 0; max-width: 660px; font-size: 20px; line-height: 1.55; color: #453a41; }
        .hero-actions { display: flex; gap: 14px; flex-wrap: wrap; margin-top: 32px; }
        section { padding: 64px 0; }
        .section-head { max-width: 780px; margin-bottom: 28px; }
        h2 { margin: 0; font-size: clamp(30px, 4vw, 46px); line-height: 1.08; letter-spacing: 0; }
        .section-head p { color: var(--muted); font-size: 18px; line-height: 1.55; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
        .card { border: 1px solid var(--line); border-radius: 8px; padding: 22px; background: white; }
        .card b { display: block; font-size: 18px; margin-bottom: 8px; }
        .card p { margin: 0; color: var(--muted); line-height: 1.5; }
        .audience-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 18px; }
        .audience-card { min-height: 210px; display: grid; align-content: space-between; gap: 18px; border: 1px solid var(--line); border-radius: 8px; padding: 24px; background: white; }
        .audience-card strong { display: block; font-size: 22px; margin-bottom: 8px; }
        .audience-card p { margin: 0; color: var(--muted); line-height: 1.55; }
        .band { background: var(--soft); border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); }
        .flow { display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; }
        .step { min-height: 132px; border: 1px solid var(--line); border-radius: 8px; background: white; padding: 18px; }
        .step .num { color: var(--brand); font-weight: 900; margin-bottom: 12px; }
        .pricing { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; }
        .price { border: 1px solid var(--line); border-radius: 8px; padding: 24px; background: white; }
        .price.featured { border-color: var(--brand); box-shadow: 0 18px 44px rgba(192, 38, 90, .14); }
        .amount { font-size: 34px; font-weight: 900; margin: 14px 0; }
        .price ul { padding: 0; margin: 18px 0 0; list-style: none; display: grid; gap: 10px; color: var(--muted); }
        .price form { margin-top: 22px; }
        .price .btn { width: 100%; }
        .cta { display: flex; align-items: center; justify-content: space-between; gap: 24px; background: #24151d; color: white; border-radius: 8px; padding: 34px; }
        .cta p { color: #eadfe5; margin: 8px 0 0; }
        footer { padding: 28px 0; color: var(--muted); border-top: 1px solid var(--line); }
        @media (max-width: 900px) { .links { display: none; } .grid-3, .pricing, .flow, .audience-grid { grid-template-columns: 1fr; } .hero { padding-top: 48px; } .cta { align-items: flex-start; flex-direction: column; } }
        @media (max-width: 640px) { .nav { height: auto; align-items: flex-start; flex-direction: column; padding: 14px 0; } .actions { width: 100%; flex-wrap: wrap; } .audience-menu, .audience-menu summary, .actions .btn { flex: 1 1 140px; } .audience-dropdown { left: 0; right: auto; width: min(280px, calc(100vw - 40px)); } }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="wrap nav">
            <a class="brand" href="/"><img class="brand-logo" src="/logo.png" alt="Secretaria Virtual"></a>
            <nav class="links">
                <a href="#funciones">Funciones</a>
                <a href="#flujo">Flujo</a>
                <a href="#integraciones">Integraciones</a>
                <a href="#planes">Planes</a>
            </nav>
            <div class="actions">
                <details class="audience-menu">
                    <summary>Acceder como</summary>
                    <div class="audience-dropdown">
                        <a href="/particular">
                            <b>Particular</b>
                            <span>Reservar o cambiar una cita en un salon.</span>
                        </a>
                        <a href="/registro">
                            <b>Salon</b>
                            <span>Crear cuenta y gestionar agenda, llamadas y clientes.</span>
                        </a>
                    </div>
                </details>
                @auth
                    <a class="btn" href="/consola">Consola</a>
                @else
                    <a class="btn" href="/login">Login</a>
                @endauth
                <a class="btn primary" href="/registro">Crear cuenta</a>
            </div>
        </div>
    </header>

    <main>
        <section class="hero">
            <div class="wrap hero-grid">
                <div>
                    <div class="eyebrow"><span class="dot"></span> Voz, IA y agenda para salones</div>
                    <h1>Recepcion virtual para salones de belleza</h1>
                    <p class="lead">Atiende llamadas, agenda cortes, color, uñas y tratamientos, confirma citas y sincroniza todo con Google Calendar sin saturar a tu equipo.</p>
                    <div class="hero-actions">
                        <a class="btn primary" href="/registro">Crear cuenta</a>
                        <a class="btn" href="#flujo">Ver como funciona</a>
                    </div>
                </div>
            </div>
        </section>

        <section id="funciones">
            <div class="wrap">
                <div class="section-head">
                    <h2>Una agenda pensada para el ritmo real de un salon</h2>
                    <p>Gestiona servicios, duraciones, estilistas, estaciones, depositos, clientes frecuentes y recordatorios automaticos.</p>
                </div>
                <div class="grid-3">
                    <article class="card"><b>Citas por servicio</b><p>Corte, blower, color, manicure, facial, extensiones y paquetes con duracion estimada.</p></article>
                    <article class="card"><b>Estilistas y estaciones</b><p>Asigna profesional, silla o cabina para evitar choques de agenda.</p></article>
                    <article class="card"><b>Ficha del cliente</b><p>Preferencias, formula de color, alergias, notas y estilista favorito.</p></article>
                    <article class="card"><b>Twilio Voice y SMS</b><p>Confirmaciones, cambios, cancelaciones y recordatorios por llamada o mensaje.</p></article>
                    <article class="card"><b>Google Calendar</b><p>Eventos sincronizados para equipos que ya trabajan con calendarios externos.</p></article>
                    <article class="card"><b>Stripe</b><p>Planes por salon y posibilidad futura de cobrar depositos o penalidades.</p></article>
                </div>
            </div>
        </section>

        <section id="particular" class="band">
            <div class="wrap">
                <div class="section-head">
                    <h2>Elige como quieres usar Secretaria Virtual</h2>
                    <p>Separamos el acceso del cliente final y el panel del salon para que cada persona llegue al flujo correcto.</p>
                </div>
                <div class="audience-grid">
                    <article class="audience-card">
                        <div>
                            <strong>Particular</strong>
                            <p>Para clientes que quieren reservar, confirmar o cambiar una cita con un salon.</p>
                        </div>
                        <a class="btn" href="/particular">Reservar una cita</a>
                    </article>
                    <article class="audience-card">
                        <div>
                            <strong>Salon</strong>
                            <p>Para duenos y equipos que necesitan controlar agenda, clientes, llamadas y configuracion.</p>
                        </div>
                        <a class="btn primary" href="/registro">Crear cuenta de salon</a>
                    </article>
                </div>
            </div>
        </section>

        <section id="flujo">
            <div class="wrap">
                <div class="section-head">
                    <h2>Del telefono a la silla correcta</h2>
                    <p>El sistema filtra por cliente, servicio, estilista y disponibilidad antes de confirmar una reserva.</p>
                </div>
                <div class="flow">
                    <div class="step"><div class="num">01</div><b>Cliente llama</b><p>Twilio recibe el numero entrante.</p></div>
                    <div class="step"><div class="num">02</div><b>Perfil</b><p>Se consultan preferencias y citas activas.</p></div>
                    <div class="step"><div class="num">03</div><b>Servicio</b><p>La IA identifica corte, color, uñas o tratamiento.</p></div>
                    <div class="step"><div class="num">04</div><b>Agenda</b><p>Se asigna estilista, horario y estacion disponible.</p></div>
                    <div class="step"><div class="num">05</div><b>Aviso</b><p>Calendar, email y SMS quedan actualizados.</p></div>
                </div>
            </div>
        </section>

        <section id="integraciones">
            <div class="wrap">
                <div class="section-head">
                    <h2>Integraciones principales</h2>
                    <p>Twilio para llamadas y SMS, Google Calendar para la agenda compartida, Stripe para planes y proveedores de voz IA como OpenAI, ElevenLabs o Azure AI Speech.</p>
                </div>
                <div class="grid-3">
                    <article class="card"><b>Twilio</b><p>Telefonia, SMS y webhooks para controlar la conversacion.</p></article>
                    <article class="card"><b>Google Calendar</b><p>Eventos por cita con ID externo para cambios y cancelaciones.</p></article>
                    <article class="card"><b>Stripe</b><p>Clientes, suscripciones, pagos recurrentes y estados de plan.</p></article>
                </div>
            </div>
        </section>

        <section id="planes" class="band">
            <div class="wrap">
                @if (session('stripe_error'))
                    <div class="card" style="margin-bottom:18px;border-color:#fecaca;background:#fef2f2;color:#991b1b;font-weight:900;">
                        {{ session('stripe_error') }}
                    </div>
                @endif
                <div class="section-head">
                    <h2>Tres planes para salones en crecimiento</h2>
                    <p>Los precios quedan parametrizados para ajustar limites de citas, llamadas, SMS y usuarios.</p>
                </div>
                <div class="pricing">
                    <article class="price">
                        <b>Basico</b><div class="amount">$49/mes</div><p>Gestion manual de citas y clientes.</p>
                        <ul><li>1 calendario</li><li>Notificaciones email</li><li>Agenda de servicios</li></ul>
                        <form method="POST" action="{{ route('stripe.checkout', 'basico') }}">@csrf<button class="btn" type="submit">Elegir Basico</button></form>
                    </article>
                    <article class="price featured">
                        <b>Profesional</b><div class="amount">$99/mes</div><p>Automatizacion telefonica para recepcion diaria.</p>
                        <ul><li>Twilio Voice</li><li>SMS automaticos</li><li>Google Calendar</li></ul>
                        <form method="POST" action="{{ route('stripe.checkout', 'profesional') }}">@csrf<button class="btn primary" type="submit">Elegir Profesional</button></form>
                    </article>
                    <article class="price">
                        <b>Salon Plus</b><div class="amount">$199/mes</div><p>Mayor volumen, multiples estilistas y reportes.</p>
                        <ul><li>Mas llamadas incluidas</li><li>Multiples salones</li><li>Historial avanzado</li></ul>
                        <form method="POST" action="{{ route('stripe.checkout', 'clinica-plus') }}">@csrf<button class="btn" type="submit">Elegir Salon Plus</button></form>
                    </article>
                </div>
            </div>
        </section>

        <section id="demo">
            <div class="wrap">
                <div class="cta">
                    <div>
                        <h2>Reduce llamadas perdidas y huecos en agenda</h2>
                        <p>Tu recepcion virtual confirma, cambia y agenda citas mientras tu equipo atiende clientes.</p>
                    </div>
                    <a class="btn primary" href="mailto:demo@secretariavirtual.local">Solicitar demo</a>
                </div>
            </div>
        </section>
    </main>

    <footer><div class="wrap">Secretaria Virtual - Plataforma para salones de belleza.</div></footer>
</body>
</html>
