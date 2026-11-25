<?php

namespace App\Filament\Pages\Auth;

use App\Enums\PanelRole;
use App\Models\User;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        $response = parent::authenticate();
        $user = Filament::auth()->user();

        if ($response !== null && $user instanceof User) {
            Log::info('panel.login.succeeded', ['user_id' => $user->getKey(), 'role' => $user->role]);
        }

        return $response;
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        return [
            ...parent::getCredentialsFromFormData($data),
            fn (Builder $query) => $query
                ->whereIn('role', [PanelRole::SuperModerator->value, PanelRole::SuperAdmin->value])
                ->whereNotNull('panel_activated_at'),
        ];
    }

    protected function throwFailureValidationException(): never
    {
        Log::notice('panel.login.rejected');

        parent::throwFailureValidationException();
    }

    protected function getRateLimitedNotification(TooManyRequestsException $exception): ?Notification
    {
        Log::notice('panel.login.throttled');

        return parent::getRateLimitedNotification($exception);
    }
}
