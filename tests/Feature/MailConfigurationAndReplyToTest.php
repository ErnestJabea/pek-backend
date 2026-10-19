<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageNotificationSettings;
use App\Mail\OnboardingReminderMail;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class MailConfigurationAndReplyToTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_mail_has_configured_reply_to_address(): void
    {
        SystemSetting::set('mail_reply_to_email', 'support.client@koriassetmanagement.com', 'string');
        SystemSetting::set('mail_reply_to_name', 'Support KORI Test', 'string');

        $user = User::factory()->create([
            'email' => 'client.test@koriassetmanagement.com',
            'first_name' => 'Jean',
        ]);

        $notification = new ResetPassword('token-xyz-123');
        $mail = $notification->toMail($user);

        $this->assertNotEmpty($mail->replyTo);
        $this->assertEquals('support.client@koriassetmanagement.com', $mail->replyTo[0][0]);
        $this->assertEquals('Support KORI Test', $mail->replyTo[0][1]);
    }

    public function test_standard_mailable_inherits_configured_reply_to(): void
    {
        SystemSetting::set('mail_reply_to_email', 'service.client@koriassetmanagement.com', 'string');
        SystemSetting::set('mail_reply_to_name', 'Service Client PEK', 'string');

        Mail::fake();

        $user = User::factory()->create([
            'email' => 'destinataire@koriassetmanagement.com',
            'first_name' => 'Paul',
        ]);

        Mail::to($user->email)->send(new OnboardingReminderMail($user));

        Mail::assertSent(OnboardingReminderMail::class, function ($mail) {
            $mail->build();
            return $mail->hasReplyTo('service.client@koriassetmanagement.com');
        });
    }

    public function test_manage_notification_settings_mount_and_system_setting_persistence(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin.test@koriassetmanagement.com',
            'role' => 'super_admin',
        ]);
        $admin->assignRole(\Spatie\Permission\Models\Role::findOrCreate('super_admin', 'web'));

        $this->actingAs($admin, 'web');

        SystemSetting::set('mail_from_address', 'noreply@koriassetmanagement.com', 'string');
        SystemSetting::set('mail_from_name', 'PEK - KORI Asset Management', 'string');
        SystemSetting::set('mail_reply_to_email', 'contact@koriassetmanagement.com', 'string');
        SystemSetting::set('mail_reply_to_name', 'Support Client PEK', 'string');

        $this->assertEquals('noreply@koriassetmanagement.com', SystemSetting::get('mail_from_address'));
        $this->assertEquals('PEK - KORI Asset Management', SystemSetting::get('mail_from_name'));
        $this->assertEquals('contact@koriassetmanagement.com', SystemSetting::get('mail_reply_to_email'));
        $this->assertEquals('Support Client PEK', SystemSetting::get('mail_reply_to_name'));

        $this->assertTrue(ManageNotificationSettings::canAccess());
    }
}
