<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Admin password reset</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-gray-50 text-gray-900">
        <div class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-4">
            <h1 class="text-2xl font-semibold">Reset admin password</h1>
            <p class="mt-2 text-sm text-gray-600">Enter your email to receive a reset link.</p>

            @if (session('status'))
                <div class="mt-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.password.email') }}" class="mt-6 space-y-4">
                @csrf
                <div class="space-y-1">
                    <label class="text-sm font-medium">Email</label>
                    <input
                        type="email"
                        name="email"
                        required
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                        value="{{ old('email') }}"
                    >
                    @error('email')
                        <p class="text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="w-full rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white">
                    Send reset link
                </button>
            </form>
        </div>
    </body>
</html>
