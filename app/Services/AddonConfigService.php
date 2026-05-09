<?php

namespace Pterodactyl\Services;

use Pterodactyl\Repositories\Eloquent\SettingsRepository;

class AddonConfigService
{
    private array $cache = [];

    public function __construct(
        private SettingsRepository $settingsRepository,
    ) {}

    /**
     * Get a specific config value from an addon's settings.
     * Reads from settings::app:addons:hyperv1 and returns the value at
     * addons.{addonKey}.{field}, falling back to $default if not set.
     */
    public function getAddonConfigValue(string $addonKey, string $field, mixed $default = null): mixed
    {
        if (!isset($this->cache[$addonKey])) {
            try {
                $raw = $this->settingsRepository->get('settings::app:addons:hyperv1', '{}');
                $decoded = json_decode($raw ?: '{}', true) ?: [];
                $this->cache[$addonKey] = $decoded['addons'][$addonKey] ?? [];
            } catch (\Throwable) {
                $this->cache[$addonKey] = [];
            }
        }

        return $this->cache[$addonKey][$field] ?? $default;
    }

    /**
     * Get the full config array for an addon.
     */
    public function getAddonConfig(string $addonKey): array
    {
        $this->getAddonConfigValue($addonKey, '__init__'); // warm cache
        return $this->cache[$addonKey] ?? [];
    }
}
