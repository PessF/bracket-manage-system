<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users', ['users' => User::query()->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['email' => mb_strtolower((string) $request->input('email'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
        ]);
        User::query()->create($data);

        return back()->with('success', __('ui.user_created'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $request->merge(['email' => mb_strtolower((string) $request->input('email'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => ['nullable', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        if ($user->isAdmin() && $data['role'] !== UserRole::ADMIN->value && $this->isLastAdmin()) {
            return back()->withErrors(__('ui.last_admin_required'));
        }

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
        ]);
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->save();

        return back()->with('success', __('ui.user_updated'));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return back()->withErrors(__('ui.cannot_delete_self'));
        }
        if ($user->isAdmin() && $this->isLastAdmin()) {
            return back()->withErrors(__('ui.last_admin_required'));
        }

        $user->delete();

        return back()->with('success', __('ui.user_deleted'));
    }

    private function isLastAdmin(): bool
    {
        return User::query()->where('role', UserRole::ADMIN->value)->count() <= 1;
    }
}
