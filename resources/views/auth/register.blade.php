<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Crear cuenta - Secretaria Virtual</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; grid-template-columns: .9fr 1.1fr; font-family: Inter, ui-sans-serif, system-ui, sans-serif; color: #10131a; background: #f6f8fb; }
        .intro { background: #111827; color: white; padding: 48px; display: flex; flex-direction: column; justify-content: space-between; }
        .brand { display: flex; align-items: center; gap: 10px; font-size: 22px; font-weight: 900; text-decoration: none; color: white; }
        .mark { width: 36px; height: 36px; border-radius: 8px; background: #ef3340; display: grid; place-items: center; }
        .intro h1 { font-size: clamp(34px, 5vw, 60px); line-height: 1; letter-spacing: 0; margin: 0; }
        .intro p { color: #cbd5e1; font-size: 18px; line-height: 1.55; max-width: 560px; }
        .panel { display: grid; place-items: center; padding: 32px; }
        form { width: min(620px, 100%); background: white; border: 1px solid #dde3ea; border-radius: 8px; padding: 28px; box-shadow: 0 24px 60px rgba(15, 23, 42, .08); }
        h2 { margin: 0 0 8px; font-size: 28px; letter-spacing: 0; }
        .muted { margin: 0 0 22px; color: #647084; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        label { display: block; font-weight: 800; margin: 14px 0 7px; }
        input, select { width: 100%; min-height: 44px; border: 1px solid #ccd5e0; border-radius: 6px; padding: 0 12px; background: white; }
        .btn { min-height: 44px; width: 100%; border: 0; border-radius: 6px; background: #ef3340; color: white; font-weight: 900; cursor: pointer; margin-top: 22px; }
        .link { color: #b91c2a; font-weight: 800; text-decoration: none; }
        .footer { margin-top: 18px; color: #647084; text-align: center; }
        .error { margin-top: 8px; color: #b91c2a; font-size: 14px; }
        .error a { color: #8f1840; font-weight: 900; }
        @media (max-width: 900px) { body { grid-template-columns: 1fr; } .grid { grid-template-columns: 1fr; } .intro { gap: 40px; } }
    </style>
</head>
<body>
    <aside class="intro">
        <a class="brand" href="/"><span class="mark">SV</span>Secretaria Virtual</a>
        <div>
            <h1>Crea la cuenta de tu salon</h1>
            <p>El registro crea tu usuario administrador, un salon inicial y activa el plan Profesional en modo prueba.</p>
        </div>
    </aside>

    <main class="panel">
        <form method="POST" action="/registro">
            @csrf
            <h2>Registro</h2>
            <p class="muted">Datos principales para empezar a usar la consola.</p>

            <div class="grid">
                <div>
                    <label for="name">Nombre del usuario</label>
                    <input id="name" name="name" value="{{ old('name') }}" autocomplete="name" required autofocus>
                    @error('name') <div class="error">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label for="email">Correo electronico</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
                    @error('email')
                        <div class="error">
                            {{ $message }}
                            <br>
                            <a href="/login">Iniciar sesion</a> · <a href="/recuperar-contrasena">Recuperar contrasena</a>
                        </div>
                    @enderror
                </div>
                <div>
                    <label for="email_confirmation">Confirmar correo electronico</label>
                    <input id="email_confirmation" name="email_confirmation" type="email" value="{{ old('email_confirmation') }}" autocomplete="email" required>
                    @error('email_confirmation') <div class="error">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label for="clinic_phone">Telefono del salon</label>
                    <input id="clinic_phone" name="clinic_phone" value="{{ old('clinic_phone') }}">
                    @error('clinic_phone') <div class="error">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label for="country_code">Pais del salon</label>
                    <select id="country_code" name="country_code" required>
                        @foreach (app(\App\Services\TwilioPhoneNumberService::class)->supportedCountries() as $code => $country)
                            <option value="{{ $code }}" @selected(old('country_code', 'US') === $code)>{{ $country }}</option>
                        @endforeach
                    </select>
                    @error('country_code') <div class="error">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label for="password">Contrasena</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" required>
                    @error('password') <div class="error">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label for="password_confirmation">Confirmar contrasena</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                    @error('password_confirmation') <div class="error">{{ $message }}</div> @enderror
                </div>
            </div>

            <button class="btn" type="submit">Crear cuenta y entrar</button>
            <div class="footer">Ya tienes cuenta? <a class="link" href="/login">Inicia sesion</a></div>
        </form>
    </main>
</body>
</html>
