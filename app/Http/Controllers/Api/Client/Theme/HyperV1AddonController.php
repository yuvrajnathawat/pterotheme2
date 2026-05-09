<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Theme;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\HyperV1AddonDefaultsService;
use Pterodactyl\Repositories\Eloquent\SettingsRepository;

class HyperV1AddonController extends Controller
{
    public function __construct(
        private HyperV1AddonDefaultsService $defaultsService,
        private SettingsRepository $settingsRepository,
    ) {}

    /**
     * GET /api/client/addons
     * Returns the current addon configuration (admin-saved settings merged with defaults).
     */
    public function show(Request $request): JsonResponse
    {
        $raw     = $this->settingsRepository->get('settings::app:addons:hyperv1', '{}');
        $decoded = json_decode($raw ?: '{}', true) ?: [];

        $defaults = $this->defaultsService->getDefaultAddons();

        // Start with defaults, then overlay saved settings per addon
        $addons = $defaults;
        foreach ($decoded['addons'] ?? [] as $key => $cfg) {
            if (isset($addons[$key])) {
                // Merge saved config into defaults — respect the saved enabled value
                $addons[$key] = array_merge($addons[$key], $cfg);
            } else {
                // Unknown addon from DB — keep as-is
                $addons[$key] = $cfg;
            }
        }

        return new JsonResponse([
            'addons'     => $addons,
            'updated_at' => $decoded['updated_at'] ?? null,
            'app_url'    => config('app.url'),
        ]);
    }

    /**
     * GET /api/client/addons/defaults
     * Returns the default addon structure.
     */
    public function defaults(Request $request): JsonResponse
    {
        return new JsonResponse([
            'addons' => $this->defaultsService->getDefaultAddons(),
        ]);
    }

    /**
     * PUT /api/client/addons
     * Saves addon configuration (admin only).
     */
    public function update(Request $request): JsonResponse
    {
        if (!$request->user()?->root_admin) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        $data = $request->input();
        $data['updated_at'] = now()->toISOString();

        $this->settingsRepository->set('settings::app:addons:hyperv1', json_encode($data));

        // Bust the public addons cache
        $cacheKey = $this->defaultsService->getAddonsCacheKey() . ':public';
        Cache::forget($cacheKey);

        return new JsonResponse(['success' => true]);
    }

    /**
     * GET /api/client/addons/check-server-availability
     * Simple availability check — always returns available.
     */
    public function checkServerAvailability(Request $request): JsonResponse
    {
        return new JsonResponse(['available' => true]);
    }
}
