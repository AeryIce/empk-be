<?php

namespace Tests\Feature;

use App\Console\Commands\ProvisionPanelUser;
use App\Enums\PanelRole;
use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Logger;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Monolog\Handler\TestHandler;
use Monolog\Logger as MonologLogger;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class PanelProvisioningTest extends TestCase
{
    use RefreshDatabase;

    private array $originalEnvironment = [];

    protected function tearDown(): void
    {
        foreach ($this->originalEnvironment as $key => $value) {
            if ($value === null) {
                Env::getRepository()->clear($key);
            } else {
                Env::getRepository()->set($key, $value);
            }
        }

        parent::tearDown();
    }

    public function test_a_new_account_is_inactive_until_the_one_time_code_is_used(): void
    {
        $options = $this->provisionOptions('new@example.invalid', ['--name' => 'Operational Test']);
        $this->provision($options);
        $this->provision($options);

        $user = User::sole();
        $this->assertNull($user->panel_activated_at);
        $this->assertFalse($user->canManagePanelContent());
        $this->assertCount(1, Mail::getSymfonyTransport()->messages());
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email, 'panel_activation_delivery' => 'email']);
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

    public function test_manual_activation_displays_once_without_smtp_and_preserves_reserved_dev_accounts(): void
    {
        $this->configureManualFixture();
        $moderator = User::factory()->create();
        $legacy = User::factory()->create();
        $this->assertSame(1, $moderator->id);
        $this->assertSame(2, $legacy->id);
        $existingHash = $moderator->password;

        $this->artisan('empk:provision-panel-user', ['email' => $moderator->email, 'role' => 'super_admin', '--target' => 'development', '--existing-user-id' => '1'])->assertFailed();
        $this->artisan('empk:provision-panel-user', ['email' => $legacy->email, 'role' => 'super_admin', '--target' => 'development', '--existing-user-id' => '2'])->assertFailed();
        $this->artisan('empk:provision-panel-user', ['email' => $moderator->email, 'role' => 'super_moderator', '--target' => 'development', '--existing-user-id' => '1'])
            ->expectsConfirmation('Approve panel access as super_moderator in development for the account entered?', 'yes')->assertSuccessful();

        $handler = new TestHandler;
        Log::swap(new Logger(new MonologLogger('manual-activation-fixture', [$handler])));
        Mail::shouldReceive('getSymfonyTransport', 'raw')->never();

        [$status, $output] = $this->runManual();
        $this->assertSame(0, $status);
        $code = $this->readManualCode($output);
        $recipient = User::where('email', 'manual-fixture@example.invalid')->sole();
        $this->assertFalse($recipient->canManagePanelContent());
        $this->assertNull($recipient->panel_activated_at);
        $this->assertFalse(str_contains($output, $recipient->email));
        $storedToken = DB::table('password_reset_tokens')->where('email', $recipient->email)->first();
        $this->assertSame('manual', $storedToken->panel_activation_delivery);
        $this->assertTrue(Hash::check($code, $storedToken->token));
        $this->assertFalse(hash_equals($storedToken->token, $code));

        [$repeatStatus, $repeatOutput] = $this->runManual();
        $this->assertSame(0, $repeatStatus);
        $this->assertFalse(str_contains($repeatOutput, $code));
        $this->assertSame(0, preg_match('/^[0-9a-f]{64}\r?$/m', $repeatOutput));

        $this->post('/admin/activate', $this->activation($recipient, $code) + ['panel_activation_delivery' => 'email'])
            ->assertRedirect('/admin/login');
        $activationSession = [session()->getId(), session()->token()];
        $this->post('/admin/activate', $this->activation($recipient, $code))->assertSessionHasErrors('activation_code');
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $recipient->email]);
        $this->assertSame(PanelRole::SuperAdmin, $recipient->refresh()->panelRole());
        $this->assertNotNull($recipient->panel_activated_at);
        $this->assertNull($recipient->email_verified_at);
        $this->assertTrue(Hash::check('A test passphrase for eMPK', $recipient->password));

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(Login::class)->fillForm(['email' => $recipient->email, 'password' => 'A test passphrase for eMPK'])
            ->call('authenticate')->assertHasNoErrors();
        $this->assertAuthenticatedAs($recipient);
        $this->assertSame(PanelRole::SuperModerator, $moderator->refresh()->panelRole());
        $this->assertTrue($moderator->isPanelModerator());
        $this->assertSame($existingHash, $moderator->password);
        $this->assertNull($legacy->refresh()->role);
        $this->assertFalse($legacy->canManagePanelContent());

        $logs = json_encode($handler->getRecords(), JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('panel.activation.completed', $logs);
        foreach ([$code, $recipient->email, 'A test passphrase for eMPK', ...$activationSession, session()->getId(), session()->token()] as $secret) {
            $this->assertFalse(str_contains($logs, $secret));
        }
    }

    public function test_reissuing_an_emailed_code_manually_replaces_its_verification_provenance(): void
    {
        $this->configureManualFixture();
        config(['mail.default' => 'array']);
        $options = array_replace($this->provisionOptions('manual-fixture@example.invalid', ['--name' => 'Manual Fixture']), ['--target' => 'development']);
        $this->provision($options);
        $recipient = User::sole();
        $emailedCode = $this->latestCode();

        $this->travel(2)->minutes();
        [$status, $output] = $this->runManual(['--resend' => true]);
        $this->assertSame(0, $status);
        $manualCode = $this->readManualCode($output);

        $this->post('/admin/activate', $this->activation($recipient, $emailedCode))->assertSessionHasErrors('activation_code');
        $this->post('/admin/activate', $this->activation($recipient, $manualCode))->assertRedirect('/admin/login');
        $this->assertTrue($recipient->refresh()->canManagePanelContent());
        $this->assertNull($recipient->email_verified_at);
        $this->assertCount(1, Mail::getSymfonyTransport()->messages());
    }

    public function test_legacy_codes_without_provenance_preserve_the_existing_verification_status(): void
    {
        foreach ([null, now()->subDay()->startOfSecond()] as $verifiedAt) {
            $recipient = User::factory()->create(['role' => PanelRole::SuperAdmin->value, 'email_verified_at' => $verifiedAt]);
            $code = Password::broker('users')->createToken($recipient);
            $this->assertDatabaseHas('password_reset_tokens', ['email' => $recipient->email, 'panel_activation_delivery' => null]);

            $this->post('/admin/activate', $this->activation($recipient, $code))->assertRedirect('/admin/login');
            $this->assertTrue($recipient->refresh()->canManagePanelContent());
            $this->assertEquals($verifiedAt, $recipient->email_verified_at);
        }
    }

    public function test_delivery_migration_rollback_preserves_the_broker_token_hash(): void
    {
        $recipient = User::factory()->create();
        $code = Password::broker('users')->createToken($recipient);
        $tokens = DB::table('password_reset_tokens')->where('email', $recipient->email);
        $tokens->update(['panel_activation_delivery' => 'manual']);
        $before = $tokens->value('token');
        $migration = require database_path('migrations/2026_11_25_000000_add_panel_activation_delivery_to_password_reset_tokens.php');

        $migration->down();
        $this->assertFalse(Schema::hasColumn('password_reset_tokens', 'panel_activation_delivery'));
        $this->assertTrue(hash_equals($before, $tokens->value('token')));
        $this->assertTrue(Password::broker('users')->tokenExists($recipient, $code));

        $migration->up();
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $recipient->email, 'panel_activation_delivery' => null]);
        $this->assertTrue(hash_equals($before, $tokens->value('token')));
    }

    public function test_manual_reissue_replaces_unexpired_codes_and_new_codes_still_expire(): void
    {
        $this->configureManualFixture();
        [$status, $output] = $this->runManual();
        $this->assertSame(0, $status);
        $original = $this->readManualCode($output);
        $recipient = User::sole();
        $this->assertSame(1, $this->runManual(['--resend' => true])[0]);

        $this->travel(2)->minutes();
        $this->assertTrue(Password::broker('users')->tokenExists($recipient, $original));
        [$status, $output] = $this->runManual(['--resend' => true]);
        $this->assertSame(0, $status);
        $replacement = $this->readManualCode($output);
        $this->assertFalse(hash_equals($original, $replacement));
        $this->post('/admin/activate', $this->activation($recipient, $original))->assertSessionHasErrors('activation_code');

        $this->travel(61)->minutes();
        $this->post('/admin/activate', $this->activation($recipient, $replacement))->assertSessionHasErrors('activation_code');
        $this->assertNull($recipient->refresh()->panel_activated_at);
    }

    public function test_manual_activation_refuses_unapproved_targets_recipients_and_terminals(): void
    {
        $this->configureManualFixture();
        $this->assertSame(1, $this->runManual(simulateTerminal: false)[0]);
        $this->assertSame(1, $this->runManual(['--no-interaction' => true])[0]);
        $this->assertSame(1, $this->runManual(confirmation: "yes\nno\n")[0]);
        $this->assertSame(1, $this->runManual(['role' => 'super_moderator'])[0]);
        $this->assertSame(1, $this->runManual(['email' => 'another@example.invalid'])[0]);
        $this->assertSame(1, $this->runManual(['--existing-user-id' => '1', '--name' => null])[0]);
        $this->assertSame(1, $this->runManual(['--target' => 'unrecognized'])[0]);

        $this->setFixtureEnvironment('RAILWAY_ENVIRONMENT_NAME', 'production');
        $this->assertSame(1, $this->runManual(['--target' => 'production'])[0]);
        $this->setFixtureEnvironment('RAILWAY_ENVIRONMENT_NAME', null);
        $this->assertSame(1, $this->runManual(['--target' => 'testing'])[0]);
        $this->setFixtureEnvironment('RAILWAY_ENVIRONMENT_NAME', 'development');
        config(['app.url' => 'http://dev.example.invalid']);
        $this->assertSame(1, $this->runManual()[0]);
        config(['app.url' => 'https://dev.example.invalid']);
        $this->setFixtureEnvironment('EMPK_DEV_MANUAL_ACTIVATION_EMAIL', null);
        $this->assertSame(1, $this->runManual()[0]);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_manual_activation_refuses_to_reuse_an_existing_unprivileged_email(): void
    {
        $this->configureManualFixture();
        $existing = User::factory()->create(['email' => 'Manual-Fixture@example.invalid']);
        $original = $existing->refresh()->getRawOriginal();
        $this->assertSame(1, $this->runManual()[0]);
        $this->assertSame(1, $this->runManual(['--resend' => true])[0]);
        $this->assertTrue($original === $existing->refresh()->getRawOriginal());
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    private function configureManualFixture(): void
    {
        $this->setFixtureEnvironment('RAILWAY_ENVIRONMENT_NAME', 'development');
        $this->setFixtureEnvironment('EMPK_DEV_MANUAL_ACTIVATION_EMAIL', 'manual-fixture@example.invalid');
        config(['app.url' => 'https://dev.example.invalid', 'mail.default' => 'log']);
    }

    private function setFixtureEnvironment(string $key, ?string $value): void
    {
        $env = Env::getRepository();
        if (! array_key_exists($key, $this->originalEnvironment)) {
            $this->originalEnvironment[$key] = $env->get($key);
        }

        $value === null ? $env->clear($key) : $env->set($key, $value);
    }

    /** @return array{int, string} */
    private function runManual(array $options = [], bool $simulateTerminal = true, string $confirmation = "yes\nyes\n"): array
    {
        $command = new ProvisionPanelUser;
        if ($simulateTerminal) {
            $command = new class extends ProvisionPanelUser
            {
                protected function hasPrivateTerminal(): bool
                {
                    return true;
                }
            };
        }

        $command->setLaravel($this->app);
        $console = new Application;
        $console->setAutoExit(false);
        $console->setCatchExceptions(false);
        $console->add($command);

        $input = new ArrayInput(['command' => $command->getName()] + $options + [
            'email' => '@approved-dev', 'role' => 'super_admin',
            '--target' => 'development', '--name' => 'Manual Fixture', '--manual-activation' => true,
        ]);
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $confirmation);
        rewind($stream);
        $input->setStream($stream);
        $output = new BufferedOutput;
        try {
            return [$console->run($input, $output), $output->fetch()];
        } finally {
            fclose($stream);
        }
    }

    private function readManualCode(string $output): string
    {
        $this->assertSame(1, preg_match_all('/^[0-9a-f]{64}\r?$/m', $output, $matches));

        return trim($matches[0][0]);
    }

    private function provisionOptions(string $email, array $extra = []): array
    {
        return ['email' => $email, 'role' => PanelRole::SuperAdmin->value, '--target' => 'testing'] + $extra;
    }

    private function approvalPrompt(string $role = 'super_admin', string $target = 'testing'): string
    {
        return "Approve panel access as {$role} in {$target} for the account entered?";
    }

    private function provision(array $options, int $exitCode = 0): void
    {
        $this->artisan('empk:provision-panel-user', $options)
            ->expectsConfirmation($this->approvalPrompt($options['role'], $options['--target']), 'yes')->assertExitCode($exitCode);
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
