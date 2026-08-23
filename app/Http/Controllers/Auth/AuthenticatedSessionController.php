<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('tournaments.index');
        }

        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $credentials['email'] = mb_strtolower($credentials['email']);

        try {
            $authenticated = Auth::attempt($credentials, $request->boolean('remember'));
        } catch (QueryException $exception) {
            throw_unless(app()->isLocal(), $exception);

            return back()
                ->withErrors(['email' => __('ui.database_unavailable')])
                ->onlyInput('email');
        }

        if (! $authenticated) {
            return back()->withErrors(['email' => __('ui.invalid_credentials')])->onlyInput('email');
        }

        $request->session()->regenerate();

        $message = $request->user()?->isAdmin()
            ? __('ui.admin_login_success')
            : __('ui.viewer_login_success');

        return redirect()->intended(route('tournaments.index'))->with('success', $message);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('tournaments.index');
    }
}
