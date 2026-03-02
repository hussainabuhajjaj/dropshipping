#!/bin/bash
# Monitor CJ Import Queue Status
echo "📊 CJ Import Queue Status"
echo "======================"
php artisan queue:monitor cj-import
echo ""
echo "📈 Recent Import Logs:"
tail -n 10 storage/logs/laravel.log | grep -i "cj.*import\|chunk" || echo "No recent import activity"
