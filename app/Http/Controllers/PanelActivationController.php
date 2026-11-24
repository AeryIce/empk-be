<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;

class PanelActivationController extends Controller
{
    public function show(): Response
    {
        return response()->view('auth.panel-activation')->withHeaders([
            'Cache-Control' => 'no-store, private',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => 'required|email:rfc|max:254',
            'activation_code' => 'required|string|size:64',
            'password' => ['bail', 'required', 'string', 'min:14', 'confirmed', function ($attribute, $value, $fail): void {
                if (strlen($value) > 72 || str_contains($value, "\0")) {
                    $fail('Gunakan kata sandi maksimal 72 byte tanpa karakter null.');
                }
            }],
        ]);

        try {
            $user = DB::transaction(fn (): ?User => $this->activate($data));
        } catch (Throwable $exception) {
            Log::error('panel.activation.failed', ['type' => $exception::class]);

            abort(503, 'Aktivasi sementara tidak tersedia.');
        }

        if ($user === null) {
            Log::notice('panel.activation.rejected');

            return redirect()->route('panel.activation')->withErrors(['activation_code' => 'Kode aktivasi tidak berlaku atau sudah kedaluwarsa.']);
        }

        Log::info('panel.activation.completed', ['user_id' => $user->getKey(), 'role' => $user->role]);

        return redirect()->route('filament.admin.auth.login');
    }

    private function activate(#[\SensitiveParameter] array $data): ?User
    {
        $user = User::query()->where('email', Str::lower($data['email']))->lockForUpdate()->first();
        $broker = Password::broker('users');

        if ($user === null || $user->panelRole() === null || $user->panel_activated_at !== null || ! $broker->tokenExists($user, $data['activation_code'])) {
            return null;
        }

        $now = now();
        $user->forceFill([
            'password' => Hash::make($data['password']),
            'email_verified_at' => $now,
            'panel_activated_at' => $now,
            'remember_token' => null,
        ])->save();
        $broker->deleteToken($user);

        return $user;
    }
}
