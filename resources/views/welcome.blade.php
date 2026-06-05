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
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: var(--ink); background: var(--white); }
        a { color: inherit; text-decoration: none; }
        .wrap { width: min(1180px, calc(100% - 40px)); margin: 0 auto; }
        .topbar { border-bottom: 1px solid var(--line); background: rgba(255,255,255,.94); position: sticky; top: 0; z-index: 10; backdrop-filter: blur(10px); }
        .nav { height: 72px; display: flex; align-items: center; justify-content: space-between; gap: 24px; }
        .brand { display: flex; align-items: center; gap: 10px; font-weight: 900; font-size: 20px; }
        .mark { width: 34px; height: 34px; border-radius: 8px; background: var(--brand); color: white; display: grid; place-items: center; font-weight: 900; }
        .links { display: flex; align-items: center; gap: 24px; color: var(--muted); font-size: 15px; }
        .actions { display: flex; align-items: center; gap: 12px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; min-height: 42px; border-radius: 6px; padding: 0 18px; font-weight: 800; border: 1px solid var(--line); background: white; }
        .btn.primary { background: var(--brand); color: white; border-color: var(--brand); }
        .btn.primary:hover { background: var(--brand-dark); }

        .hero {
            padding: 74px 0 54px;
            border-bottom: 1px solid var(--line);
            background:
                linear-gradient(90deg, rgba(255,255,255,.96) 0%, rgba(255,255,255,.9) 48%, rgba(255,255,255,.56) 100%),
                url("https://images.unsplash.com/photo-1522337660859-02fbefca4702?auto=format&fit=crop&w=1800&q=80") center/cover;
        }
        .hero-grid { display: grid; grid-template-columns: 1.02fr .98fr; gap: 44px; align-items: center; }
        .eyebrow { display: inline-flex; align-items: center; gap: 8px; color: var(--brand-dark); font-weight: 900; margin-bottom: 18px; }
        .dot { width: 8px; height: 8px; border-radius: 999px; background: var(--gold); }
        h1 { margin: 0; max-width: 760px; font-size: clamp(42px, 7vw, 76px); line-height: .98; letter-spacing: 0; }
        .lead { margin: 24px 0 0; max-width: 660px; font-size: 20px; line-height: 1.55; color: #453a41; }
        .hero-actions { display: flex; gap: 14px; flex-wrap: wrap; margin-top: 32px; }
        .panel { background: rgba(255,255,255,.95); border: 1px solid var(--line); border-radius: 8px; box-shadow: 0 24px 60px rgba(60, 22, 41, .16); overflow: hidden; }
        .call-head { padding: 16px 18px; background: #24151d; color: white; display: flex; justify-content: space-between; align-items: center; font-weight: 800; }
        .call-body { padding: 22px; display: grid; gap: 14px; }
        .line { border: 1px solid var(--line); border-radius: 8px; padding: 15px; background: white; }
        .line strong { display: block; margin-bottom: 4px; }
        .line span { color: var(--muted); }

        section { padding: 64px 0; }
        .section-head { max-width: 780px; margin-bottom: 28px; }
        h2 { margin: 0; font-size: clamp(30px, 4vw, 46px); line-height: 1.08; letter-spacing: 0; }
        .section-head p { color: var(--muted); font-size: 18px; line-height: 1.55; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
        .card { border: 1px solid var(--line); border-radius: 8px; padding: 22px; background: white; }
        .card b { display: block; font-size: 18px; margin-bottom: 8px; }
        .card p { margin: 0; color: var(--muted); line-height: 1.5; }
        .band { background: var(--soft); border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); }
        .flow { display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; }
        .step { min-height: 132px; border: 1px solid var(--line); border-radius: 8px; background: white; padding: 18px; }
        .step .num { color: var(--brand); font-weight: 900; margin-bottom: 12px; }
        .pricing { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; }
        .price { border: 1px solid var(--line); border-radius: 8px; padding: 24px; background: white; }
        .price.featured { border-color: var(--brand); box-shadow: 0 18px 44px rgba(192, 38, 90, .14); }
        .amount { font-size: 34px; font-weight: 900; margin: 14px 0; }
        .price ul { padding: 0; margin: 18px 0 0; list-style: none; display: grid; gap: 10px; color: var(--muted); }
        .cta { display: flex; align-items: center; justify-content: space-between; gap: 24px; background: #24151d; color: white; border-radius: 8px; padding: 34px; }
        .cta p { color: #eadfe5; margin: 8px 0 0; }
        footer { padding: 28px 0; color: var(--muted); border-top: 1px solid var(--line); }
        @media (max-width: 900px) { .links { display: none; } .hero-grid, .grid-3, .pricing, .flow { grid-template-columns: 1fr; } .hero { padding-top: 48px; } .cta { align-items: flex-start; flex-direction: column; } }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="wrap nav">
            <a class="brand" href="/"><span class="mark">SV</span>Secretaria Virtual</a>
            <nav class="links">
                <a href="#funciones">Funciones</a>
                <a href="#flujo">Flujo</a>
                <a href="#integraciones">Integraciones</a>
                <a href="#planes">Planes</a>
            </nav>
            <div class="actions">
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
                <aside class="panel" aria-label="Ejemplo de llamada automatizada">
                    <div class="call-head"><span>Llamada entrante</span><span>Twilio Voice</span></div>
                    <div class="call-body">
                        <div class="line"><strong>Cliente identificado</strong><span>+1 555 0142 tiene cita de color con Sofia a las 10:30 AM.</span></div>
                        <div class="line"><strong>Opciones por voz</strong><span>Confirmar, cambiar o cancelar la cita existente.</span></div>
                        <div class="line"><strong>Nueva reserva</strong><span>Si no tiene cita, la IA propone servicio, estilista y horario disponible.</span></div>
                    </div>
                </aside>
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

        <section id="flujo" class="band">
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
                <div class="section-head">
                    <h2>Tres planes para salones en crecimiento</h2>
                    <p>Los precios quedan parametrizados para ajustar limites de citas, llamadas, SMS y usuarios.</p>
                </div>
                <div class="pricing">
                    <article class="price"><b>Basico</b><div class="amount">$49/mes</div><p>Gestion manual de citas y clientes.</p><ul><li>1 calendario</li><li>Notificaciones email</li><li>Agenda de servicios</li></ul></article>
                    <article class="price featured"><b>Profesional</b><div class="amount">$99/mes</div><p>Automatizacion telefonica para recepcion diaria.</p><ul><li>Twilio Voice</li><li>SMS automaticos</li><li>Google Calendar</li></ul></article>
                    <article class="price"><b>Salon Plus</b><div class="amount">$199/mes</div><p>Mayor volumen, multiples estilistas y reportes.</p><ul><li>Mas llamadas incluidas</li><li>Multiples salones</li><li>Historial avanzado</li></ul></article>
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
