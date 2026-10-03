<?php

namespace App\Console\Commands;

use App\Services\Payments\S3pGateway;
use Illuminate\Console\Command;

class CheckS3pConnection extends Command
{
    protected $signature = 'payments:s3p-check {--network : Lire ping et le catalogue cashout, sans devis ni dÃ©bit}';

    protected $description = 'ContrÃ´ler la configuration S3P sans afficher les secrets et sans encaisser.';

    public function handle(S3pGateway $gateway): int
    {
        $testCreditAllowed = (app()->environment('staging') || config('payments.s3p.credit_test_parts') === true) && $gateway->isStaging();

        $this->table(['ContrÃ´le', 'Ã‰tat'], [
            ['Environnement Laravel', app()->environment()],
            ['Mode fournisseur', $gateway->isStaging() ? 'staging â€” aucune part rÃ©elle' : 'hors staging â€” vÃ©rifier le compte marchand'],
            ['S3P activÃ©', config('payments.s3p.enabled') ? 'oui' : 'non'],
            ['ClÃ© publique configurÃ©e', config('payments.s3p.public_key') ? 'oui' : 'non'],
            ['ClÃ© secrÃ¨te configurÃ©e', config('payments.s3p.secret_key') ? 'oui' : 'non'],
            ['Secret callback configurÃ©', config('payments.s3p.webhook_secret') ? 'oui' : 'non'],
            ['Fuseau callback valide', in_array(config('payments.s3p.timestamp_timezone'), \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), true) ? 'oui â€” confirmation fournisseur nÃ©cessaire' : 'non â€” activation production bloquÃ©e'],
            ['Orange configurÃ©', $gateway->available('orange_money') ? 'oui' : 'non ('.$gateway->availabilityReason('orange_money').')'],
            ['MTN configurÃ©', $gateway->available('mtn_momo') ? 'oui' : 'non ('.$gateway->availabilityReason('mtn_momo').')'],
            ['CrÃ©dit de parts de recette', $testCreditAllowed ? 'autorisÃ© (convention de test activÃ©e)' : 'non (mettre S3P_CREDIT_TEST_PARTS=true si test)'],
            ['Date verifytx de recette', $testCreditAllowed && config('payments.s3p.staging_use_provider_timestamp') ? 'autorisÃ©e (convention de test)' : 'non'],
            ['Simulation demandÃ©e', config('payments.s3p.simulation') ? 'oui â€” rÃ©servÃ©e aux tests isolÃ©s' : 'non'],
        ]);
        if (! $this->option('network')) {
            return self::SUCCESS;
        }
        if (! config('payments.s3p.public_key') || ! config('payments.s3p.secret_key') || config('payments.s3p.simulation')) {
            $this->error('Configurer les accÃ¨s S3P et dÃ©sactiver la simulation avant ce contrÃ´le rÃ©seau.');

            return self::FAILURE;
        }
        $enabled = config('payments.s3p.enabled');
        try {
            // CLI diagnostic only: permit reads without enabling payment creation in the application.
            config(['payments.s3p.enabled' => true]);
            $catalog = $gateway->inspectConnection();
            $this->line(json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $this->info('Lecture rÃ©ussie. Aucun devis ni encaissement effectuÃ©.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            $status = method_exists($e, 'response') && $e->response() ? $e->response()->status() : null;
            $this->error('Ã‰chec du contrÃ´le S3P ('.$e::class.($status ? ' HTTP '.$status : '').') : '.$msg);
            $this->warn('Indication : VÃ©rifier que S3P_PUBLIC_KEY et S3P_SECRET_KEY sont bien les identifiants Maviance valides pour lâ€™URL : ' . config('payments.s3p.base_url'));

            return self::FAILURE;
        } finally {
            config(['payments.s3p.enabled' => $enabled]);
        }
    }
}