<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="/favicon-grid.svg?v=1">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <meta name="description" content="Secretary365 atiende las llamadas de tu salon, reserva citas y mantiene tu agenda al dia, las 24 horas.">
    <title>Secretary365 | Recepcionista IA para salones de belleza</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #211a1e;
            --muted: #6f656b;
            --cream: #fbf8f5;
            --blush: #f7e9ed;
            --rose: #b72d58;
            --rose-dark: #8d1f43;
            --rose-soft: #fff1f5;
            --sage: #dce7dc;
            --sage-dark: #335644;
            --line: #eadfe3;
            --white: #fff;
            --shadow: 0 24px 70px rgba(73, 38, 50, .12);
            --max: 1200px;
        }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { margin: 0; color: var(--ink); background: var(--white); font-family: "DM Sans", Arial, sans-serif; -webkit-font-smoothing: antialiased; }
        a { color: inherit; text-decoration: none; }
        button { font: inherit; }
        .wrap { width: min(var(--max), calc(100% - 40px)); margin: 0 auto; }
        .announcement { padding: 9px 20px; color: #fff; background: var(--ink); text-align: center; font-size: 13px; font-weight: 600; }
        .topbar { position: sticky; top: 0; z-index: 30; border-bottom: 1px solid rgba(234, 223, 227, .9); background: rgba(255, 255, 255, .92); backdrop-filter: blur(14px); }
        .nav { min-height: 72px; display: flex; align-items: center; justify-content: space-between; gap: 28px; }
        .brand-logo { display: block; width: 230px; height: 52px; object-fit: cover; object-position: center; }
        .links { display: flex; align-items: center; gap: 28px; color: #51484d; font-size: 14px; font-weight: 600; }
        .links a:hover { color: var(--rose); }
        .actions { display: flex; align-items: center; gap: 10px; }
        .btn { min-height: 46px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; border: 1px solid var(--line); border-radius: 999px; padding: 0 20px; color: var(--ink); background: var(--white); font-weight: 700; transition: .2s ease; cursor: pointer; }
        .btn:hover { border-color: #d7c1c9; transform: translateY(-1px); }
        .btn.primary { color: #fff; border-color: #111827; background: #111827; box-shadow: 0 12px 24px rgba(17,24,39,.18); }
        .btn.primary:hover { border-color: #000; background: #000; }
        .btn.dark { color: #fff; border-color: var(--ink); background: var(--ink); }
        .btn.small { min-height: 40px; padding: 0 16px; font-size: 14px; }

        .hero { position: relative; overflow: hidden; padding: 86px 0 72px; background: var(--cream); }
        .hero::before { content: ""; position: absolute; width: 540px; height: 540px; top: -250px; right: -110px; border-radius: 50%; background: #f2d9e0; filter: blur(2px); }
        .hero::after { content: ""; position: absolute; width: 250px; height: 250px; bottom: -150px; left: 42%; border-radius: 50%; background: var(--sage); }
        .hero-grid { position: relative; z-index: 1; display: grid; grid-template-columns: 1.02fr .98fr; align-items: center; gap: 74px; }
        .eyebrow { display: inline-flex; align-items: center; gap: 9px; margin-bottom: 22px; color: var(--rose-dark); font-size: 13px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        .eyebrow::before { content: ""; width: 9px; height: 9px; border-radius: 50%; background: var(--rose); box-shadow: 0 0 0 5px #f4dbe3; }
        h1, h2, h3 { font-family: Manrope, Arial, sans-serif; }
        h1 { max-width: 650px; margin: 0; font-size: clamp(44px, 5.6vw, 72px); line-height: .99; letter-spacing: -.055em; }
        h1 em { color: var(--rose); font-style: normal; }
        .lead { max-width: 620px; margin: 26px 0 0; color: #51474c; font-size: 19px; line-height: 1.62; }
        .hero-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 32px; }
        .trust-row { display: flex; flex-wrap: wrap; gap: 20px; margin-top: 28px; color: var(--muted); font-size: 13px; font-weight: 600; }
        .trust-row span { display: inline-flex; align-items: center; gap: 7px; }
        .trust-row span::before { content: "\2713"; display: grid; place-items: center; width: 19px; height: 19px; border-radius: 50%; color: var(--sage-dark); background: var(--sage); font-size: 12px; }

        .demo-shell { position: relative; padding: 22px; border: 1px solid rgba(255,255,255,.7); border-radius: 32px; background: rgba(255,255,255,.66); box-shadow: var(--shadow); backdrop-filter: blur(10px); }
        .demo-top { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 4px 4px 20px; }
        .live { display: inline-flex; align-items: center; gap: 8px; color: var(--sage-dark); font-size: 13px; font-weight: 800; }
        .live::before { content: ""; width: 9px; height: 9px; border-radius: 50%; background: #43a66d; box-shadow: 0 0 0 5px #dcefe3; }
        .call-time { color: var(--muted); font-size: 13px; }
        .conversation { display: grid; gap: 13px; padding: 24px; border-radius: 22px; background: var(--white); }
        .caller { display: flex; align-items: center; gap: 12px; padding-bottom: 18px; border-bottom: 1px solid var(--line); }
        .avatar { width: 46px; height: 46px; display: grid; place-items: center; flex: 0 0 auto; border-radius: 50%; color: #fff; background: var(--rose); font-family: Manrope, sans-serif; font-weight: 800; }
        .caller b { display: block; font-size: 15px; }
        .caller span { color: var(--muted); font-size: 13px; }
        .bubble { max-width: 88%; border-radius: 16px 16px 16px 5px; padding: 12px 14px; background: #f5f2f3; font-size: 14px; line-height: 1.45; }
        .bubble.ai { justify-self: end; border-radius: 16px 16px 5px 16px; color: #fff; background: var(--rose); }
        .booking-card { margin-top: 5px; padding: 16px; border: 1px solid #d8e4d9; border-radius: 16px; background: #f4f8f4; }
        .booking-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
        .booking-head b { font-size: 14px; }
        .status { padding: 5px 9px; border-radius: 999px; color: #286141; background: #dcefe3; font-size: 11px; font-weight: 800; }
        .booking-info { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; color: #4e6254; font-size: 12px; }
        .floating-note { position: absolute; right: -24px; bottom: 30px; max-width: 190px; padding: 13px 15px; border-radius: 15px; background: var(--ink); color: #fff; box-shadow: 0 16px 35px rgba(33,26,30,.2); font-size: 12px; line-height: 1.4; }

        section { padding: 88px 0; }
        .social-proof { padding: 26px 0; border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); }
        .proof-row { display: flex; align-items: center; justify-content: center; flex-wrap: wrap; gap: 18px 34px; color: var(--muted); font-size: 14px; }
        .proof-row strong { color: var(--ink); }
        .proof-pill { padding: 8px 13px; border-radius: 999px; background: var(--cream); font-weight: 700; }
        .section-head { max-width: 760px; margin-bottom: 42px; }
        .section-head.center { margin-right: auto; margin-left: auto; text-align: center; }
        .kicker { display: block; margin-bottom: 12px; color: var(--rose); font-size: 13px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        h2 { margin: 0; font-size: clamp(34px, 4vw, 52px); line-height: 1.08; letter-spacing: -.04em; }
        .section-head p { margin: 18px 0 0; color: var(--muted); font-size: 18px; line-height: 1.6; }

        .problem-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; }
        .feature { padding: 28px; border: 1px solid var(--line); border-radius: 22px; background: var(--white); }
        .feature-icon { width: 48px; height: 48px; display: grid; place-items: center; margin-bottom: 24px; border-radius: 14px; color: var(--rose-dark); background: var(--rose-soft); font-size: 21px; font-weight: 800; }
        .feature h3 { margin: 0 0 10px; font-size: 20px; }
        .feature p { margin: 0; color: var(--muted); line-height: 1.58; }
        .feature.sage .feature-icon { color: var(--sage-dark); background: var(--sage); }

        .showcase { background: var(--ink); color: #fff; }
        .showcase-grid { display: grid; grid-template-columns: .86fr 1.14fr; align-items: center; gap: 70px; }
        .showcase .kicker { color: #f2a8be; }
        .showcase p { color: #cfc4c9; }
        .check-list { display: grid; gap: 15px; margin-top: 28px; }
        .check-item { display: flex; align-items: flex-start; gap: 12px; color: #eee7ea; line-height: 1.5; }
        .check-item::before { content: "\2713"; width: 23px; height: 23px; display: grid; place-items: center; flex: 0 0 auto; border-radius: 50%; color: var(--ink); background: #f2a8be; font-size: 12px; font-weight: 900; }
        .day-card { padding: 26px; border: 1px solid #473b41; border-radius: 24px; background: #2c2428; }
        .day-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 22px; }
        .day-head span { color: #bfb2b8; font-size: 13px; }
        .agenda { display: grid; gap: 10px; }
        .appointment { display: grid; grid-template-columns: 58px 1fr auto; align-items: center; gap: 14px; padding: 13px; border-radius: 14px; background: #372e33; }
        .appointment time { color: #f2a8be; font-size: 13px; font-weight: 800; }
        .appointment b { display: block; font-size: 14px; }
        .appointment small { color: #bfb2b8; }
        .appointment .tag { padding: 5px 8px; border-radius: 999px; color: #bde0c9; background: #33483b; font-size: 10px; font-weight: 800; }

        .how { background: var(--cream); }
        .steps { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; counter-reset: step; }
        .step { position: relative; min-height: 230px; padding: 27px; border-radius: 22px; background: var(--white); counter-increment: step; }
        .step::before { content: "0" counter(step); display: block; margin-bottom: 36px; color: var(--rose); font-family: Manrope, sans-serif; font-size: 14px; font-weight: 800; }
        .step h3 { margin: 0 0 10px; font-size: 18px; }
        .step p { margin: 0; color: var(--muted); line-height: 1.55; }

        .audiences { display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; }
        .audience { min-height: 150px; display: grid; align-content: end; padding: 22px; border-radius: 18px; background: var(--blush); }
        .audience:nth-child(even) { background: var(--sage); }
        .audience b { font-family: Manrope, sans-serif; font-size: 17px; }
        .audience span { margin-top: 5px; color: var(--muted); font-size: 13px; }

        .pricing-section { background: var(--cream); }
        .pricing { display: grid; grid-template-columns: repeat(3, 1fr); align-items: stretch; gap: 18px; }
        .price { position: relative; display: flex; flex-direction: column; padding: 30px; border: 1px solid var(--line); border-radius: 24px; background: var(--white); }
        .price.featured { color: #fff; border-color: var(--rose); background: var(--rose); box-shadow: 0 24px 60px rgba(183,45,88,.2); }
        .popular { position: absolute; top: 18px; right: 18px; padding: 6px 10px; border-radius: 999px; color: var(--rose-dark); background: #fff; font-size: 10px; font-weight: 800; text-transform: uppercase; }
        .plan-name { font-family: Manrope, sans-serif; font-size: 18px; font-weight: 800; }
        .amount { margin: 18px 0 6px; font-family: Manrope, sans-serif; font-size: 42px; font-weight: 800; letter-spacing: -.04em; }
        .amount small { font-family: "DM Sans", sans-serif; font-size: 14px; font-weight: 500; letter-spacing: 0; }
        .price > p { min-height: 48px; margin: 0; color: var(--muted); line-height: 1.5; }
        .featured > p, .featured li { color: #f8dce5; }
        .price ul { flex: 1; display: grid; align-content: start; gap: 12px; margin: 24px 0 28px; padding: 22px 0 0; border-top: 1px solid var(--line); list-style: none; color: #554a50; }
        .featured ul { border-color: rgba(255,255,255,.25); }
        .price li::before { content: "\2713"; margin-right: 9px; font-weight: 900; }
        .price form { margin: 0; }
        .price .btn { width: 100%; }
        .featured .btn { color: var(--rose-dark); border-color: #fff; background: #fff; }

        .faq-grid { display: grid; grid-template-columns: .7fr 1.3fr; gap: 70px; }
        .faq-grid .section-head { margin: 0; }
        .faqs { border-top: 1px solid var(--line); }
        details.faq { border-bottom: 1px solid var(--line); }
        .faq summary { display: flex; justify-content: space-between; gap: 20px; padding: 22px 0; font-family: Manrope, sans-serif; font-size: 17px; font-weight: 700; cursor: pointer; list-style: none; }
        .faq summary::-webkit-details-marker { display: none; }
        .faq summary::after { content: "+"; color: var(--rose); font-size: 23px; font-weight: 500; }
        .faq[open] summary::after { content: "-"; }
        .faq p { margin: -4px 0 22px; color: var(--muted); line-height: 1.6; }

        .final-cta { padding-top: 20px; }
        .cta-box { position: relative; overflow: hidden; display: flex; align-items: center; justify-content: space-between; gap: 40px; padding: 54px; border-radius: 30px; color: #fff; background: var(--rose); }
        .cta-box::after { content: ""; position: absolute; width: 260px; height: 260px; right: -80px; bottom: -160px; border-radius: 50%; background: rgba(255,255,255,.13); }
        .cta-box h2 { max-width: 650px; font-size: clamp(32px, 4vw, 48px); }
        .cta-box p { margin: 14px 0 0; color: #f9dfe7; }
        .cta-box .btn { position: relative; z-index: 1; flex: 0 0 auto; }

        footer { margin-top: 88px; padding: 45px 0; color: var(--muted); border-top: 1px solid var(--line); }
        .footer-row { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 22px; }
        .footer-links { display: flex; flex-wrap: wrap; gap: 20px; font-size: 13px; }
        .footer-brand { width: 150px; opacity: .85; }

        @media (max-width: 1020px) {
            .links { display: none; }
            .hero-grid, .showcase-grid, .faq-grid { grid-template-columns: 1fr; }
            .hero-grid { gap: 55px; }
            .hero-copy { text-align: center; }
            .hero-copy .lead { margin-right: auto; margin-left: auto; }
            .hero-actions, .trust-row { justify-content: center; }
            .demo-shell { max-width: 620px; margin: 0 auto; }
            .problem-grid, .pricing { grid-template-columns: repeat(2, 1fr); }
            .steps { grid-template-columns: repeat(2, 1fr); }
            .audiences { grid-template-columns: repeat(3, 1fr); }
            .faq-grid { gap: 36px; }
        }
        @media (max-width: 720px) {
            .announcement { font-size: 12px; }
            .nav { min-height: 66px; }
            .brand-logo { width: 180px; height: 42px; }
            .actions .login-link { display: none; }
            .hero { padding: 58px 0; }
            .hero::before { width: 380px; height: 380px; }
            .hero-grid { gap: 42px; }
            h1 { font-size: clamp(40px, 12vw, 58px); }
            .lead { font-size: 17px; }
            .floating-note { position: static; max-width: none; margin-top: 14px; }
            section { padding: 68px 0; }
            .problem-grid, .pricing, .steps, .audiences { grid-template-columns: 1fr; }
            .appointment { grid-template-columns: 52px 1fr; }
            .appointment .tag { display: none; }
            .cta-box { align-items: flex-start; flex-direction: column; padding: 36px 28px; }
            .cta-box .btn { width: 100%; }
        }
        @media (max-width: 460px) {
            .wrap { width: min(var(--max), calc(100% - 28px)); }
            .actions .btn { min-height: 40px; padding: 0 14px; font-size: 13px; }
            .booking-info { grid-template-columns: 1fr; }
            .conversation { padding: 17px; }
            .demo-shell { padding: 14px; border-radius: 24px; }
        }
    </style>
</head>
<body>
    <div class="announcement">Tu salon puede atender llamadas y reservar citas incluso cuando todo el equipo esta ocupado.</div>

    <header class="topbar">
        <div class="wrap nav">
            <a href="/" aria-label="Secretary365, inicio">
                <img class="brand-logo" src="/logo-home-v2.png" alt="Secretary365">
            </a>
            <nav class="links" aria-label="Navegacion principal">
                <a href="#ventajas">Ventajas</a>
                <a href="#como-funciona">Como funciona</a>
                <a href="#planes">Planes</a>
                <a href="#preguntas">Preguntas</a>
            </nav>
            <div class="actions">
                @auth
                    <a class="btn small login-link" href="/consola">Ir a la consola</a>
                @else
                    <a class="btn small login-link" href="/login">Iniciar sesion</a>
                @endauth
                <a class="btn primary small" href="/registro">Probar Secretary365</a>
            </div>
        </div>
    </header>

    <main>
        <section class="hero">
            <div class="wrap hero-grid">
                <div class="hero-copy">
                    <div class="eyebrow">Recepcionista IA para belleza y bienestar</div>
                    <h1>Cada llamada puede ser una <em>nueva cita</em></h1>
                    <p class="lead">Secretary365 responde por tu salon, habla con tus clientes, consulta la agenda y reserva, cambia o cancela citas durante las 24 horas.</p>
                    <div class="hero-actions">
                        <a class="btn primary" href="/registro">Empezar ahora</a>
                        <a class="btn" href="#demostracion">Ver como atiende</a>
                    </div>
                    <div class="trust-row">
                        <span>Disponible 24/7</span>
                        <span>Agenda sincronizada</span>
                        <span>Sin llamadas perdidas</span>
                    </div>
                </div>

                <div class="demo-shell" id="demostracion">
                    <div class="demo-top">
                        <span class="live">Llamada en curso</span>
                        <span class="call-time">02:18</span>
                    </div>
                    <div class="conversation">
                        <div class="caller">
                            <div class="avatar">LM</div>
                            <div><b>Laura Martinez</b><span>Cliente habitual</span></div>
                        </div>
                        <div class="bubble">Hola, queria reservar un corte y color para el viernes por la tarde.</div>
                        <div class="bubble ai">Claro, Laura. Marta tiene disponibilidad a las 17:30. &iquest;Te viene bien?</div>
                        <div class="bubble">Perfecto.</div>
                        <div class="booking-card">
                            <div class="booking-head"><b>Nueva cita</b><span class="status">Confirmada</span></div>
                            <div class="booking-info">
                                <span>Viernes, 17:30</span>
                                <span>Corte + color</span>
                                <span>Con Marta</span>
                                <span>120 minutos</span>
                            </div>
                        </div>
                    </div>
                    <div class="floating-note">Confirmacion enviada y agenda actualizada automaticamente.</div>
                </div>
            </div>
        </section>

        <div class="social-proof">
            <div class="wrap proof-row">
                <strong>Creado para negocios como el tuyo</strong>
                <span class="proof-pill">Peluquerias</span>
                <span class="proof-pill">Barberias</span>
                <span class="proof-pill">Centros de estetica</span>
                <span class="proof-pill">Spas</span>
                <span class="proof-pill">Salones de unas</span>
            </div>
        </div>

        <section id="ventajas">
            <div class="wrap">
                <div class="section-head center">
                    <span class="kicker">Mas citas, menos interrupciones</span>
                    <h2>Tu equipo se ocupa de los clientes. Secretary365 se ocupa del telefono.</h2>
                    <p>Una recepcion profesional para los momentos en los que no puedes parar un servicio para responder.</p>
                </div>
                <div class="problem-grid">
                    <article class="feature">
                        <div class="feature-icon">24</div>
                        <h3>Atiende a cualquier hora</h3>
                        <p>Responde cuando el salon esta cerrado, en horas punta o mientras todo el equipo esta trabajando.</p>
                    </article>
                    <article class="feature sage">
                        <div class="feature-icon">01</div>
                        <h3>Reserva sin errores</h3>
                        <p>Consulta servicios, duraciones, profesionales y huecos reales antes de confirmar cada cita.</p>
                    </article>
                    <article class="feature">
                        <div class="feature-icon">SMS</div>
                        <h3>Reduce las ausencias</h3>
                        <p>Envia confirmaciones y recordatorios para que tus clientes recuerden sus citas y avisen a tiempo.</p>
                    </article>
                    <article class="feature sage">
                        <div class="feature-icon">IA</div>
                        <h3>Habla como tu salon</h3>
                        <p>Personaliza el saludo, los servicios, los horarios y la forma en la que quieres atender.</p>
                    </article>
                    <article class="feature">
                        <div class="feature-icon">360</div>
                        <h3>Conoce a cada cliente</h3>
                        <p>Consulta citas anteriores, preferencias, profesional habitual y notas importantes.</p>
                    </article>
                    <article class="feature sage">
                        <div class="feature-icon">CAL</div>
                        <h3>Todo queda sincronizado</h3>
                        <p>La agenda del salon y Google Calendar se mantienen actualizados con cada cambio.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="showcase">
            <div class="wrap showcase-grid">
                <div>
                    <span class="kicker">Una recepcion que no se satura</span>
                    <h2>Convierte las llamadas en una agenda organizada</h2>
                    <p class="lead">Cada conversacion termina con una accion clara y queda registrada para que tu equipo mantenga el control.</p>
                    <div class="check-list">
                        <div class="check-item">Reserva, reprograma y cancela citas.</div>
                        <div class="check-item">Asigna el profesional y la duracion correctos.</div>
                        <div class="check-item">Responde preguntas sobre horarios y servicios.</div>
                        <div class="check-item">Registra llamadas y envia confirmaciones.</div>
                    </div>
                </div>
                <div class="day-card">
                    <div class="day-head"><b>Agenda de hoy</b><span>Viernes, 12 de junio</span></div>
                    <div class="agenda">
                        <div class="appointment"><time>09:30</time><div><b>Maria &middot; Balayage</b><small>Andrea &middot; 150 min</small></div><span class="tag">Confirmada</span></div>
                        <div class="appointment"><time>10:00</time><div><b>Carmen &middot; Manicura</b><small>Lucia &middot; 60 min</small></div><span class="tag">Confirmada</span></div>
                        <div class="appointment"><time>12:45</time><div><b>Elena &middot; Corte</b><small>Marta &middot; 45 min</small></div><span class="tag">Nueva</span></div>
                        <div class="appointment"><time>17:30</time><div><b>Laura &middot; Corte + color</b><small>Marta &middot; 120 min</small></div><span class="tag">Por IA</span></div>
                        <div class="appointment"><time>19:30</time><div><b>Sofia &middot; Tratamiento facial</b><small>Paula &middot; 60 min</small></div><span class="tag">Confirmada</span></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="how" id="como-funciona">
            <div class="wrap">
                <div class="section-head center">
                    <span class="kicker">Asi de sencillo</span>
                    <h2>De llamada perdida a cita confirmada</h2>
                    <p>Secretary365 atiende el proceso completo sin que tu equipo tenga que dejar lo que esta haciendo.</p>
                </div>
                <div class="steps">
                    <article class="step"><h3>El cliente llama</h3><p>Tu numero recibe la llamada y la recepcionista se presenta con el nombre de tu salon.</p></article>
                    <article class="step"><h3>Entiende lo que necesita</h3><p>Identifica el servicio, el profesional preferido y el horario que busca el cliente.</p></article>
                    <article class="step"><h3>Consulta tu agenda</h3><p>Comprueba la disponibilidad real y ofrece los mejores huecos libres.</p></article>
                    <article class="step"><h3>Confirma y avisa</h3><p>Crea la cita, actualiza el calendario y envia la confirmacion correspondiente.</p></article>
                </div>
            </div>
        </section>

        <section>
            <div class="wrap">
                <div class="section-head center">
                    <span class="kicker">Especializada en tu sector</span>
                    <h2>No es una recepcionista generica</h2>
                    <p>Entiende que un balayage no dura lo mismo que un corte y que cada servicio necesita el profesional adecuado.</p>
                </div>
                <div class="audiences">
                    <div class="audience"><b>Peluquerias</b><span>Corte, color y tratamientos</span></div>
                    <div class="audience"><b>Barberias</b><span>Corte, barba y packs</span></div>
                    <div class="audience"><b>Estetica</b><span>Cabinas y tratamientos</span></div>
                    <div class="audience"><b>Unas</b><span>Manicura y pedicura</span></div>
                    <div class="audience"><b>Spas</b><span>Masajes y experiencias</span></div>
                </div>
            </div>
        </section>

        <section class="pricing-section" id="planes">
            <div class="wrap">
                @if (session('stripe_error'))
                    <div class="feature" style="margin-bottom:20px;border-color:#e8a5b8;background:#fff1f5;color:#8d1f43;font-weight:700;">
                        {{ session('stripe_error') }}
                    </div>
                @endif
                <div class="section-head center">
                    <span class="kicker">Planes claros</span>
                    <h2>Elige cuanto quieres automatizar</h2>
                    <p>Empieza con la agenda y activa la recepcion telefonica cuando tu salon este preparado.</p>
                </div>
                <div class="pricing">
                    <article class="price">
                        <span class="plan-name">Basico</span>
                        <div class="amount">$49 <small>/ mes</small></div>
                        <p>Para digitalizar la agenda y organizar la informacion del salon.</p>
                        <ul>
                            <li>Hasta 150 citas al mes</li>
                            <li>Agenda de servicios y clientes</li>
                            <li>Notificaciones por email</li>
                            <li>2 usuarios incluidos</li>
                        </ul>
                        <form method="POST" action="{{ route('stripe.checkout', 'basico') }}">@csrf<button class="btn" type="submit">Elegir Basico</button></form>
                    </article>
                    <article class="price featured">
                        <span class="popular">Mas elegido</span>
                        <span class="plan-name">Profesional</span>
                        <div class="amount">$99 <small>/ mes</small></div>
                        <p>Para atender llamadas y automatizar la recepcion diaria.</p>
                        <ul>
                            <li>Hasta 500 citas al mes</li>
                            <li>600 minutos de voz</li>
                            <li>500 SMS incluidos</li>
                            <li>Google Calendar y estilistas</li>
                        </ul>
                        <form method="POST" action="{{ route('stripe.checkout', 'profesional') }}">@csrf<button class="btn" type="submit">Elegir Profesional</button></form>
                    </article>
                    <article class="price">
                        <span class="plan-name">Salon Plus</span>
                        <div class="amount">$199 <small>/ mes</small></div>
                        <p>Para equipos grandes, varias ubicaciones y mayor volumen.</p>
                        <ul>
                            <li>Citas y usuarios sin limite</li>
                            <li>2.000 minutos de voz</li>
                            <li>2.000 SMS incluidos</li>
                            <li>Reportes y multiples salones</li>
                        </ul>
                        <form method="POST" action="{{ route('stripe.checkout', 'clinica-plus') }}">@csrf<button class="btn" type="submit">Elegir Salon Plus</button></form>
                    </article>
                </div>
            </div>
        </section>

        <section id="preguntas">
            <div class="wrap faq-grid">
                <div class="section-head">
                    <span class="kicker">Preguntas frecuentes</span>
                    <h2>Todo lo que necesitas saber</h2>
                    <p>Respuestas sencillas antes de poner tu nueva recepcion en marcha.</p>
                </div>
                <div class="faqs">
                    <details class="faq" open>
                        <summary>&iquest;Secretary365 sustituye el numero de mi salon?</summary>
                        <p>Puedes utilizar un numero asignado al servicio o conectar el flujo telefonico definido para tu negocio. La configuracion dependera del pais y de la disponibilidad de numeracion.</p>
                    </details>
                    <details class="faq">
                        <summary>&iquest;Puede reservar con distintos profesionales?</summary>
                        <p>Si. Consulta la disponibilidad por servicio y profesional para evitar cruces y respetar la duracion de cada cita.</p>
                    </details>
                    <details class="faq">
                        <summary>&iquest;Que ocurre si la IA no puede resolver una llamada?</summary>
                        <p>La llamada queda registrada para revision y el flujo puede configurarse para ofrecer una alternativa segun las necesidades del salon.</p>
                    </details>
                    <details class="faq">
                        <summary>&iquest;Se integra con Google Calendar?</summary>
                        <p>Si. Las citas pueden sincronizarse con Google Calendar para que el equipo trabaje con una agenda compartida y actualizada.</p>
                    </details>
                    <details class="faq">
                        <summary>&iquest;Puedo cambiar horarios, servicios y mensajes?</summary>
                        <p>Si. El salon controla su informacion, sus servicios, la configuracion de la agenda y las preferencias de notificacion.</p>
                    </details>
                </div>
            </div>
        </section>

        <section class="final-cta">
            <div class="wrap">
                <div class="cta-box">
                    <div>
                        <h2>La proxima cita puede llegar mientras estas atendiendo esta.</h2>
                        <p>Pon tu recepcion en marcha y deja de perder clientes por no poder responder.</p>
                    </div>
                    <a class="btn dark" href="/registro">Crear cuenta de salon</a>
                </div>
            </div>
        </section>
    </main>

    <footer>
        <div class="wrap footer-row">
            <img class="footer-brand" src="/logo.png" alt="Secretary365">
            <div class="footer-links">
                <a href="#ventajas">Ventajas</a>
                <a href="#planes">Planes</a>
                <a href="/particular">Reservar como cliente</a>
                <a href="/login">Acceso de salones</a>
            </div>
            <span>&copy; {{ date('Y') }} Secretary365</span>
        </div>
    </footer>
</body>
</html>
