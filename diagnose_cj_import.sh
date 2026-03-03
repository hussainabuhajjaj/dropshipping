#!/bin/bash

# CJ Import Diagnostic Script
# Run this to diagnose import issues

echo "🔍 CJ Import Diagnostic Tool"
echo "=========================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

print_status() {
    echo -e "${GREEN}[OK]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

echo "🔍 Diagnosing CJ Import System..."
echo ""

# 1. Check if Laravel is working
echo "📋 1. Laravel Status"
if php artisan --version > /dev/null 2>&1; then
    print_status "Laravel is working"
    php artisan --version | head -1
else
    print_error "Laravel is not working"
    exit 1
fi

# 2. Check database connection
echo ""
echo "🗄️ 2. Database Connection"
if php artisan tinker --execute="echo 'DB OK';" 2>/dev/null | grep -q "DB OK"; then
    print_status "Database connection is working"
else
    print_error "Database connection failed"
fi

# 3. Check queue worker status
echo ""
echo "👷 3. Queue Worker Status"
if pgrep -f "queue:work.*cj-import" > /dev/null; then
    print_status "Queue worker is running"
    echo "  PID: $(pgrep -f 'queue:work.*cj-import')"
else
    print_warning "Queue worker is not running"
    echo "  Start with: php artisan queue:work --queue=cj-import --daemon"
fi

# 4. Check Redis connection
echo ""
echo "🔴 4. Redis Connection"
if php artisan tinker --execute="
try {
    \Illuminate\Support\Facades\Redis::ping();
    echo 'Redis OK';
} catch(Exception \$e) {
    echo 'Redis Failed';
}
" 2>/dev/null | grep -q "Redis OK"; then
    print_status "Redis connection is working"
else
    print_error "Redis connection failed"
fi

# 5. Check queue status
echo ""
echo "📊 5. Queue Status"
php artisan queue:monitor cj-import 2>/dev/null | head -10

# 6. Check failed jobs
echo ""
echo "❌ 6. Failed Jobs"
FAILED_COUNT=$(php artisan queue:failed 2>/dev/null | grep -c "^\[" || echo "0")
if [ "$FAILED_COUNT" -eq 0 ]; then
    print_status "No failed jobs"
else
    print_warning "$FAILED_COUNT failed jobs found"
    echo "  View with: php artisan queue:failed"
    echo "  Retry with: php artisan queue:retry all"
fi

# 7. Check recent import activity
echo ""
echo "📥 7. Recent Import Activity"
php artisan tinker --execute="
\$logs = \App\Models\UserActivityLog::where('action', 'like', '%cj.import%')->latest()->limit(3)->get(['action', 'description', 'created_at']);
foreach(\$logs as \$log) {
    echo \$log->created_at . ' - ' . \$log->action . ': ' . \$log->description . PHP_EOL;
}
" 2>/dev/null || echo "Could not fetch activity logs"

# 8. Check product counts
echo ""
echo "📦 8. Product Statistics"
php artisan tinker --execute="
echo 'Total products: ' . \App\Domain\Products\Models\Product::count() . PHP_EOL;
echo 'CJ products: ' . \App\Domain\Products\Models\Product::whereNotNull('cj_pid')->count() . PHP_EOL;
echo 'Recent CJ products (last hour): ' . \App\Domain\Products\Models\Product::whereNotNull('cj_pid')->where('created_at', '>', now()->subHour())->count() . PHP_EOL;
" 2>/dev/null || echo "Could not fetch product statistics"

# 9. Check CJ API connection
echo ""
echo "🌐 9. CJ API Connection"
php artisan tinker --execute="
try {
    \$client = app(\App\Infrastructure\Fulfillment\Clients\CJDropshippingClient::class);
    \$response = \$client->listProducts(['pageSize' => 1]);
    echo 'CJ API: OK (' . count(\$response->data ?? []) . ' products found)';
} catch(Exception \$e) {
    echo 'CJ API: FAILED - ' . \$e->getMessage();
}
" 2>/dev/null || echo "Could not test CJ API"

# 10. Check import tracker
echo ""
echo "📊 10. Import Tracker Status"
php artisan tinker --execute="
\$tracker = app(\App\Services\Cj\CjCatalogImportTracker::class);
\$keys = \Illuminate\Support\Facades\Cache::getRedis()->keys('*cj_bulk_import*');
echo 'Active tracking keys: ' . count(\$keys) . PHP_EOL;
if(count(\$keys) > 0) {
    foreach(\$keys as \$key) {
        \$data = \$tracker->get(str_replace('laravel_database_', '', \$key));
        if(\$data) {
            echo 'Key: ' . \$key . ' - Status: ' . \$data['status'] . ' (' . \$data['processed'] . '/' . \$data['total'] . ')' . PHP_EOL;
        }
    }
}
" 2>/dev/null || echo "Could not check import tracker"

# 11. Check recent errors in logs
echo ""
echo "📋 11. Recent Errors"
if [ -f "storage/logs/laravel.log" ]; then
    ERROR_COUNT=$(tail -n 100 storage/logs/laravel.log | grep -i "error\|exception\|failed" | wc -l | tr -d ' ')
    if [ "$ERROR_COUNT" -eq 0 ]; then
        print_status "No recent errors in logs"
    else
        print_warning "$ERROR_COUNT errors found in last 100 log lines"
        echo "  Recent errors:"
        tail -n 100 storage/logs/laravel.log | grep -i "error\|exception\|failed" | tail -3
    fi
else
    print_warning "Log file not found"
fi

# 12. Test a simple import
echo ""
echo "🧪 12. Test Import Job"
php artisan tinker --execute="
try {
    \$job = new \App\Jobs\ImportCjProductPipelineChunkJob(['test_pid'], ['tracking_key' => 'test_key', 'chunk_index' => 0]);
    echo 'Import job class: OK';
} catch(Exception \$e) {
    echo 'Import job class: FAILED - ' . \$e->getMessage();
}
" 2>/dev/null || echo "Could not test import job"

echo ""
echo "📊 Diagnostic Summary"
echo "===================="

# Provide recommendations
echo ""
echo "🔧 Common Fixes:"
echo "1. If queue worker is not running:"
echo "   php artisan queue:work --queue=cj-import --daemon"
echo ""
echo "2. If there are failed jobs:"
echo "   php artisan queue:retry all"
echo ""
echo "3. If Redis is not working:"
echo "   Check Redis service: systemctl status redis"
echo ""
echo "4. Clear cache and restart:"
echo "   php artisan cache:clear"
echo "   php artisan queue:restart"
echo ""
echo "5. Check CJ API credentials in .env file"
echo ""

echo "📞 If issues persist:"
echo "- Check full logs: tail -f storage/logs/laravel.log"
echo "- Run security audit: ./security_audit.sh"
echo "- Review deployment guide: CJ_IMPORT_PRODUCTION_GUIDE.md"
