#!/bin/bash

# Automated variant recovery script
# Runs the resync command in safe batches until all products have variants

SCRIPT_DIR="/var/www/vhosts/simbazu.net/httpdocs"
DB_USER="simbazu"
DB_PASS="ll?8jK?WlkXy1iu6"
DB_NAME="admin_simbazu"
BATCH_SIZE=20
WAIT_TIME=900  # 15 minutes between runs

cd "$SCRIPT_DIR" || exit 1

echo "Starting automated variant recovery..."
echo "Batch size: $BATCH_SIZE products"
echo "Wait time: $WAIT_TIME seconds (15 minutes) between batches"
echo "=========================================="

RUN_COUNT=0

while true; do
    RUN_COUNT=$((RUN_COUNT + 1))
    echo ""
    echo "Run #$RUN_COUNT - $(date)"
    
    # Check how many products need variants
    REMAINING=$(mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -N -e "SELECT COUNT(*) FROM products p LEFT JOIN product_variants pv ON p.id = pv.product_id WHERE pv.id IS NULL AND p.cj_pid IS NOT NULL AND p.cj_pid != '';" 2>/dev/null)
    
    if [ -z "$REMAINING" ]; then
        echo "Error: Could not query database. Please check credentials."
        exit 1
    fi
    
    echo "Products remaining: $REMAINING"
    
    if [ "$REMAINING" -eq 0 ]; then
        echo ""
        echo "=========================================="
        echo "✅ All products recovered!"
        echo "Total runs: $RUN_COUNT"
        echo "Completed at: $(date)"
        echo "=========================================="
        break
    fi
    
    # Run the resync command
    echo "Syncing $BATCH_SIZE products..."
    php artisan cj:resync-all --limit="$BATCH_SIZE" --batch-size=10 --delay=5
    
    SYNC_STATUS=$?
    if [ $SYNC_STATUS -ne 0 ]; then
        echo "Warning: Sync command returned error code $SYNC_STATUS"
    fi
    
    # Check if we should continue
    if [ "$REMAINING" -le "$BATCH_SIZE" ]; then
        echo "Last batch - checking final count..."
        sleep 10
        continue
    fi
    
    # Wait before next run
    echo "Waiting $WAIT_TIME seconds before next run..."
    sleep "$WAIT_TIME"
done

echo ""
echo "Recovery complete! You can now run stock sync:"
echo "php artisan cj:sync-existing-stock --turbo --force"
