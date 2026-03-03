#!/bin/bash

# Server health monitoring during recovery
LOG_FILE="/tmp/critical-recovery.log"
MONITOR_INTERVAL=60  # Check every 60 seconds

echo "=========================================="
echo "Server Health Monitor"
echo "Started at: $(date)"
echo "=========================================="
echo ""

while true; do
    clear
    echo "=========================================="
    echo "Server Health Monitor - $(date)"
    echo "=========================================="
    echo ""
    
    # Memory usage
    echo "📊 MEMORY USAGE:"
    free -h | grep -E "Mem|Swap"
    echo ""
    
    # CPU load
    echo "💻 CPU LOAD:"
    uptime
    echo ""
    
    # Top processes by CPU
    echo "🔥 TOP CPU PROCESSES:"
    ps aux --sort=-%cpu | head -6 | awk '{printf "%-10s %5s %5s %s\n", $1, $3, $4, $11}'
    echo ""
    
    # Top processes by Memory
    echo "🧠 TOP MEMORY PROCESSES:"
    ps aux --sort=-%mem | head -6 | awk '{printf "%-10s %5s %5s %s\n", $1, $3, $4, $11}'
    echo ""
    
    # Check if recovery script is running
    if pgrep -f "critical-recovery.sh" > /dev/null; then
        echo "✅ Recovery script: RUNNING"
    else
        echo "❌ Recovery script: NOT RUNNING"
    fi
    echo ""
    
    # Check recovery progress
    if [ -f "$LOG_FILE" ]; then
        echo "📈 RECOVERY PROGRESS:"
        tail -5 "$LOG_FILE" | grep -E "Batch|remaining|Progress|complete"
        echo ""
    fi
    
    # Check for OOM kills
    OOM_COUNT=$(dmesg | grep -i "out of memory" | wc -l)
    if [ "$OOM_COUNT" -gt 0 ]; then
        echo "⚠️  WARNING: $OOM_COUNT OOM events detected!"
    fi
    
    # Disk usage
    echo "💾 DISK USAGE:"
    df -h / | tail -1 | awk '{printf "Used: %s / %s (%s)\n", $3, $2, $5}'
    echo ""
    
    echo "=========================================="
    echo "Press Ctrl+C to stop monitoring"
    echo "Next update in $MONITOR_INTERVAL seconds..."
    echo "=========================================="
    
    sleep "$MONITOR_INTERVAL"
done
