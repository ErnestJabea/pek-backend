<?php

namespace Tests\Feature;

use App\Mail\OtpMail;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthenticationSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_returns_an_opaque_challenge_and_hashes_otp(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/register', [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.test',
            'city' => 'Yaoundé',
            'country' => 'Cameroun',
            'password' => 'a-secure-password',
            'password_confirmation' => 'a-secure-password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message', 'challenge_id'])
            ->assertJsonMissingPath('otp_debug')
            ->assertJsonMissingPath('code');

        $otp = OtpCode::firstOrFail();
        $this->assertSame($otp->challenge_id, $response->json('challenge_id'));
        $this->assertNotSame(6, strlen($otp->code));

        Mail::assertSent(OtpMail::class, function (OtpMail $mail) use ($otp) {
            return Hash::check($mail->otpCode, $otp->code);
        });
    }

    public function test_registration_requires_password_confirmation(): void
    {
        Mail::fake();

        // Missing confirmation
        $response = $this->postJson('/api/register', [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada-conf@example.test',
            'city' => 'Yaoundé',
            'country' => 'Cameroun',
            'password' => 'a-secure-password',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        // Mismatched confirmation
        $responseMismatch = $this->postJson('/api/register', [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada-conf@example.test',
            'city' => 'Yaoundé',
            'country' => 'Cameroun',
            'password' => 'a-secure-password',
            'password_confirmation' => 'a-different-password',
        ]);
        $responseMismatch->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_otp_is_single_use_and_no_plain_token_is_returned(): void
    {
        Mail::fake();
        $user = User::create([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.test',
            'password' => bcrypt('a-secure-password'),
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $login = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'a-secure-password',
        ])->assertOk();
        $otp = OtpCode::firstOrFail();
        $plainCode = null;
        Mail::assertSent(OtpMail::class, function (OtpMail $mail) use (&$plainCode) {
            $plainCode = $mail->otpCode;

            return true;
        });

        $verification = $this->postJson('/api/verify-otp', [
            'challenge_id' => $login->json('challenge_id'),
            'code' => $plainCode,
        ]);
        $verification->assertOk()
            ->assertJsonPath('access_token', 'cookie_session');
        $this->assertNotNull($otp->fresh()->consumed_at);

        $this->postJson('/api/verify-otp', [
            'challenge_id' => $login->json('challenge_id'),
            'code' => $plainCode,
        ])->assertUnprocessable();
    }

    public function test_unknown_resend_does_not_reveal_account_existence_or_create_otp(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/resend-otp', ['email' => 'unknown@example.test']);

        $response->assertOk()
            ->assertJsonPath('message', 'Un nouveau code a été envoyé.')
            ->assertJsonStructure(['challenge_id']);
        $this->assertDatabaseCount('otp_codes', 0);
        Mail::assertNothingSent();
    }

    public function test_cookie_authenticated_writes_require_a_trusted_browser_origin(): void
    {
        $user = User::create([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.test',
            'password' => bcrypt('a-secure-password'),
        ]);
        $token = $user->createToken('browser')->plainTextToken;
        $payload = [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'city' => 'Yaoundé',
            'country' => 'Cameroun',
        ];

        $this->withCredentials()
            ->withCookie('auth_token', $token)
            ->withHeader('Origin', 'https://evil.example')
            ->postJson('/api/update-profile', $payload)
            ->assertForbidden()
            ->assertJsonPath('message', 'Origine de requête non autorisée.');

        $this->withCredentials()
            ->withCookie('auth_token', $token)
            ->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/update-profile', $payload)
            ->assertOk();
    }
}
