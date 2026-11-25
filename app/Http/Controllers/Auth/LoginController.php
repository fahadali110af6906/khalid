<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    /**
     * Show the application login form.
     */
    public function showLoginForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard.owner');
        }

        return view('auth.login');
    }

    /**
     * Handle an authentication attempt.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::transliterate(Str::lower($request->input('email')).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ]),
            ]);
        }

        $authenticated = Auth::attempt($credentials, $request->boolean('remember'));

        // Local development password flexibility for ease of testing & demonstration
        if (!$authenticated && app()->environment('local')) {
            $matchedUser = \App\Models\User::where('email', $credentials['email'])->first();
            $allowedLocalPasswords = [
                'Owner@Khalid2026!',
                '12345678',
                'password',
                'admin123',
                'admin',
                'Tariq@Shop1Pass!',
                'Irfan@Shop2Pass!',
                'Aslam@Factory1Pass!',
            ];
            if ($matchedUser && in_array($credentials['password'], $allowedLocalPasswords, true)) {
                Auth::login($matchedUser, $request->boolean('remember'));
                $authenticated = true;
            }
        }

        if ($authenticated) {
            RateLimiter::clear($throttleKey);

            // Verify account status
            if (!Auth::user()->is_active) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()->withErrors([
                    'email' => 'This user account is currently deactivated.',
                ]);
            }

            $request->session()->regenerate();

            // Direct user according to role
            $user = Auth::user();
            if ($user->hasRole('factory_manager')) {
                return redirect()->intended(route('dashboard.factory'));
            }
            if ($user->hasRole(['shop_manager', 'shop_cashier'])) {
                return redirect()->intended(route('dashboard.shop'));
            }

            return redirect()->intended(route('dashboard.owner'));
        }

        RateLimiter::hit($throttleKey);

        throw ValidationException::withMessages([
            'email' => trans('auth.failed'),
        ]);
    }

    /**
     * Log the user out of the application.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'You have been logged out successfully.');
    }

    /**
     * Explicitly reject public self-registration.
     */
    public function disabledRegistration()
    {
        abort(403, 'Public self-registration is disabled. Please contact the Khalid Traders administrator.');
    }
}
