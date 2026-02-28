#!/bin/bash

# Optimized recovery script for critical products
# Syncs 10 products every 5 minutes with minimal server load

SCRIPT_DIR="/var/www/vhosts/simbazu.net/httpdocs"
DB_USER="simbazu"
DB_PASS="ll?8jK?WlkXy1iu6"
DB_NAME="admin_simbazu"
BATCH_SIZE=10
WAIT_TIME=300  # 5 minutes between batches

cd "$SCRIPT_DIR" || exit 1

echo "=========================================="
echo "Critical Product Variant Recovery"
echo "=========================================="
echo "Batch size: $BATCH_SIZE products"
echo "Wait time: $WAIT_TIME seconds (5 minutes)"
echo "Started at: $(date)"
echo "=========================================="
echo ""

RUN_COUNT=0
TOTAL_SYNCED=0

while true; do
    RUN_COUNT=$((RUN_COUNT + 1))
    echo "Batch #$RUN_COUNT - $(date)"
    
    # Check remaining products
    REMAINING=$(mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -N -e "SELECT COUNT(*) FROM products p LEFT JOIN product_variants pv ON p.id = pv.product_id WHERE pv.id IS NULL AND p.cj_pid IS NOT NULL AND p.cj_pid != '';" 2>/dev/null)
    
    if [ -z "$REMAINING" ]; then
        echo "Error: Could not query database"
        exit 1
    fi
    
    echo "Products remaining: $REMAINING"
    
    if [ "$REMAINING" -eq 0 ]; then
        echo ""
        echo "=========================================="
        echo "✅ All critical products recovered!"
        echo "Total batches: $RUN_COUNT"
        echo "Total synced: $TOTAL_SYNCED products"
        echo "Completed at: $(date)"
        echo "=========================================="
        break
    fi
    
    # Sync batch
    echo "Syncing $BATCH_SIZE products..."
    php artisan cj:resync-all --limit="$BATCH_SIZE" --batch-size=5 --delay=10
    
    SYNC_STATUS=$?
    if [ $SYNC_STATUS -eq 0 ]; then
        SYNCED_THIS_BATCH=$((REMAINING > BATCH_SIZE ? BATCH_SIZE : REMAINING))
        TOTAL_SYNCED=$((TOTAL_SYNCED + SYNCED_THIS_BATCH))
        echo "✓ Batch complete"
    else
        echo "⚠ Warning: Sync returned error code $SYNC_STATUS"
    fi
    
    # Check memory usage
    MEM_AVAILABLE=$(free -m | awk 'NR==2{print $7}')
    echo "Memory available: ${MEM_AVAILABLE}MB"
    
    # If memory is low, wait longer
    if [ "$MEM_AVAILABLE" -lt 3000 ]; then
        echo "⚠ Low memory detected, waiting extra 2 minutes..."
        sleep 120
    fi
    
    # Check if this is the last batch
    if [ "$REMAINING" -le "$BATCH_SIZE" ]; then
        echo "Last batch - verifying completion..."
        sleep 10
        continue
    fi
    
    # Progress report
    PROGRESS=$((TOTAL_SYNCED * 100 / 1356))
    echo "Progress: $TOTAL_SYNCED/1356 products ($PROGRESS%)"
    
    # Wait before next batch
    echo "Waiting $WAIT_TIME seconds before next batch..."
    echo ""
    sleep "$WAIT_TIME"
done

echo ""
echo "Recovery complete! Run stock sync:"
echo "php artisan cj:sync-existing-stock --turbo --force"
