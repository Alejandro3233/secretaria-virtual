<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Crear cuenta - Secretaria Virtual</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; grid-template-columns: .9fr 1.1fr; font-family: Roboto, Arial, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", "Helvetica Neue", sans-serif; color: #10131a; background: #f6f8fb; -webkit-font-smoothing: antialiased; text-rendering: optimizeLegibility; }
        .intro { background: #111827; color: white; padding: 48px; display: flex; flex-direction: column; justify-content: space-between; }
        .brand { display: flex; align-items: center; gap: 0; font-size: 22px; font-weight: 900; text-decoration: none; color: white; }
        .brand-logo { width: 190px; max-width: 100%; height: auto; display: block; border-radius: 8px; }
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
        .hint { margin-top: 7px; color: #647084; font-size: 13px; line-height: 1.35; }
        .hint.ok { color: #166534; font-weight: 800; }
        .hint.warn { color: #92400e; font-weight: 800; }
        @media (max-width: 900px) { body { grid-template-columns: 1fr; } .grid { grid-template-columns: 1fr; } .intro { gap: 40px; } }
    </style>
</head>
<body>
    <aside class="intro">
        <a class="brand" href="/"><img class="brand-logo" src="/logo.png" alt="Secretaria Virtual"></a>
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
                    <label for="clinic_name">Nombre del salon</label>
                    <input id="clinic_name" name="clinic_name" value="{{ old('clinic_name') }}" autocomplete="organization" required autofocus>
                    @error('clinic_name') <div class="error">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label for="clinic_address">Direccion del salon</label>
                    <input id="clinic_address" name="clinic_address" value="{{ old('clinic_address') }}" autocomplete="street-address" required>
                    @error('clinic_address') <div class="error">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label for="name">Nombre del usuario</label>
                    <input id="name" name="name" value="{{ old('name') }}" autocomplete="given-name" required>
                    @error('name') <div class="error">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label for="last_name">Apellido</label>
                    <input id="last_name" name="last_name" value="{{ old('last_name') }}" autocomplete="family-name" required>
                    @error('last_name') <div class="error">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label for="email">Correo electronico</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
                    @error('email')
                        <div class="error">
                            {{ $message }}
                            <br>
                            <a href="/login">Iniciar sesion</a> - <a href="/recuperar-contrasena">Recuperar contrasena</a>
                        </div>
                    @enderror
                </div>
                <div>
                    <label for="email_confirmation">Confirmar correo electronico</label>
                    <input id="email_confirmation" name="email_confirmation" type="email" value="{{ old('email_confirmation') }}" autocomplete="email" required>
                    @error('email_confirmation') <div class="error">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label for="mobile_phone">Telefono movil</label>
                    <input id="mobile_phone" name="mobile_phone" type="tel" value="{{ old('mobile_phone') }}" autocomplete="tel" required>
                    <div class="hint" id="mobile_country_hint">Incluye el prefijo internacional, por ejemplo +1, +34, +52 o +57.</div>
                    @error('mobile_phone') <div class="error">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label for="mobile_phone_confirmation">Confirmar telefono movil</label>
                    <input id="mobile_phone_confirmation" name="mobile_phone_confirmation" type="tel" value="{{ old('mobile_phone_confirmation') }}" autocomplete="tel" required>
                    @error('mobile_phone_confirmation') <div class="error">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label for="clinic_phone">Telefono del salon</label>
                    <input id="clinic_phone" name="clinic_phone" type="tel" value="{{ old('clinic_phone') }}" autocomplete="tel">
                    @error('clinic_phone') <div class="error">{{ $message }}</div> @enderror
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
                <div>
                    <label for="country_code">Pais del salon</label>
                    <select id="country_code" name="country_code" required>
                        @foreach (app(\App\Services\TwilioPhoneNumberService::class)->supportedCountries() as $code => $country)
                            <option value="{{ $code }}" @selected(old('country_code', 'US') === $code)>{{ $country }}</option>
                        @endforeach
                    </select>
                    <div class="hint" id="country_code_hint">Se ajustara automaticamente si el telefono movil incluye prefijo internacional.</div>
                    @error('country_code') <div class="error">{{ $message }}</div> @enderror
                </div>
            </div>

            <button class="btn" type="submit">Crear cuenta y entrar</button>
            <div class="footer">Ya tienes cuenta? <a class="link" href="/login">Inicia sesion</a></div>
        </form>
    </main>
    <script>
        const mobilePhoneInput = document.getElementById('mobile_phone');
        const countrySelect = document.getElementById('country_code');
        const mobileCountryHint = document.getElementById('mobile_country_hint');
        const countryCodeHint = document.getElementById('country_code_hint');
        const supportedCountries = @json(app(\App\Services\TwilioPhoneNumberService::class)->supportedCountries());
        const phonePrefixes = [
            { prefix: '57', country: 'CO' },
            { prefix: '52', country: 'MX' },
            { prefix: '44', country: 'GB' },
            { prefix: '34', country: 'ES' },
            { prefix: '1', country: 'US', note: 'El prefijo +1 tambien se usa en Canada; se selecciono Estados Unidos por defecto.' },
        ];

        function normalizePhoneDigits(value) {
            return value.replace(/[^\d+]/g, '').replace(/(?!^)\+/g, '');
        }

        function detectCountryFromPhone(value) {
            const normalized = normalizePhoneDigits(value);

            if (!normalized.startsWith('+')) {
                return null;
            }

            const digits = normalized.slice(1);

            return phonePrefixes.find((option) => digits.startsWith(option.prefix)) || null;
        }

        function updateCountryFromMobilePhone() {
            const detected = detectCountryFromPhone(mobilePhoneInput.value);

            mobileCountryHint.className = 'hint';
            countryCodeHint.className = 'hint';

            if (!mobilePhoneInput.value.trim()) {
                mobileCountryHint.textContent = 'Incluye el prefijo internacional, por ejemplo +1, +34, +52 o +57.';
                countryCodeHint.textContent = 'Se ajustara automaticamente si el telefono movil incluye prefijo internacional.';
                return;
            }

            if (!mobilePhoneInput.value.trim().startsWith('+')) {
                mobileCountryHint.className = 'hint warn';
                mobileCountryHint.textContent = 'Agrega el prefijo internacional para detectar el pais automaticamente.';
                countryCodeHint.textContent = 'Pais del salon pendiente de confirmacion manual.';
                return;
            }

            if (!detected || !supportedCountries[detected.country]) {
                mobileCountryHint.className = 'hint warn';
                mobileCountryHint.textContent = 'No pude detectar un pais soportado con este prefijo.';
                countryCodeHint.textContent = 'Selecciona el pais del salon manualmente.';
                return;
            }

            countrySelect.value = detected.country;
            mobileCountryHint.className = 'hint ok';
            countryCodeHint.className = 'hint ok';
            mobileCountryHint.textContent = `Detectado: ${supportedCountries[detected.country]}.`;
            countryCodeHint.textContent = detected.note || `Pais del salon actualizado a ${supportedCountries[detected.country]}.`;
        }

        mobilePhoneInput.addEventListener('input', updateCountryFromMobilePhone);
        updateCountryFromMobilePhone();
    </script>
</body>
</html>
