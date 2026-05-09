<?php
namespace Pterodactyl\Providers;

use Throwable;
use Illuminate\Http\Request;
use Pterodactyl\Models\Database;
use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Pterodactyl\Http\Middleware\TrimStrings;
use Pterodactyl\Http\Middleware\AdminAuthenticate;
use Pterodactyl\Http\Middleware\Admin\LogAdminAction;
use Pterodactyl\Http\Middleware\RequireTwoFactorAuthentication;
use Pterodactyl\Services\HyperV1AddonDefaultsService;
use Pterodactyl\Repositories\Eloquent\SettingsRepository;
use Pterodactyl\Http\Middleware\EnsureStatefulRequests;
use Pterodactyl\Http\Middleware\Api\IsValidJson;
use Pterodactyl\Http\Controllers\Api\Client\Theme\PwaController;
use Pterodactyl\Http\Controllers\Base\PublicNodeStatusController;
use Pterodactyl\Http\Controllers\Api\Public\WingsHealthController;
use Pterodactyl\Http\Controllers\Api\Public\WingsAddonSettingsController;
use Pterodactyl\Http\Controllers\Api\Public\NodeBackupApiController;
use Pterodactyl\Enum\ResourceLimit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
class RouteServiceProvider extends ServiceProvider
{
    protected const FILE_PATH_REGEX = '/^\/api\/client\/servers\/([a-z0-9-]{36})\/files(\/?$|\/(.)*$)/i';
    public function boot(): void
    {
        $this->configureRateLimiting();
        TrimStrings::skipWhen(function (Request $request) {
            return preg_match(self::FILE_PATH_REGEX, $request->getPathInfo()) === 1;
        });
        Route::model('database', Database::class);
        $this->routes(function () {
            Route::middleware('web')->group(function () {
                Route::middleware(['auth.session', RequireTwoFactorAuthentication::class])
                    ->group(base_path('routes/base.php'));
                Route::middleware(['auth.session', RequireTwoFactorAuthentication::class, AdminAuthenticate::class, LogAdminAction::class])
                    ->prefix('/admin')
                    ->group(base_path('routes/admin.php'));
                Route::middleware('guest')->prefix('/auth')->group(base_path('routes/auth.php'));
                Route::get('/api/client/addons', function () {
                    $defaultsService = app(HyperV1AddonDefaultsService::class);
                    $cacheKey = $defaultsService->getAddonsCacheKey() . ':public';
                    $cachedJson = Cache::remember($cacheKey, 86400, function () use ($defaultsService) {
                        $settingsRepository = app(SettingsRepository::class);
                        $raw = $settingsRepository->get('settings::app:addons:hyperv1', '{}');
                        $decoded = [];
                        try {
                            $decoded = json_decode($raw ?: '{}', true, 512, JSON_THROW_ON_ERROR);
                        } catch (Throwable) {
                            $decoded = [];
                        }
                        $defaultAddons = $defaultsService->getDefaultAddons();
                        // Start with ALL defaults (all enabled), then overlay saved settings
                        $savedAddons = is_array($decoded) && isset($decoded['addons']) ? $decoded['addons'] : [];
                        $mergedAddons = $defaultAddons;
                        foreach ($savedAddons as $key => $cfg) {
                            if (isset($mergedAddons[$key])) {
                                $mergedAddons[$key] = array_merge($mergedAddons[$key], $cfg, ['enabled' => true]);
                            }
                        }
                        $data = [
                            'addons'     => $mergedAddons,
                            'updated_at' => $decoded['updated_at'] ?? null,
                            'app_url'    => config('app.url'),
                        ];
                        foreach ($data['addons'] as $addonKey => &$addonConfig) {
                            if (!isset($addonConfig['enabled']) || $addonConfig['enabled'] === false) {
                                $configKeysToRemove = [];
                                switch ($addonKey) {
                                    case 'Notifications':
                                        $configKeysToRemove = ['notifications', 'broadcast'];
                                        break;
                                }
                                foreach ($configKeysToRemove as $configKey) {
                                    if (isset($addonConfig[$configKey])) {
                                        unset($addonConfig[$configKey]);
                                    }
                                }
                            }
                            // Use the public-only field whitelist — never expose admin fields
                            $allowedFields = $defaultsService->getPublicAllowedFields()[$addonKey] ?? ['enabled', 'name', 'description', 'category'];
                            $filteredConfig = [];
                            foreach ($allowedFields as $field) {
                                if (array_key_exists($field, $addonConfig)) {
                                    $filteredConfig[$field] = $addonConfig[$field];
                                }
                            }
                            $addonConfig = $filteredConfig;
                        }
                        // Strip ALL known sensitive fields as a defense-in-depth measure
                        $sensitiveFields = [
                            'secret_key', 'cloudflare_api_token', 'cloudflare_email',
                            'stripe_secret_key', 'paypal_secret', 'cashfree_secret_key',
                            'razorpay_key_secret', 'litepay_secret', 'smepay_secret',
                            'mercadopago_access_token', 'discord_client_secret',
                            'google_client_secret', 'github_client_secret',
                            'whmcs_client_secret', 'paymenter_client_secret',
                            'wemx_secret', 'bot_token', 'client_secret',
                            'access_token', 'api_key', 'webhook_url',
                            'discord_webhook_url', 'proxy_db_password',
                            'proxy_db_user', 'proxy_db_host', 'proxy_db_port',
                            'proxy_db_name', 'proxy_base_url',
                        ];
                        if (is_array($data['addons'])) {
                            foreach ($data['addons'] as $addonKey => &$addonConfig) {
                                if (is_array($addonConfig)) {
                                    foreach ($sensitiveFields as $field) {
                                        unset($addonConfig[$field]);
                                    }
                                    // Strip null, empty string, and false values to reduce payload
                                    // Exception: SiteAlerts 'alerts' array is always kept
                                    $addonConfig = array_filter($addonConfig, function ($value, $key) use ($addonKey) {
                                        if ($addonKey === 'SiteAlerts' && $key === 'alerts') return true;
                                        return $value !== null && $value !== '' && $value !== false;
                                    }, ARRAY_FILTER_USE_BOTH);
                                }
                            }
                            unset($addonConfig);
                            // Remove addons that have no meaningful fields after stripping
                            $data['addons'] = array_filter($data['addons'], function ($addonConfig) {
                                return !empty($addonConfig);
                            });
                        }
                        return json_encode($data);
                    });
                    return response($cachedJson)->header('Content-Type', 'application/json');
                });
            });
            Route::get('/api/public/pwa/manifest.json', [PwaController::class, 'manifest'])
                ->name('api.public.pwa.manifest');
            Route::get('/api/public/pwa/sw-config.js', [PwaController::class, 'serviceWorkerConfig'])
                ->name('api.public.pwa.sw-config');
            Route::get('/api/public/node-status', [PublicNodeStatusController::class, 'index'])
                ->name('api.public.node-status');
            Route::post('/api/public/hyper/wings/health', [WingsHealthController::class, 'verify'])
                ->middleware([IsValidJson::class, 'throttle:30,1'])
                ->name('api.public.hyper.wings.health');
            Route::post('/api/public/hyper/wings/addon-settings', [WingsAddonSettingsController::class, 'fetch'])
                ->middleware([IsValidJson::class, 'throttle:24,2'])
                ->name('api.public.hyper.wings.addon-settings');

            // ── Node Backup API (wings-agent calls these) ─────────────────
            Route::prefix('/api/public/wings-addon')->middleware([IsValidJson::class, 'throttle:120,1'])->group(function () {
                Route::post('/node-backup/run-start', [NodeBackupApiController::class, 'runStart'])
                    ->name('api.public.wings-addon.node-backup.run-start');
                Route::post('/node-backup/progress', [NodeBackupApiController::class, 'progress'])
                    ->name('api.public.wings-addon.node-backup.progress');
                Route::post('/node-backup/complete', [NodeBackupApiController::class, 'complete'])
                    ->name('api.public.wings-addon.node-backup.complete');
                Route::get('/node-backup/list', [NodeBackupApiController::class, 'listBackups'])
                    ->name('api.public.wings-addon.node-backup.list');
                Route::post('/node-backup/delete', [NodeBackupApiController::class, 'deleteBackup'])
                    ->name('api.public.wings-addon.node-backup.delete');
                Route::post('/node-backup/delete-run', [NodeBackupApiController::class, 'deleteRun'])
                    ->name('api.public.wings-addon.node-backup.delete-run');
                Route::post('/node-backup/filter-known-run-ids', [NodeBackupApiController::class, 'filterKnownRunIds'])
                    ->name('api.public.wings-addon.node-backup.filter-known-run-ids');
                Route::post('/node-backup/prune-dedup-records', [NodeBackupApiController::class, 'pruneDedupRecords'])
                    ->name('api.public.wings-addon.node-backup.prune-dedup-records');

                Route::post('/transfer/{transferId}/progress', [NodeBackupApiController::class, 'transferProgress'])
                    ->name('api.public.wings-addon.transfer.progress');
                Route::post('/transfer/{transferId}/complete', [NodeBackupApiController::class, 'transferComplete'])
                    ->name('api.public.wings-addon.transfer.complete');
                Route::post('/transfer/{transferId}/failed', [NodeBackupApiController::class, 'transferFailed'])
                    ->name('api.public.wings-addon.transfer.failed');
                Route::get('/transfer/{transferId}/status', [NodeBackupApiController::class, 'transferStatus'])
                    ->name('api.public.wings-addon.transfer.status');

                // Server Import callbacks (wings-agent → panel)
                Route::post('/server-import/complete', [NodeBackupApiController::class, 'serverImportComplete'])
                    ->name('api.public.wings-addon.server-import.complete');
            });
                Route::middleware(['api', RequireTwoFactorAuthentication::class])->group(function () {
                    Route::middleware(['application-api', 'throttle:api.application'])
                        ->prefix('/api/application')
                        ->scopeBindings()
                        ->group(base_path('routes/api-application.php'));
                    Route::middleware(['client-api', 'throttle:api.client'])
                        ->prefix('/api/client')
                        ->scopeBindings()
                        ->group(base_path('routes/api-client.php'));
                });            Route::middleware('daemon')
                ->prefix('/api/remote')
                ->scopeBindings()
                ->group(base_path('routes/api-remote.php'));
        });

        ResourceLimit::boot();
    }
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('authentication', function (Request $request) {
            if ($request->route()->named('auth.post.forgot-password')) {
                return Limit::perMinute(2)->by($request->ip());
            }
            return Limit::perMinute(10);
        });
        RateLimiter::for('api.client', function (Request $request) {
            $key = optional($request->user())->uuid ?: $request->ip();
            return Limit::perMinutes(
                config('http.rate_limit.client_period'),
                config('http.rate_limit.client')
            )->by($key);
        });
        RateLimiter::for('api.application', function (Request $request) {
            $key = optional($request->user())->uuid ?: $request->ip();
            return Limit::perMinutes(
                config('http.rate_limit.application_period'),
                config('http.rate_limit.application')
            )->by($key);
        });
    }
}
