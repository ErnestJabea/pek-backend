<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Services\Payments\PaymentAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class S3pWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        $secret = (string) config('payments.s3p.webhook_secret');
        abort_unless(config('payments.s3p.enabled') && $secret !== '', 503);
        abort_if(strlen($request->getContent()) > 16384, 413);
        $signature = (string) $request->header('X-Signature', '');
        abort_unless(preg_match('/^[a-f0-9]{40}$/', $signature) && hash_equals(hash_hmac('sha1', $request->getContent(), $secret), $signature), 403);
        abort_unless($request->isJson(), 415);
        $data = $request->validate([
            'trid' => ['required', 'string', 'max:36'],
            'timestamp' => ['required', 'string', 'max:64'],
            'status' => ['required', Rule::in(['SUCCESS', 'ERRORED', 'ERROR', 'REVERSED', 'PENDING'])],
            'errorCode' => ['present', 'nullable', 'regex:/^\d{1,10}$/D'],
        ]);
        // The legacy callback guide uses ERROR; verifytx remains authoritative.
        if ($data['status'] === 'ERROR') {
            $data['status'] = 'ERRORED';
        }
        $ptn = $request->header('X-Ptn');
        $delivery = $request->header('X-Delivery');
        validator(['ptn' => $ptn, 'delivery' => $delivery], [
            'ptn' => ['required', 'string', 'regex:/^[A-Za-z0-9._:@-]{1,100}$/D'],
            'delivery' => ['required', 'uuid'],
        ])->validate();
        $sub = Subscription::where('s3p_reference', $data['trid'])->first();
        if (! $sub) {
            return response()->json(['received' => true]);
        }
        abort_if($sub->s3p_ptn && $sub->s3p_ptn !== $ptn, 409, 'PTN incohérent.');
        // Persist before acknowledging. No external API call in the webhook request.
        // Deduplication is based on signed bytes, not on unsigned delivery headers.
        DB::transaction(function () use ($request, $sub, $ptn, $delivery, $data) {
            $hash = hash('sha256', $request->getContent());
            $inserted = DB::table('s3p_callback_inbox')->insertOrIgnore([
                'subscription_id' => $sub->id, 'body_hash' => $hash, 'delivery_id' => $delivery,
                'ptn' => $ptn, 'provider_status' => $data['status'], 'provider_timestamp' => $data['timestamp'],
                'error_code' => (string) $data['errorCode'], 'received_at' => now(), 'next_attempt_at' => now(),
            ]);
            if ($inserted) {
                PaymentAudit::record($sub->id, 'mobile_callback_received', ['body_hash' => $hash, 'status' => $data['status']]);
            }
        });

        return response()->json(['received' => true]);
    }
}
