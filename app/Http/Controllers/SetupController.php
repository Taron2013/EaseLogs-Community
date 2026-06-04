<?php

namespace App\Http\Controllers;

use App\Http\Requests\FirstRunSetupRequest;
use App\Models\User;
use App\Support\DemoMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SetupController extends Controller
{
    public function create(): View
    {
        return view('setup.create');
    }

    public function store(FirstRunSetupRequest $request): RedirectResponse
    {
        DemoMode::ensureAllowed('registration');

        if (User::query()->exists()) {
            abort(404);
        }

        $user = DB::transaction(function () use ($request): User {
            if (User::query()->lockForUpdate()->exists()) {
                abort(404);
            }

            return User::query()->create([
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'password' => $request->validated('password'),
            ]);
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()
            ->route('artworks.index')
            ->with('success', 'Your account is ready. You are signed in.');
    }
}
