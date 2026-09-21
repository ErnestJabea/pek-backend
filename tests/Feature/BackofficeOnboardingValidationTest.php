<?php

namespace Tests\Feature;

use App\Filament\Resources\OnboardingSessionResource\Pages\ViewOnboardingSession;
use App\Mail\OnboardingValidatedMail;
use App\Models\OnboardingSession;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BackofficeOnboardingValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['identity_verification.required' => false]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Mail::fake();
        Storage::fake('kyc_private');
    }

    public function test_backoffice_validates_session_even_with_missing_documents(): void
    {
        $admin = $this->createAdmin();
        $session = $this->createCompletedSession();

        Livewire::actingAs($admin)
            ->test(ViewOnboardingSession::class, ['record' => $session->getRouteKey()])
            ->callAction('validate')
            ->assertNotified('Dossier d\'onboarding validé avec succès !');

        $this->assertSame('validated', $session->fresh()->status);
        $this->assertSame(array_values(OnboardingSession::REQUIRED_DOCUMENTS), $session->fresh()->missingRequiredDocuments());
        $this->assertDatabaseHas('onboarding_events', [
            'onboarding_session_id' => $session->id,
            'actor_user_id' => $admin->id,
            'event_type' => 'validated',
            'from_status' => 'completed',
            'to_status' => 'validated',
        ]);
        Mail::assertSent(OnboardingValidatedMail::class, 1);
    }

    public function test_backoffice_validates_only_when_every_required_document_exists(): void
    {
        $admin = $this->createAdmin();
        $session = $this->createCompletedSession();
        $documentPaths = [];

        foreach (array_keys(OnboardingSession::REQUIRED_DOCUMENTS) as $attribute) {
            $path = "secure_onboardings/documents/{$session->id}-{$attribute}.pdf";
            Storage::disk('kyc_private')->put($path, 'test-document');
            $documentPaths[$attribute] = $path;
        }

        $session->update($documentPaths);

        Livewire::actingAs($admin)
            ->test(ViewOnboardingSession::class, ['record' => $session->getRouteKey()])
            ->callAction('validate')
            ->assertNotified('Dossier d\'onboarding validé avec succès !');

        $this->assertSame([], $session->fresh()->missingRequiredDocuments());
        $this->assertSame('validated', $session->fresh()->status);
        $this->assertDatabaseHas('onboarding_events', [
            'onboarding_session_id' => $session->id,
            'actor_user_id' => $admin->id,
            'event_type' => 'validated',
            'from_status' => 'completed',
            'to_status' => 'validated',
        ]);
        Mail::assertSent(OnboardingValidatedMail::class, 1);
    }

    private function createAdmin(): User
    {
        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'PEK',
            'email' => 'admin-validation@example.test',
            'password' => 'a-secure-password',
            'role' => 'admin',
        ]);
        $admin->assignRole('super_admin');

        return $admin;
    }

    private function createCompletedSession(): OnboardingSession
    {
        $client = User::create([
            'first_name' => 'Client',
            'last_name' => 'Test',
            'email' => 'client-validation@example.test',
            'password' => 'a-secure-password',
            'role' => 'client',
        ]);

        return $client->onboardingSession()->create([
            'status' => 'completed',
            'current_step' => 'completed',
            'payload' => [],
        ]);
    }
}
