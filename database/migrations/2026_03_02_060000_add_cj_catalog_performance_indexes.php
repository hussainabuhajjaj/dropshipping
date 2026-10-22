<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * CJ Catalog Performance Optimization - Critical Indexes
     * 
     * These indexes will significantly improve CJ catalog performance:
     * - Import speed (checking existing products)
     * - Sync operations (finding stale products)
     * - Search and filtering performance
     * - UI responsiveness
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // CRITICAL: CJ PID lookup (used in every import)
            // This is the most important index for CJ catalog performance
            $table->index('cj_pid', 'idx_products_cj_pid');
            
            // HIGH PRIORITY: CJ sync operations (used for bulk sync)
            // Finds products that need syncing with CJ
            $table->index(['cj_sync_enabled', 'cj_synced_at'], 'idx_products_cj_sync');
            
            // HIGH PRIORITY: Active CJ products (used for catalog display)
            // Shows only active CJ products in the catalog
            $table->index(['cj_pid', 'is_active', 'deleted_at'], 'idx_products_cj_active');
            
            // MEDIUM PRIORITY: Warehouse filtering (used in CJ catalog filters)
            // Filters products by CJ warehouse
            $table->index(['cj_warehouse_id', 'is_active'], 'idx_products_cj_warehouse');
            
            // MEDIUM PRIORITY: Stock management (used for inventory checks)
            // Finds products with low/out of stock
            $table->index(['stock_on_hand', 'is_active'], 'idx_products_stock');
            
            // MEDIUM PRIORITY: Category browsing (used for CJ catalog navigation)
            // Filters products by category
            $table->index(['category_id', 'is_active'], 'idx_products_category');
            
            // LOW PRIORITY: Status filtering (used for admin product management)
            // Filters products by status
            $table->index(['status', 'is_active'], 'idx_products_status');
            
            // LOW PRIORITY: Featured products (used for homepage/catalog highlights)
            // Finds featured products for display
            $table->index(['is_featured', 'is_active'], 'idx_products_featured');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            // CRITICAL: CJ variant lookup (used in import/sync)
            // Finds variants by CJ variant ID
            $table->index('cj_vid', 'idx_product_variants_cj_vid');
            
            // HIGH PRIORITY: Product variants (used for product display)
            // Finds all variants for a product
            $table->index('product_id', 'idx_product_variants_product');
            
            // MEDIUM PRIORITY: Stock management (used for inventory checks)
            // Finds variants with stock issues
            $table->index(['product_id', 'stock_on_hand'], 'idx_product_variants_stock');
            
            // MEDIUM PRIORITY: Price filtering (used for price-based searches)
            // Finds variants in price ranges
            $table->index(['product_id', 'price'], 'idx_product_variants_price');
        });

        Schema::table('product_images', function (Blueprint $table) {
            // HIGH PRIORITY: Product images (used for product display)
            // Finds all images for a product
            $table->index('product_id', 'idx_product_images_product');
            
            // MEDIUM PRIORITY: Image ordering (used for proper display)
            // Orders images by position
            $table->index(['product_id', 'position'], 'idx_product_images_order');
        });

        Schema::table('supplier_products', function (Blueprint $table) {
            // MEDIUM PRIORITY: Supplier variants (used for cost/fulfillment)
            // Finds supplier products for variants
            $table->index('product_variant_id', 'idx_supplier_products_variant');
            
            // MEDIUM PRIORITY: Active suppliers (used for fulfillment)
            // Finds active supplier products
            $table->index(['product_variant_id', 'is_active'], 'idx_supplier_products_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_products_cj_pid');
            $table->dropIndex('idx_products_cj_sync');
            $table->dropIndex('idx_products_cj_active');
            $table->dropIndex('idx_products_cj_warehouse');
            $table->dropIndex('idx_products_stock');
            $table->dropIndex('idx_products_category');
            $table->dropIndex('idx_products_status');
            $table->dropIndex('idx_products_featured');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropIndex('idx_product_variants_cj_vid');
            $table->dropIndex('idx_product_variants_product');
            $table->dropIndex('idx_product_variants_stock');
            $table->dropIndex('idx_product_variants_price');
        });

        Schema::table('product_images', function (Blueprint $table) {
            $table->dropIndex('idx_product_images_product');
            $table->dropIndex('idx_product_images_order');
        });

        Schema::table('supplier_products', function (Blueprint $table) {
            $table->dropIndex('idx_supplier_products_variant');
            $table->dropIndex('idx_supplier_products_active');
        });
    }
};
