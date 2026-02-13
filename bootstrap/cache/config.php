<?php return array (
  'hashing' => 
  array (
    'driver' => 'bcrypt',
    'bcrypt' => 
    array (
      'rounds' => '12',
      'verify' => true,
      'limit' => NULL,
    ),
    'argon' => 
    array (
      'memory' => 65536,
      'threads' => 1,
      'time' => 4,
      'verify' => true,
    ),
    'rehash_on_login' => true,
  ),
  'concurrency' => 
  array (
    'default' => 'process',
  ),
  'view' => 
  array (
    'paths' => 
    array (
      0 => '/Users/husseinabuhajjaj/Sites/dropshipping/resources/views',
    ),
    'compiled' => '/Users/husseinabuhajjaj/Sites/dropshipping/storage/framework/views',
  ),
  'ai' => 
  array (
    'provider' => 'deepseek',
    'moderation' => 
    array (
      'blacklist' => 
      array (
      ),
      'use_deepseek' => false,
    ),
  ),
  'ali_express' => 
  array (
    'base_url' => 'https://api-sg.aliexpress.com',
    'client_id' => NULL,
    'client_secret' => NULL,
    'api_base' => 'https://openapi.aliexpress.com/gateway.do',
    'redirect_uri' => NULL,
  ),
  'app' => 
  array (
    'name' => 'Simbazu',
    'env' => 'local',
    'debug' => true,
    'url' => 'http://localhost',
    'frontend_url' => 'http://localhost:3000',
    'asset_url' => NULL,
    'timezone' => 'UTC',
    'locale' => 'en',
    'fallback_locale' => 'en',
    'faker_locale' => 'en_US',
    'cipher' => 'AES-256-CBC',
    'key' => 'base64:3Q+asl8k9dN2g2R+9SuxP5AwRmH8nhUrKnxwtdftuu0=',
    'previous_keys' => 
    array (
    ),
    'maintenance' => 
    array (
      'driver' => 'file',
      'store' => 'database',
    ),
    'providers' => 
    array (
      0 => 'Illuminate\\Auth\\AuthServiceProvider',
      1 => 'Illuminate\\Broadcasting\\BroadcastServiceProvider',
      2 => 'Illuminate\\Bus\\BusServiceProvider',
      3 => 'Illuminate\\Cache\\CacheServiceProvider',
      4 => 'Illuminate\\Foundation\\Providers\\ConsoleSupportServiceProvider',
      5 => 'Illuminate\\Concurrency\\ConcurrencyServiceProvider',
      6 => 'Illuminate\\Cookie\\CookieServiceProvider',
      7 => 'Illuminate\\Database\\DatabaseServiceProvider',
      8 => 'Illuminate\\Encryption\\EncryptionServiceProvider',
      9 => 'Illuminate\\Filesystem\\FilesystemServiceProvider',
      10 => 'Illuminate\\Foundation\\Providers\\FoundationServiceProvider',
      11 => 'Illuminate\\Hashing\\HashServiceProvider',
      12 => 'Illuminate\\Mail\\MailServiceProvider',
      13 => 'Illuminate\\Notifications\\NotificationServiceProvider',
      14 => 'Illuminate\\Pagination\\PaginationServiceProvider',
      15 => 'Illuminate\\Auth\\Passwords\\PasswordResetServiceProvider',
      16 => 'Illuminate\\Pipeline\\PipelineServiceProvider',
      17 => 'Illuminate\\Queue\\QueueServiceProvider',
      18 => 'Illuminate\\Redis\\RedisServiceProvider',
      19 => 'Illuminate\\Session\\SessionServiceProvider',
      20 => 'Illuminate\\Translation\\TranslationServiceProvider',
      21 => 'Illuminate\\Validation\\ValidationServiceProvider',
      22 => 'Illuminate\\View\\ViewServiceProvider',
      23 => 'App\\Providers\\AppServiceProvider',
      24 => 'App\\Providers\\FulfillmentServiceProvider',
      25 => 'App\\Providers\\Filament\\AdminPanelProvider',
      26 => 'App\\Providers\\AuthServiceProvider',
      27 => 'App\\Providers\\BroadcastServiceProvider',
      28 => 'App\\Providers\\EventServiceProvider',
      29 => 'App\\Providers\\HorizonServiceProvider',
      30 => 'App\\Providers\\QueueServiceProvider',
      31 => 'App\\Providers\\AppServiceProvider',
      32 => 'App\\Providers\\HorizonServiceProvider',
    ),
    'aliases' => 
    array (
      'App' => 'Illuminate\\Support\\Facades\\App',
      'Arr' => 'Illuminate\\Support\\Arr',
      'Artisan' => 'Illuminate\\Support\\Facades\\Artisan',
      'Auth' => 'Illuminate\\Support\\Facades\\Auth',
      'Benchmark' => 'Illuminate\\Support\\Benchmark',
      'Blade' => 'Illuminate\\Support\\Facades\\Blade',
      'Broadcast' => 'Illuminate\\Support\\Facades\\Broadcast',
      'Bus' => 'Illuminate\\Support\\Facades\\Bus',
      'Cache' => 'Illuminate\\Support\\Facades\\Cache',
      'Concurrency' => 'Illuminate\\Support\\Facades\\Concurrency',
      'Config' => 'Illuminate\\Support\\Facades\\Config',
      'Context' => 'Illuminate\\Support\\Facades\\Context',
      'Cookie' => 'Illuminate\\Support\\Facades\\Cookie',
      'Crypt' => 'Illuminate\\Support\\Facades\\Crypt',
      'Date' => 'Illuminate\\Support\\Facades\\Date',
      'DB' => 'Illuminate\\Support\\Facades\\DB',
      'Eloquent' => 'Illuminate\\Database\\Eloquent\\Model',
      'Event' => 'Illuminate\\Support\\Facades\\Event',
      'File' => 'Illuminate\\Support\\Facades\\File',
      'Gate' => 'Illuminate\\Support\\Facades\\Gate',
      'Hash' => 'Illuminate\\Support\\Facades\\Hash',
      'Http' => 'Illuminate\\Support\\Facades\\Http',
      'Js' => 'Illuminate\\Support\\Js',
      'Lang' => 'Illuminate\\Support\\Facades\\Lang',
      'Log' => 'Illuminate\\Support\\Facades\\Log',
      'Mail' => 'Illuminate\\Support\\Facades\\Mail',
      'Notification' => 'Illuminate\\Support\\Facades\\Notification',
      'Number' => 'Illuminate\\Support\\Number',
      'Password' => 'Illuminate\\Support\\Facades\\Password',
      'Process' => 'Illuminate\\Support\\Facades\\Process',
      'Queue' => 'Illuminate\\Support\\Facades\\Queue',
      'RateLimiter' => 'Illuminate\\Support\\Facades\\RateLimiter',
      'Redirect' => 'Illuminate\\Support\\Facades\\Redirect',
      'Request' => 'Illuminate\\Support\\Facades\\Request',
      'Response' => 'Illuminate\\Support\\Facades\\Response',
      'Route' => 'Illuminate\\Support\\Facades\\Route',
      'Schedule' => 'Illuminate\\Support\\Facades\\Schedule',
      'Schema' => 'Illuminate\\Support\\Facades\\Schema',
      'Session' => 'Illuminate\\Support\\Facades\\Session',
      'Storage' => 'Illuminate\\Support\\Facades\\Storage',
      'Str' => 'Illuminate\\Support\\Str',
      'Uri' => 'Illuminate\\Support\\Uri',
      'URL' => 'Illuminate\\Support\\Facades\\URL',
      'Validator' => 'Illuminate\\Support\\Facades\\Validator',
      'View' => 'Illuminate\\Support\\Facades\\View',
      'Vite' => 'Illuminate\\Support\\Facades\\Vite',
    ),
    'inventory' => 
    array (
      'low_stock_warning_threshold' => 5,
      'allow_uncertain_stock' => true,
      'limited_availability_threshold' => 3,
    ),
    'orders' => 
    array (
      'auto_approve_refunds' => true,
      'delivery_confirmation_days' => 30,
    ),
  ),
  'auth' => 
  array (
    'defaults' => 
    array (
      'guard' => 'web',
      'passwords' => 'users',
    ),
    'guards' => 
    array (
      'web' => 
      array (
        'driver' => 'session',
        'provider' => 'users',
      ),
      'customer' => 
      array (
        'driver' => 'session',
        'provider' => 'customers',
      ),
      'admin' => 
      array (
        'driver' => 'session',
        'provider' => 'users',
      ),
      'sanctum' => 
      array (
        'driver' => 'sanctum',
        'provider' => NULL,
      ),
    ),
    'providers' => 
    array (
      'users' => 
      array (
        'driver' => 'eloquent',
        'model' => 'App\\Models\\User',
      ),
      'customers' => 
      array (
        'driver' => 'eloquent',
        'model' => 'App\\Models\\Customer',
      ),
    ),
    'passwords' => 
    array (
      'users' => 
      array (
        'provider' => 'users',
        'table' => 'password_reset_tokens',
        'expire' => 60,
        'throttle' => 60,
      ),
      'customers' => 
      array (
        'provider' => 'customers',
        'table' => 'customer_password_reset_tokens',
        'expire' => 60,
        'throttle' => 60,
      ),
    ),
    'password_timeout' => 10800,
  ),
  'broadcasting' => 
  array (
    'default' => 'pusher',
    'connections' => 
    array (
      'reverb' => 
      array (
        'driver' => 'reverb',
        'key' => NULL,
        'secret' => NULL,
        'app_id' => NULL,
        'options' => 
        array (
          'host' => NULL,
          'port' => 443,
          'scheme' => 'https',
          'useTLS' => true,
        ),
        'client_options' => 
        array (
        ),
      ),
      'pusher' => 
      array (
        'driver' => 'pusher',
        'key' => '8f2076eb587f6afbc49f',
        'secret' => '2ffa6b477523860789ea',
        'app_id' => '2115183',
        'options' => 
        array (
          'cluster' => 'mt1',
          'useTLS' => true,
          'host' => 'api-mt1.pusher.com',
          'port' => 443,
          'scheme' => 'https',
          'encrypted' => true,
        ),
        'client_options' => 
        array (
        ),
      ),
      'ably' => 
      array (
        'driver' => 'ably',
        'key' => NULL,
      ),
      'log' => 
      array (
        'driver' => 'log',
      ),
      'null' => 
      array (
        'driver' => 'null',
      ),
      'redis' => 
      array (
        'driver' => 'redis',
        'connection' => 'default',
      ),
    ),
  ),
  'cache' => 
  array (
    'default' => 'redis',
    'stores' => 
    array (
      'array' => 
      array (
        'driver' => 'array',
        'serialize' => false,
      ),
      'session' => 
      array (
        'driver' => 'session',
        'key' => '_cache',
      ),
      'database' => 
      array (
        'driver' => 'database',
        'connection' => NULL,
        'table' => 'cache',
        'lock_connection' => NULL,
        'lock_table' => NULL,
      ),
      'file' => 
      array (
        'driver' => 'file',
        'path' => '/Users/husseinabuhajjaj/Sites/dropshipping/storage/framework/cache/data',
        'lock_path' => '/Users/husseinabuhajjaj/Sites/dropshipping/storage/framework/cache/data',
      ),
      'memcached' => 
      array (
        'driver' => 'memcached',
        'persistent_id' => NULL,
        'sasl' => 
        array (
          0 => NULL,
          1 => NULL,
        ),
        'options' => 
        array (
        ),
        'servers' => 
        array (
          0 => 
          array (
            'host' => '127.0.0.1',
            'port' => 11211,
            'weight' => 100,
          ),
        ),
      ),
      'redis' => 
      array (
        'driver' => 'redis',
        'connection' => 'cache',
        'lock_connection' => 'default',
      ),
      'dynamodb' => 
      array (
        'driver' => 'dynamodb',
        'key' => '',
        'secret' => '',
        'region' => 'us-east-1',
        'table' => 'cache',
        'endpoint' => NULL,
      ),
      'octane' => 
      array (
        'driver' => 'octane',
      ),
      'failover' => 
      array (
        'driver' => 'failover',
        'stores' => 
        array (
          0 => 'database',
          1 => 'array',
        ),
      ),
    ),
    'prefix' => 'simbazu-cache',
  ),
  'cors' => 
  array (
    'paths' => 
    array (
      0 => 'api/*',
      1 => 'sanctum/csrf-cookie',
    ),
    'allowed_methods' => 
    array (
      0 => '*',
    ),
    'allowed_origins' => 
    array (
      0 => 'http://localhost:3000',
      1 => 'http://localhost:8080',
    ),
    'allowed_origins_patterns' => 
    array (
    ),
    'allowed_headers' => 
    array (
      0 => '*',
    ),
    'exposed_headers' => 
    array (
    ),
    'max_age' => 0,
    'supports_credentials' => true,
  ),
  'currency' => 
  array (
    'base' => 'USD',
    'rates' => 
    array (
      'USD_XAF' => '600',
      'USD_XOF' => '600',
    ),
    'decimals' => 
    array (
      'USD' => 2,
      'XAF' => 0,
      'XOF' => 0,
    ),
    'aliases' => 
    array (
      'XFC' => 'XAF',
      'XFA' => 'XAF',
    ),
  ),
  'database' => 
  array (
    'default' => 'mysql',
    'connections' => 
    array (
      'sqlite' => 
      array (
        'driver' => 'sqlite',
        'url' => NULL,
        'database' => 'laravel_dev',
        'prefix' => '',
        'foreign_key_constraints' => true,
        'busy_timeout' => NULL,
        'journal_mode' => NULL,
        'synchronous' => NULL,
        'transaction_mode' => 'DEFERRED',
      ),
      'mysql' => 
      array (
        'driver' => 'mysql',
        'url' => NULL,
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'laravel_dev',
        'username' => 'dev',
        'password' => 'devpassword',
        'unix_socket' => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'prefix_indexes' => true,
        'strict' => true,
        'engine' => NULL,
        'options' => 
        array (
        ),
      ),
      'mariadb' => 
      array (
        'driver' => 'mariadb',
        'url' => NULL,
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'laravel_dev',
        'username' => 'dev',
        'password' => 'devpassword',
        'unix_socket' => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'prefix_indexes' => true,
        'strict' => true,
        'engine' => NULL,
        'options' => 
        array (
        ),
      ),
      'pgsql' => 
      array (
        'driver' => 'pgsql',
        'url' => NULL,
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'laravel_dev',
        'username' => 'dev',
        'password' => 'devpassword',
        'charset' => 'utf8',
        'prefix' => '',
        'prefix_indexes' => true,
        'search_path' => 'public',
        'sslmode' => 'prefer',
      ),
      'sqlsrv' => 
      array (
        'driver' => 'sqlsrv',
        'url' => NULL,
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'laravel_dev',
        'username' => 'dev',
        'password' => 'devpassword',
        'charset' => 'utf8',
        'prefix' => '',
        'prefix_indexes' => true,
      ),
    ),
    'migrations' => 
    array (
      'table' => 'migrations',
      'update_date_on_publish' => true,
    ),
    'redis' => 
    array (
      'client' => 'phpredis',
      'options' => 
      array (
        'cluster' => 'redis',
        'prefix' => 'simbazu-database-',
        'persistent' => false,
      ),
      'default' => 
      array (
        'url' => NULL,
        'host' => '127.0.0.1',
        'username' => NULL,
        'password' => NULL,
        'port' => '6379',
        'database' => '0',
        'max_retries' => 3,
        'backoff_algorithm' => 'decorrelated_jitter',
        'backoff_base' => 100,
        'backoff_cap' => 1000,
      ),
      'cache' => 
      array (
        'url' => NULL,
        'host' => '127.0.0.1',
        'username' => NULL,
        'password' => NULL,
        'port' => '6379',
        'database' => '1',
        'max_retries' => 3,
        'backoff_algorithm' => 'decorrelated_jitter',
        'backoff_base' => 100,
        'backoff_cap' => 1000,
      ),
      'horizon' => 
      array (
        'url' => NULL,
        'host' => '127.0.0.1',
        'username' => NULL,
        'password' => NULL,
        'port' => '6379',
        'database' => '0',
        'max_retries' => 3,
        'backoff_algorithm' => 'decorrelated_jitter',
        'backoff_base' => 100,
        'backoff_cap' => 1000,
        'options' => 
        array (
          'prefix' => 'simbazu_horizon:',
        ),
      ),
    ),
  ),
  'expo-notifications' => 
  array (
    'automatically_delete_token' => true,
    'drivers' => 
    array (
      'token' => 'YieldStudio\\LaravelExpoNotifier\\Storage\\ExpoTokenStorageMysql',
      'ticket' => 'YieldStudio\\LaravelExpoNotifier\\Storage\\ExpoTicketStorageMysql',
      'notification' => 'YieldStudio\\LaravelExpoNotifier\\Storage\\ExpoPendingNotificationStorageMysql',
    ),
    'database' => 
    array (
      'tokens_table_name' => 'expo_tokens',
      'tickets_table_name' => 'expo_tickets',
      'notifications_table_name' => 'expo_notifications',
    ),
    'service' => 
    array (
      'api_url' => 'https://exp.host/--/api/v2/push',
      'host' => 'exp.host',
      'access_token' => NULL,
      'limits' => 
      array (
        'push_notifications_per_request' => 99,
      ),
      'use_fcm_legacy_api' => false,
    ),
  ),
  'filament' => 
  array (
    'broadcasting' => 
    array (
    ),
    'default_filesystem_disk' => 'local',
    'assets_path' => NULL,
    'cache_path' => '/Users/husseinabuhajjaj/Sites/dropshipping/bootstrap/cache/filament',
    'livewire_loading_delay' => 'default',
    'file_generation' => 
    array (
      'flags' => 
      array (
      ),
    ),
    'system_route_prefix' => 'filament',
    'path' => 'admin',
    'domain' => NULL,
    'auth' => 
    array (
      'guard' => 'admin',
      'pages' => 
      array (
        'login' => 'Filament\\Http\\Livewire\\Auth\\Login',
      ),
    ),
    'middleware' => 
    array (
      'base' => 
      array (
        0 => 'Illuminate\\Cookie\\Middleware\\EncryptCookies',
        1 => 'Illuminate\\Cookie\\Middleware\\AddQueuedCookiesToResponse',
        2 => 'Illuminate\\Session\\Middleware\\StartSession',
        3 => 'Illuminate\\View\\Middleware\\ShareErrorsFromSession',
        4 => 'Illuminate\\Foundation\\Http\\Middleware\\VerifyCsrfToken',
        5 => 'Illuminate\\Routing\\Middleware\\SubstituteBindings',
        6 => 'Filament\\Http\\Middleware\\DispatchServingFilamentEvent',
      ),
      'auth' => 
      array (
        0 => 'Filament\\Http\\Middleware\\Authenticate',
      ),
    ),
    'panel' => 
    array (
      0 => 'App\\Providers\\Filament\\AdminPanelProvider',
    ),
  ),
  'filesystems' => 
  array (
    'default' => 'local',
    'disks' => 
    array (
      'local' => 
      array (
        'driver' => 'local',
        'root' => '/Users/husseinabuhajjaj/Sites/dropshipping/storage/app/private',
        'serve' => true,
        'throw' => false,
        'report' => false,
      ),
      'public' => 
      array (
        'driver' => 'local',
        'root' => '/Users/husseinabuhajjaj/Sites/dropshipping/storage/app/public',
        'url' => 'http://localhost/storage',
        'visibility' => 'public',
        'throw' => false,
        'report' => false,
      ),
      's3' => 
      array (
        'driver' => 's3',
        'key' => '',
        'secret' => '',
        'region' => 'us-east-1',
        'bucket' => '',
        'url' => NULL,
        'endpoint' => NULL,
        'use_path_style_endpoint' => false,
        'throw' => false,
        'report' => false,
      ),
    ),
    'links' => 
    array (
      '/Users/husseinabuhajjaj/Sites/dropshipping/public/storage' => '/Users/husseinabuhajjaj/Sites/dropshipping/storage/app/public',
    ),
  ),
  'horizon' => 
  array (
    'name' => 'Simbazu',
    'domain' => NULL,
    'path' => 'horizon',
    'use' => 'default',
    'prefix' => 'simbazu_horizon:',
    'middleware' => 
    array (
      0 => 'web',
    ),
    'waits' => 
    array (
      'redis:default' => 60,
      'redis:translations' => 120,
      'redis:cj-sync' => 120,
    ),
    'trim' => 
    array (
      'recent' => 60,
      'pending' => 60,
      'completed' => 60,
      'recent_failed' => 10080,
      'failed' => 10080,
      'monitored' => 10080,
    ),
    'silenced' => 
    array (
    ),
    'silenced_tags' => 
    array (
    ),
    'metrics' => 
    array (
      'trim_snapshots' => 
      array (
        'job' => 24,
        'queue' => 24,
      ),
    ),
    'fast_termination' => false,
    'memory_limit' => 64,
    'defaults' => 
    array (
      'supervisor-1' => 
      array (
        'connection' => 'redis',
        'queue' => 
        array (
          0 => 'default',
        ),
        'balance' => 'auto',
        'autoScalingStrategy' => 'time',
        'maxProcesses' => 1,
        'maxTime' => 0,
        'maxJobs' => 0,
        'memory' => 128,
        'tries' => 1,
        'timeout' => 60,
        'nice' => 0,
      ),
      'supervisor-translations' => 
      array (
        'connection' => 'redis',
        'queue' => 
        array (
          0 => 'translations',
        ),
        'balance' => 'auto',
        'autoScalingStrategy' => 'time',
        'maxProcesses' => 2,
        'maxTime' => 0,
        'maxJobs' => 0,
        'memory' => 256,
        'tries' => 3,
        'timeout' => 1200,
        'nice' => 0,
      ),
      'supervisor-cj-sync' => 
      array (
        'connection' => 'redis',
        'queue' => 
        array (
          0 => 'cj-sync',
        ),
        'balance' => 'auto',
        'autoScalingStrategy' => 'time',
        'maxProcesses' => 3,
        'maxTime' => 0,
        'maxJobs' => 0,
        'memory' => 192,
        'tries' => 3,
        'timeout' => 1200,
        'nice' => 0,
      ),
    ),
    'environments' => 
    array (
      'production' => 
      array (
        'supervisor-1' => 
        array (
          'maxProcesses' => 10,
          'balanceMaxShift' => 1,
          'balanceCooldown' => 3,
        ),
        'supervisor-translations' => 
        array (
          'maxProcesses' => 4,
          'balanceCooldown' => 3,
        ),
        'supervisor-cj-sync' => 
        array (
          'maxProcesses' => 4,
          'balanceCooldown' => 3,
        ),
      ),
      'local' => 
      array (
        'supervisor-1' => 
        array (
          'maxProcesses' => 3,
        ),
        'supervisor-translations' => 
        array (
          'maxProcesses' => 1,
          'timeout' => 1200,
        ),
        'supervisor-cj-sync' => 
        array (
          'maxProcesses' => 1,
          'timeout' => 1200,
        ),
      ),
    ),
    'watch' => 
    array (
      0 => 'app',
      1 => 'bootstrap',
      2 => 'config/**/*.php',
      3 => 'database/**/*.php',
      4 => 'public/**/*.php',
      5 => 'resources/**/*.php',
      6 => 'routes',
      7 => 'composer.lock',
      8 => 'composer.json',
      9 => '.env',
    ),
  ),
  'livewire' => 
  array (
    'class_namespace' => 'App\\Livewire',
    'view_path' => '/Users/husseinabuhajjaj/Sites/dropshipping/resources/views/livewire',
    'layout' => 'components.layouts.app',
    'lazy_placeholder' => NULL,
    'temporary_file_upload' => 
    array (
      'disk' => NULL,
      'rules' => NULL,
      'directory' => NULL,
      'middleware' => NULL,
      'preview_mimes' => 
      array (
        0 => 'png',
        1 => 'gif',
        2 => 'bmp',
        3 => 'svg',
        4 => 'wav',
        5 => 'mp4',
        6 => 'mov',
        7 => 'avi',
        8 => 'wmv',
        9 => 'mp3',
        10 => 'm4a',
        11 => 'jpg',
        12 => 'jpeg',
        13 => 'mpga',
        14 => 'webp',
        15 => 'wma',
      ),
      'max_upload_time' => 5,
      'cleanup' => true,
    ),
    'render_on_redirect' => false,
    'legacy_model_binding' => false,
    'inject_assets' => true,
    'navigate' => 
    array (
      'show_progress_bar' => true,
      'progress_bar_color' => '#2299dd',
    ),
    'inject_morph_markers' => true,
    'smart_wire_keys' => false,
    'pagination_theme' => 'tailwind',
    'release_token' => 'a',
  ),
  'logging' => 
  array (
    'default' => 'stack',
    'deprecations' => 
    array (
      'channel' => NULL,
      'trace' => false,
    ),
    'channels' => 
    array (
      'stack' => 
      array (
        'driver' => 'stack',
        'channels' => 
        array (
          0 => 'single',
        ),
        'ignore_exceptions' => false,
      ),
      'single' => 
      array (
        'driver' => 'single',
        'path' => '/Users/husseinabuhajjaj/Sites/dropshipping/storage/logs/laravel.log',
        'level' => 'debug',
        'replace_placeholders' => true,
      ),
      'daily' => 
      array (
        'driver' => 'daily',
        'path' => '/Users/husseinabuhajjaj/Sites/dropshipping/storage/logs/laravel.log',
        'level' => 'debug',
        'days' => 14,
        'replace_placeholders' => true,
      ),
      'slack' => 
      array (
        'driver' => 'slack',
        'url' => NULL,
        'username' => 'Laravel Log',
        'emoji' => ':boom:',
        'level' => 'debug',
        'replace_placeholders' => true,
      ),
      'papertrail' => 
      array (
        'driver' => 'monolog',
        'level' => 'debug',
        'handler' => 'Monolog\\Handler\\SyslogUdpHandler',
        'handler_with' => 
        array (
          'host' => NULL,
          'port' => NULL,
          'connectionString' => 'tls://:',
        ),
        'processors' => 
        array (
          0 => 'Monolog\\Processor\\PsrLogMessageProcessor',
        ),
      ),
      'stderr' => 
      array (
        'driver' => 'monolog',
        'level' => 'debug',
        'handler' => 'Monolog\\Handler\\StreamHandler',
        'handler_with' => 
        array (
          'stream' => 'php://stderr',
        ),
        'formatter' => NULL,
        'processors' => 
        array (
          0 => 'Monolog\\Processor\\PsrLogMessageProcessor',
        ),
      ),
      'syslog' => 
      array (
        'driver' => 'syslog',
        'level' => 'debug',
        'facility' => 8,
        'replace_placeholders' => true,
      ),
      'errorlog' => 
      array (
        'driver' => 'errorlog',
        'level' => 'debug',
        'replace_placeholders' => true,
      ),
      'null' => 
      array (
        'driver' => 'monolog',
        'handler' => 'Monolog\\Handler\\NullHandler',
      ),
      'emergency' => 
      array (
        'path' => '/Users/husseinabuhajjaj/Sites/dropshipping/storage/logs/laravel.log',
      ),
      'deprecations' => 
      array (
        'driver' => 'monolog',
        'handler' => 'Monolog\\Handler\\NullHandler',
      ),
    ),
  ),
  'mail' => 
  array (
    'default' => 'smtp',
    'mailers' => 
    array (
      'smtp' => 
      array (
        'transport' => 'smtp',
        'scheme' => 'smtp',
        'url' => NULL,
        'host' => 'smtppro.zoho.eu',
        'port' => '465',
        'username' => 'info@simbazu.net',
        'password' => 'Simbazu@2026',
        'timeout' => NULL,
        'local_domain' => 'localhost',
      ),
      'ses' => 
      array (
        'transport' => 'ses',
      ),
      'postmark' => 
      array (
        'transport' => 'postmark',
      ),
      'resend' => 
      array (
        'transport' => 'resend',
      ),
      'sendmail' => 
      array (
        'transport' => 'sendmail',
        'path' => '/usr/sbin/sendmail -bs -i',
      ),
      'log' => 
      array (
        'transport' => 'log',
        'channel' => NULL,
      ),
      'array' => 
      array (
        'transport' => 'array',
      ),
      'failover' => 
      array (
        'transport' => 'failover',
        'mailers' => 
        array (
          0 => 'smtp',
          1 => 'log',
        ),
        'retry_after' => 60,
      ),
      'roundrobin' => 
      array (
        'transport' => 'roundrobin',
        'mailers' => 
        array (
          0 => 'ses',
          1 => 'postmark',
        ),
        'retry_after' => 60,
      ),
    ),
    'from' => 
    array (
      'address' => 'info@simbazu.net',
      'name' => 'Simbazu',
    ),
    'markdown' => 
    array (
      'theme' => 'default',
      'paths' => 
      array (
        0 => '/Users/husseinabuhajjaj/Sites/dropshipping/resources/views/vendor/mail',
      ),
    ),
  ),
  'pricing' => 
  array (
    'min_margin_percent' => 20,
    'shipping_buffer_percent' => 10,
    'max_discount_percent' => 30,
    'category_margin_tiers' => 
    array (
    ),
    'bulk_margin_queue' => 'pricing',
    'compare_at_queue' => 'pricing',
  ),
  'promotions' => 
  array (
    'display' => 
    array (
      'enabled' => true,
    ),
    'display_limits' => 
    array (
      'home' => 5,
      'category' => 3,
      'product' => 2,
      'cart' => 3,
      'checkout' => 3,
    ),
    'caps' => 
    array (
      'first_order_max_discount' => 10.0,
      'high_value_max_discount' => 15.0,
    ),
  ),
  'queue' => 
  array (
    'default' => 'redis',
    'connections' => 
    array (
      'sync' => 
      array (
        'driver' => 'sync',
      ),
      'database' => 
      array (
        'driver' => 'database',
        'connection' => NULL,
        'table' => 'jobs',
        'queue' => 'default',
        'retry_after' => 90,
        'timeout' => 120,
        'after_commit' => false,
      ),
      'beanstalkd' => 
      array (
        'driver' => 'beanstalkd',
        'host' => 'localhost',
        'queue' => 'default',
        'retry_after' => 90,
        'block_for' => 0,
        'after_commit' => false,
      ),
      'sqs' => 
      array (
        'driver' => 'sqs',
        'key' => '',
        'secret' => '',
        'prefix' => 'https://sqs.us-east-1.amazonaws.com/your-account-id',
        'queue' => 'default',
        'suffix' => NULL,
        'region' => 'us-east-1',
        'after_commit' => false,
      ),
      'redis' => 
      array (
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'default',
        'retry_after' => 1200,
        'block_for' => NULL,
        'after_commit' => false,
      ),
      'deferred' => 
      array (
        'driver' => 'deferred',
      ),
      'failover' => 
      array (
        'driver' => 'failover',
        'connections' => 
        array (
          0 => 'database',
          1 => 'deferred',
        ),
      ),
      'background' => 
      array (
        'driver' => 'background',
      ),
    ),
    'batching' => 
    array (
      'database' => 'mysql',
      'table' => 'job_batches',
    ),
    'failed' => 
    array (
      'driver' => 'database-uuids',
      'database' => 'mysql',
      'table' => 'failed_jobs',
    ),
  ),
  'sanctum' => 
  array (
    'stateful' => 
    array (
      0 => 'localhost',
      1 => 'localhost:3000',
      2 => '127.0.0.1',
      3 => '127.0.0.1:8000',
      4 => '::1',
      5 => 'localhost',
    ),
    'guard' => 
    array (
      0 => 'web',
    ),
    'expiration' => NULL,
    'token_prefix' => '',
    'middleware' => 
    array (
      'authenticate_session' => 'Laravel\\Sanctum\\Http\\Middleware\\AuthenticateSession',
      'encrypt_cookies' => 'App\\Http\\Middleware\\EncryptCookies',
      'validate_csrf_token' => 'App\\Http\\Middleware\\ValidateCsrfToken',
    ),
  ),
  'scramble' => 
  array (
    'api_path' => 'api',
    'api_domain' => NULL,
    'export_path' => 'api.json',
    'info' => 
    array (
      'version' => '0.0.1',
      'description' => '',
    ),
    'ui' => 
    array (
      'title' => NULL,
      'theme' => 'light',
      'hide_try_it' => false,
      'hide_schemas' => false,
      'logo' => '',
      'try_it_credentials_policy' => 'include',
      'layout' => 'responsive',
    ),
    'servers' => NULL,
    'enum_cases_description_strategy' => 'description',
    'enum_cases_names_strategy' => false,
    'flatten_deep_query_parameters' => true,
    'middleware' => 
    array (
      0 => 'web',
      1 => 'Dedoc\\Scramble\\Http\\Middleware\\RestrictedDocsAccess',
    ),
    'extensions' => 
    array (
    ),
  ),
  'services' => 
  array (
    'postmark' => 
    array (
      'key' => NULL,
    ),
    'resend' => 
    array (
      'key' => NULL,
    ),
    'ses' => 
    array (
      'key' => '',
      'secret' => '',
      'region' => 'us-east-1',
    ),
    'slack' => 
    array (
      'notifications' => 
      array (
        'bot_user_oauth_token' => NULL,
        'channel' => NULL,
      ),
    ),
    'payments' => 
    array (
      'webhook_secret' => NULL,
    ),
    'paystack' => 
    array (
      'secret_key' => NULL,
      'public_key' => NULL,
      'webhook_secret' => NULL,
      'base_url' => 'https://api.paystack.co',
    ),
    'korapay' => 
    array (
      'secret_key' => NULL,
      'public_key' => NULL,
      'webhook_secret' => NULL,
      'base_url' => NULL,
      'initialize_endpoint' => NULL,
      'verify_endpoint' => NULL,
    ),
    'stripe' => 
    array (
      'key' => NULL,
      'secret' => NULL,
      'webhook_secret' => NULL,
    ),
    'shippo' => 
    array (
      'api_token' => NULL,
      'base_url' => 'https://api.goshippo.com',
    ),
    'returns' => 
    array (
      'address_line1' => '123 Return Center',
      'address_line2' => NULL,
      'city' => 'City',
      'state' => 'State',
      'postal_code' => '00000',
      'country' => 'US',
      'phone' => '+1234567890',
    ),
    'tracking' => 
    array (
      'webhook_secret' => NULL,
    ),
    'cj' => 
    array (
      'app_id' => 'CJ5005823',
      'api_secret' => NULL,
      'api_key' => 'CJ5005823@api@4e918ab020cb4c68aed7ab59d2b73b9f',
      'base_url' => 'https://developers.cjdropshipping.com/api2.0',
      'warehouse_list_endpoint' => '/v1/product/globalWarehouse/list',
      'timeout' => '30',
      'webhook_secret' => NULL,
      'platform_token' => NULL,
      'alerts_email' => NULL,
      'ship_to_default' => 'CN',
    ),
    'queue_reporting' => 
    array (
      'enabled' => true,
      'emails' => 
      array (
        0 => 'info@simbazu.net',
      ),
      'interval_minutes' => 10,
      'send_empty' => false,
    ),
    'google' => 
    array (
      'client_id' => NULL,
      'client_secret' => NULL,
      'redirect' => NULL,
    ),
    'facebook' => 
    array (
      'client_id' => NULL,
      'client_secret' => NULL,
      'redirect' => NULL,
    ),
    'apple' => 
    array (
      'client_id' => NULL,
      'client_secret' => NULL,
      'redirect' => NULL,
    ),
    'whatsapp' => 
    array (
      'provider' => 'meta',
      'meta' => 
      array (
        'token' => NULL,
        'phone_number_id' => NULL,
        'api_version' => 'v19.0',
        'base_url' => 'https://graph.facebook.com',
      ),
      'twilio' => 
      array (
        'sid' => NULL,
        'token' => NULL,
        'from' => NULL,
      ),
      'vonage' => 
      array (
        'jwt' => NULL,
        'from' => NULL,
        'endpoint' => 'https://api.nexmo.com/v1/messages',
      ),
    ),
    'deepseek' => 
    array (
      'key' => 'sk-65a8a69a95924760be423a26677bc076',
      'base_url' => 'https://api.deepseek.com',
      'model' => 'deepseek-chat',
      'timeout' => 20,
    ),
    'libre_translate' => 
    array (
      'base_url' => 'https://libretranslate.de',
      'key' => NULL,
      'timeout' => 10,
    ),
    'translation_provider' => 'deepseek',
    'translation_locales' => 
    array (
      0 => 'en',
      1 => 'fr',
    ),
    'translation_source_locale' => 'en',
  ),
  'session' => 
  array (
    'driver' => 'database',
    'lifetime' => 120,
    'expire_on_close' => false,
    'encrypt' => false,
    'files' => '/Users/husseinabuhajjaj/Sites/dropshipping/storage/framework/sessions',
    'connection' => NULL,
    'table' => 'sessions',
    'store' => NULL,
    'lottery' => 
    array (
      0 => 2,
      1 => 100,
    ),
    'cookie' => 'simbazu-session',
    'path' => '/',
    'domain' => NULL,
    'secure' => NULL,
    'http_only' => true,
    'same_site' => 'lax',
    'partitioned' => false,
  ),
  'support_chat' => 
  array (
    'queue' => 'support',
    'attachments' => 
    array (
      'disk' => 'public',
      'max_kb' => 10240,
      'allowed_mimes' => 
      array (
        0 => 'image/jpeg',
        1 => 'image/png',
        2 => 'image/webp',
        3 => 'application/pdf',
        4 => 'text/plain',
      ),
      'image_max_width' => 1600,
      'image_quality' => 82,
      'image_convert_to_webp' => true,
    ),
    'realtime' => 
    array (
      'enabled' => true,
    ),
    'escalation' => 
    array (
      'enabled' => true,
      'sla_minutes' => 15,
      'repeat_minutes' => 60,
      'max_per_run' => 200,
    ),
    'digest' => 
    array (
      'enabled' => true,
      'send_empty' => false,
      'max_rows' => 10,
    ),
  ),
  'blade-heroicons' => 
  array (
    'prefix' => 'heroicon',
    'fallback' => '',
    'class' => '',
    'attributes' => 
    array (
    ),
  ),
  'blade-icons' => 
  array (
    'sets' => 
    array (
    ),
    'class' => '',
    'attributes' => 
    array (
    ),
    'fallback' => '',
    'components' => 
    array (
      'disabled' => false,
      'default' => 'icon',
    ),
  ),
  'inertia' => 
  array (
    'ssr' => 
    array (
      'enabled' => true,
      'url' => 'http://127.0.0.1:13714',
      'ensure_bundle_exists' => true,
    ),
    'ensure_pages_exist' => false,
    'page_paths' => 
    array (
      0 => '/Users/husseinabuhajjaj/Sites/dropshipping/resources/js/Pages',
    ),
    'page_extensions' => 
    array (
      0 => 'js',
      1 => 'jsx',
      2 => 'svelte',
      3 => 'ts',
      4 => 'tsx',
      5 => 'vue',
    ),
    'use_script_element_for_initial_page' => false,
    'testing' => 
    array (
      'ensure_pages_exist' => true,
      'page_paths' => 
      array (
        0 => '/Users/husseinabuhajjaj/Sites/dropshipping/resources/js/Pages',
      ),
      'page_extensions' => 
      array (
        0 => 'js',
        1 => 'jsx',
        2 => 'svelte',
        3 => 'ts',
        4 => 'tsx',
        5 => 'vue',
      ),
    ),
    'history' => 
    array (
      'encrypt' => false,
    ),
  ),
  'tinker' => 
  array (
    'commands' => 
    array (
    ),
    'alias' => 
    array (
    ),
    'dont_alias' => 
    array (
      0 => 'App\\Nova',
    ),
  ),
);
