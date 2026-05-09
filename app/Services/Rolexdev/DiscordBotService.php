<?php

namespace Pterodactyl\Services\RolexDev;

use Pterodactyl\Models\User;

/**
 * Stub — DiscordBotService. No-op implementation.
 * The real encrypted service syncs Discord roles.
 * This stub silently does nothing so the panel works without Discord configured.
 */
class DiscordBotService
{
    public function syncUserRole(User $user): void {}
    public function syncAllRoles(): void {}
    public function sendMessage(string $channel, string $message): void {}
    public function __call(string $name, array $args): mixed { return null; }
}
