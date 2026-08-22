<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class FirstAdminSetupController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if ($this->adminExists()) {
            return redirect()->route('login')->withErrors(['email' => __('ui.admin_already_configured')]);
        }

        return view('auth.setup');
    }

    public function store(Request $request): RedirectResponse
    {
        if ($this->adminExists()) {
            return redirect()->route('login')->withErrors(['email' => __('ui.admin_already_configured')]);
        }

        $request->merge(['email' => mb_strtolower((string) $request->input('email'))]);
        $data = $request->validate([
            'setup_token' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        $expectedToken = (string) config('access.setup_token');
        if ($expectedToken === '' || ! hash_equals($expectedToken, $data['setup_token'])) {
            return back()->withErrors(['setup_token' => __('ui.invalid_setup_token')])->onlyInput('name', 'email');
        }

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => UserRole::ADMIN,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('tournaments.index')->with('success', __('ui.admin_created'));
    }

    private function adminExists(): bool
    {
        return User::query()->where('role', UserRole::ADMIN->value)->exists();
    }
}
