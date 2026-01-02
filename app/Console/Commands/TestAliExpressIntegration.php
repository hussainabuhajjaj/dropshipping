<?php

namespace App\Console\Commands;

use App\Models\AliExpressToken;
use App\Infrastructure\Fulfillment\Clients\AliExpressClient;
use App\Domain\Products\Services\AliExpressProductImportService;
use App\Domain\Products\Services\AliExpressCategorySyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestAliExpressIntegration extends Command
{
    protected $signature = 'aliexpress:test {--action=full}';
    
    protected $description = 'Test AliExpress integration (token, client, services). Actions: full, token, categories, products, refresh';

    public function handle(): int
    {
        $action = $this->option('action');
        
        $this->line("\n🔍 AliExpress Integration Test\n");
        $this->line("Action: <info>$action</info>\n");

        try {
            match ($action) {
                'full' => $this->testFull(),
                'token' => $this->testToken(),
                'categories' => $this->testCategories(),
                'products' => $this->testProducts(),
                'refresh' => $this->testTokenRefresh(),
                default => $this->error("Unknown action: $action"),
            };

            $this->info("\n✅ Test completed successfully\n");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("\n❌ Test failed: " . $e->getMessage() . "\n");
            Log::error('AliExpress test error', ['error' => $e->getMessage(), 'action' => $action]);
            return Command::FAILURE;
        }
    }

    private function testFull(): void
    {
        $this->testToken();
        $this->line('');
        $this->testCategories();
        $this->line('');
        $this->testProducts();
    }

    private function testToken(): void
    {
        $this->line('📋 Testing Token Storage and Retrieval...');
        
        $token = AliExpressToken::getLatestToken();
        
        if (!$token) {
            $this->warn('⚠️  No token found. Run: php artisan serve, then visit /aliexpress/oauth/redirect');
            return;
        }

        $this->info('✓ Token found');
        $this->line("  • Access Token: " . substr($token->access_token, 0, 20) . "...");
        $this->line("  • Expires At: " . ($token->expires_at ? $token->expires_at->diffForHumans() : 'Never'));
        $this->line("  • Refresh Token: " . ($token->refresh_token ? substr($token->refresh_token, 0, 20) . "..." : 'None'));
        
        $this->info('✓ Token Status: ' . ($token->isExpired() ? '❌ Expired' : '✅ Valid'));
        $this->info('✓ Can Refresh: ' . ($token->canRefresh() ? '✅ Yes' : '❌ No'));
    }

    private function testCategories(): void
    {
        $this->line('📂 Testing Category Sync...');
        
        $token = AliExpressToken::getLatestToken();
        if (!$token) {
            $this->warn('⚠️  No token found. Skipping.');
            return;
        }

        if ($token->isExpired()) {
            $this->warn('⚠️  Token expired. Cannot test. Run: php artisan aliexpress:test --action=refresh');
            return;
        }

        try {
            $service = app(AliExpressCategorySyncService::class);
            $this->line('Syncing categories...');
            
            $categories = $service->syncCategories();
            $this->info('✓ Categories synced: ' . count($categories));
            
            if (count($categories) > 0) {
                $this->line('  Sample categories:');
                foreach (array_slice($categories, 0, 3) as $cat) {
                    $this->line("    • {$cat['name']} (ID: {$cat['ali_category_id']})");
                }
            }
        } catch (\Exception $e) {
            $this->error('✗ Category sync failed: ' . $e->getMessage());
        }
    }

    private function testProducts(): void
    {
        $this->line('🛍️  Testing Product Import...');
        
        $token = AliExpressToken::getLatestToken();
        if (!$token) {
            $this->warn('⚠️  No token found. Skipping.');
            return;
        }

        if ($token->isExpired()) {
            $this->warn('⚠️  Token expired. Cannot test. Run: php artisan aliexpress:test --action=refresh');
            return;
        }

        try {
            $service = app(AliExpressProductImportService::class);
            $this->line('Searching for electronics...');
            
            $products = $service->importBySearch([
                'keyword' => 'electronics',
                'page_size' => 5,
            ]);
            
            $this->info('✓ Products imported: ' . count($products));
            
            if (count($products) > 0) {
                $this->line('  Sample products:');
                foreach (array_slice($products, 0, 3) as $prod) {
                    $this->line("    • {$prod['name']} (Price: {$prod['price']})");
                }
            }
        } catch (\Exception $e) {
            $this->error('✗ Product import failed: ' . $e->getMessage());
        }
    }

    private function testTokenRefresh(): void
    {
        $this->line('🔄 Testing Token Refresh...');
        
        $token = AliExpressToken::getLatestToken();
        if (!$token) {
            $this->warn('⚠️  No token found. Skipping.');
            return;
        }

        if (!$token->canRefresh()) {
            $this->warn('⚠️  Cannot refresh token (no refresh_token or refresh expired).');
            return;
        }

        try {
            $this->line('Attempting refresh...');
            $response = \Illuminate\Support\Facades\Http::asForm()->post(
                'https://api-sg.aliexpress.com/rest/auth/token/create',
                [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $token->refresh_token,
                    'client_id' => config('ali_express.client_id'),
                    'client_secret' => config('ali_express.client_secret'),
                ]
            );

            $data = $response->json();

            if (!isset($data['access_token'])) {
                throw new \Exception('API returned no access_token: ' . json_encode($data));
            }

            $token->update([
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? $token->refresh_token,
                'expires_at' => isset($data['expires_in']) ? now()->addSeconds($data['expires_in']) : null,
                'raw' => json_encode($data),
            ]);

            $this->info('✓ Token refreshed successfully');
            $this->line("  • New Token Expires: " . $token->expires_at->diffForHumans());
        } catch (\Exception $e) {
            $this->error('✗ Token refresh failed: ' . $e->getMessage());
        }
    }
}
