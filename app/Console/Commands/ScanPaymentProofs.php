<?php

namespace App\Console\Commands;

use App\Models\PaymentProof;
use App\Services\Payments\PaymentAudit;
use App\Services\Payments\ProofScanner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ScanPaymentProofs extends Command
{
    protected $signature = 'payments:scan-proofs';
    protected $description = 'Analyser les justificatifs en quarantaine avec ClamAV.';
    public function handle(ProofScanner $scanner): int
    {
        if (!config('payments.scanner_binary')) {
            $this->error('PAYMENT_PROOF_SCANNER absent : les documents restent en quarantaine.');
            return self::FAILURE;
        }
        PaymentProof::where('scan_status', 'quarantined')->limit(50)->get()->each(function ($proof) use ($scanner) {
            $path = Storage::disk('payment_private')->path($proof->path);
            if (!is_file($path) || !hash_equals($proof->sha256, hash_file('sha256', $path))) $status = 'infected';
            else $status = $scanner->scan($path);
            $proof->update(['scan_status' => $status]);
            PaymentAudit::record($proof->subscription_id, 'proof_scanned', ['proof_id' => $proof->id, 'status' => $status]);
        });
        return self::SUCCESS;
    }
}
