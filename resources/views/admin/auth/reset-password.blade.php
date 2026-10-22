<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Set new admin password</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-gray-50 text-gray-900">
        <div class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-4">
            <h1 class="text-2xl font-semibold">Set a new password</h1>
            <p class="mt-2 text-sm text-gray-600">Choose a new password for your admin account.</p>

            <form method="POST" action="{{ route('admin.password.store') }}" class="mt-6 space-y-4">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div class="space-y-1">
                    <label class="text-sm font-medium">Email</label>
                    <input
                        type="email"
                        name="email"
                        required
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                        value="{{ old('email', $email) }}"
                    >
                    @error('email')
                        <p class="text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="space-y-1">
                    <label class="text-sm font-medium">Password</label>
                    <input
                        type="password"
                        name="password"
                        required
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                    >
                    @error('password')
                        <p class="text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="space-y-1">
                    <label class="text-sm font-medium">Confirm password</label>
                    <input
                        type="password"
                        name="password_confirmation"
                        required
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                    >
                </div>

                <button type="submit" class="w-full rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white">
                    Reset password
                </button>
            </form>
        </div>
    </body>
</html>
