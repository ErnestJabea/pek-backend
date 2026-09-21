<?php

namespace App\Http\Controllers;

use App\Models\PaymentProof;
use App\Models\Subscription;
use App\Services\Payments\PaymentAudit;
use App\Services\Payments\ProofScanner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PaymentProofController extends Controller
{
    public function index(Request $request, int $id)
    {
        $sub = $request->user()->subscriptions()->findOrFail($id);

        return response()->json($sub->paymentProofs()->latest()->get())->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request, int $id, ProofScanner $scanner)
    {
        $sub = $request->user()->subscriptions()->findOrFail($id);
        abort_unless(in_array($sub->moyen_paiement, ['bank_transfer', 'virement'], true), 422);
        abort_if($sub->funds_received_at || $sub->statut === 'Succès', 409, 'Ce virement est déjà confirmé.');
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.config('payments.proof_max_kb')],
            'declared_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'declared_amount' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'declared_reference' => ['nullable', 'string', 'max:120'],
        ]);
        $file = $request->file('file');
        $mime = $file->getMimeType();
        $extension = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'][$mime] ?? null;
        abort_unless($extension, 422, 'Format de justificatif non accepté.');
        if ($extension === 'pdf') {
            abort_unless(file_get_contents($file->getRealPath(), false, null, 0, 5) === '%PDF-', 422, 'PDF invalide.');
        } else {
            $image = @getimagesize($file->getRealPath());
            abort_unless($image && $image[0] * $image[1] <= 25000000 && $image['mime'] === $mime, 422, 'Image invalide ou trop grande.');
        }
        $path = $file->storeAs((string) $sub->id, Str::uuid().'.'.$extension, 'payment_private');
        try {
            $status = $scanner->scan(Storage::disk('payment_private')->path($path));
            $proof = DB::transaction(function () use ($sub, $request, $file, $path, $mime, $status, $data) {
                $locked = Subscription::lockForUpdate()->findOrFail($sub->id);
                abort_if($locked->funds_received_at || $locked->statut === 'Succès', 409);
                abort_if($locked->paymentProofs()->count() >= 10, 422, 'Limite de justificatifs atteinte. Contactez le support.');
                $proof = $locked->paymentProofs()->create([
                    'user_id' => $request->user()->id, 'path' => $path,
                    'original_name' => Str::limit(basename($file->getClientOriginalName()), 180, ''),
                    'mime' => $mime, 'size' => $file->getSize(),
                    'sha256' => hash_file('sha256', $file->getRealPath()), 'scan_status' => $status,
                    'declared_date' => $data['declared_date'], 'declared_amount' => $data['declared_amount'],
                    'declared_reference' => $data['declared_reference'] ?? null,
                ]);
                PaymentAudit::record($sub->id, 'proof_uploaded', ['proof_id' => $proof->id, 'scan_status' => $status], $request->user()->id);

                return $proof;
            });
        } catch (\Throwable $e) {
            Storage::disk('payment_private')->delete($path);
            throw $e;
        }

        return response()->json(['message' => 'Justificatif reçu. La réception des fonds doit encore être vérifiée.', 'proof' => $proof], 201);
    }

    public function download(Request $request, PaymentProof $proof)
    {
        abort_unless($request->user()->id === $proof->user_id || $request->user()->can('view_payment_proof'), 404);
        abort_unless($proof->scan_status === 'clean', 423, 'Document en quarantaine ou rejeté par le contrôle de sécurité.');
        $path = Storage::disk('payment_private')->path($proof->path);
        abort_unless(is_file($path) && hash_equals($proof->sha256, hash_file('sha256', $path)), 423, 'Intégrité du justificatif non vérifiée.');
        PaymentAudit::record($proof->subscription_id, 'proof_downloaded', ['proof_id' => $proof->id], $request->user()->id);
        $extension = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'][$proof->mime];

        return Storage::disk('payment_private')->download($proof->path, 'justificatif-'.$proof->id.'.'.$extension, [
            'Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private', 'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
