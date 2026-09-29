<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiagnoseS3pPayment extends Command
{
    protected $signature = 'payments:s3p-diagnose {subscription : Identifiant de souscription}';

    protected $description = 'Lire le diagnostic enregistré sans appel Maviance, débit ou modification.';

    public function handle(): int
    {
        $id = (string) $this->argument('subscription');
        if (! ctype_digit($id) || ! ($sub = Subscription::find($id)) || ! $sub->mobile_provider) {
            $this->error('Souscription mobile introuvable.');

            return self::FAILURE;
        }
        $testPhone = match ($sub->mobile_provider) {
            'orange_money' => '237697123000',
            'mtn_momo' => '237650134682',
            default => null,
        };
        try {
            $number = preg_replace('/\D/', '', (string) $sub->payment_phone);
            $number = strlen($number) === 9 ? '237'.$number : $number;
            $matches = $testPhone !== null && $number === $testPhone;
        } catch (\Throwable) {
            $matches = null;
        }
        $events = DB::table('payment_events')->where('subscription_id', $sub->id)
            ->where('type', 'mobile_check_required')->orderByDesc('id')->limit(5)->get(['details', 'created_at']);
        $diagnostics = $events->map(function ($event) {
            $details = json_decode($event->details, true);

            return ['created_at' => $event->created_at] + array_intersect_key(is_array($details) ? $details : [],
                array_flip(['exception', 'phase', 'http_status', 'provider_code', 'reason']));
        })->all();
        $this->line(json_encode([
            'id' => $sub->id, 'mobile_state' => $sub->mobile_state,
            'reference' => $sub->s3p_reference, 'ptn_present' => ! empty($sub->s3p_ptn),
            'numero_recette_fourni_correspond' => $matches,
            'callbacks_enregistres' => DB::table('s3p_callback_inbox')->where('subscription_id', $sub->id)->count(),
            'diagnostics' => $diagnostics,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $this->info('Lecture locale uniquement. Aucun appel fournisseur ni modification.');
        if ($events->isEmpty()) {
            $this->info('Aucune erreur de lancement enregistrée pour cette souscription.');
        } elseif (! isset($diagnostics[0]['phase'])) {
            $this->warn('Ancien événement : le détail du refus initial ne peut pas être reconstitué par cette commande.');
        }

        return self::SUCCESS;
    }
}
