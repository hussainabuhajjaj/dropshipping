#!/usr/bin/env bash
set -euo pipefail

SSH_TARGET="${SIMBAZU_PROD_SSH_TARGET:-simbazu.net@93.93.113.243}"
APP_DIR="${SIMBAZU_PROD_APP_DIR:-/var/www/vhosts/simbazu.net/httpdocs}"

ssh -o BatchMode=yes -o ConnectTimeout=10 "$SSH_TARGET" "cd '$APP_DIR' && php artisan tinker --execute='
echo \"Simbazu Production\\n\";
echo \"Date: \" . now()->format(\"Y-m-d H:i\") . \" UTC\\n\\n\";
echo \"Environment: \" . app()->environment() . \"\\n\";
echo \"Debug: \" . (config(\"app.debug\") ? \"on\" : \"off\") . \"\\n\";
echo \"Database: \" . DB::connection()->getDatabaseName() . \"\\n\";
echo \"Products: \" . DB::table(\"products\")->count() . \"\\n\";
echo \"Orders: \" . DB::table(\"orders\")->count() . \"\\n\";
echo \"Queued jobs: \" . (Schema::hasTable(\"jobs\") ? DB::table(\"jobs\")->count() : 0) . \"\\n\";
echo \"Failed jobs: \" . (Schema::hasTable(\"failed_jobs\") ? DB::table(\"failed_jobs\")->count() : 0) . \"\\n\";
if (Schema::hasTable(\"woocommerce_product_maps\")) {
    echo \"Woo product maps: \" . DB::table(\"woocommerce_product_maps\")->count() . \"\\n\";
}
if (Schema::hasTable(\"woocommerce_sync_logs\")) {
    echo \"Woo failed logs: \" . DB::table(\"woocommerce_sync_logs\")->where(\"status\", \"failed\")->count() . \"\\n\";
}
'"
