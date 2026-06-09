<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recuperar contrasena - Secretaria Virtual</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; font-family: "OpenAI Sans", Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", "Helvetica Neue", Arial, sans-serif; background: #fbf7f9; color: #181216; padding: 24px; -webkit-font-smoothing: antialiased; text-rendering: optimizeLegibility; }
        form { width: min(460px, 100%); background: white; border: 1px solid #eadfe5; border-radius: 8px; padding: 28px; box-shadow: 0 24px 60px rgba(60, 22, 41, .12); }
        h1 { margin: 0 0 8px; font-size: 30px; letter-spacing: 0; }
        p { color: #70646b; line-height: 1.55; margin: 0 0 22px; }
        label { display: block; font-weight: 800; margin-bottom: 7px; }
        input { width: 100%; min-height: 44px; border: 1px solid #dbcbd4; border-radius: 6px; padding: 0 12px; }
        .btn { min-height: 44px; width: 100%; border: 0; border-radius: 6px; background: #c0265a; color: white; font-weight: 900; cursor: pointer; margin-top: 20px; }
        .link { display: inline-block; margin-top: 18px; color: #8f1840; font-weight: 800; text-decoration: none; }
        .error { margin-top: 8px; color: #b91c2a; font-size: 14px; }
        .notice { margin-bottom: 16px; border: 1px solid #bbf7d0; background: #f0fdf4; color: #166534; border-radius: 6px; padding: 12px; font-weight: 800; }
    </style>
</head>
<body>
    <form method="POST" action="/recuperar-contrasena">
        @csrf
        <h1>Recuperar contrasena</h1>
        <p>Escribe tu correo y te enviaremos un enlace para crear una nueva contrasena.</p>

        @if (session('status'))
            <div class="notice">{{ session('status') }}</div>
        @endif

        <label for="email">Correo electronico</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus>
        @error('email') <div class="error">{{ $message }}</div> @enderror

        <button class="btn" type="submit">Enviar enlace</button>
        <a class="link" href="/login">Volver al login</a>
    </form>
</body>
</html>
