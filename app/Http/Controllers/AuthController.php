<?php

namespace App\Http\Controllers;

use App\Models\ChatState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Sign-in for the test accounts Douglas hands out. There is no self-registration. */
class AuthController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:120'],
            'password' => ['required', 'string', 'max:200'],
            'website' => ['nullable', 'max:0'], // honeypot: people never fill it in
        ]);

        // Auth::attempt() moves the session to a new id, so note the old one first.
        $guestSessionId = $request->session()->getId();
        if (! Auth::attempt(['email' => mb_strtolower($data['email']), 'password' => $data['password']], true)) {
            throw ValidationException::withMessages(['email' => 'That email address and password do not match a test account.']);
        }

        // The new session id starts the chat afresh on the signed-in route.
        ChatState::forget($guestSessionId);
        $request->session()->regenerate();

        return redirect()->intended(route('channel'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        ChatState::forget($request->session()->getId());
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
