<?php

namespace App\Console\Commands;

use App\Services\Payments\S3pGateway;
use Illuminate\Console\Command;

class CheckS3pConnection extends Command
{
    protected $signature = 'payments:s3p-check {--network : Lire ping et le catalogue cashout, sans devis ni débit}';
    protected $description = 'Contrôler la configuration S3P sans afficher les secrets et sans encaisser.';

    public function handle(S3pGateway $gateway): int
    {
        $this->table(['Contrôle', 'État'], [
            ['S3P activé', config('payments.s3p.enabled') ? 'oui' : 'non'],
            ['Clé publique configurée', config('payments.s3p.public_key') ? 'oui' : 'non'],
            ['Clé secrète configurée', config('payments.s3p.secret_key') ? 'oui' : 'non'],
            ['Secret callback configuré', config('payments.s3p.webhook_secret') ? 'oui' : 'non'],
            ['Orange configuré', $gateway->available('orange_money') ? 'oui' : 'non'],
            ['MTN configuré', $gateway->available('mtn_momo') ? 'oui' : 'non'],
            ['Simulation demandée', config('payments.s3p.simulation') ? 'oui — réservée aux tests isolés' : 'non'],
        ]);
        if (!$this->option('network')) return self::SUCCESS;
        if (!config('payments.s3p.public_key') || !config('payments.s3p.secret_key') || config('payments.s3p.simulation')) {
            $this->error('Configurer le staging S3P et désactiver la simulation avant ce contrôle réseau.');
            return self::FAILURE;
        }
        $enabled = config('payments.s3p.enabled');
        try {
            // CLI diagnostic only: permit reads without enabling payment creation in the application.
            config(['payments.s3p.enabled' => true]);
            $catalog = $gateway->inspectConnection();
            $this->line(json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $this->info('Lecture réussie. Aucun devis ni encaissement effectué.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Échec du contrôle S3P ('.$e::class.'). Vérifier TLS, les accès et la configuration ; aucun secret affiché.');
            return self::FAILURE;
        } finally {
            config(['payments.s3p.enabled' => $enabled]);
        }
    }
}
