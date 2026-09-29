<?php

namespace App\Services\Payments;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Throwable;

final class S3pFailureDiagnostic
{
    public static function from(Throwable $error): array
    {
        // Never persist exception messages, response bodies, URLs or request headers.
        $data = ['exception' => $error::class];
        if ($error instanceof RequestException) {
            $data['http_status'] = $error->response->status();
            $code = $error->response->json('respCode');
            if ((is_int($code) || is_string($code)) && preg_match('/^\d{1,12}$/D', (string) $code)) {
                $data['provider_code'] = (string) $code;
            }
            $data['reason'] = 'provider_http_error';
        } elseif ($error instanceof ConnectionException) {
            $data['reason'] = 'connection_error';
        } else {
            $data['reason'] = match ($error->getMessage()) {
                'Devis expiré avant exécution.' => 'quote_expired_before_collect',
                'Réponse d’encaissement incohérente. Vérification obligatoire.' => 'invalid_collect_response',
                'Contexte de transaction absent ou modifié.' => 'context_mismatch',
                'Numéro de portefeuille invalide.' => 'invalid_wallet',
                'Autorités TLS indisponibles.' => 'ca_bundle_unavailable',
                'Jeton S3P invalide.' => 'invalid_access_token',
                'Le devis S3P ne correspond pas au total confirmé.' => 'quote_mismatch',
                'Devis expiré ou expiration absente.' => 'quote_expiry_invalid',
                'Le service doit désigner un seul produit d’encaissement.' => 'catalog_selection_invalid',
                default => 'technical_error',
            };
        }

        return $data;
    }
}
