<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Status Card -->
        <div class="rounded-lg border @if($isConfigured) border-green-300 bg-green-50 @else border-yellow-300 bg-yellow-50 @endif p-6">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0">
                    @if($isConfigured)
                        <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    @else
                        <svg class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    @endif
                </div>
                <div>
                    <h3 class="text-lg font-semibold @if($isConfigured) text-green-900 @else text-yellow-900 @endif">
                        @if($isConfigured)
                            Google OAuth Configured
                        @else
                            Configuration Incomplete
                        @endif
                    </h3>
                    <p class="mt-1 text-sm @if($isConfigured) text-green-700 @else text-yellow-700 @endif">
                        @if($isConfigured)
                            Your application is connected to Google Calendar API and ready to authenticate users.
                        @else
                            Complete the setup below to enable Google OAuth and Calendar integration.
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <!-- Configuration Details -->
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header flex items-center gap-x-3 overflow-hidden px-6 py-4">
                <div class="grid flex-1 gap-y-1">
                    <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        OAuth Configuration
                    </h3>
                    <p class="fi-section-header-description text-sm text-gray-500 dark:text-gray-400">
                        Credentials and settings from your .env file
                    </p>
                </div>
            </div>
            <div class="fi-section-content-ctn border-t border-gray-200 dark:border-white/10">
                <div class="fi-section-content p-6">
                    <dl class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Client ID</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $clientId ?? 'Not set' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Client Secret</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $clientSecret ?? 'Not set' }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Redirect URI</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $redirectUri ?? 'Not set' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        <!-- File Status -->
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header flex items-center gap-x-3 overflow-hidden px-6 py-4">
                <div class="grid flex-1 gap-y-1">
                    <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        File Status
                    </h3>
                    <p class="fi-section-header-description text-sm text-gray-500 dark:text-gray-400">
                        Required files in storage/app/private/google/
                    </p>
                </div>
            </div>
            <div class="fi-section-content-ctn border-t border-gray-200 dark:border-white/10">
                <div class="fi-section-content p-6">
                    <div class="space-y-3">
                        <div class="flex items-center justify-between rounded-lg border border-gray-200 p-3 dark:border-white/10">
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">oauth-credentials.json</p>
                                <p class="text-xs text-gray-500">Google OAuth client credentials</p>
                            </div>
                            @if($hasCredentialsFile)
                                <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">
                                    ✓ Present
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800">
                                    ✗ Missing
                                </span>
                            @endif
                        </div>

                        <div class="flex items-center justify-between rounded-lg border border-gray-200 p-3 dark:border-white/10">
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">oauth-token.json</p>
                                <p class="text-xs text-gray-500">Current access token (auto-generated)</p>
                            </div>
                            @if($hasAccessToken)
                                <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">
                                    ✓ Present
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800">
                                    Not yet authorized
                                </span>
                            @endif
                        </div>

                        <div class="flex items-center justify-between rounded-lg border border-gray-200 p-3 dark:border-white/10">
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">oauth-refresh-token.json</p>
                                <p class="text-xs text-gray-500">Refresh token for token renewal</p>
                            </div>
                            @if($hasRefreshToken)
                                <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">
                                    ✓ Present
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800">
                                    Not yet authorized
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Setup Instructions -->
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header flex items-center gap-x-3 overflow-hidden px-6 py-4">
                <div class="grid flex-1 gap-y-1">
                    <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        Quick Setup Guide
                    </h3>
                </div>
            </div>
            <div class="fi-section-content-ctn border-t border-gray-200 dark:border-white/10">
                <div class="fi-section-content p-6">
                    <ol class="list-decimal space-y-3 pl-5 text-sm text-gray-700 dark:text-gray-300">
                        <li>Create OAuth credentials in <a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="text-primary-600 hover:underline">Google Cloud Console</a></li>
                        <li>Enable Google Calendar API in API Library</li>
                        <li>Download credentials JSON file</li>
                        <li>Place file at: <code class="rounded bg-gray-100 px-1 text-xs dark:bg-gray-800">storage/app/private/google/oauth-credentials.json</code></li>
                        <li>Add to .env:
                            <pre class="mt-2 overflow-x-auto rounded bg-gray-100 p-2 text-xs dark:bg-gray-800">GOOGLE_CLIENT_ID=your_id
GOOGLE_CLIENT_SECRET=your_secret
GOOGLE_REDIRECT_URI=https://yourdomain.com/auth/google/callback</pre>
                        </li>
                        <li>Users can connect at: <a href="{{ route('auth.google.redirect') }}" target="_blank" class="text-primary-600 hover:underline">/auth/google/redirect</a></li>
                    </ol>
                    <div class="mt-4">
                        <a href="{{ asset('docs/GOOGLE_OAUTH_SETUP.md') }}" class="text-sm font-medium text-primary-600 hover:underline">
                            View full documentation →
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
