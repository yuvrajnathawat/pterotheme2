<?php

namespace Pterodactyl\Services;

class HyperV1AddonDefaultsService
{
    public function getAddonsCacheKey(): string
    {
        return 'hyperv1:addons:defaults';
    }

    /**
     * Full addon defaults with name, description, category.
     * Mirrors the JS frontend defaults so the UI always has metadata.
     */
    public function getDefaultAddons(): array
    {
        return [
            // ── Core / UI ─────────────────────────────────────────────────
            'theme-settings'           => ['enabled' => true,  'name' => 'Theme Settings',              'description' => 'User theme customization',                                          'category' => 'Appearance'],
            'LanguageTranslations'     => ['enabled' => true,  'name' => 'Language Translations',       'description' => 'Multi-language support for the panel',                              'category' => 'Appearance'],
            'SiteAlerts'               => ['enabled' => true,  'name' => 'Site Alerts',                 'description' => 'Display site-wide alerts to users',                                 'category' => 'Appearance'],
            'UserRegister'             => ['enabled' => true,  'name' => 'User Registration',           'description' => 'Allow users to register accounts',                                  'category' => 'Management'],
            'demo-mode'                => ['enabled' => true,  'name' => 'Demo Mode',                   'description' => 'Enable demo mode for the panel',                                    'category' => 'Management'],
            'Notifications'            => ['enabled' => true,  'name' => 'Notifications',               'description' => 'Real-time notifications for users',                                 'category' => 'Appearance'],
            'ads-layout'               => ['enabled' => true,  'name' => 'Ads Layout',                  'description' => 'Display advertisements on the panel',                               'category' => 'Appearance'],
            'pwa'                      => ['enabled' => true,  'name' => 'PWA',                         'description' => 'Progressive Web App support',                                       'category' => 'Appearance'],

            // ── Auth / SSO ────────────────────────────────────────────────
            'sso-login'                => ['enabled' => true,  'name' => 'SSO Login',                   'description' => 'Single Sign-On with Discord, Google, GitHub and more',              'category' => 'Authentication'],
            'CloudflareTurnstile'      => ['enabled' => true,  'name' => 'Cloudflare Turnstile',        'description' => 'Bot protection using Cloudflare Turnstile',                         'category' => 'Authentication'],

            // ── Billing ───────────────────────────────────────────────────
            'billing'                  => ['enabled' => true,  'name' => 'Billing',                     'description' => 'Built-in billing and subscription management',                      'category' => 'Billing'],
            'wemx'                     => ['enabled' => true,  'name' => 'Wemx Integration',            'description' => 'Integrate with Wemx billing platform',                              'category' => 'Billing'],
            'paymenter'                => ['enabled' => true,  'name' => 'Paymenter Integration',       'description' => 'Integrate with Paymenter billing platform',                         'category' => 'Billing'],

            // ── Server management ─────────────────────────────────────────
            'server-splitter'          => ['enabled' => true,  'name' => 'Server Splitter',             'description' => 'Split server resources into multiple sub-servers',                  'category' => 'Management'],
            'ReverseProxy'             => ['enabled' => true,  'name' => 'Reverse Proxy',               'description' => 'Manage reverse proxy configurations for servers',                   'category' => 'Management'],
            'server-type-changer'      => ['enabled' => true,  'name' => 'Server Type Changer',         'description' => 'Change server type and egg without reinstalling',                   'category' => 'Management'],
            'auto-suspend'             => ['enabled' => true,  'name' => 'Auto Suspend',                'description' => 'Automatically suspend servers based on expiry dates',               'category' => 'Management'],
            'ServerWiper'              => ['enabled' => true,  'name' => 'Server Wiper',                'description' => 'Automated server wipe scheduling',                                  'category' => 'Management'],
            'ScheduledWipes'           => ['enabled' => true,  'name' => 'Scheduled Wipes',             'description' => 'Schedule automatic server wipes',                                   'category' => 'Management'],
            'upload-from-url'          => ['enabled' => true,  'name' => 'Upload From URL',             'description' => 'Upload files to servers directly from a URL',                       'category' => 'Management'],
            'recycle-bin'              => ['enabled' => true,  'name' => 'Recycle Bin',                 'description' => 'Recover deleted files from the recycle bin',                        'category' => 'Management'],
            'command-history'          => ['enabled' => true,  'name' => 'Command History',             'description' => 'View and replay previously executed commands',                      'category' => 'Management'],
            'custom-monitor'           => ['enabled' => true,  'name' => 'Custom Monitor',              'description' => 'Custom resource monitoring for servers',                            'category' => 'Management'],
            'server-import'            => ['enabled' => true,  'name' => 'Server Importer',             'description' => 'Import servers from other panels',                                  'category' => 'Management'],
            'template-installer'       => ['enabled' => true,  'name' => 'Template Installer',         'description' => 'Install prebuilt server templates',                                 'category' => 'Management'],
            'restrict-files'           => ['enabled' => true,  'name' => 'Restrict Files',              'description' => 'Restrict file operations for specific files',                       'category' => 'Management'],
            'startup-presets'          => ['enabled' => true,  'name' => 'Startup Presets',             'description' => 'Save and load startup configuration presets',                       'category' => 'Management'],
            'schedule-presets'         => ['enabled' => true,  'name' => 'Schedule Presets',            'description' => 'Save and load schedule presets',                                    'category' => 'Management'],
            'console-log-upload'       => ['enabled' => true,  'name' => 'Console Log Upload',          'description' => 'Upload console logs to a paste service',                            'category' => 'Management'],
            'move-files'               => ['enabled' => true,  'name' => 'Move Files',                  'description' => 'Move files between directories',                                    'category' => 'Management'],
            'quick-file-access'        => ['enabled' => true,  'name' => 'Quick File Access',           'description' => 'Quick access to frequently used files',                             'category' => 'Management'],
            'direct-folder-upload'     => ['enabled' => true,  'name' => 'Direct Folder Upload',        'description' => 'Upload entire folders to servers',                                  'category' => 'Management'],
            'SimpleSchedule'           => ['enabled' => true,  'name' => 'Simple Schedule',             'description' => 'Simplified scheduling interface',                                   'category' => 'Management'],
            'ServerSorter'             => ['enabled' => true,  'name' => 'Server Sorter',               'description' => 'Sort and organize servers on the dashboard',                        'category' => 'Management'],
            'AccountInfoUpdate'        => ['enabled' => true,  'name' => 'Account Info Update',         'description' => 'Allow users to update their account information',                   'category' => 'Management'],
            'staff-request'            => ['enabled' => true,  'name' => 'Staff Request',               'description' => 'Allow users to request staff assistance',                           'category' => 'Management'],

            // ── Minecraft ─────────────────────────────────────────────────
            'minecraft-player-manager'          => ['enabled' => true, 'name' => 'Player Manager',              'description' => 'Manage players on your Minecraft server',                  'category' => 'Minecraft'],
            'minecraft-version'                 => ['enabled' => true, 'name' => 'Minecraft Version',           'description' => 'View current Minecraft server version',                    'category' => 'Minecraft'],
            'minecraft-version-changer'         => ['enabled' => true, 'name' => 'Java Version Changer',        'description' => 'Change Minecraft Java server versions',                    'category' => 'Minecraft'],
            'minecraft-mod-installer'           => ['enabled' => true, 'name' => 'Java Mod Installer',          'description' => 'Install and manage Minecraft mods',                        'category' => 'Minecraft'],
            'minecraft-plugin-installer'        => ['enabled' => true, 'name' => 'Java Plugin Manager',         'description' => 'Install and manage Minecraft plugins',                     'category' => 'Minecraft'],
            'minecraft-modpack-installer'       => ['enabled' => true, 'name' => 'Java ModPack Installer',      'description' => 'Install and manage Minecraft modpacks',                    'category' => 'Minecraft'],
            'minecraft-configuration'           => ['enabled' => true, 'name' => 'Config Editor',               'description' => 'Configure Minecraft server settings',                      'category' => 'Minecraft'],
            'minecraft-world-manager'           => ['enabled' => true, 'name' => 'Java World Manager',          'description' => 'Manage Minecraft worlds',                                  'category' => 'Minecraft'],
            'minecraft-icon-changer'            => ['enabled' => true, 'name' => 'Icon Changer',                'description' => 'Upload and change server icon',                            'category' => 'Minecraft'],
            'minecraft-votifier-tester'         => ['enabled' => true, 'name' => 'Votifier Tester',             'description' => 'Test Votifier votes for Minecraft servers',                'category' => 'Minecraft'],
            'minecraft-motd-changer'            => ['enabled' => true, 'name' => 'MOTD Changer',                'description' => 'Change server MOTD (Message of the Day)',                  'category' => 'Minecraft'],
            'minecraft-player-counter'          => ['enabled' => true, 'name' => 'Player Counter',              'description' => 'Display player count for Minecraft servers',               'category' => 'Minecraft'],
            'MinecraftPlayerCount'              => ['enabled' => true, 'name' => 'Minecraft Player Count',      'description' => 'Displays player count for Minecraft servers',              'category' => 'Minecraft'],
            'minecraft-bedrock-version-changer' => ['enabled' => true, 'name' => 'Bedrock Version Changer',     'description' => 'Change Minecraft Bedrock server versions',                 'category' => 'Minecraft'],
            'minecraft-bedrock-addon-installer' => ['enabled' => true, 'name' => 'Bedrock Addon Installer',     'description' => 'Install and manage Minecraft Bedrock addons',              'category' => 'Minecraft'],
            'minecraft-bedrock-map-manager'     => ['enabled' => true, 'name' => 'Bedrock Map Manager',         'description' => 'Manage Minecraft Bedrock maps',                            'category' => 'Minecraft'],
            'minecraft-bedrock-pack-installer'  => ['enabled' => true, 'name' => 'Bedrock Pack Installer',      'description' => 'Install and manage Minecraft Bedrock packs',              'category' => 'Minecraft'],
            'minecraft-bedrock-script-installer'=> ['enabled' => true, 'name' => 'Bedrock Script Installer',    'description' => 'Install and manage Minecraft Bedrock scripts',             'category' => 'Minecraft'],

            // ── Game-specific ─────────────────────────────────────────────
            'fivem-utils'              => ['enabled' => true,  'name' => 'FiveM Utils',                 'description' => 'Utilities for FiveM game servers',                                 'category' => 'FiveM', 'allowed_eggs' => [], 'allowed_nests' => []],
            'arma-reforger-mod-manager'=> ['enabled' => true,  'name' => 'Arma Reforger Mod Manager',   'description' => 'Manage and assign mods for Arma Reforger servers',                 'category' => 'Arma Reforger'],
            'arma-reforger-admin-tools'=> ['enabled' => true,  'name' => 'Arma Reforger Admin Tools',   'description' => 'Admin tools for Arma Reforger servers',                            'category' => 'Arma Reforger'],
            'ark-mod-installer'        => ['enabled' => true,  'name' => 'Ark Mod Installer',           'description' => 'Install and manage ARK mods',                                      'category' => 'Ark'],
            'hytale-mod-installer'     => ['enabled' => true,  'name' => 'Hytale Mod Installer',        'description' => 'Install and manage Hytale mods',                                   'category' => 'Hytale'],
            'hytale-world-manager'     => ['enabled' => true,  'name' => 'Hytale World Manager',        'description' => 'Manage Hytale worlds',                                             'category' => 'Hytale'],

            // ── Infrastructure ────────────────────────────────────────────
            'wings-addon'              => ['enabled' => true,  'name' => 'Wings Addon',                 'description' => 'Extended Wings daemon functionality',                              'category' => 'Infrastructure'],
            'node-backup'              => ['enabled' => true,  'name' => 'Node Backup',                 'description' => 'Backup and restore node data',                                     'category' => 'Infrastructure'],
            'GlobalStorageBackends'    => ['enabled' => true,  'name' => 'Global Storage Backends',     'description' => 'Configure global storage backends for backups',                    'category' => 'Infrastructure'],
            'ServerStats'              => ['enabled' => true,  'name' => 'Server Stats',                'description' => 'Display server resource statistics',                               'category' => 'Infrastructure'],
            'DiscordBot'               => ['enabled' => true,  'name' => 'Discord Bot',                 'description' => 'Discord bot integration for the panel',                            'category' => 'Infrastructure'],
            'node-status'              => ['enabled' => true,  'name' => 'Node Status',                 'description' => 'Display node status on the dashboard',                             'category' => 'Infrastructure'],
            'network-statistics'       => ['enabled' => true,  'name' => 'Network Statistics',          'description' => 'Display network statistics for servers',                           'category' => 'Infrastructure'],
            'firewall-manager'         => ['enabled' => true,  'name' => 'Firewall Manager',            'description' => 'Manage firewall rules for servers',                                'category' => 'Infrastructure'],
            'ddos-alert'               => ['enabled' => true,  'name' => 'DDoS Alert',                  'description' => 'Real-time DDoS attack alerts',                                     'category' => 'Infrastructure'],
            'fastdl-manager'           => ['enabled' => true,  'name' => 'FastDL Manager',              'description' => 'Manage FastDL for game servers',                                   'category' => 'Infrastructure'],

            // ── Admin ─────────────────────────────────────────────────────
            'login-as-user'            => ['enabled' => true,  'name' => 'Login As User',               'description' => 'Impersonate users for support purposes',                           'category' => 'Admin'],
            'subdomain-manager'        => ['enabled' => true,  'name' => 'Subdomain Manager',           'description' => 'Manage subdomains for game servers',                               'category' => 'Admin'],
            'database-manager'         => ['enabled' => true,  'name' => 'Database Manager',            'description' => 'Advanced database management tools',                               'category' => 'Admin'],
            'SubdomainManager'         => ['enabled' => true,  'name' => 'Subdomain Manager',           'description' => 'Manage subdomains for game servers',                               'category' => 'Admin'],
        ];
    }

    /**
     * Fields that are safe to expose publicly per addon.
     */
    public function getPublicAllowedFields(): array
    {
        return [
            'sso-login'            => ['enabled', 'passkeys_enabled', 'discord_enabled', 'discord_client_id', 'google_enabled', 'google_client_id', 'github_enabled', 'github_client_id', 'whmcs_enabled', 'whmcs_client_id', 'whmcs_url', 'whmcs_custom_name', 'paymenter_enabled', 'paymenter_url', 'paymenter_client_id', 'paymenter_custom_name'],
            'CloudflareTurnstile'  => ['enabled', 'site_key'],
            'demo-mode'            => ['enabled'],
            'SiteAlerts'           => ['enabled', 'alerts'],
            'UserRegister'         => ['enabled'],
            'LanguageTranslations' => ['enabled', 'defaultLanguage'],
            'pwa'                  => ['enabled', 'app_name', 'app_short_name', 'theme_color', 'background_color', 'status_bar_style'],
            'billing'              => ['enabled', 'currency_symbol', 'currency_code'],
            'Notifications'        => ['enabled'],
            'ads-layout'           => ['enabled'],
            'DiscordBot'           => ['enabled'],
            'ServerStats'          => ['enabled'],
        ];
    }
}
