<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^(\+213[\s.-]?[5-7]|0[5-7])([\s.-]?\d{2}){4}$/'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'phone.regex' => 'Le téléphone doit être au format +213 5 55 12 34 56 ou 05 55 12 34 56.',
        ]);

        // This form only ever produces clients. Agencies register through their
        // own controller, and the administrator exists solely via seeder.
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'role' => User::ROLE_CLIENT,
        ]);
        $user->syncRoles([User::ROLE_CLIENT]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
