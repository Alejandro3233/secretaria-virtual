<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RecordClinicRequestUsage;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [EnsureUserIsActive::class, RecordClinicRequestUsage::class]);
        $middleware->validateCsrfTokens(except: [
            'twilio/voice/*',
            'stripe/webhook',
            'cita/*/adelantar',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response): Response {
            if ($response->getStatusCode() === 419 && request()->is('login')) {
                return redirect()->route('login')->with(
                    'status',
                    'Tu sesión caducó. Vuelve a introducir tus datos para continuar.',
                );
            }

            return $response;
        });
    })->create();
