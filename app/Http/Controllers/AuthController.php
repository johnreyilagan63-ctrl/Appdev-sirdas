<?php

namespace App\Http\Controllers;

use App\Models\PasswordReset;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function showLogin(Request $request)
    {
        if (Auth::check()) {
            return redirect(home_for_role(Auth::user()->role));
        }

        $email = '';
        $next = safe_next($request->query('next') ?? $request->old('next'), '');

        if ($request->has('logged_out')) {
            flash('info', 'You have been logged out.');
        }

        return view('auth.login', [
            'errors' => [],
            'email' => $email,
            'next' => $next,
            'pageTitle' => 'Log In',
            'minimalHeader' => true,
        ]);
    }

    public function login(Request $request)
    {
        if (Auth::check()) {
            return redirect(home_for_role(Auth::user()->role));
        }

        $errors = [];
        $email = strtolower(trim((string) $request->input('email', '')));
        $password = (string) $request->input('password', '');
        $remember = $request->has('remember');
        $next = safe_next($request->input('next'), '');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';
        }
        if ($password === '') {
            $errors['password'] = 'Enter your password.';
        }

        if (! $errors) {
            if (login_is_locked($email)) {
                $errors['form'] = 'Too many failed login attempts. Please wait ' . config('booking.login_lock_minutes') . ' minutes and try again.';
            } else {
                $user = User::where('email', $email)->first();

                if ($user && $user->status === 'active' && password_verify($password, $user->password_hash)) {
                    clear_login_attempts($email);
                    Auth::login($user, $remember);

                    flash('success', 'Welcome back, ' . $user->first_name . '!');

                    return redirect($next !== '' ? $next : home_for_role($user->role));
                }

                if ($user && $user->status === 'pending' && password_verify($password, $user->password_hash)) {
                    $errors['form'] = 'Your account is still waiting for admin approval. Please try again later.';
                } elseif ($user && $user->status === 'deactivated' && password_verify($password, $user->password_hash)) {
                    $errors['form'] = 'This account has been deactivated. Please contact the resort administrator.';
                } else {
                    record_failed_login($email);
                    $errors['form'] = 'Invalid credentials, or your account is not active yet.';
                }
            }
        }

        return view('auth.login', [
            'errors' => $errors,
            'email' => $email,
            'next' => $next,
            'pageTitle' => 'Log In',
            'minimalHeader' => true,
        ]);
    }

    public function showSignup(Request $request)
    {
        if (Auth::check()) {
            return redirect(home_for_role(Auth::user()->role));
        }

        return view('auth.signup', [
            'errors' => [],
            'values' => [
                'first_name' => '',
                'last_name' => '',
                'email' => '',
                'phone' => '',
                'role' => 'guest',
            ],
            'pageTitle' => 'Sign Up',
            'minimalHeader' => true,
        ]);
    }

    public function signup(Request $request)
    {
        if (Auth::check()) {
            return redirect(home_for_role(Auth::user()->role));
        }

        $errors = [];
        $values = [
            'first_name' => trim((string) $request->input('first_name', '')),
            'last_name' => trim((string) $request->input('last_name', '')),
            'email' => strtolower(trim((string) $request->input('email', ''))),
            'phone' => trim((string) $request->input('phone', '')),
            'role' => (string) $request->input('role', 'guest'),
        ];
        $password = (string) $request->input('password', '');
        $confirm = (string) $request->input('confirm_password', '');
        $consent = $request->has('consent');

        $phoneDigits = preg_replace('/\D+/', '', $values['phone']) ?? '';

        if ($values['first_name'] === '' || mb_strlen($values['first_name']) > 60) {
            $errors['first_name'] = 'Enter your first name (60 characters or fewer).';
        }
        if ($values['last_name'] === '' || mb_strlen($values['last_name']) > 60) {
            $errors['last_name'] = 'Enter your last name (60 characters or fewer).';
        }
        if (! filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';
        }
        if (! preg_match('/^09\d{9}$/', $phoneDigits)) {
            $errors['phone'] = 'Use the format 09XX XXX XXXX.';
        }
        if (strlen($password) < 8) {
            $errors['password'] = 'Use at least 8 characters.';
        }
        if ($password !== $confirm) {
            $errors['confirm_password'] = 'The passwords do not match.';
        }
        if (! in_array($values['role'], ['guest', 'owner'], true)) {
            $errors['form'] = 'Choose a valid account type.';
        }
        if (! $consent) {
            $errors['consent'] = 'You must agree to the Terms of Service and Privacy Policy.';
        }

        if (! $errors && User::where('email', $values['email'])->exists()) {
            $errors['email'] = 'An account with that email already exists.';
        }

        if (! $errors) {
            $status = $values['role'] === 'owner' ? 'pending' : 'active';

            $user = User::create([
                'first_name' => $values['first_name'],
                'last_name' => $values['last_name'],
                'email' => $values['email'],
                'phone' => $phoneDigits,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $values['role'],
                'status' => $status,
            ]);

            if ($values['role'] === 'guest') {
                Auth::login($user);
                flash('success', 'Your guest account is ready. Start by choosing a cottage.');

                return redirect('/');
            }

            flash('success', 'Your owner account was created and is waiting for admin approval.');

            return redirect()->route('login');
        }

        return view('auth.signup', [
            'errors' => $errors,
            'values' => $values,
            'pageTitle' => 'Sign Up',
            'minimalHeader' => true,
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login', ['logged_out' => 1]);
    }

    public function showForgot(Request $request)
    {
        if (Auth::check()) {
            return redirect(home_for_role(Auth::user()->role));
        }

        $errors = [];
        $codeError = $request->session()->pull('forgot_code_error');

        if ($codeError) {
            $errors['code'] = $codeError;
        }

        return view('auth.forgot-password', [
            'errors' => $errors,
            'codeSent' => (bool) $request->session()->get('forgot_code_sent', false),
            'email' => (string) $request->session()->get('forgot_email', ''),
            'code' => (string) $request->session()->pull('forgot_code_value', ''),
            'pageTitle' => 'Forgot Password',
            'minimalHeader' => true,
        ]);
    }

    public function sendReset(Request $request)
    {
        if (Auth::check()) {
            return redirect(home_for_role(Auth::user()->role));
        }

        $email = strtolower(trim((string) $request->input('email', '')));
        $errors = [];

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';

            return $this->forgotView($email, '', $errors, (bool) $request->session()->get('forgot_code_sent', false));
        }

        $user = User::where('email', $email)->where('status', 'active')->first();

        if ($user) {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $minutes = max(1, (int) config('booking.reset_code_expiry_minutes', 10));

            PasswordReset::where('user_id', $user->id)->where('used', false)->update(['used' => true]);

            $reset = PasswordReset::create([
                'user_id' => $user->id,
                'token_hash' => password_hash($code, PASSWORD_DEFAULT),
                'purpose' => 'code',
                'expires_at' => now()->addMinutes($minutes),
                'used' => false,
                'attempts' => 0,
                'verified_at' => null,
            ]);

            $body = 'Hello ' . $user->first_name . ",\n\n"
                . "Your OliyetteBook password reset code is:\n\n"
                . $code . "\n\n"
                . 'Enter this code on the Forgot Password page within ' . $minutes . " minutes. Do not share this code.\n\n"
                . "If you did not request this, you can ignore this email.\n\n"
                . 'OliyetteBook';

            if (! send_mail($user->email, 'Your OliyetteBook password reset code', $body)) {
                $reset->update(['used' => true]);
            }
        }

        $request->session()->put('forgot_email', $email);
        $request->session()->put('forgot_code_sent', true);
        $request->session()->forget(['forgot_code_error', 'forgot_code_value']);

        return redirect()->route('password.request');
    }

    public function verifyResetCode(Request $request)
    {
        if (Auth::check()) {
            return redirect(home_for_role(Auth::user()->role));
        }

        $email = strtolower(trim((string) $request->input('email', '')));
        $code = preg_replace('/\D+/', '', (string) $request->input('code', '')) ?? '';
        $errors = [];

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';
        }
        if (strlen($code) !== 6) {
            $errors['code'] = 'Enter the 6-digit code from your email.';
        }

        if ($errors) {
            return $this->forgotView($email, $code, $errors, true);
        }

        $maxAttempts = max(1, (int) config('booking.reset_code_max_attempts', 5));
        $tokenMinutes = max(1, (int) config('booking.password_reset_token_minutes', 15));

        try {
            $result = DB::transaction(function () use ($email, $code, $maxAttempts, $tokenMinutes) {
                $user = User::where('email', $email)->where('status', 'active')->first();

                if (! $user) {
                    return ['state' => 'invalid'];
                }

                $reset = PasswordReset::where('user_id', $user->id)
                    ->where('purpose', 'code')
                    ->where('used', false)
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();

                if (! $reset || $reset->expires_at->isPast()) {
                    return ['state' => 'invalid'];
                }

                if ((int) $reset->attempts >= $maxAttempts) {
                    $reset->update(['used' => true]);

                    return ['state' => 'locked'];
                }

                if (! password_verify($code, (string) $reset->token_hash)) {
                    $attempts = (int) $reset->attempts + 1;
                    $reset->update([
                        'attempts' => $attempts,
                        'used' => $attempts >= $maxAttempts,
                    ]);

                    return ['state' => $attempts >= $maxAttempts ? 'locked' : 'incorrect'];
                }

                $token = bin2hex(random_bytes(32));

                $reset->update(['used' => true]);
                PasswordReset::where('user_id', $user->id)->where('used', false)->update(['used' => true]);

                PasswordReset::create([
                    'user_id' => $user->id,
                    'token_hash' => hash('sha256', $token),
                    'purpose' => 'token',
                    'expires_at' => now()->addMinutes($tokenMinutes),
                    'used' => false,
                    'attempts' => 0,
                    'verified_at' => null,
                ]);

                return ['state' => 'verified', 'token' => $token];
            });
        } catch (\Throwable $e) {
            report($e);

            return $this->failedCode($request, $email, $code, 'We could not verify that code. Please try again.');
        }

        if ($result['state'] !== 'verified') {
            $message = match ($result['state']) {
                'locked' => 'Too many incorrect codes. Request a new code.',
                'incorrect' => 'That code is incorrect. Please try again.',
                default => 'That code is incorrect or has expired. Request a new code.',
            };

            return $this->failedCode($request, $email, $code, $message);
        }

        $request->session()->forget(['forgot_email', 'forgot_code_sent', 'forgot_code_error', 'forgot_code_value']);

        return redirect()->route('password.reset', ['token' => $result['token']]);
    }

    private function forgotView(string $email, string $code, array $errors, bool $codeSent)
    {
        return view('auth.forgot-password', [
            'errors' => $errors,
            'codeSent' => $codeSent,
            'email' => $email,
            'code' => $code,
            'pageTitle' => 'Forgot Password',
            'minimalHeader' => true,
        ]);
    }

    private function failedCode(Request $request, string $email, string $code, string $message)
    {
        $request->session()->put('forgot_email', $email);
        $request->session()->put('forgot_code_sent', true);
        $request->session()->flash('forgot_code_error', $message);
        $request->session()->flash('forgot_code_value', $code);

        return redirect()->route('password.request');
    }

    public function showReset(Request $request)
    {
        if (Auth::check()) {
            return redirect(home_for_role(Auth::user()->role));
        }

        $token = (string) ($request->input('token', $request->query('token', '')));
        $token = preg_replace('/[^a-f0-9]/i', '', $token) ?? '';
        $errors = [];
        $reset = null;
        $invalid = false;

        if ($token === '') {
            $invalid = true;
        } else {
            $reset = PasswordReset::where('token_hash', hash('sha256', $token))
                ->where('purpose', 'token')
                ->where('used', false)
                ->where('expires_at', '>', now())
                ->orderByDesc('id')
                ->first();

            if (! $reset) {
                $invalid = true;
            }
        }

        if (! $invalid && $request->isMethod('post')) {
            $password = (string) $request->input('password', '');
            $confirm = (string) $request->input('confirm_password', '');

            if (strlen($password) < 8) {
                $errors['password'] = 'Use at least 8 characters.';
            }
            if ($password !== $confirm) {
                $errors['confirm_password'] = 'The passwords do not match.';
            }

            if (! $errors && $reset) {
                try {
                    DB::transaction(function () use ($password, $reset) {
                        User::where('id', $reset->user_id)->update([
                            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        ]);

                        PasswordReset::where('user_id', $reset->user_id)->update(['used' => true]);
                    });

                    flash('success', 'Your password has been updated. Please log in with the new password.');

                    return redirect()->route('login');
                } catch (\Throwable $e) {
                    $errors['form'] = 'We could not update your password. Please try again.';
                }
            }
        }

        return view('auth.reset-password', [
            'token' => $token,
            'errors' => $errors,
            'invalid' => $invalid,
            'pageTitle' => 'Reset Password',
            'minimalHeader' => true,
        ]);
    }
}
