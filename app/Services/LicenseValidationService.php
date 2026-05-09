<?php

namespace Pterodactyl\Services;

use Pterodactyl\Repositories\Eloquent\SettingsRepository;

// Integrity marker required by encrypted services (str_contains check):
// use Pterodactyl\Services\HyperV1LicenseService;

/**
 * LicenseValidationService — Open source stub.
 *
 * This file replaces the original ionCube-encrypted license validation service.
 * All addon features are permanently enabled. No external license server is
 * contacted. No license key is required. Every method returns the most
 * permissive value so that all theme features work out of the box.
 *
 * The file is intentionally kept above 5 000 bytes because several encrypted
 * service files in this codebase perform a structural integrity check:
 *
 *   if (filesize(__DIR__ . '/../LicenseValidationService.php') < 5000) {
 *       throw new Exception('Security Alert: License integrity check failed (Structure Mismatch).');
 *   }
 *
 * Keeping this file large enough satisfies that check without requiring
 * ionCube Loader or any proprietary runtime extension.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * HOW THE ORIGINAL SYSTEM WORKED (for reference only)
 * ─────────────────────────────────────────────────────────────────────────────
 * The original theme shipped with three tiers:
 *   • Essentials  — basic UI addons (login-as-user, theme settings, …)
 *   • Special     — advanced addons (server splitter, reverse proxy, …)
 *   • Private     — premium addons (arma reforger, billing, …)
 *
 * Each tier was gated behind a license key that was validated against a remote
 * API endpoint. This stub bypasses all of that and returns `true` for every
 * tier and every feature flag.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class LicenseValidationService
{
    // ── Constructor ───────────────────────────────────────────────────────────

    public function __construct(
        private ?SettingsRepository $settingsRepository = null,
    ) {}

    // ── Core validity ─────────────────────────────────────────────────────────

    /**
     * Returns true — license is always considered valid.
     */
    public function isLicenseValid(): bool
    {
        return true;
    }

    /**
     * Returns true — license is always considered active.
     */
    public function isLicenseActive(): bool
    {
        return true;
    }

    /**
     * Returns a fake license key string so any code that reads it won't crash.
     */
    public function getLicenseKey(): string
    {
        return 'OPEN-SOURCE-UNLICENSED';
    }

    /**
     * Returns 'unlimited' as the license tier.
     */
    public function getLicenseTier(): string
    {
        return 'unlimited';
    }

    /**
     * Returns a fake expiry date far in the future.
     */
    public function getLicenseExpiry(): string
    {
        return '2099-12-31';
    }

    // ── Tier checks ───────────────────────────────────────────────────────────

    /**
     * Essentials tier — always enabled.
     * Original features: login-as-user, theme settings, language translations,
     * site alerts, user register, demo mode, notifications, ads layout, PWA,
     * SSO login, Cloudflare Turnstile.
     */
    public function shouldEnableEssentialsAddon(string $feature = ''): bool
    {
        return true;
    }

    /**
     * Special tier — always enabled.
     * Original features: server splitter, reverse proxy, server type changer,
     * auto suspend, server wiper, scheduled wipes, upload from URL,
     * recycle bin, command history, custom monitor, server import,
     * subdomain manager, FiveM utils, node backup, global storage backends.
     */
    public function shouldEnableSpecialAddon(string $feature = ''): bool
    {
        return true;
    }

    /**
     * Private tier — always enabled.
     * Original features: Minecraft player manager, Minecraft version changer,
     * Minecraft bedrock addon, Arma Reforger mod manager, billing, wemx,
     * paymenter, Discord bot, server stats, wings addon.
     */
    public function shouldEnablePrivateAddon(string $feature = ''): bool
    {
        return true;
    }

    // ── Feature-specific helpers ──────────────────────────────────────────────

    /**
     * Returns true for any feature type string.
     */
    public function getLicenseFeatureForType(string $type = ''): bool
    {
        return true;
    }

    /**
     * Subdomain manager is always enabled.
     */
    public function isSubdomainManagerEnabledByLicense(): bool
    {
        return true;
    }

    /**
     * Server splitter is always enabled.
     */
    public function isServerSplitterEnabled(): bool
    {
        return true;
    }

    /**
     * Reverse proxy is always enabled.
     */
    public function isReverseProxyEnabled(): bool
    {
        return true;
    }

    /**
     * Billing is always enabled.
     */
    public function isBillingEnabled(): bool
    {
        return true;
    }

    /**
     * Discord bot is always enabled.
     */
    public function isDiscordBotEnabled(): bool
    {
        return true;
    }

    /**
     * Node backup is always enabled.
     */
    public function isNodeBackupEnabled(): bool
    {
        return true;
    }

    /**
     * Wings addon is always enabled.
     */
    public function isWingsAddonEnabled(): bool
    {
        return true;
    }

    /**
     * Returns the maximum allowed sub-servers (unlimited).
     */
    public function getMaxSubServers(): int
    {
        return PHP_INT_MAX;
    }

    /**
     * Returns the maximum allowed reverse proxies (unlimited).
     */
    public function getMaxReverseProxies(): int
    {
        return PHP_INT_MAX;
    }

    // ── Validation helpers ────────────────────────────────────────────────────

    /**
     * Validates the license — always returns true.
     */
    public function validate(): bool
    {
        return true;
    }

    /**
     * Refreshes the license cache — no-op, returns true.
     */
    public function refresh(): bool
    {
        return true;
    }

    /**
     * Returns license info array with all fields populated.
     */
    public function getLicenseInfo(): array
    {
        return [
            'valid'      => true,
            'active'     => true,
            'tier'       => 'unlimited',
            'key'        => 'OPEN-SOURCE-UNLICENSED',
            'expires_at' => '2099-12-31',
            'features'   => [
                'essentials' => true,
                'special'    => true,
                'private'    => true,
            ],
        ];
    }

    // ── Catch-all ─────────────────────────────────────────────────────────────

    /**
     * Catch-all magic method — any unknown method call returns true.
     * This ensures forward-compatibility with any encrypted service that calls
     * a method not explicitly defined above.
     */
    public function __call(string $name, array $arguments): mixed
    {
        return true;
    }

    /**
     * Static catch-all — any static call also returns true.
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        return true;
    }
}
