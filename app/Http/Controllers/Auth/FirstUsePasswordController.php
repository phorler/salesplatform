<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * First-use account activation. Allowed users are seeded with no password
 * (must_set_password = true); each sets their own password the first time they
 * use the site. There is no public registration — only seeded accounts exist.
 */
class FirstUsePasswordController extends Controller
{
    public function create(): View
    {
        return view('auth.first-use');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::where('email', $request->email)
            ->where('must_set_password', true)
            ->first();

        if (! $user) {
            // Same message whether the email is unknown or already activated —
            // don't reveal which accounts exist.
            return back()->withErrors([
                'email' => __('This account is not awaiting setup. If you already set a password, sign in instead.'),
            ])->onlyInput('email');
        }

        $user->forceFill([
            'password' => Hash::make($request->password),
            'must_set_password' => false,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        event(new Registered($user));

        Auth::login($user);

        return redirect()->route('dashboard');
    }
}
