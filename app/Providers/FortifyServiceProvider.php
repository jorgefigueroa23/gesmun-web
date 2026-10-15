<?php

namespace App\Providers;

use App\Models\Usuario;
use App\Actions\Fortify\ResetUserPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();

        Fortify::authenticateUsing(function (Request $request) {

        $usuario = Usuario::where(
                'USUARI_T_CODUSU',
                $request->input('email')
                )
                // Versión anterior:
                // ->where('USUARI_T_WEB', 1)
                ->when(
                    \Illuminate\Support\Facades\Schema::hasColumn((new Usuario)->getTable(), 'USUARI_T_WEB'),
                    fn ($query) => $query->where('USUARI_T_WEB', 1)
                )
                ->first();

            if (!$usuario) {
                return null;
            }

        $encoded = base64_encode(
            mb_convert_encoding(
                $request->input('password'),
                'UTF-16LE',
                'UTF-8'
            )
            );

            if ($usuario->USUARI_T_CLAUSU === $encoded) {
                return $usuario;
            }

        return null;
        });

    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn () => view('pages::auth.login'));
        Fortify::verifyEmailView(fn () => view('pages::auth.verify-email'));
        Fortify::resetPasswordView(fn () => view('pages::auth.reset-password'));
        Fortify::requestPasswordResetLinkView(fn () => view('pages::auth.forgot-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

    }
}
