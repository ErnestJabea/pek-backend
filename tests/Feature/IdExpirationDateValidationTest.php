<?php

namespace Tests\Feature;

use App\Http\Requests\StoreKYCRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class IdExpirationDateValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_rejects_past_expiration_date(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/register', [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean.dupont@example.test',
            'city' => 'Douala',
            'country' => 'Cameroun',
            'type_piece' => 'CNI',
            'num_piece' => '1122334455',
            'expiration_piece' => '2026-05-05', // Past date as in user screenshot
            'password' => 'SecurePass1234!',
            'password_confirmation' => 'SecurePass1234!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['expiration_piece']);

        $errors = $response->json('errors.expiration_piece');
        $this->assertStringContainsString('au moins 1 mois', $errors[0]);
    }

    public function test_registration_rejects_expiration_date_less_than_one_month(): void
    {
        Mail::fake();

        // 10 days from now is strictly < 1 month
        $expDate = now()->addDays(10)->toDateString();

        $response = $this->postJson('/api/register', [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean.dupont2@example.test',
            'city' => 'Douala',
            'country' => 'Cameroun',
            'type_piece' => 'CNI',
            'num_piece' => '1122334455',
            'expiration_piece' => $expDate,
            'password' => 'SecurePass1234!',
            'password_confirmation' => 'SecurePass1234!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['expiration_piece']);

        $errors = $response->json('errors.expiration_piece');
        $this->assertStringContainsString('au moins 1 mois', $errors[0]);
    }

    public function test_registration_accepts_valid_expiration_date(): void
    {
        Mail::fake();

        // 6 months from now is well above 1 month
        $expDate = now()->addMonths(6)->toDateString();

        $response = $this->postJson('/api/register', [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean.dupont3@example.test',
            'city' => 'Douala',
            'country' => 'Cameroun',
            'type_piece' => 'CNI',
            'num_piece' => '1122334455',
            'expiration_piece' => $expDate,
            'password' => 'SecurePass1234!',
            'password_confirmation' => 'SecurePass1234!',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message', 'challenge_id']);
    }

    public function test_store_kyc_request_rule_enforces_one_month_validity(): void
    {
        $rules = (new StoreKYCRequest)->rules();
        $messages = (new StoreKYCRequest)->messages();

        // 1. Past date
        $validatorPast = Validator::make(
            ['expiration_piece' => '2026-05-05'],
            ['expiration_piece' => $rules['expiration_piece']],
            $messages
        );
        $this->assertTrue($validatorPast->fails());

        // 2. Date within 15 days
        $validatorSoon = Validator::make(
            ['expiration_piece' => now()->addDays(15)->toDateString()],
            ['expiration_piece' => $rules['expiration_piece']],
            $messages
        );
        $this->assertTrue($validatorSoon->fails());
        $this->assertStringContainsString('au moins 1 mois', $validatorSoon->errors()->first('expiration_piece'));

        // 3. Date 2 years in the future
        $validatorFuture = Validator::make(
            ['expiration_piece' => now()->addYears(2)->toDateString()],
            ['expiration_piece' => $rules['expiration_piece']],
            $messages
        );
        $this->assertFalse($validatorFuture->fails());
    }

    public function test_renew_identity_document_enforces_one_month_validity(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'type_piece' => 'CNI',
            'num_piece' => 'OLD-12345',
            'expiration_piece' => now()->addDays(5)->toDateString(),
        ]);

        // Attempt renewal with date < 1 month
        $responseReject = $this->actingAs($user)->postJson('/api/user/renew-identity-document', [
            'type_piece' => 'Passeport',
            'num_piece' => 'NEW-98765',
            'expiration_piece' => now()->addDays(10)->toDateString(),
        ]);

        $responseReject->assertStatus(422)
            ->assertJsonValidationErrors(['expiration_piece']);

        // Renewal with valid date (> 1 month)
        $validExpDate = now()->addYears(5)->toDateString();
        $responseAccept = $this->actingAs($user)->postJson('/api/user/renew-identity-document', [
            'type_piece' => 'Passeport',
            'num_piece' => 'NEW-98765',
            'expiration_piece' => $validExpDate,
        ]);

        $responseAccept->assertOk()
            ->assertJsonPath('user.expiration_piece', $validExpDate)
            ->assertJsonPath('user.num_piece', 'NEW-98765');
    }
}
