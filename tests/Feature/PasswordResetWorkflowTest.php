<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_notification_for_valid_user(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'client.test@koriassetmanagement.com',
            'role' => 'client',
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/forgot-password', [
            'email' => 'client.test@koriassetmanagement.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Si un compte correspond à cette adresse, un lien de réinitialisation va être envoyé.',
            ]);

        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            function ($notification) use ($user) {
                return true;
            }
        );
    }

    public function test_forgot_password_returns_generic_message_for_non_existent_email(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/forgot-password', [
            'email' => 'inconnu@koriassetmanagement.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Si un compte correspond à cette adresse, un lien de réinitialisation va être envoyé.',
            ]);

        Notification::assertNothingSent();
    }

    public function test_forgot_password_validates_email_format(): void
    {
        $response = $this->postJson('/api/forgot-password', [
            'email' => 'email-invalide',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_reset_password_succeeds_with_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'investisseur@koriassetmanagement.com',
            'password' => Hash::make('AncienMotDePasse123!'),
            'has_temp_password' => true,
        ]);

        $token = Password::createToken($user);

        $response = $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => 'investisseur@koriassetmanagement.com',
            'password' => 'NouveauMotDePasse2026@Secure',
            'password_confirmation' => 'NouveauMotDePasse2026@Secure',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Votre mot de passe a été réinitialisé.',
            ]);

        $user->refresh();
        $this->assertTrue(Hash::check('NouveauMotDePasse2026@Secure', $user->password));
        $this->assertFalse((bool) $user->has_temp_password);
    }

    public function test_reset_password_fails_with_invalid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'investisseur@koriassetmanagement.com',
            'password' => Hash::make('AncienMotDePasse123!'),
        ]);

        $response = $this->postJson('/api/reset-password', [
            'token' => 'token-completement-faux',
            'email' => 'investisseur@koriassetmanagement.com',
            'password' => 'NouveauMotDePasse2026@Secure',
            'password_confirmation' => 'NouveauMotDePasse2026@Secure',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Le lien de réinitialisation est invalide ou expiré.',
            ]);
    }

    public function test_reset_password_requires_minimum_12_characters(): void
    {
        $user = User::factory()->create([
            'email' => 'investisseur@koriassetmanagement.com',
        ]);

        $token = Password::createToken($user);

        $response = $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => 'investisseur@koriassetmanagement.com',
            'password' => 'Court123!',
            'password_confirmation' => 'Court123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_reset_password_requires_matching_confirmation(): void
    {
        $user = User::factory()->create([
            'email' => 'investisseur@koriassetmanagement.com',
        ]);

        $token = Password::createToken($user);

        $response = $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => 'investisseur@koriassetmanagement.com',
            'password' => 'NouveauMotDePasse2026@Secure',
            'password_confirmation' => 'PasLeMemeMotDePasse2026!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_generated_reset_url_format(): void
    {
        $user = User::factory()->create([
            'email' => 'investisseur@koriassetmanagement.com',
        ]);

        $notification = new ResetPassword('test-token-12345');
        $mail = $notification->toMail($user);

        $this->assertNotEmpty($mail->actionUrl);
        $this->assertStringContainsString('/password-reset/test-token-12345', $mail->actionUrl);
        $this->assertStringContainsString('email=investisseur%40koriassetmanagement.com', $mail->actionUrl);
    }

    public function test_forgot_password_in_english(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'client.en@koriassetmanagement.com',
            'role' => 'client',
        ]);

        $response = $this->postJson('/api/forgot-password', [
            'email' => 'client.en@koriassetmanagement.com',
            'lang' => 'en',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'If an account matches this email address, a password reset link has been sent.',
            ]);

        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            function ($notification) use ($user) {
                $mail = $notification->toMail($user);
                return $mail->subject === 'Password Reset for your PEK Account'
                    && ($mail->viewData['isEn'] ?? false) === true;
            }
        );
    }

    public function test_reset_password_in_english(): void
    {
        $user = User::factory()->create([
            'email' => 'client.en@koriassetmanagement.com',
            'password' => Hash::make('OldPassword123!'),
        ]);

        $token = Password::createToken($user);

        $response = $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => 'client.en@koriassetmanagement.com',
            'password' => 'BrandNewPassword2026@Secure',
            'password_confirmation' => 'BrandNewPassword2026@Secure',
            'lang' => 'en',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Your password has been successfully reset.',
            ]);
    }

    public function test_password_reset_mail_includes_civility_for_personne_physique(): void
    {
        Notification::fake();

        // 1. Personne physique avec civilité M. (Français)
        $userM = User::factory()->create([
            'first_name' => 'Ernest',
            'last_name' => 'ESSOME',
            'email' => 'ernest.m@koriassetmanagement.com',
            'civ' => 'M.',
            'nature_client' => 'Personne Physique',
            'role' => 'client',
            'email_verified_at' => now(),
        ]);

        $this->postJson('/api/forgot-password', [
            'email' => 'ernest.m@koriassetmanagement.com',
            'lang' => 'fr',
        ]);

        Notification::assertSentTo($userM, ResetPassword::class, function ($notification) use ($userM) {
            $mail = $notification->toMail($userM);
            return ($mail->viewData['name'] ?? '') === 'M. Ernest ESSOME';
        });

        // 2. Personne physique avec civilité Mme (Français)
        $userMme = User::factory()->create([
            'first_name' => 'Carine',
            'last_name' => 'FOMETHE',
            'email' => 'carine.mme@koriassetmanagement.com',
            'civ' => 'Mme',
            'nature_client' => 'Personne Physique',
            'role' => 'client',
            'email_verified_at' => now(),
        ]);

        $this->postJson('/api/forgot-password', [
            'email' => 'carine.mme@koriassetmanagement.com',
            'lang' => 'fr',
        ]);

        Notification::assertSentTo($userMme, ResetPassword::class, function ($notification) use ($userMme) {
            $mail = $notification->toMail($userMme);
            return ($mail->viewData['name'] ?? '') === 'Mme Carine FOMETHE';
        });

        // 3. Personne physique en anglais
        $this->postJson('/api/forgot-password', [
            'email' => 'ernest.m@koriassetmanagement.com',
            'lang' => 'en',
        ]);

        Notification::assertSentTo($userM, ResetPassword::class, function ($notification) use ($userM) {
            $mail = $notification->toMail($userM);
            return ($mail->viewData['name'] ?? '') === 'Mr. Ernest ESSOME';
        });

        // 4. Personne morale (aucune civilité)
        $userMorale = User::factory()->create([
            'first_name' => 'E-JABBING',
            'last_name' => 'SARL',
            'email' => 'contact.morale@koriassetmanagement.com',
            'nature_client' => 'Personne Morale',
            'role' => 'client',
            'email_verified_at' => now(),
        ]);

        $this->postJson('/api/forgot-password', [
            'email' => 'contact.morale@koriassetmanagement.com',
            'lang' => 'fr',
        ]);

        Notification::assertSentTo($userMorale, ResetPassword::class, function ($notification) use ($userMorale) {
            $mail = $notification->toMail($userMorale);
            return ($mail->viewData['name'] ?? '') === 'E-JABBING SARL';
        });
    }
}
