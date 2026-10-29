<?php

namespace App\Providers;

use App\Actions\ResetUserPassword;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Fortify::loginView(fn () => view('auth.login'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn () => view('auth.reset-password'));
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::authenticateUsing(function ($r) {
            $u = User::where('email', $r->email)->where('active', true)->first();

            return $u && Hash::check($r->password, $u->password) ? $u : null;
        });
        RateLimiter::for('login', fn ($r) => Limit::perMinute(5)->by(mb_strtolower($r->input('email', '')).'|'.$r->ip()));
    }
}
