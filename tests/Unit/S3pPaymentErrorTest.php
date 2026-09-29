<?php

namespace Tests\Unit;

use App\Services\Payments\S3pPaymentError;
use Tests\TestCase;

class S3pPaymentErrorTest extends TestCase
{
    public function test_maps_known_error_codes_to_messages_and_advice(): void
    {
        // 703201 - Timeout
        $this->assertEquals('Délai USSD expiré', S3pPaymentError::label('703201'));
        $this->assertStringContainsString('délai de confirmation', S3pPaymentError::message('703201'));
        $this->assertTrue(S3pPaymentError::canRetry('703201'));

        // 703107 - Solde insuffisant
        $this->assertEquals('Solde insuffisant', S3pPaymentError::label('703107'));
        $this->assertStringContainsString('solde', S3pPaymentError::message('703107'));
        $this->assertStringContainsString('recharger', S3pPaymentError::advice('703107'));
        $this->assertTrue(S3pPaymentError::canRetry('703107'));

        // 703103 - Compte bloqué
        $this->assertEquals('Compte opérateur bloqué', S3pPaymentError::label('703103'));
        $this->assertFalse(S3pPaymentError::canRetry('703103'));

        // 703203 - PIN incorrect
        $this->assertEquals('Code PIN incorrect', S3pPaymentError::label('703203'));
        $this->assertStringContainsString('code secret', S3pPaymentError::message('703203'));

        // 701101 - Opérateur en maintenance
        $this->assertEquals('Échec de transaction', S3pPaymentError::label('701101'));

        // Default fallback
        $this->assertEquals('Échec de transaction', S3pPaymentError::label('999999'));
        $this->assertFalse(S3pPaymentError::canRetry('999999'));
    }

    public function test_to_array_returns_structured_payload(): void
    {
        $payload = S3pPaymentError::toArray('703107');

        $this->assertEquals('703107', $payload['error_code']);
        $this->assertEquals('Solde insuffisant', $payload['error_label']);
        $this->assertArrayHasKey('message', $payload);
        $this->assertArrayHasKey('action_advice', $payload);
        $this->assertTrue($payload['can_retry']);
    }
    public function test_unknown_codes_do_not_claim_no_debit_or_allow_retry(): void
    {
        foreach ([null, '', '0', '999999', '703204', '703102', '703108', '702104', '701100', '701101', '705000'] as $code) {
            $this->assertFalse(S3pPaymentError::canRetry($code));
            $this->assertStringNotContainsString('Aucun montant', S3pPaymentError::message($code));
            $this->assertStringContainsString('référence PEK', S3pPaymentError::message($code));
        }
        $this->assertStringStartsWith('Orange Money :', S3pPaymentError::message('703107', 'orange_money'));
        $this->assertStringStartsWith('MTN MoMo :', S3pPaymentError::message('703107', 'mtn_momo'));
        $this->assertStringNotContainsString('<script>', S3pPaymentError::message(null, '<script>'));
    }

}
