#!/bin/bash

# Kill existing queue workers
pkill -f "php artisan queue:work"

# Start multiple queue workers for different queues
echo "Starting queue workers..."

# Media queue worker (handles image sync)
php artisan queue:work --queue=media --timeout=1200 --tries=3 --sleep=3 &
echo "Media queue worker started (PID: $!)"

# Variants queue worker (handles variant sync)
php artisan queue:work --queue=variants --timeout=1200 --tries=3 --sleep=3 &
echo "Variants queue worker started (PID: $!)"

# CJ sync queue worker (handles CJ API calls)
php artisan queue:work --queue=cj-sync --timeout=1200 --tries=3 --sleep=3 &
echo "CJ sync queue worker started (PID: $!)"

# Pricing queue worker (handles margin calculations)
php artisan queue:work --queue=pricing --timeout=1200 --tries=3 --sleep=3 &
echo "Pricing queue worker started (PID: $!)"

# Import queue worker (handles product imports)
php artisan queue:work --queue=import --timeout=1200 --tries=3 --sleep=3 &
echo "Import queue worker started (PID: $!)"

# Default queue worker (handles everything else)
php artisan queue:work --queue=default --timeout=1200 --tries=3 --sleep=3 &
echo "Default queue worker started (PID: $!)"

echo "All queue workers started!"
echo "Use 'pkill -f \"php artisan queue:work\"' to stop all workers"

# Show running workers
echo ""
echo "Running queue workers:"
ps aux | grep "php artisan queue:work" | grep -v grep
