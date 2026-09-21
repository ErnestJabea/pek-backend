<?php

namespace App\Console\Commands;

use App\Models\OnboardingSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MigrateKycPrivateStorage extends Command
{
    protected $signature = 'kyc:migrate-private-storage {--delete-source : Supprimer les copies publiques après vérification}';

    protected $description = 'Copie les documents KYC historiques vers le disque privé et vérifie leur intégrité';

    public function handle(): int
    {
        $target = Storage::disk('kyc_private');
        $sources = [Storage::disk('public'), Storage::disk('local')];
        $migrated = 0;

        // First quarantine every historical file, including orphaned documents that are no longer referenced in DB.
        foreach ($sources as $source) {
            foreach ($source->allFiles('secure_onboardings') as $sourcePath) {
                $targetPath = $this->targetPath($sourcePath);
                $content = $source->get($sourcePath);
                $target->put($targetPath, $content);
                if (hash('sha256', $content) !== hash('sha256', $target->get($targetPath))) {
                    throw new RuntimeException("Échec de vérification de {$sourcePath}.");
                }
                if ($this->option('delete-source')) {
                    $source->delete($sourcePath);
                }
                $migrated++;
            }
        }

        OnboardingSession::query()->orderBy('id')->each(function (OnboardingSession $session) use ($target, $sources, &$migrated) {
            $paths = array_filter([
                $session->signature_path,
                $session->doc_piece_identite,
                $session->doc_justificatif_domicile,
                $session->doc_photo,
                $session->doc_origine_fonds,
                'secure_onboardings/kyc_'.$session->id.'.pdf',
                'secure_onboardings/risk_'.$session->id.'.pdf',
                'secure_onboardings/labft_'.$session->id.'.pdf',
            ]);

            foreach ($paths as $sourcePath) {
                $targetPath = $this->targetPath($sourcePath);

                foreach ($sources as $source) {
                    if (! $source->exists($sourcePath)) {
                        continue;
                    }

                    $content = $source->get($sourcePath);
                    $target->put($targetPath, $content);
                    if (! $target->exists($targetPath) || hash('sha256', $content) !== hash('sha256', $target->get($targetPath))) {
                        throw new RuntimeException("Échec de vérification de {$sourcePath}.");
                    }

                    if ($this->option('delete-source')) {
                        $source->delete($sourcePath);
                    }
                    $migrated++;
                    break;
                }
            }
        });

        $this->info("{$migrated} document(s) KYC migré(s) et vérifié(s) sur le disque privé.");

        return self::SUCCESS;
    }

    private function targetPath(string $sourcePath): string
    {
        foreach (['kyc_', 'risk_', 'labft_'] as $prefix) {
            if (str_starts_with($sourcePath, 'secure_onboardings/'.$prefix)) {
                return 'generated/'.basename($sourcePath);
            }
        }

        return $sourcePath;
    }
}
