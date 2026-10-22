<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ClaimAccountController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/ClaimAccount', [
            'status' => session('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $customer = Customer::query()->where('email', $request->string('email'))->first();

        if (! $customer) {
            throw ValidationException::withMessages([
                'email' => 'We could not find a customer with that email.',
            ]);
        }

        if (! empty($customer->password)) {
            throw ValidationException::withMessages([
                'email' => 'This account already has a password. Please log in instead.',
            ]);
        }

        $status = Password::broker('customers')->sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', __($status));
        }

        throw ValidationException::withMessages([
            'email' => [trans($status)],
        ]);
    }
}
