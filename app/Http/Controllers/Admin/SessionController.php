<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdministrationAccess;
use App\Models\User;
use App\Services\Admin\AdminCredentials;
use App\Services\Admin\AdminPresentation;
use App\Services\Admin\LoginRateLimit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Modules\Core\Template\Enums\CmsPresentation;

class SessionController extends Controller
{
    public function create(AdminPresentation $presentation): Response
    {
        return $presentation->render(CmsPresentation::SystemAuthLogin);
    }

    public function store(Request $request, AdminPresentation $presentation, LoginRateLimit $limiter): Response|RedirectResponse
    {
        $email = is_string($request->input('email')) ? AdminCredentials::email($request->input('email')) : '';
        $credentials = ['email' => $email, 'password' => $request->input('password')];
        $validator = Validator::make($credentials, [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => AdminCredentials::passwordRules(),
        ]);
        $invalid = $validator->fails();
        if ($invalid) {
            return redirect()->route('admin.login')->withErrors(['email' => 'admin.login.failed'])->withInput(['email' => $email]);
        }
        $authenticated = false;
        $retryAfter = $limiter->attempt($email, (string) $request->ip(), function () use (&$authenticated, $credentials): bool {
            $authenticated = Auth::guard('web')->attemptWhen($credentials, fn ($user) => AdministrationAccess::permits($user));
            if (! $authenticated && ! User::query()->where('email', $credentials['email'])->exists()) {
                // Burn one bcrypt operation within the admission lock for unknown accounts.
                Hash::driver('bcrypt')->make((string) $credentials['password']);
            }

            return $authenticated;
        });
        if ($retryAfter > 0) {
            $request->session()->flash('login_throttled', true);

            return $presentation->render(CmsPresentation::SystemAuthLogin, 429)->header('Retry-After', (string) $retryAfter);
        }
        if (! $authenticated) {
            return redirect()->route('admin.login')->withErrors(['email' => 'admin.login.failed'])->withInput(['email' => $email]);
        }
        $request->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
