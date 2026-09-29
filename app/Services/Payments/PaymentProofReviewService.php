<?php

namespace App\Services\Payments;

use App\Models\Notification;
use App\Models\PaymentProof;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PaymentProofReviewService
{
    public function review(PaymentProof $proof, User $actor, array $data): void
    {
        abort_unless($actor->can('review_payment_proof'), 403);
        validator($data, [
            'status' => ['required', 'in:examined,replacement_requested'],
            'note' => ['required', 'string', 'max:2000'],
        ])->validate();

        DB::transaction(function () use ($proof, $actor, $data) {
            $proof = PaymentProof::lockForUpdate()->findOrFail($proof->id);
            if ($data['status'] === 'examined') {
                if ($proof->scan_status !== 'clean') {
                    throw ValidationException::withMessages(['status' => 'Document non consultable : une analyse antivirus concluante est requise. Vous pouvez demander un remplacement.']);
                }
                $path = Storage::disk('payment_private')->path($proof->path);
                if (! is_file($path) || ! hash_equals($proof->sha256, hash_file('sha256', $path))) {
                    throw ValidationException::withMessages(['status' => 'Intégrité du justificatif non vérifiée. Demandez un remplacement.']);
                }
            }
            $proof->update(['review_status' => $data['status'], 'review_note' => $data['note'], 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);
            PaymentAudit::record($proof->subscription_id, 'proof_reviewed', ['proof_id' => $proof->id, 'status' => $data['status'], 'note' => $data['note']], $actor->id);
            Notification::create(['user_id' => $proof->user_id, 'title' => $data['status'] === 'replacement_requested' ? 'Nouveau justificatif de virement demandé' : 'Justificatif de virement examiné', 'body' => $data['note'], 'type' => 'subscription']);
        });
    }
}
