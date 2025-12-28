# Google OAuth Integration Test Script
# Run this from PowerShell after packages are installed

Write-Host "======================================" -ForegroundColor Cyan
Write-Host "Google OAuth Integration Test" -ForegroundColor Cyan
Write-Host "======================================" -ForegroundColor Cyan
Write-Host ""

# Change to project directory
Set-Location -Path "e:\laragon\www\dropshipping"

# Test 1: Check .env configuration
Write-Host "Test 1: Environment Variables" -ForegroundColor Yellow
$envContent = Get-Content .env -Raw
$hasClientId = $envContent -match "GOOGLE_CLIENT_ID="
$hasClientSecret = $envContent -match "GOOGLE_CLIENT_SECRET="
$hasRedirectUri = $envContent -match "GOOGLE_REDIRECT_URI="

if ($hasClientId -and $hasClientSecret -and $hasRedirectUri) {
    Write-Host "✓ Environment variables configured" -ForegroundColor Green
} else {
    Write-Host "✗ Missing environment variables" -ForegroundColor Red
    Write-Host "  Add GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_REDIRECT_URI to .env" -ForegroundColor Gray
}
Write-Host ""

# Test 2: Check credentials file
Write-Host "Test 2: Credentials File" -ForegroundColor Yellow
$credentialsPath = "storage\app\private\google\oauth-credentials.json"
if (Test-Path $credentialsPath) {
    Write-Host "✓ Credentials file found" -ForegroundColor Green
} else {
    Write-Host "✗ Credentials file missing" -ForegroundColor Red
    Write-Host "  Place oauth-credentials.json at: $credentialsPath" -ForegroundColor Gray
}
Write-Host ""

# Test 3: Check required directories
Write-Host "Test 3: Storage Directories" -ForegroundColor Yellow
$googleDir = "storage\app\private\google"
if (Test-Path $googleDir) {
    Write-Host "✓ Google storage directory exists" -ForegroundColor Green
} else {
    Write-Host "✗ Google storage directory missing" -ForegroundColor Red
    Write-Host "  Creating directory..." -ForegroundColor Gray
    New-Item -ItemType Directory -Path $googleDir -Force | Out-Null
    Write-Host "  Directory created!" -ForegroundColor Green
}
Write-Host ""

# Test 4: Check routes
Write-Host "Test 4: OAuth Routes" -ForegroundColor Yellow
$routesContent = Get-Content "routes\auth.php" -Raw
$hasOAuthRoutes = $routesContent -match "auth/google/redirect"
if ($hasOAuthRoutes) {
    Write-Host "✓ OAuth routes registered" -ForegroundColor Green
} else {
    Write-Host "✗ OAuth routes not found in routes/auth.php" -ForegroundColor Red
}
Write-Host ""

# Test 5: Check service file
Write-Host "Test 5: Service Files" -ForegroundColor Yellow
$serviceExists = Test-Path "app\Services\GoogleOAuthService.php"
$controllerExists = Test-Path "app\Http\Controllers\Auth\GoogleOAuthController.php"
if ($serviceExists -and $controllerExists) {
    Write-Host "✓ Service and controller files present" -ForegroundColor Green
} else {
    Write-Host "✗ Missing service or controller files" -ForegroundColor Red
}
Write-Host ""

# Test 6: Check Vue component
Write-Host "Test 6: Frontend Component" -ForegroundColor Yellow
$componentExists = Test-Path "resources\js\Components\GoogleCalendar.vue"
if ($componentExists) {
    Write-Host "✓ GoogleCalendar.vue component exists" -ForegroundColor Green
} else {
    Write-Host "✗ GoogleCalendar.vue component missing" -ForegroundColor Red
}
Write-Host ""

# Test 7: Check packages
Write-Host "Test 7: Composer Packages" -ForegroundColor Yellow
$composerLock = Get-Content "composer.lock" -Raw
$hasSocialite = $composerLock -match "laravel/socialite"
$hasGoogleApi = $composerLock -match "google/apiclient"
if ($hasSocialite -and $hasGoogleApi) {
    Write-Host "✓ Required packages installed" -ForegroundColor Green
} else {
    Write-Host "✗ Missing packages" -ForegroundColor Red
    if (-not $hasSocialite) {
        Write-Host "  Missing: laravel/socialite" -ForegroundColor Gray
    }
    if (-not $hasGoogleApi) {
        Write-Host "  Missing: google/apiclient" -ForegroundColor Gray
    }
    Write-Host "  Run: composer require laravel/socialite google/apiclient" -ForegroundColor Gray
}
Write-Host ""

# Summary
Write-Host "======================================" -ForegroundColor Cyan
Write-Host "Test Summary" -ForegroundColor Cyan
Write-Host "======================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "If all tests pass, you can:" -ForegroundColor White
Write-Host "1. Start your Laravel server" -ForegroundColor Gray
Write-Host "2. Visit: http://localhost/auth/google/redirect" -ForegroundColor Gray
Write-Host "3. Complete OAuth authorization" -ForegroundColor Gray
Write-Host "4. Test Calendar API: http://localhost/api/calendar/events" -ForegroundColor Gray
Write-Host ""
Write-Host "Documentation: docs\GOOGLE_OAUTH_SETUP.md" -ForegroundColor Yellow
Write-Host ""
