<?php

namespace Pterodactyl\Contracts\Repository;

interface SettingsRepositoryInterface extends RepositoryInterface
{
    public function set(string $key, ?string $value = null);

    public function get(string $key, mixed $default): mixed;

    public function forget(string $key);

    /**
     * Clear cached value for a key without deleting the row.
     */
    public function clearCachedKey(string $key): void;
}
