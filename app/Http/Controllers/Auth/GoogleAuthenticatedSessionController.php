<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CallForwardingOnboardingService;
use Google\Client as GoogleClient;
use Google\Service\Oauth2;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class GoogleAuthenticatedSessionController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $state = Str::random(40);
        $request->session()->put('google_login_oauth_state', $state);

        $client = $this->client();
        $client->setState($state);

        return redirect()->away($client->createAuthUrl());
    }

    public function callback(Request $request, CallForwardingOnboardingService $onboarding): RedirectResponse
    {
        $expectedState = $request->session()->pull('google_login_oauth_state');

        if (! $expectedState || ! hash_equals($expectedState, (string) $request->query('state'))) {
            return redirect('/login')->withErrors(['email' => 'No pudimos validar el inicio de sesion con Google.']);
        }

        if (! $request->query('code')) {
            return redirect('/login')->withErrors(['email' => 'Google no devolvio un codigo de autorizacion valido.']);
        }

        $client = $this->client();
        $token = $client->fetchAccessTokenWithAuthCode((string) $request->query('code'));

        if (isset($token['error'])) {
            return redirect('/login')->withErrors(['email' => $token['error_description'] ?? $token['error']]);
        }

        $client->setAccessToken($token);

        $googleUser = (new Oauth2($client))->userinfo->get();
        $email = strtolower((string) $googleUser->getEmail());

        if (! $email) {
            return redirect('/login')->withErrors(['email' => 'Tu cuenta de Google no devolvio un correo valido.']);
        }

        $user = User::withTrashed()->where('google_id', $googleUser->getId())
            ->orWhere('email', $email)
            ->first();

        if ($user && ($user->trashed() || ! $user->is_active)) {
            return redirect('/login')->withErrors([
                'email' => 'Esta cuenta está deshabilitada. Contacta con el administrador.',
            ]);
        }

        if (! $user) {
            $user = User::create([
                'name' => $googleUser->getName() ?: $email,
                'email' => $email,
                'google_id' => $googleUser->getId(),
                'avatar_url' => $googleUser->getPicture(),
                'email_verified_at' => now(),
                'is_active' => true,
            ]);
        } else {
            $user->forceFill([
                'google_id' => $user->google_id ?: $googleUser->getId(),
                'avatar_url' => $googleUser->getPicture(),
                'email_verified_at' => $user->email_verified_at ?: now(),
            ])->save();
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended($onboarding->destinationFor($user));
    }

    private function client(): GoogleClient
    {
        $client = new GoogleClient();
        $client->setClientId((string) config('google.auth.client_id'));
        $client->setClientSecret((string) config('google.auth.client_secret'));
        $client->setRedirectUri((string) config('google.auth.redirect_uri'));
        $client->setAccessType('online');
        $client->setIncludeGrantedScopes(true);
        $client->addScope('openid');
        $client->addScope('email');
        $client->addScope('profile');

        return $client;
    }
}
