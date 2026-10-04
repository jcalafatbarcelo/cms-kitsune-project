<?php

namespace App\Http\Middleware;

use App\Models\AdministrationAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireSuperAdmin
{
    public function handle(Request $request, Closure $next, string $mode = 'protected'): Response
    {
        $user = Auth::guard('web')->user();
        if ($user !== null) {
            abort_unless(AdministrationAccess::permits($user), 403);
            if ($mode === 'guest') {
                return redirect()->route('admin.dashboard');
            }
        } elseif ($mode !== 'guest') {
            return redirect()->route('admin.login');
        }

        return $next($request);
    }
}
