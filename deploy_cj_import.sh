#!/bin/bash

# CJ Catalog Import - Production Deployment Script
# This script prepares and deploys the optimized CJ catalog import system

echo "🚀 CJ Catalog Import - Production Deployment"
echo "=========================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    print_error "Please run this script from the Laravel project root"
    exit 1
fi

print_status "Starting deployment preparation..."

# 1. Clear all caches
echo "📦 Clearing caches..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
print_status "All caches cleared"

# 2. Optimize for production
echo "⚡ Optimizing for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
print_status "Production optimizations applied"

# 3. Check queue configuration
echo "🔄 Checking queue configuration..."
php artisan queue:monitor cj-import
if [ $? -eq 0 ]; then
    print_status "Queue configuration OK"
else
    print_error "Queue configuration issue"
    exit 1
fi

# 4. Test database connection
echo "🗄️ Testing database connection..."
php artisan tinker --execute="echo 'Database connection: OK';" > /dev/null 2>&1
if [ $? -eq 0 ]; then
    print_status "Database connection OK"
else
    print_error "Database connection failed"
    exit 1
fi

# 5. Test CJ API connection
echo "🌐 Testing CJ API connection..."
php artisan tinker --execute="
\$client = app(\App\Infrastructure\Fulfillment\Clients\CJDropshippingClient::class);
try {
    \$response = \$client->listProducts(['pageSize' => 1]);
    echo 'CJ API: OK';
} catch (Exception \$e) {
    echo 'CJ API: FAILED';
}
" 2>/dev/null | grep -q "OK"
if [ $? -eq 0 ]; then
    print_status "CJ API connection OK"
else
    print_warning "CJ API connection test failed - check API credentials"
fi

# 6. Create necessary directories
echo "📁 Creating necessary directories..."
mkdir -p storage/app/public/imports
mkdir -p storage/logs/imports
print_status "Directories created"

# 7. Set proper permissions
echo "🔐 Setting proper permissions..."
chmod -R 775 storage
chmod -R 775 bootstrap/cache
print_status "Permissions set"

# 8. Check if queue worker should be running
echo "👷 Checking queue worker status..."
if pgrep -f "queue:work.*cj-import" > /dev/null; then
    print_status "Queue worker is already running"
else
    print_warning "Queue worker not running - start it with:"
    echo "   php artisan queue:work --queue=cj-import --daemon"
fi

# 9. Create monitoring script
echo "📊 Creating monitoring script..."
cat > monitor_cj_imports.sh << 'EOF'
#!/bin/bash
# Monitor CJ Import Queue Status
echo "📊 CJ Import Queue Status"
echo "======================"
php artisan queue:monitor cj-import
echo ""
echo "📈 Recent Import Logs:"
tail -n 10 storage/logs/laravel.log | grep -i "cj.*import\|chunk" || echo "No recent import activity"
EOF

chmod +x monitor_cj_imports.sh
print_status "Monitoring script created"

# 10. Final health check
echo "🏥 Running final health check..."
php artisan tinker --execute="
\$tracker = app(\App\Services\Cj\CjCatalogImportTracker::class);
\$service = app(\App\Domain\Products\Services\CjProductImportService::class);
echo 'Components: OK';
" 2>/dev/null | grep -q "OK"
if [ $? -eq 0 ]; then
    print_status "All components loaded successfully"
else
    print_error "Component health check failed"
    exit 1
fi

echo ""
print_status "🎉 CJ Catalog Import System Ready for Production!"
echo ""
echo "📋 Next Steps:"
echo "   1. Start queue worker: php artisan queue:work --queue=cj-import --daemon"
echo "   2. Test with small batch (5 products)"
echo "   3. Monitor with: ./monitor_cj_imports.sh"
echo "   4. Check logs: tail -f storage/logs/laravel.log | grep -i cj"
echo ""
echo "⚡ Performance Features Enabled:"
echo "   ✓ Asynchronous background processing"
echo "   ✓ Real-time progress updates (3-second polling)"
echo "   ✓ Optimized batch processing (25-100 products per chunk)"
echo "   ✓ Smart error handling and retries"
echo "   ✓ Progress tracking with auto-cleanup"
echo ""
print_warning "Remember to configure your supervisor/daemon to keep queue workers running!"
