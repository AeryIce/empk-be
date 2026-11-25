<?php

namespace App\Console\Commands;

use App\Enums\PanelRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Mail\Transport\ResendTransport;
use Illuminate\Mail\Transport\SesTransport;
use Illuminate\Mail\Transport\SesV2Transport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\Bridge\Postmark\Transport\PostmarkApiTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Throwable;

class ProvisionPanelUser extends Command
{
    protected $signature = 'empk:provision-panel-user {email : Email address or @approved-dev for manual activation} {role}
        {--target= : Expected Railway environment or local APP_ENV}
        {--name= : Name for a new account}
        {--existing-user-id= : Explicitly grant panel access to this existing account}
        {--manual-activation : Display a one-time activation code in a private Railway DEV terminal}
        {--resend : Replace the activation code for a pending account}';

    protected $description = 'Provision one explicitly approved Filament account';

    public function handle(): int
    {
        $recipient = $this->argument('email');
        $role = PanelRole::tryFrom($this->argument('role'));
        $railwayEnvironment = env('RAILWAY_ENVIRONMENT_NAME');
        $target = $railwayEnvironment ?: app()->environment();
        $name = trim((string) $this->option('name'));
        $existingId = $this->option('existing-user-id');
        $manual = $this->option('manual-activation');
        $resend = $this->option('resend');
        $email = Str::lower(trim($manual ? (string) env('EMPK_DEV_MANUAL_ACTIVATION_EMAIL') : $recipient));

        if ($role === null || $email === 'test@example.com' || Validator::make(['email' => $email, 'name' => $name], [
            'email' => 'required|email:rfc|max:254',
            'name' => 'max:255',
        ])->fails()) {
            $this->error('Provide a valid individual email and a supported panel role.');

            return self::FAILURE;
        }

        if ($this->option('target') !== $target
            || ($this->input->hasParameterOption('--existing-user-id') && $existingId === null)
            || ($existingId !== null && ($resend || $name !== '' || ! ctype_digit($existingId)))) {
            $this->error('Check the target environment and choose either a new account, resend, or an explicit existing user ID.');

            return self::FAILURE;
        }

        if ($railwayEnvironment === 'development'
            && ($existingId === '2' || ($existingId === '1' && $role !== PanelRole::SuperModerator))) {
            $this->error('The requested role is refused for this existing DEV account.');

            return self::FAILURE;
        }

        if ($manual && ($railwayEnvironment !== 'development' || $recipient !== '@approved-dev'
            || $role !== PanelRole::SuperAdmin || $existingId !== null
            || parse_url(config('app.url'), PHP_URL_SCHEME) !== 'https' || ! $this->hasPrivateTerminal())) {
            $this->error('Manual activation requires Railway development, HTTPS, the approved new SuperAdmin account, and a private interactive terminal.');

            return self::FAILURE;
        }

        if (! $this->input->isInteractive() || ! $this->confirm("Approve panel access as {$role->value} in {$target} for the account entered?")) {
            $this->warn('Interactive operator approval is required.');

            return self::FAILURE;
        }

        if ($manual && ! $this->confirm('Confirm the recipient owns the approved address and this terminal is not being recorded?')) {
            $this->warn('Manual activation requires private identity and terminal confirmation.');

            return self::FAILURE;
        }

        try {
            [$user, $code, $status] = DB::transaction(fn () => $this->provision($email, $role, $name, $existingId, $resend, $manual));
        } catch (Throwable $exception) {
            Log::warning('panel.provision.refused', ['role' => $role->value, 'target' => $target, 'type' => $exception::class]);
            $this->error('Provisioning refused. Check the account state, options, and mail configuration.');

            return self::FAILURE;
        }

        Log::info("panel.provision.{$status}", ['user_id' => $user->getKey(), 'role' => $role->value, 'target' => $target]);

        if ($manual && $code !== null) {
            try {
                $minutes = config('auth.passwords.users.expire');
                $this->info("Single-use activation code (expires in {$minutes} minutes). Share it privately with the verified recipient.");
                $this->output->writeln($code, OutputInterface::OUTPUT_RAW);
            } catch (Throwable) {
                Log::warning('panel.activation.display_failed', ['user_id' => $user->getKey()]);
                $this->error('The account needs activation. Use --resend with approval if the code was not displayed.');

                return self::FAILURE;
            }

            return self::SUCCESS;
        }

        if ($code !== null) {
            try {
                $url = rtrim(config('app.url'), '/').'/admin/activate';
                $minutes = config('auth.passwords.users.expire');

                $sent = Mail::raw("Open {$url} and enter this one-time code to set your password:\n\n{$code}\n\nThis code expires in {$minutes} minutes.", function ($message) use ($user): void {
                    $message->to($user->email)->subject('Activate your eMPK Filament account');
                });

                if ($sent === null) {
                    throw new RuntimeException;
                }
            } catch (Throwable) {
                Log::warning('panel.activation.delivery_failed', ['user_id' => $user->getKey()]);
                $this->error('The account needs activation. Check delivery and use --resend with approval.');

                return self::FAILURE;
            }

            $this->info('Activation email submitted to the mail transport.');
        } else {
            $this->info(match ($status) {
                'pending' => 'Activation is pending. Use --resend only if a replacement code is needed.',
                'existing_granted' => 'Panel access granted to the selected existing account.',
                default => 'The account already has the requested panel access.',
            });
        }

        return self::SUCCESS;
    }

    protected function hasPrivateTerminal(): bool
    {
        return $this->input instanceof ArgvInput
            && ! env('CI', false)
            && stream_isatty(STDIN) && stream_isatty(STDOUT) && stream_isatty(STDERR)
            && $this->output->getVerbosity() >= OutputInterface::VERBOSITY_NORMAL;
    }

    /** @return array{User, ?string, string} */
    private function provision(string $email, PanelRole $role, string $name, ?string $existingId, bool $resend, bool $manual): array
    {
        $matches = User::query()->whereRaw('LOWER(email) = ?', [$email])->lockForUpdate()->limit(2)->get();
        $user = $matches->first();

        if ($matches->count() > 1 || ($existingId !== null && (string) $user?->getKey() !== $existingId)) {
            throw new RuntimeException;
        }

        if ($user && $user->role === $role->value && $user->panel_activated_at !== null) {
            return [$user, null, 'already_active'];
        }

        if ($existingId !== null) {
            if ($user->role !== null || $user->panel_activated_at !== null) {
                throw new RuntimeException;
            }

            $user->forceFill(['role' => $role->value, 'panel_activated_at' => now()])->save();

            return [$user, null, 'existing_granted'];
        }

        if ($user && ($user->role !== $role->value || $user->email !== $email)) {
            throw new RuntimeException;
        }

        if ($user && ! $resend) {
            return [$user, null, 'pending'];
        }

        if (! $manual) {
            $transport = Mail::getSymfonyTransport();
            $canSend = ($transport instanceof EsmtpTransport || $transport instanceof SesTransport
                || $transport instanceof SesV2Transport || $transport instanceof PostmarkApiTransport || $transport instanceof ResendTransport)
                && parse_url(config('app.url'), PHP_URL_SCHEME) === 'https';

            if (! $canSend && ! (app()->environment('testing') && $transport instanceof ArrayTransport)) {
                throw new RuntimeException;
            }
        }

        $broker = Password::broker('users');

        if ($user) {
            if ($broker->getRepository()->recentlyCreatedToken($user)) {
                throw new RuntimeException;
            }

            $status = 'resent';
        } else {
            if ($resend || $name === '') {
                throw new RuntimeException;
            }

            $user = new User(['name' => $name, 'email' => $email, 'password' => Hash::make(Str::random(64))]);
            $user->role = $role->value;
            $user->save();
            $status = 'created';
        }

        $code = $broker->createToken($user);
        $updated = DB::table(config('auth.passwords.users.table'))
            ->where('email', $user->getEmailForPasswordReset())
            ->update(['panel_activation_delivery' => $manual ? 'manual' : 'email']);

        if ($updated !== 1) {
            throw new RuntimeException;
        }

        return [$user, $code, $status];
    }
}
