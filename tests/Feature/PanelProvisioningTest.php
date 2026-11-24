<?php

namespace Tests\Feature;

use App\Enums\PanelRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PanelProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_account_is_inactive_until_the_one_time_code_is_used(): void
    {
        $options = $this->provisionOptions('new@example.invalid', ['--name' => 'Operational Test']);
        $this->provision($options);
        $this->provision($options);

        $user = User::sole();
        $this->assertNull($user->panel_activated_at);
        $this->assertFalse($user->canManagePanelContent());
        $this->assertCount(1, Mail::getSymfonyTransport()->messages());
        $code = $this->latestCode();

        $this->get('/admin/activate')->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
        $this->post('/admin/activate', $this->activation($user, $code, 'short'))
            ->assertSessionHasErrors('password')->assertSessionMissing('_old_input.activation_code');
        $this->post('/admin/activate', $this->activation($user, $code))->assertRedirect('/admin/login');
        $this->assertGuest();
        $this->assertTrue($user->refresh()->canManagePanelContent());
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('A test passphrase for eMPK', $user->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);

        $passwordHash = $user->password;
        $this->post('/admin/activate', $this->activation($user, $code, 'A replacement test passphrase'))
            ->assertSessionHasErrors('activation_code');
        $this->assertSame($passwordHash, $user->refresh()->password);
        $this->provision($options);
        $this->assertCount(1, Mail::getSymfonyTransport()->messages());
    }

    public function test_expired_codes_are_rejected_and_resend_replaces_only_the_pending_code(): void
    {
        $options = $this->provisionOptions('pending@example.invalid', ['--name' => 'Pending Test']);
        $this->provision($options);
        $user = User::sole();
        $originalCode = $this->latestCode();
        $this->provision($options + ['--resend' => true], 1);

        $this->travel(61)->minutes();
        $this->post('/admin/activate', $this->activation($user, $originalCode))->assertSessionHasErrors('activation_code');
        $this->assertNull($user->refresh()->panel_activated_at);
        $this->provision($options + ['--resend' => true]);
        $newCode = $this->latestCode();

        $this->assertNotSame($originalCode, $newCode);
        $this->assertFalse(Password::broker('users')->tokenExists($user, $originalCode));
        $this->post('/admin/activate', $this->activation($user, $newCode))->assertRedirect('/admin/login');
        $this->assertTrue($user->refresh()->canManagePanelContent());
    }

    public function test_existing_accounts_require_an_exact_id_and_keep_their_credentials_and_role(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'Existing@example.invalid', 'remember_token' => 'existing-token']);
        $passwordHash = $user->password;
        $options = $this->provisionOptions('existing@example.invalid');

        $this->provision($options + ['--name' => 'Replacement'], 1);
        $this->provision($options + ['--existing-user-id' => (string) ($user->id + 1)], 1);
        $this->assertNull($user->refresh()->role);

        $approved = $options + ['--existing-user-id' => (string) $user->id];
        $this->provision($approved);
        $this->provision($approved);
        $this->provision(array_replace($approved, ['role' => PanelRole::SuperModerator->value]), 1);

        $user->refresh();
        $this->assertSame(PanelRole::SuperAdmin, $user->panelRole());
        $this->assertSame($passwordHash, $user->password);
        $this->assertSame('existing-token', $user->remember_token);
        $this->assertNull($user->email_verified_at);
        $this->assertCount(0, Mail::getSymfonyTransport()->messages());
    }

    public function test_provisioning_requires_correct_environment_and_operator_approval(): void
    {
        $options = $this->provisionOptions('new@example.invalid', ['--name' => 'New Test']);
        $this->artisan('empk:provision-panel-user', array_replace($options, ['--target' => 'production']))->assertFailed();
        $this->artisan('empk:provision-panel-user', $options + ['--no-interaction' => true])->assertFailed();
        $this->artisan('empk:provision-panel-user', $options)->expectsConfirmation($this->approvalPrompt(), 'no')->assertFailed();
        config(['mail.default' => 'log']);
        $this->provision($options, 1);
        config(['app.url' => 'https://dev.example.invalid', 'mail.default' => 'smtp', 'mail.mailers.smtp.url' => 'log://localhost']);
        $this->provision($options, 1);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_the_additive_migration_preserves_credentials_in_both_directions(): void
    {
        $user = User::factory()->create(['role' => PanelRole::SuperAdmin->value, 'panel_activated_at' => now()]);
        $passwordHash = $user->password;
        $migration = require database_path('migrations/2026_11_24_000000_add_panel_access_to_users_table.php');

        $migration->down();
        $this->assertFalse(Schema::hasColumn('users', 'role'));
        $this->assertFalse(Schema::hasColumn('users', 'panel_activated_at'));
        $this->assertSame($passwordHash, $user->refresh()->password);

        $migration->up();
        $this->assertSame($passwordHash, $user->refresh()->password);
        $this->assertNull($user->role);
        $this->assertNull($user->panel_activated_at);
    }

    private function provisionOptions(string $email, array $extra = []): array
    {
        return ['email' => $email, 'role' => PanelRole::SuperAdmin->value, '--target' => 'testing'] + $extra;
    }

    private function approvalPrompt(string $role = 'super_admin'): string
    {
        return "Approve panel access as {$role} in testing for the account entered?";
    }

    private function provision(array $options, int $exitCode = 0): void
    {
        $this->artisan('empk:provision-panel-user', $options)
            ->expectsConfirmation($this->approvalPrompt($options['role']), 'yes')->assertExitCode($exitCode);
    }

    private function latestCode(): string
    {
        $body = Mail::getSymfonyTransport()->messages()->last()->getOriginalMessage()->getTextBody();
        preg_match('/\b[0-9a-f]{64}\b/', $body, $matches);

        return $matches[0];
    }

    private function activation(User $user, string $code, string $password = 'A test passphrase for eMPK'): array
    {
        return [
            'email' => $user->email,
            'activation_code' => $code,
            'password' => $password,
            'password_confirmation' => $password,
        ];
    }
}
