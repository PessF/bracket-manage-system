<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ApiTokenController extends Controller
{
    public function show(Request $request): View
    {
        return view('admin.api-token', ['plainTextToken' => null, 'user' => $request->user()]);
    }

    public function store(Request $request): View
    {
        $token = Str::random(64);
        $request->user()->forceFill([
            'api_token_hash' => hash('sha256', $token),
            'api_token_last_used_at' => null,
        ])->save();

        return view('admin.api-token', ['plainTextToken' => $token, 'user' => $request->user()]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->forceFill([
            'api_token_hash' => null,
            'api_token_last_used_at' => null,
        ])->save();

        return back()->with('success', __('ui.api_token_revoked'));
    }
}
