<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesion - Secretaria Virtual</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; grid-template-columns: 1fr 1fr; font-family: Inter, ui-sans-serif, system-ui, sans-serif; color: #10131a; background: #f6f8fb; }
        .intro { background: #111827; color: white; padding: 48px; display: flex; flex-direction: column; justify-content: space-between; }
        .brand { display: flex; align-items: center; gap: 10px; font-size: 22px; font-weight: 900; text-decoration: none; color: white; }
        .mark { width: 36px; height: 36px; border-radius: 8px; background: #ef3340; display: grid; place-items: center; }
        .intro h1 { font-size: clamp(36px, 5vw, 64px); line-height: 1; letter-spacing: 0; margin: 0; }
        .intro p { color: #cbd5e1; font-size: 18px; line-height: 1.55; max-width: 560px; }
        .panel { display: grid; place-items: center; padding: 32px; }
        form { width: min(440px, 100%); background: white; border: 1px solid #dde3ea; border-radius: 8px; padding: 28px; box-shadow: 0 24px 60px rgba(15, 23, 42, .08); }
        h2 { margin: 0 0 8px; font-size: 28px; letter-spacing: 0; }
        .muted { margin: 0 0 22px; color: #647084; }
        label { display: block; font-weight: 800; margin: 14px 0 7px; }
        input[type="email"], input[type="password"] { width: 100%; min-height: 44px; border: 1px solid #ccd5e0; border-radius: 6px; padding: 0 12px; }
        .row { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 16px; }
        .check { display: inline-flex; align-items: center; gap: 8px; color: #647084; }
        .btn { min-height: 44px; width: 100%; border: 0; border-radius: 6px; background: #c0265a; color: white; font-weight: 900; cursor: pointer; margin-top: 20px; }
        .google-btn { min-height: 44px; width: 100%; border: 1px solid #dde3ea; border-radius: 6px; background: white; color: #10131a; font-weight: 900; display: flex; align-items: center; justify-content: center; gap: 10px; margin-top: 20px; }
        .google-mark { width: 20px; height: 20px; border-radius: 50%; display: grid; place-items: center; color: #4285f4; font-weight: 900; border: 1px solid #dde3ea; }
        .divider { display: flex; align-items: center; gap: 12px; color: #647084; font-weight: 800; font-size: 13px; margin: 20px 0 6px; }
        .divider::before, .divider::after { content: ""; height: 1px; background: #dde3ea; flex: 1; }
        .link { color: #b91c2a; font-weight: 800; text-decoration: none; }
        .error { margin-top: 8px; color: #b91c2a; font-size: 14px; }
        .notice { margin: 0 0 16px; border: 1px solid #bbf7d0; background: #f0fdf4; color: #166534; border-radius: 6px; padding: 12px; font-weight: 800; }
        @media (max-width: 860px) { body { grid-template-columns: 1fr; } .intro { gap: 40px; } }
    </style>
</head>
<body>
    <aside class="intro">
        <a class="brand" href="/"><span class="mark">SV</span>Secretaria Virtual</a>
        <div>
            <h1>Bienvenido a tu consola de salon</h1>
            <p>Gestiona agenda, estilistas, llamadas, SMS, Google Calendar y pagos desde un solo lugar.</p>
        </div>
    </aside>

    <main class="panel">
        <form method="POST" action="/login">
            @csrf
            <h2>Iniciar sesion</h2>
            <p class="muted">Accede con el usuario de tu salon.</p>

            @if (session('status'))
                <div class="notice">{{ session('status') }}</div>
            @endif

            <a class="google-btn" href="/auth/google/redirect">
                <span class="google-mark">G</span>
                Continuar con Google
            </a>

            <div class="divider">o entra con correo</div>

            <label for="email">Correo electronico</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
            @error('email') <div class="error">{{ $message }}</div> @enderror

            <label for="password">Contrasena</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
            @error('password') <div class="error">{{ $message }}</div> @enderror

            <div class="row">
                <label class="check"><input type="checkbox" name="remember" value="1"> Recordarme</label>
                <a class="link" href="/recuperar-contrasena">Recuperar contrasena</a>
            </div>

            <button class="btn" type="submit">Entrar a la consola</button>
            <div class="row" style="justify-content:center;">
                <a class="link" href="/registro">Crear cuenta</a>
            </div>
        </form>
    </main>
</body>
</html>
