<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Psr\Log\LogLevel;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<Throwable>, LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // Intercepter toutes les exceptions de limitation de requêtes (Rate Limiting / Throttle)
        $this->renderable(function (TooManyRequestsHttpException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $headers = $e->getHeaders();
                $seconds = isset($headers['Retry-After']) ? (int) $headers['Retry-After'] : null;

                $acceptLang = strtolower(substr((string) $request->header('Accept-Language', ''), 0, 2));
                $locale = in_array($acceptLang, ['en', 'fr'], true) ? $acceptLang : app()->getLocale();

                $message = $this->getHumanizedThrottleMessage($request, $seconds, $locale);

                return response()->json([
                    'message' => $message,
                    'retry_after' => $seconds,
                ], 429, $headers);
            }
        });
    }

    /**
     * Message clair, bienveillant et non technique adapté à chaque type d'action.
     */
    protected function getHumanizedThrottleMessage($request, ?int $seconds, string $locale): string
    {
        $path = strtolower($request->path());
        $isEn = ($locale === 'en');

        $durationFr = 'un court instant';
        $durationEn = 'a short moment';

        if ($seconds && $seconds > 0) {
            if ($seconds > 60) {
                $mins = (int) ceil($seconds / 60);
                $durationFr = "environ {$mins} minute" . ($mins > 1 ? 's' : '');
                $durationEn = "about {$mins} minute" . ($mins > 1 ? 's' : '');
            } else {
                $durationFr = 'quelques instants';
                $durationEn = 'a few moments';
            }
        }

        // 1. Réinitialisation ou modification de mot de passe
        if (str_contains($path, 'password') || str_contains($path, 'forgot-password') || str_contains($path, 'reset-password')) {
            return $isEn
                ? "You have made several consecutive password requests. For your account security, please wait {$durationEn} before trying again."
                : "Vous avez effectué plusieurs demandes de mot de passe consécutives. Par mesure de sécurité pour votre compte, veuillez patienter {$durationFr} avant de réessayer.";
        }

        // 2. Connexion
        if (str_contains($path, 'login')) {
            return $isEn
                ? "Too many consecutive sign-in attempts were detected. For your account security, please wait {$durationEn} before trying again."
                : "Plusieurs tentatives de connexion consécutives ont été constatées. Par mesure de sécurité pour votre compte, veuillez patienter {$durationFr} avant de vous reconnecter.";
        }

        // 3. Inscription / Création de compte
        if (str_contains($path, 'register')) {
            return $isEn
                ? "Multiple registration attempts were detected. For security reasons, please wait {$durationEn} before submitting your registration again."
                : "Plusieurs tentatives d'inscription ont été effectuées. Par mesure de sécurité, veuillez patienter {$durationFr} avant de renouveler votre inscription.";
        }

        // 4. Code de vérification / OTP
        if (str_contains($path, 'otp') || str_contains($path, 'verify') || str_contains($path, 'resend')) {
            return $isEn
                ? "Too many verification code attempts were made. For security reasons, please wait {$durationEn} before requesting or entering a new code."
                : "Vous avez demandé ou saisi trop de codes de vérification consécutifs. Par sécurité, veuillez patienter {$durationFr} avant de demander un nouveau code.";
        }

        // 5. Initiation de paiement
        if (str_contains($path, 'payment') || str_contains($path, 'subscription')) {
            return $isEn
                ? "Multiple payment requests were initiated in a short period. For transaction security, please wait {$durationEn} before trying again."
                : "Plusieurs demandes de paiement ont été initiées en peu de temps. Pour sécuriser votre opération, veuillez patienter {$durationFr} avant de réessayer.";
        }

        // 6. Message général bienveillant et clair
        return $isEn
            ? "Too many consecutive attempts. For your security, please wait {$durationEn} before trying again."
            : "Vous avez effectué trop de tentatives consécutives. Par mesure de sécurité, veuillez patienter {$durationFr} avant de renouveler votre opération.";
    }

    /**
     * Convert an authentication exception into a response.
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $redirectTo = $exception->redirectTo($request);
        if ($redirectTo) {
            return redirect()->guest($redirectTo);
        }

        if ($request->is('admin*')) {
            return redirect()->guest(route('filament.admin.auth.login'));
        }

        return redirect()->guest(route('login'));
    }
}
