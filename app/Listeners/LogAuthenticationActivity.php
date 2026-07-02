<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Log;

class LogAuthenticationActivity
{
    public function handleLogin(Login $event): void
    {
        Log::info('auth.login', [
            'user_id' => $event->user->getAuthIdentifier(),
            'email' => $event->user->email,
            'ip' => request()->ip(),
        ]);
    }

    public function handleLogout(Logout $event): void
    {
        Log::info('auth.logout', [
            'user_id' => $event->user?->getAuthIdentifier(),
            'email' => $event->user?->email,
        ]);
    }

    public function handleFailed(Failed $event): void
    {
        Log::warning('auth.failed', [
            'email' => $event->credentials['email'] ?? null,
            'ip' => request()->ip(),
        ]);
    }

    public function handleLockout(Lockout $event): void
    {
        Log::warning('auth.lockout', [
            'email' => $event->request->input('email'),
            'ip' => $event->request->ip(),
        ]);
    }
}
