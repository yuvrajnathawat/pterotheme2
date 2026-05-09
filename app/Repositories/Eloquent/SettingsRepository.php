<?php

namespace Pterodactyl\Repositories\Eloquent;

use Pterodactyl\Models\Setting;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

class SettingsRepository extends EloquentRepository implements SettingsRepositoryInterface
{
    /**
     * Per-worker value cache. With Octane, static properties persist across
     * requests on the same worker — we only cache found values here, never
     * DB misses, so a setting created after worker boot is visible immediately.
     */
    private static array $cache = [];

    public function model(): string
    {
        return Setting::class;
    }

    public function set(string $key, ?string $value = null)
    {
        $this->clearCache($key);
        $this->withoutFreshModel()->updateOrCreate(['key' => $key], ['value' => $value ?? '']);
        self::$cache[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        $instance = $this->getBuilder()->where('key', $key)->first();
        if (is_null($instance)) {
            // Do NOT cache misses — with Octane this worker lives across many
            // requests and a setting may be written at any time after boot.
            return value($default);
        }

        return self::$cache[$key] = $instance->value;
    }

    public function forget(string $key)
    {
        $this->clearCache($key);
        $this->deleteWhere(['key' => $key]);
    }

    /**
     * Clear cached value for a key without deleting the row.
     */
    public function clearCachedKey(string $key): void
    {
        $this->clearCache($key);
    }

    private function clearCache(string $key)
    {
        unset(self::$cache[$key]);
    }
}
