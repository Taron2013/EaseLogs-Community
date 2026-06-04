<?php

namespace App\Http\Controllers;

use App\Support\DemoUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DemoLoginController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        if (! DemoUser::allowsOneClickLogin() || ! DemoUser::isConfigured()) {
            abort(404);
        }

        $user = DemoUser::ensureExists();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('artworks.index'));
    }
}
