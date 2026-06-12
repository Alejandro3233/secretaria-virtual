<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Restablecer contrasena - Secretaria Virtual</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; font-family: Roboto, Arial, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", "Helvetica Neue", sans-serif; background: #fbf7f9; color: #181216; padding: 24px; -webkit-font-smoothing: antialiased; text-rendering: optimizeLegibility; }
        form { width: min(460px, 100%); background: white; border: 1px solid #eadfe5; border-radius: 8px; padding: 28px; box-shadow: 0 24px 60px rgba(60, 22, 41, .12); }
        h1 { margin: 0 0 8px; font-size: 30px; letter-spacing: 0; }
        p { color: #70646b; line-height: 1.55; margin: 0 0 22px; }
        label { display: block; font-weight: 800; margin: 14px 0 7px; }
        input { width: 100%; min-height: 44px; border: 1px solid #dbcbd4; border-radius: 6px; padding: 0 12px; }
        .btn { min-height: 44px; width: 100%; border: 0; border-radius: 6px; background: #c0265a; color: white; font-weight: 900; cursor: pointer; margin-top: 20px; }
        .error { margin-top: 8px; color: #b91c2a; font-size: 14px; }
    </style>
</head>
<body>
    <form method="POST" action="/restablecer-contrasena">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <h1>Nueva contrasena</h1>
        <p>Crea una contrasena nueva para recuperar el acceso a tu salon.</p>

        <label for="email">Correo electronico</label>
        <input id="email" name="email" type="email" value="{{ old('email', $request->email) }}" required>
        @error('email') <div class="error">{{ $message }}</div> @enderror

        <label for="password">Nueva contrasena</label>
        <input id="password" name="password" type="password" required>
        @error('password') <div class="error">{{ $message }}</div> @enderror

        <label for="password_confirmation">Confirmar contrasena</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required>

        <button class="btn" type="submit">Actualizar contrasena</button>
    </form>
</body>
</html>
